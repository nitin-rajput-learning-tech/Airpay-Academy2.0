<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Submit identity verification. Client sends ID + selfie as base64.
 *
 * We decode, pass to the verifier, persist score only, free memory.
 */
class submit_identity extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, ''),
            'id_b64'    => new external_value(PARAM_RAW, 'Base64 ID photo'),
            'selfie_b64' => new external_value(PARAM_RAW, 'Base64 selfie'),
        ]);
    }
    public static function execute(int $sessionid, string $id_b64, string $selfie_b64): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('sessionid', 'id_b64', 'selfie_b64'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_proctoring:attempt', $ctx);

        // Bound the photo size to prevent abuse (10 MB raw → ~13.3 MB b64).
        if (strlen($params['id_b64']) > 14_000_000 || strlen($params['selfie_b64']) > 14_000_000) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring',
                '', 'Photo too large');
        }

        $id_bytes     = base64_decode($params['id_b64'], true) ?: '';
        $selfie_bytes = base64_decode($params['selfie_b64'], true) ?: '';
        if (empty($id_bytes) || empty($selfie_bytes)) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring',
                '', 'Invalid photo encoding');
        }

        $id_row = \local_airpay_proctoring\session_manager::submit_identity(
            (int) $params['sessionid'], (int) $USER->id, $id_bytes, $selfie_bytes);

        return [
            'passed'      => (bool) $id_row->passed,
            'match_score' => (float) $id_row->match_score,
            'error_code'  => (string) ($id_row->error_code ?? ''),
            'error_msg'   => (string) ($id_row->error_msg ?? ''),
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'passed'      => new external_value(PARAM_BOOL, ''),
            'match_score' => new external_value(PARAM_FLOAT, ''),
            'error_code'  => new external_value(PARAM_TEXT, ''),
            'error_msg'   => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
