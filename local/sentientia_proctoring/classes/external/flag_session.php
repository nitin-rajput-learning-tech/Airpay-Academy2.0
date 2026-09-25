<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class flag_session extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $sessionid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('sessionid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:review', $ctx);

        $s = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $params['sessionid']], '*', MUST_EXIST);
        // ── B2 fix: tenant equality before flagging ─────────────────────
        // ADR-031: and a reviewer with no tenant flags nothing.
        \local_sentientia_proctoring\session_manager::require_session_access((int) $s->costcenterid);

        $s->status = 'flagged';
        $s->timemodified = time();
        $DB->update_record('local_sentientia_proctor_sessions', $s);
        return ['success' => true, 'status' => $s->status];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, ''),
            'status'  => new external_value(PARAM_ALPHANUMEXT, ''),
        ]);
    }
}
