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
 * Native enrol — find user by email/employee_id, enrol in a course.
 *
 * Replaces the deep-link to Moodle core /enrol/users.php. Same result
 * but stays within the airpay UX.
 */
class enrol_single extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, ''),
            'identifier' => new external_value(PARAM_TEXT, 'email OR employee_id OR username'),
        ]);
    }

    public static function execute(int $courseid, string $identifier): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'identifier'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_courses:enrol', $ctx);

        // Resolve user — try email first, then employee_id, then username.
        $id = trim($params['identifier']);
        $user = $DB->get_record_sql(
            "SELECT * FROM {user}
              WHERE deleted = 0 AND suspended = 0
                AND (email = :e OR open_employeeid = :emp OR username = :un)
              LIMIT 1",
            ['e' => $id, 'emp' => $id, 'un' => $id]);
        if (!$user) {
            return ['success' => false, 'enrolled' => false,
                    'reason' => 'User not found: ' . $id, 'userid' => 0];
        }

        // Already enrolled?
        $context = \context_course::instance($params['courseid']);
        if (is_enrolled($context, $user->id)) {
            return ['success' => true, 'enrolled' => false,
                    'reason' => 'Already enrolled', 'userid' => (int) $user->id];
        }

        // Use or create manual enrol instance.
        $instance = $DB->get_record('enrol',
            ['courseid' => $params['courseid'], 'enrol' => 'manual', 'status' => 0]);
        if (!$instance) {
            $course = $DB->get_record('course',
                ['id' => $params['courseid']], '*', MUST_EXIST);
            $plugin = enrol_get_plugin('manual');
            $plugin->add_default_instance($course);
            $instance = $DB->get_record('enrol',
                ['courseid' => $params['courseid'], 'enrol' => 'manual', 'status' => 0]);
        }
        $plugin = enrol_get_plugin('manual');
        $studentroleid = (int) ($DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5);
        $plugin->enrol_user($instance, $user->id, $studentroleid, time(), 0,
            ENROL_USER_ACTIVE);

        return [
            'success'   => true,
            'enrolled'  => true,
            'reason'    => '',
            'userid'    => (int) $user->id,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, ''),
            'enrolled' => new external_value(PARAM_BOOL, ''),
            'reason'   => new external_value(PARAM_TEXT, ''),
            'userid'   => new external_value(PARAM_INT, ''),
        ]);
    }
}
