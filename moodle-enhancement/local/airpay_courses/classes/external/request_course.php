<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_courses\request_manager;

/**
 * Sprint D — a receiving-tenant manager files a request that the
 * given Airpay course be added to their tenant's catalogue.
 *
 * Idempotent: if a pending request already exists for the same
 * (course, tenant) pair, returns the existing request id. If the
 * course is ALREADY shared, returns request_id=0 with state=
 * 'already_shared' so the UI can show "no action needed".
 *
 * @package local_airpay_courses
 */
class request_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course to request'),
        ]);
    }

    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_courses:request_course', $context);
        require_sesskey();

        // request_manager::create_request returns 0 when the course is
        // already shared to this manager's tenant. The UI uses that
        // signal to show "Already in your catalog" instead of "Request
        // sent".
        $request_id = request_manager::create_request((int) $params['courseid']);

        return [
            'courseid'   => (int) $params['courseid'],
            'request_id' => $request_id,
            // request_state gives the UI a clean status string to render.
            // We compute it again post-create so it accounts for any
            // dedup short-circuits.
            'state'      => request_manager::request_state(
                (int) $params['courseid'],
                self::current_tenant_root()),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'   => new external_value(PARAM_INT, 'Course id'),
            'request_id' => new external_value(PARAM_INT,
                'New (or existing pending) request id; 0 if already_shared'),
            'state'      => new external_value(PARAM_ALPHAEXT,
                'Current state: none | pending | approved | rejected | already_shared'),
        ]);
    }

    /**
     * Derive the requesting tenant from $USER->open_path — duplicated
     * from request_manager (private API there) so the WS can show
     * the post-action state without trusting the client.
     */
    private static function current_tenant_root(): int {
        // ADR-018 Wave 2: delegate to the Sentientia seam (was a duplicated inline
        // parse; root_for_current_user reads $USER itself).
        return \local_sentientia_core\tenant_identity::root_for_current_user();
    }
}
