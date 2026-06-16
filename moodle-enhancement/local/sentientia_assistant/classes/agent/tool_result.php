<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * Immutable outcome of one tool authorisation + execution attempt.
 *
 * The agentic copilot's contract: the LLM PROPOSES a {@see tool_call};
 * platform code (here) authorises and executes it; the result is always
 * a tool_result, which is what gets audit-logged and rendered back to the
 * learner. Carrying an explicit `outcome` string lets the audit log and
 * the UI distinguish "denied because no capability" from "denied because
 * cross-tenant" from "executed" — never collapsing them into a bool.
 *
 * @package local_sentientia_assistant
 */
class tool_result {

    // ─── Outcome constants — mirror the audit table's `outcome` column ──
    /** The action was proposed but not yet (or never) executed. */
    public const OUTCOME_PROPOSED = 'proposed';
    /** Denied: the acting user lacks the tool's capability. */
    public const OUTCOME_DENIED_CAPABILITY = 'denied_capability';
    /** Denied: the action crosses a tenant boundary. */
    public const OUTCOME_DENIED_TENANT = 'denied_tenant';
    /** Denied: the proposed arguments failed validation. */
    public const OUTCOME_DENIED_INVALID = 'denied_invalid';
    /** Executed successfully and changed state. */
    public const OUTCOME_EXECUTED = 'executed';
    /** Idempotent no-op — already in the desired state. */
    public const OUTCOME_NOOP = 'noop';
    /** Execution failed (transient or unexpected). */
    public const OUTCOME_FAILED = 'failed';

    /** @var string One of the OUTCOME_* constants. */
    public string $outcome;

    /** @var string Human-readable, locale-resolved message for the learner. NEVER contains secrets. */
    public string $message;

    /** @var bool Did this attempt actually change persistent state? */
    public bool $statechanged;

    /**
     * @param string $outcome      One of the OUTCOME_* constants.
     * @param string $message      Learner-facing message (already localised).
     * @param bool   $statechanged Whether state was mutated.
     */
    public function __construct(string $outcome, string $message, bool $statechanged = false) {
        $this->outcome = $outcome;
        $this->message = $message;
        $this->statechanged = $statechanged;
    }

    /** Was the attempt denied for any reason? */
    public function is_denied(): bool {
        return in_array($this->outcome, [
            self::OUTCOME_DENIED_CAPABILITY,
            self::OUTCOME_DENIED_TENANT,
            self::OUTCOME_DENIED_INVALID,
        ], true);
    }

    /** Did the attempt succeed (executed or harmless no-op)? */
    public function is_success(): bool {
        return $this->outcome === self::OUTCOME_EXECUTED
            || $this->outcome === self::OUTCOME_NOOP;
    }
}
