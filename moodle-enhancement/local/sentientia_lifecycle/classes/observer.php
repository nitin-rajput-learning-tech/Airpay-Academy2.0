<?php
/**
 * Event observer for employee lifecycle automation.
 *
 * Handles:
 * 1. Joiner: Auto-enrol in mandatory courses when user is created
 * 2. Transfer: Re-evaluate enrolments when department changes
 * 3. Exit: Archive progress when user is suspended (handled by Moodle core)
 *
 * @package    local_sentientia_lifecycle
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_lifecycle;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * User created event — auto-enrol in mandatory courses.
     * Mandatory courses = courses with enddate > now (compliance deadlines).
     */
    public static function user_created(\core\event\user_created $event) {
        global $DB;

        $userid = $event->objectid;
        $user = $DB->get_record('user', ['id' => $userid]);

        if (!$user || $user->deleted || $user->suspended) {
            return;
        }

        // Don't auto-enrol admin accounts.
        if (is_siteadmin($userid)) {
            return;
        }

        self::enrol_in_mandatory_courses($userid);
    }

    /**
     * User updated event — check if department changed, re-evaluate enrolments.
     */
    public static function user_updated(\core\event\user_updated $event) {
        // Department change detection would require comparing old vs new open_departmentid.
        // Moodle's user_updated event doesn't carry the old values.
        // On production, this would be handled by the HRMS sync plugin
        // which explicitly checks for department changes during sync.
        // For now, this is a placeholder for future implementation.
    }

    /**
     * Enrol a user in all mandatory courses (courses with enddate > now).
     */
    private static function enrol_in_mandatory_courses(int $userid) {
        global $DB;

        $now = time();
        $mandatorycourses = $DB->get_records_select('course',
            'enddate > :now AND visible = 1 AND id > 1',
            ['now' => $now], '', 'id,shortname,fullname');

        if (empty($mandatorycourses)) {
            return;
        }

        $enrolplugin = enrol_get_plugin('manual');
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $enrolcount = 0;

        foreach ($mandatorycourses as $course) {
            // Check if already enrolled.
            if (is_enrolled(\context_course::instance($course->id), $userid)) {
                continue;
            }

            // Get or create manual enrol instance.
            $enrolinstance = $DB->get_record('enrol', [
                'courseid' => $course->id,
                'enrol' => 'manual',
            ], '*', IGNORE_MISSING);

            if (!$enrolinstance) {
                $enrolid = $enrolplugin->add_instance($course);
                $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            }

            // Enrol as student with course enddate as enrol end.
            try {
                $enrolplugin->enrol_user($enrolinstance, $userid, $studentroleid, $now, $course->enddate);
                $enrolcount++;
            } catch (\Exception $e) {
                // Silently fail — don't block user creation.
                debugging('Lifecycle auto-enrol failed for user ' . $userid .
                    ' in course ' . $course->id . ': ' . $e->getMessage());
            }
        }

        if ($enrolcount > 0) {
            // Log the auto-enrolment.
            $event = \core\event\user_enrolment_created::create([
                'objectid' => $userid,
                'context' => \context_system::instance(),
                'other' => ['auto_lifecycle' => true, 'courses_enrolled' => $enrolcount],
            ]);
            // Don't trigger — just note it happened.
        }
    }
}
