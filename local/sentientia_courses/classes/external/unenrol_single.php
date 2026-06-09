<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Unenrol a single user from a single course.
 *
 * Idempotent — unenrolling a not-enrolled user returns success=true.
 * Only unenrols manual enrolment; other methods (cohort, self-enrol,
 * fee) are preserved.
 */
class unenrol_single extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, ''),
            'userid'   => new external_value(PARAM_INT, ''),
        ]);
    }

    public static function execute(int $courseid, int $userid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'userid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_courses:enrol', $ctx);

        $instance = $DB->get_record('enrol',
            ['courseid' => $params['courseid'], 'enrol' => 'manual', 'status' => 0]);
        if (!$instance) {
            // No manual instance means user isn't enrolled via that path.
            return ['success' => true, 'unenrolled' => false,
                    'reason' => 'No manual enrol instance'];
        }
        $plugin = enrol_get_plugin('manual');
        $plugin->unenrol_user($instance, $params['userid']);

        return ['success' => true, 'unenrolled' => true, 'reason' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, ''),
            'unenrolled' => new external_value(PARAM_BOOL, ''),
            'reason'     => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
