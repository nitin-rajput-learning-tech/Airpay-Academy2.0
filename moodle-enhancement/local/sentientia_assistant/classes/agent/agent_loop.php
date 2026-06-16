<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * The agent loop — orchestrates one turn of the RAG + tool-use copilot.
 *
 * Flow:
 *   1. Flag gate. If sentientia.assistant.agentic.enabled is OFF, return a
 *      no-op result — the legacy nav Q&A surface is untouched.
 *   2. Build tenant-scoped RAG context (context_builder).
 *   3. Ask the client (mock by default) for a PROPOSAL.
 *   4. Parse the untrusted JSON into a tool_call (or chat-only).
 *   5. Resolve the proposed tool name through the registry. Unknown name
 *      → audit denied_invalid, return chat.
 *   6. WRITE tools: by default we PROPOSE (do not execute) and return the
 *      proposal for the learner to confirm. Only when $confirm === true
 *      do we run tool::authorise_and_run().
 *   7. READ tools (recommend_content): safe to run immediately.
 *   8. Audit EVERY outcome.
 *
 * This split is the "LLM proposes, platform authorizes and executes"
 * contract made concrete: the model's output never directly mutates
 * state; a human confirm + the guard chain stand between.
 *
 * @package local_sentientia_assistant
 */
class agent_loop {

    /** Read-only tools that may run without an explicit confirm step. */
    private const AUTORUN_TOOLS = ['recommend_content'];

    /**
     * Run one turn.
     *
     * @param int    $userid  Acting user (always the current learner).
     * @param string $query   The learner's untrusted message.
     * @param bool   $confirm True when the learner has confirmed a prior
     *                        write proposal (executes it). False = propose only.
     * @return array {
     *     enabled: bool,        agentic flag state
     *     message: string,      learner-facing reply (already localised/escaped-safe text)
     *     mode: string,         'mock'|'live'|'failed'|'disabled'
     *     proposal: ?array,     {tool, label, args, rationale} when a write action awaits confirm
     *     outcome: ?string,     tool_result outcome when something ran
     *     statechanged: bool
     * }
     */
    public static function run(int $userid, string $query, bool $confirm = false): array {
        // 1. Flag gate — default OFF keeps production behaviour unchanged.
        if (!class_exists('\\local_sentientia_platform\\feature_flags')
                || !\local_sentientia_platform\feature_flags::is_enabled('sentientia.assistant.agentic.enabled')) {
            return [
                'enabled'      => false,
                'message'      => '',
                'mode'         => 'disabled',
                'proposal'     => null,
                'outcome'      => null,
                'statechanged' => false,
            ];
        }

        // 2. Tenant-scoped RAG context (own data only).
        $context = context_builder::build($userid);

        // 3. Allowed tool schemas for THIS user + ask the client to propose.
        $schemas = tool_registry::schemas_for_user($userid);
        $proposal = agent_client::propose($query, $context, $schemas);

        if ($proposal['mode'] === 'failed') {
            return [
                'enabled'      => true,
                'message'      => get_string('agent_unavailable', 'local_sentientia_assistant'),
                'mode'         => 'failed',
                'proposal'     => null,
                'outcome'      => null,
                'statechanged' => false,
            ];
        }

        // 4. Parse the UNTRUSTED proposal JSON.
        $decoded = json_decode($proposal['raw'], true);
        if (!is_array($decoded)) {
            return [
                'enabled'      => true,
                'message'      => get_string('agent_unavailable', 'local_sentientia_assistant'),
                'mode'         => $proposal['mode'],
                'proposal'     => null,
                'outcome'      => null,
                'statechanged' => false,
            ];
        }

        // The assistant's chat message — treat as untrusted text. Callers
        // (external) run it through format_text before display.
        $chatmessage = (isset($decoded['message']) && is_string($decoded['message']))
            ? \core_text::substr($decoded['message'], 0, 2000) : '';

        $call = tool_call::from_decoded($decoded, $proposal['mode']);

        // Chat-only turn — no tool proposed.
        if ($call === null) {
            return [
                'enabled'      => true,
                'message'      => $chatmessage !== '' ? $chatmessage
                    : get_string('agent_help', 'local_sentientia_assistant'),
                'mode'         => $proposal['mode'],
                'proposal'     => null,
                'outcome'      => null,
                'statechanged' => false,
            ];
        }

        // 5. Resolve the proposed (untrusted) tool name.
        $tool = tool_registry::get($call->tool);
        if ($tool === null) {
            // The model named a tool that doesn't exist — audit + refuse.
            $result = new tool_result(
                tool_result::OUTCOME_DENIED_INVALID,
                get_string('agent_denied_invalid', 'local_sentientia_assistant'),
                false
            );
            audit_log::record($userid, $call, $result);
            return [
                'enabled'      => true,
                'message'      => $result->message,
                'mode'         => $proposal['mode'],
                'proposal'     => null,
                'outcome'      => $result->outcome,
                'statechanged' => false,
            ];
        }

        $isautorun = in_array($tool->name(), self::AUTORUN_TOOLS, true);

        // 6. WRITE tool, not yet confirmed → PROPOSE only. Record the
        //    proposal in the audit trail (outcome=proposed) and hand it
        //    back for the learner to confirm. Nothing executes.
        if (!$isautorun && !$confirm) {
            $proposedresult = new tool_result(
                tool_result::OUTCOME_PROPOSED,
                $chatmessage !== '' ? $chatmessage : $tool->label(),
                false
            );
            audit_log::record($userid, $call, $proposedresult);
            return [
                'enabled'  => true,
                'message'  => $proposedresult->message,
                'mode'     => $proposal['mode'],
                'proposal' => [
                    'tool'      => $tool->name(),
                    'label'     => $tool->label(),
                    'args'      => $call->args,
                    'rationale' => \core_text::substr($call->rationale, 0, 500),
                ],
                'outcome'      => tool_result::OUTCOME_PROPOSED,
                'statechanged' => false,
            ];
        }

        // 7. Authorise + execute through the guard chain. This is the ONLY
        //    place a tool can mutate state, and it re-checks capability +
        //    tenant + idempotency regardless of what the model proposed.
        $result = $tool->authorise_and_run($call, $userid);

        // 8. Audit the real outcome.
        $idempotency = null;
        if ($result->is_success()) {
            // Recompute the key from the validated path is internal to the
            // tool; for the audit we store the tool's key on the raw args
            // (best-effort — only used for forensics, not authorisation).
            $idempotency = $tool->idempotency_key($call->args, $userid);
        }
        audit_log::record($userid, $call, $result, $idempotency);

        // Embedding hooks — notify the learner on WhatsApp / Teams of a
        // completed action. Best-effort, class_exists-guarded, mock-safe;
        // a failure here never affects the action outcome above.
        if ($result->statechanged) {
            integration\channel_hooks::notify($userid, $result, $tool->name());
        }

        return [
            'enabled'      => true,
            'message'      => $result->message,
            'mode'         => $proposal['mode'],
            'proposal'     => null,
            'outcome'      => $result->outcome,
            'statechanged' => $result->statechanged,
        ];
    }
}
