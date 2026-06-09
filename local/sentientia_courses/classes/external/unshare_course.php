<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_courses\sharing_manager;

/**
 * Sprint C — withdraw a single (courseid, tenant_id) share.
 *
 * "Withdraw" rather than "delete" because we keep the audit history.
 * The share row stays in the DB with status='withdrawn'; future
 * re-shares reuse the same row.
 *
 * Audit: fires \local_sentientia_courses\event\course_share_withdrawn.
 *
 * @package local_sentientia_courses
 */
class unshare_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'  => new external_value(PARAM_INT, 'Course id'),
            'tenantid'  => new external_value(PARAM_INT,
                'Tenant root id to withdraw the share from'),
        ]);
    }

    public static function execute(int $courseid, int $tenantid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid, 'tenantid' => $tenantid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_courses:share_to_tenant', $context);
        require_sesskey();

        $changed = sharing_manager::unshare_course(
            (int) $params['courseid'],
            (int) $params['tenantid']);

        return [
            'courseid' => (int) $params['courseid'],
            'tenantid' => (int) $params['tenantid'],
            'changed'  => $changed,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT,
                'Course that was unshared'),
            'tenantid' => new external_value(PARAM_INT,
                'Tenant the share was withdrawn from'),
            'changed'  => new external_value(PARAM_BOOL,
                'True if a row was updated (false on no-op = already withdrawn)'),
        ]);
    }
}
