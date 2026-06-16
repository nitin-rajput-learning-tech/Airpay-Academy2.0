<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * External API for the agentic copilot (P1.3).
 *
 * Two AJAX endpoints:
 *   - agent_turn:    propose + (for read tools) run a turn. Returns the
 *                    assistant message and, for write actions, a PROPOSAL
 *                    the UI shows for explicit learner confirmation.
 *   - agent_confirm: the learner confirmed a write proposal — re-run the
 *                    turn with $confirm=true so the guard chain executes it.
 *
 * Both require the :useagent capability and login. The agent_loop re-checks
 * the per-tool capability + tenant on every action regardless — these WS
 * gates are the outer ring of defence in depth.
 *
 * @package    local_sentientia_assistant
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use local_sentientia_assistant\agent\agent_loop;

class external_agent extends \external_api {

    // ─── agent_turn ──────────────────────────────────────────────────

    public static function agent_turn_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'query' => new \external_value(PARAM_TEXT, 'Learner message (untrusted)'),
        ]);
    }

    public static function agent_turn(string $query): array {
        global $USER;

        $params = self::validate_parameters(self::agent_turn_parameters(), ['query' => $query]);
        self::validate_context(\context_system::instance());
        require_capability('local/sentientia_assistant:useagent', \context_system::instance());

        $clean = clean_param($params['query'], PARAM_TEXT);
        if (trim($clean) === '') {
            return self::empty_response();
        }

        $turn = agent_loop::run((int) $USER->id, $clean, false);
        return self::shape($turn);
    }

    public static function agent_turn_returns(): \external_single_structure {
        return self::returns_structure();
    }

    // ─── agent_confirm ───────────────────────────────────────────────

    public static function agent_confirm_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'query'  => new \external_value(PARAM_TEXT, 'The original learner message that produced the proposal'),
            'tool'   => new \external_value(PARAM_ALPHANUMEXT, 'The proposed tool name to confirm'),
        ]);
    }

    public static function agent_confirm(string $query, string $tool): array {
        global $USER;

        $params = self::validate_parameters(self::agent_confirm_parameters(),
            ['query' => $query, 'tool' => $tool]);
        self::validate_context(\context_system::instance());
        require_capability('local/sentientia_assistant:useagent', \context_system::instance());

        $clean = clean_param($params['query'], PARAM_TEXT);
        if (trim($clean) === '') {
            return self::empty_response();
        }

        // Re-run with confirm=true. The loop re-derives the proposal from
        // the same query + context and executes it through the guard chain.
        // The $tool param is informational/telemetry — authority comes from
        // the capability + tenant checks inside tool::authorise_and_run,
        // NOT from the client asserting a tool name.
        $turn = agent_loop::run((int) $USER->id, $clean, true);
        return self::shape($turn);
    }

    public static function agent_confirm_returns(): \external_single_structure {
        return self::returns_structure();
    }

    // ─── shared helpers ──────────────────────────────────────────────

    /** Shape a loop result into the WS return structure (escaping untrusted text). */
    private static function shape(array $turn): array {
        // The message may contain untrusted LLM text — render through
        // format_text (markdown, sanitised) before it reaches the browser.
        $message = format_text($turn['message'] ?? '', FORMAT_MARKDOWN);

        $hasproposal = !empty($turn['proposal']);
        $proposal = $turn['proposal'] ?? null;

        return [
            'enabled'       => (bool) ($turn['enabled'] ?? false),
            'message'       => $message,
            'mode'          => (string) ($turn['mode'] ?? 'disabled'),
            'hasproposal'   => $hasproposal,
            'proposaltool'  => $hasproposal ? (string) $proposal['tool'] : '',
            'proposallabel' => $hasproposal ? (string) $proposal['label'] : '',
            'outcome'       => (string) ($turn['outcome'] ?? ''),
            'statechanged'  => (bool) ($turn['statechanged'] ?? false),
        ];
    }

    private static function empty_response(): array {
        return [
            'enabled'       => true,
            'message'       => format_text(
                get_string('agent_help', 'local_sentientia_assistant'), FORMAT_PLAIN),
            'mode'          => 'mock',
            'hasproposal'   => false,
            'proposaltool'  => '',
            'proposallabel' => '',
            'outcome'       => '',
            'statechanged'  => false,
        ];
    }

    private static function returns_structure(): \external_single_structure {
        return new \external_single_structure([
            'enabled'       => new \external_value(PARAM_BOOL, 'Whether the agentic copilot is enabled'),
            'message'       => new \external_value(PARAM_RAW, 'Assistant reply (sanitised HTML)'),
            'mode'          => new \external_value(PARAM_ALPHA, 'mock|live|failed|disabled'),
            'hasproposal'   => new \external_value(PARAM_BOOL, 'A write action awaits learner confirmation'),
            'proposaltool'  => new \external_value(PARAM_ALPHANUMEXT, 'Proposed tool name (if any)'),
            'proposallabel' => new \external_value(PARAM_TEXT, 'Localised proposal label (if any)'),
            'outcome'       => new \external_value(PARAM_ALPHANUMEXT, 'Tool outcome if something ran'),
            'statechanged'  => new \external_value(PARAM_BOOL, 'Whether persistent state changed'),
        ]);
    }
}
