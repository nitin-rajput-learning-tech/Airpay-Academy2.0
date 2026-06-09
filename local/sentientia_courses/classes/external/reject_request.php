<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_courses\request_manager;

/**
 * Sprint D — Airpay Super Admin rejects a pending request.
 *
 * The request row stays in the DB with status='rejected' and the
 * optional rationale stored on `decision_reason` — the requester sees
 * it in their outbox.
 *
 * @package local_sentientia_courses
 */
class reject_request extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, 'Pending request id'),
            'reason'    => new external_value(PARAM_TEXT,
                'Optional rejection rationale (shown back to the requester)',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $requestid, string $reason = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['requestid' => $requestid, 'reason' => $reason]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_courses:approve_request', $context);
        require_sesskey();

        $changed = request_manager::reject_request(
            (int) $params['requestid'],
            (string) $params['reason']);

        return [
            'requestid' => (int) $params['requestid'],
            'changed'   => $changed,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'requestid' => new external_value(PARAM_INT, 'Request that was acted on'),
            'changed'   => new external_value(PARAM_BOOL,
                'True if the request was newly rejected (false on no-op = already rejected)'),
        ]);
    }
}
