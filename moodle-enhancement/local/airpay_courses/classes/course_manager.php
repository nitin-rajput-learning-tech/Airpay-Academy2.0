<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_airpay_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Course manager — progress tracking, enrollment helpers, course queries.
 *
 * Replaces \local_courses\lib\accesslib methods and the scattered
 * course queries found throughout core_renderer and airpay plugins.
 *
 * @package    local_airpay_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_manager {

    /**
     * Get course completion percentage for a user.
     *
     * Drop-in replacement for:
     *   \local_courses\lib\accesslib()::get_user_course_progress_percentage($courseid, $userid)
     *
     * Uses Moodle core completion API (no BizLMS wrapper needed).
     *
     * @param int $courseid
     * @param int $userid
     * @return float  Percentage 0-100, or 0 if no completion tracking
     */
    public static function get_progress_percentage(int $courseid, int $userid): float {
        $course = get_course($courseid);
        if (empty($course) || $course->enablecompletion == 0) {
            return 0.0;
        }

        $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
        return $progress !== null ? round((float) $progress, 1) : 0.0;
    }

    /**
     * Check if a user has completed a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_completed(int $courseid, int $userid): bool {
        global $DB;
        return $DB->record_exists('course_completions', [
            'course'        => $courseid,
            'userid'        => $userid,
            'timecompleted' => ['>', 0],
        ]);
    }

    /**
     * Get completion deadline for a course based on open_coursecompletiondays.
     *
     * @param int $courseid
     * @param int $userid
     * @return int|null  Unix timestamp of deadline, or null if no deadline
     */
    public static function get_completion_deadline(int $courseid, int $userid): ?int {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, open_coursecompletiondays');
        if (empty($course->open_coursecompletiondays) || $course->open_coursecompletiondays <= 0) {
            return null;
        }

        // Get enrollment start time.
        $enroltime = $DB->get_field_sql(
            "SELECT MIN(ue.timestart)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid AND ue.timestart > 0",
            ['cid' => $courseid, 'uid' => $userid]
        );

        if (empty($enroltime)) {
            return null;
        }

        return (int) $enroltime + ($course->open_coursecompletiondays * 86400);
    }

    /**
     * Count visible courses for a tenant.
     *
     * @param string $pathfilter  e.g. "/1/%" — empty = all
     * @return int
     */
    public static function count_visible_courses(string $pathfilter = ''): int {
        global $DB;

        $sql = "SELECT COUNT(id) FROM {course} WHERE visible = 1 AND id != 1";
        $params = [];

        if (!empty($pathfilter)) {
            $sql .= " AND open_path LIKE :cpath";
            $params['cpath'] = $pathfilter;
        }

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Check if user has course management capability (L&D admin detection).
     *
     * Checks BOTH old (local/courses:manage) and new (local/airpay_courses:manage)
     * capabilities during transition.
     *
     * @param \context|null $context  (null = system context)
     * @return bool
     */
    public static function can_manage(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();

        return is_siteadmin()
            || has_capability('local/airpay_courses:manage', $context)
            || has_capability('local/courses:manage', $context);
    }

    /**
     * Check if user can enrol others.
     *
     * @param \context|null $context
     * @return bool
     */
    public static function can_enrol(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();

        return is_siteadmin()
            || has_capability('local/airpay_courses:enrol', $context)
            || has_capability('local/courses:enrol', $context);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Create a new course.
     *
     * Uses Moodle's create_course() so all events fire (course_created event,
     * gradebook setup, blocks added per format defaults, etc.).
     *
     * @param object $data  Form data with: fullname, shortname, category, format,
     *                      summary, summaryformat, plus optional open_* fields
     * @return int  New course ID
     * @throws \moodle_exception
     */
    public static function create(object $data): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Validate required fields.
        if (empty($data->fullname) || empty($data->shortname) || empty($data->category)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_courses');
        }

        // Check shortname uniqueness.
        if ($DB->record_exists('course', ['shortname' => $data->shortname])) {
            throw new \moodle_exception('shortnametaken', 'local_airpay_courses');
        }

        // Build course record. create_course() wants stdClass with specific fields.
        $course = new \stdClass();
        $course->category    = (int) $data->category;
        $course->fullname    = trim($data->fullname);
        $course->shortname   = trim($data->shortname);
        $course->idnumber    = $data->idnumber ?? '';
        $course->summary     = $data->summary ?? '';
        $course->summaryformat = $data->summaryformat ?? FORMAT_HTML;
        $course->format      = $data->format ?? 'topics';
        $course->numsections = (int) ($data->numsections ?? 5);
        $course->visible     = isset($data->visible) ? (int) $data->visible : 1;
        $course->startdate   = $data->startdate ?? time();
        $course->enddate     = $data->enddate ?? 0;
        $course->lang        = $data->lang ?? '';

        // Tenant scoping — derive open_path from organisation if set.
        if (!empty($data->open_costcenterid)) {
            $org = $DB->get_record('local_airpay_org', ['id' => $data->open_costcenterid]);
            if ($org) {
                $course->open_path = $org->path;
                $course->open_costcenterid = $org->id;
            }
        }

        // Create via core API.
        $newcourse = create_course($course);

        return $newcourse->id;
    }

    /**
     * Update an existing course.
     *
     * @param int $courseid
     * @param object $data
     * @return bool
     * @throws \moodle_exception
     */
    public static function update(int $courseid, object $data): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $existing = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Build update record.
        $course = new \stdClass();
        $course->id = $courseid;

        // Standard fields.
        $stdfields = ['fullname', 'shortname', 'idnumber', 'summary', 'summaryformat',
                      'format', 'visible', 'startdate', 'enddate', 'lang', 'category'];
        foreach ($stdfields as $field) {
            if (isset($data->$field)) {
                $course->$field = $data->$field;
            }
        }

        // Shortname uniqueness check.
        if (isset($course->shortname) && $course->shortname !== $existing->shortname) {
            if ($DB->record_exists_select('course',
                'shortname = :sn AND id != :id',
                ['sn' => $course->shortname, 'id' => $courseid])) {
                throw new \moodle_exception('shortnametaken', 'local_airpay_courses');
            }
        }

        // Tenant scoping update.
        if (isset($data->open_costcenterid)) {
            $org = $DB->get_record('local_airpay_org', ['id' => $data->open_costcenterid]);
            if ($org) {
                $course->open_path = $org->path;
                $course->open_costcenterid = $org->id;
            }
        }

        update_course($course);
        return true;
    }

    /**
     * Toggle course visibility (show/hide).
     *
     * @param int $courseid
     * @param bool|null $visible  null = toggle current state
     * @return bool  New visibility
     * @throws \moodle_exception
     */
    public static function toggle_visibility(int $courseid, ?bool $visible = null): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $DB->get_record('course', ['id' => $courseid], 'id, visible', MUST_EXIST);

        $newstate = $visible ?? !((bool) $course->visible);

        // Use update_course so events fire and visibility cache is invalidated.
        $update = (object) [
            'id'      => $courseid,
            'visible' => $newstate ? 1 : 0,
            'visibleold' => $newstate ? 1 : 0,
        ];
        update_course($update);

        return $newstate;
    }

    /**
     * Delete a course.
     *
     * @param int $courseid
     * @return bool
     * @throws \moodle_exception
     */
    public static function delete(int $courseid): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Block deleting site course (id=1).
        if ($courseid <= 1) {
            throw new \moodle_exception('cannotdeletesitecourse', 'local_airpay_courses');
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        return delete_course($course, false);
    }
}
