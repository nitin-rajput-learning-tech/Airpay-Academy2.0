<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base for every agent tool.
 *
 * THE SECURITY MODEL (CLAUDE.md §13 — SECURITY-CRITICAL):
 *
 *   The LLM never executes anything. It proposes a {@see tool_call}. The
 *   platform runs {@see authorise_and_run()}, which — in this fixed order
 *   — :
 *
 *     1. validate_args()      — cast + bound-check the UNTRUSTED args.
 *                               Reject anything malformed (denied_invalid).
 *     2. require_capability() — the acting user MUST hold this tool's
 *                               capability at CONTEXT_SYSTEM. Throwing here
 *                               is converted to denied_capability.
 *     3. tenant scope check   — the resource the tool touches MUST be in
 *                               the acting user's tenant tree, enforced via
 *                               \local_sentientia_platform\tenant. Failure
 *                               is denied_tenant. No action crosses tenants.
 *     4. is_noop()            — idempotency: if the world is already in the
 *                               desired state, return OUTCOME_NOOP without
 *                               mutating anything.
 *     5. execute()            — the only place that changes state. Runs ONLY
 *                               after 1–4 all pass.
 *
 * Subclasses implement name(), capability(), validate_args(), is_noop(),
 * resource_tenant(), and execute(). They MUST NOT skip any guard — the
 * base orchestrates them so a tool author cannot forget one.
 *
 * @package local_sentientia_assistant
 */
abstract class tool {

    /**
     * Stable tool identifier — what the LLM proposes and the registry keys on.
     * Must match /^[a-z0-9_]+$/.
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * The capability the acting user must hold (checked at CONTEXT_SYSTEM).
     *
     * @return string e.g. 'local/sentientia_assistant:enrol'
     */
    abstract public function capability(): string;

    /**
     * Short, localised, human-readable label for the proposal UI + audit.
     *
     * @return string
     */
    abstract public function label(): string;

    /**
     * A JSON-serialisable schema describing the tool to the LLM — name,
     * description, and the argument shape. Used by the prompt builder so
     * the model knows which tools exist and how to call them.
     *
     * @return array
     */
    abstract public function schema(): array;

    /**
     * Validate + normalise the UNTRUSTED proposed arguments.
     *
     * Return a clean, fully-typed args array on success. Throw
     * {@see invalid_tool_args} (caught by the base) on any problem — never
     * trust, never coerce silently past a bound.
     *
     * @param array $rawargs Untrusted args from the LLM.
     * @param int   $userid  Acting user id (the proposal always acts on self).
     * @return array Clean args.
     * @throws invalid_tool_args
     */
    abstract protected function validate_args(array $rawargs, int $userid): array;

    /**
     * The tenant root (costcenterid) of the resource this validated call
     * would touch — used for the cross-tenant guard. Return 0 when the
     * tool is inherently tenant-neutral (e.g. read-only self-recommend),
     * in which case the base falls back to the acting user's own tenant.
     *
     * @param array $args   Validated args.
     * @param int   $userid Acting user id.
     * @return int Tenant root, or 0 for "use the acting user's tenant".
     */
    abstract protected function resource_tenant(array $args, int $userid): int;

    /**
     * Idempotency check: is the world ALREADY in the desired post-state?
     * When true, the base returns OUTCOME_NOOP and execute() is skipped.
     *
     * @param array $args   Validated args.
     * @param int   $userid Acting user id.
     * @return bool
     */
    abstract protected function is_noop(array $args, int $userid): bool;

    /**
     * Perform the state change. Runs ONLY after every guard has passed and
     * is_noop() returned false. Must itself be safe to re-run (wrap in a
     * transaction if multi-step). Return the learner-facing result.
     *
     * @param array $args   Validated args.
     * @param int   $userid Acting user id.
     * @return tool_result
     */
    abstract protected function execute(array $args, int $userid): tool_result;

    /**
     * Stable idempotency key for an (acting user, tool, validated args)
     * triple. Default hashes a canonicalised JSON of the args; subclasses
     * may override to narrow what counts as "the same action".
     *
     * @param array $args   Validated args.
     * @param int   $userid Acting user id.
     * @return string 64-char hex.
     */
    public function idempotency_key(array $args, int $userid): string {
        ksort($args);
        return hash('sha256', $userid . '|' . $this->name() . '|' . json_encode($args));
    }

    /**
     * THE orchestrator. Runs the full guard chain in order and returns a
     * tool_result. NEVER bypassed — the agent loop and the WS layer both
     * call this and only this to run a proposed tool.
     *
     * @param tool_call $call   The untrusted proposal.
     * @param int       $userid Acting user id (always the current learner).
     * @return tool_result
     */
    final public function authorise_and_run(tool_call $call, int $userid): tool_result {
        global $DB;

        // 1. Validate UNTRUSTED args. Reject malformed proposals.
        try {
            $args = $this->validate_args($call->args, $userid);
        } catch (invalid_tool_args $e) {
            return new tool_result(
                tool_result::OUTCOME_DENIED_INVALID,
                get_string('agent_denied_invalid', 'local_sentientia_assistant'),
                false
            );
        }

        // 2. Capability check at system context. The LLM proposing a tool
        //    confers NO authority — the human user must hold the cap.
        $context = \context_system::instance();
        if (!has_capability($this->capability(), $context, $userid)) {
            return new tool_result(
                tool_result::OUTCOME_DENIED_CAPABILITY,
                get_string('agent_denied_capability', 'local_sentientia_assistant'),
                false
            );
        }

        // 3. Tenant scope. The resource MUST be in the acting user's tenant
        //    tree. No action crosses a tenant boundary — even if the LLM
        //    proposed a foreign id, this is where it dies.
        $resourcetenant = $this->resource_tenant($args, $userid);
        if ($resourcetenant === 0) {
            // Tenant-neutral tool — scope to the acting user's own tenant.
            $resourcetenant = \local_sentientia_platform\tenant::root_for_current_user();
        }
        if (!\local_sentientia_platform\tenant::viewer_can_access($resourcetenant, $userid)) {
            return new tool_result(
                tool_result::OUTCOME_DENIED_TENANT,
                get_string('agent_denied_tenant', 'local_sentientia_assistant'),
                false
            );
        }

        // 4. Idempotency. Already in the desired state? No-op, no mutation.
        if ($this->is_noop($args, $userid)) {
            return new tool_result(
                tool_result::OUTCOME_NOOP,
                get_string('agent_noop', 'local_sentientia_assistant'),
                false
            );
        }

        // 5. Execute — the ONLY place state changes, after all guards pass.
        try {
            return $this->execute($args, $userid);
        } catch (\Throwable $e) {
            debugging('agent tool ' . $this->name() . ' failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return new tool_result(
                tool_result::OUTCOME_FAILED,
                get_string('agent_failed', 'local_sentientia_assistant'),
                false
            );
        }
    }
}
