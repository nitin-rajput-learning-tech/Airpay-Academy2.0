<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_courses\sharing_manager;

/**
 * Sprint C — list current + historic shares for a single course.
 *
 * Used by the admin share modal (share.php) to pre-populate which
 * tenant checkboxes are currently ticked. Returns one row per tenant
 * that has EVER been linked (active or withdrawn) so the UI can show
 * "Public: shared since 2026-04-01" or "ZEEA: withdrawn 2026-05-01".
 *
 * @package local_sentientia_courses
 */
class list_course_shares extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid]);

        $context = \context_system::instance();
        self::validate_context($context);
        // Read-only: any user who can view courses can see the share state.
        require_capability('local/sentientia_courses:view', $context);

        // ADR-031 (follow-up, 2026-09-25): :view is a manager-archetype
        // default, so every tenant admin holds it - and this returned the
        // share state, shared_by user id and timestamps of ANY course id. A
        // scoped caller now reads only a course in their own tenant (or a
        // legacy course with no open_path, which every tenant's list shows);
        // one with no tenant reads nothing. A missing course id is refused
        // the same way, so the call is no existence oracle.
        if (!\local_sentientia_platform\tenant::is_cross_tenant()) {
            $course = $DB->get_record('course', ['id' => (int) $params['courseid']], '*', IGNORE_MISSING);
            if (!$course || \local_sentientia_platform\tenant::scope_path() === null) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
            \local_sentientia_platform\tenant::require_path_access(
                rtrim(trim((string) ($course->open_path ?? '')), '/'));
        }

        $shares = sharing_manager::list_course_shares((int) $params['courseid']);
        $known = sharing_manager::known_tenants();
        $known_by_id = [];
        foreach ($known as $t) {
            $known_by_id[(int) $t->id] = $t->name;
        }

        // Build the response with one row per share. We don't return
        // tenants that have never been linked — the UI uses
        // known_tenants() separately to render the full checkbox list.
        $rows = [];
        foreach ($shares as $tenant_id => $row) {
            $rows[] = [
                'tenant_id'   => (int) $tenant_id,
                'tenant_name' => $known_by_id[(int) $tenant_id] ?? "Tenant $tenant_id",
                'status'      => (string) $row->status,
                'shared_by'   => (int) $row->shared_by,
                'timeshared'  => (int) $row->timeshared,
                'timemodified' => (int) $row->timemodified,
            ];
        }

        return [
            'courseid' => (int) $params['courseid'],
            'shares'   => $rows,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'shares'   => new external_multiple_structure(
                new external_single_structure([
                    'tenant_id'    => new external_value(PARAM_INT, 'Tenant root id'),
                    'tenant_name'  => new external_value(PARAM_TEXT,
                        'Human-friendly tenant name (Airpay / Public / ZEEA)'),
                    'status'       => new external_value(PARAM_ALPHA,
                        'active | withdrawn'),
                    'shared_by'    => new external_value(PARAM_INT,
                        'userid of the admin who last touched this row'),
                    'timeshared'   => new external_value(PARAM_INT,
                        'unix ts — when the share row was first created'),
                    'timemodified' => new external_value(PARAM_INT,
                        'unix ts — last status change'),
                ]),
                'Per-tenant share state for this course'),
        ]);
    }
}
