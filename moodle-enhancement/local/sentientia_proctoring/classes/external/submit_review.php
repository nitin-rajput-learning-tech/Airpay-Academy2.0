<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class submit_review extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, ''),
            'decision'  => new external_value(PARAM_ALPHA, 'clean|warn|fail'),
            'note'      => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
        ]);
    }
    public static function execute(int $sessionid, string $decision, string $note = ''): array {
        global $USER, $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('sessionid', 'decision', 'note'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:review', $ctx);

        // ── B2 fix: tenant equality before reviewing ────────────────────
        $session_row = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => (int) $params['sessionid']], 'id, costcenterid', MUST_EXIST);
        \local_sentientia_platform\tenant::require_access(
            (int) $session_row->costcenterid, (int) $USER->id);

        $s = \local_sentientia_proctoring\session_manager::submit_review(
            (int) $params['sessionid'], (int) $USER->id,
            (string) $params['decision'], (string) $params['note']);
        return [
            'success'         => true,
            'human_decision'  => $s->human_decision,
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'        => new external_value(PARAM_BOOL, ''),
            'human_decision' => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
