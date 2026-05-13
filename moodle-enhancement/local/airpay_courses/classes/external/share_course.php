<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use local_airpay_courses\sharing_manager;

/**
 * Sprint C — share a course to one or more tenants.
 *
 * Idempotent: the underlying sharing_manager skips already-active
 * (courseid, tenant_id) pairs. Returns per-tenant outcome arrays so
 * the admin UI can render "Public: newly shared, ZEEA: was already
 * shared".
 *
 * Audit: every NEW or REACTIVATED share fires
 * \local_airpay_courses\event\course_share_created — picked up by
 * the standard Moodle logstore + airpay_core/audit_log dashboard.
 *
 * @package local_airpay_courses
 */
class share_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'  => new external_value(PARAM_INT, 'Course to share'),
            'tenantids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Tenant root id'),
                'Array of tenant root IDs to share to (e.g. [77, 177])'),
        ]);
    }

    /**
     * @param int $courseid
     * @param array<int> $tenantids
     * @return array
     */
    public static function execute(int $courseid, array $tenantids): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid, 'tenantids' => $tenantids]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_courses:share_to_tenant', $context);
        require_sesskey();

        $out = sharing_manager::share_course(
            (int) $params['courseid'],
            array_map('intval', $params['tenantids']));

        return [
            'courseid'   => (int) $params['courseid'],
            'shared'     => array_values($out['shared']),
            'reactivated' => array_values($out['reactivated']),
            'unchanged'  => array_values($out['unchanged']),
            // Map errors {tenantid => message} into an indexed list for the
            // WS schema (which can't return arbitrary-keyed maps cleanly).
            'errors'     => array_map(
                fn($k, $v) => ['tenant_id' => (int) $k, 'message' => (string) $v],
                array_keys($out['errors']), $out['errors']),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'    => new external_value(PARAM_INT,
                'The course that was shared'),
            'shared'      => new external_multiple_structure(
                new external_value(PARAM_INT, 'Tenant id newly shared to'),
                'Tenants where a fresh share row was inserted'),
            'reactivated' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Tenant id reactivated'),
                'Tenants whose previously-withdrawn share was reactivated'),
            'unchanged'   => new external_multiple_structure(
                new external_value(PARAM_INT, 'Tenant id'),
                'Tenants that already had an active share — no-op'),
            'errors'      => new external_multiple_structure(
                new external_single_structure([
                    'tenant_id' => new external_value(PARAM_INT, 'Tenant id'),
                    'message'   => new external_value(PARAM_TEXT, 'Error message'),
                ]),
                'Per-tenant errors (invalid id, unknown tenant, etc.)'),
        ]);
    }
}
