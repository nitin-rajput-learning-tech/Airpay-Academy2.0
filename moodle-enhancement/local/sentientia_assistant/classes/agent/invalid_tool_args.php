<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * Thrown by tool::validate_args() when the UNTRUSTED LLM-proposed
 * arguments are malformed, out of bounds, or reference a resource the
 * tool cannot act on. Caught inside tool::authorise_and_run() and turned
 * into an OUTCOME_DENIED_INVALID result so a bad proposal can never reach
 * execute().
 *
 * @package local_sentientia_assistant
 */
class invalid_tool_args extends \moodle_exception {

    /**
     * @param string $reason Internal reason code (for debugging only — not shown to the learner).
     */
    public function __construct(string $reason = 'invalid') {
        parent::__construct('agent_denied_invalid', 'local_sentientia_assistant', '', null, $reason);
    }
}
