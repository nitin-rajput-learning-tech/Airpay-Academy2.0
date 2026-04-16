<?php
namespace local_airpay_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * Learning path manager — CRUD and progress queries.
 *
 * Replaces BizLMS local_learningplan functionality.
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_manager {

    private const TABLE = 'local_airpay_learningpath';
    private const COURSES_TABLE = 'local_airpay_learningpath_courses';
    private const USERS_TABLE = 'local_airpay_learningpath_users';

    /**
     * Get a learning path by ID.
     *
     * @param int $pathid
     * @return object|false
     */
    public static function get(int $pathid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $pathid]);
    }

    /**
     * Get all courses in a learning path (ordered).
     *
     * @param int $pathid
     * @return array  Course records with sortorder
     */
    public static function get_courses(int $pathid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT lpc.*, c.fullname, c.shortname, c.visible
               FROM {" . self::COURSES_TABLE . "} lpc
               JOIN {course} c ON c.id = lpc.courseid
              WHERE lpc.pathid = :pathid
           ORDER BY lpc.sortorder ASC",
            ['pathid' => $pathid]
        );
    }

    /**
     * Check if a user is enrolled in a learning path.
     *
     * @param int $pathid
     * @param int $userid
     * @return bool
     */
    public static function is_enrolled(int $pathid, int $userid): bool {
        global $DB;

        // Check Airpay table first.
        if ($DB->record_exists(self::USERS_TABLE, ['pathid' => $pathid, 'userid' => $userid])) {
            return true;
        }

        // Fallback: check BizLMS table.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_learningplan_user')) {
            return $DB->record_exists('local_learningplan_user', [
                'planid' => $pathid,
                'userid' => $userid,
            ]);
        }

        return false;
    }

    /**
     * Get user's progress through a learning path.
     *
     * @param int $pathid
     * @param int $userid
     * @return object  {total_courses, completed_courses, percentage}
     */
    public static function get_user_progress(int $pathid, int $userid): object {
        global $DB;

        $courses = self::get_courses($pathid);
        $total = count($courses);
        $completed = 0;

        foreach ($courses as $pathcourse) {
            if ($DB->record_exists_select('course_completions',
                "course = :cid AND userid = :uid AND timecompleted > 0",
                ['cid' => $pathcourse->courseid, 'uid' => $userid])) {
                $completed++;
            }
        }

        return (object) [
            'total_courses'     => $total,
            'completed_courses' => $completed,
            'percentage'        => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Count learning paths for a tenant.
     *
     * @param string $pathfilter  e.g. "/1/%" or empty for all
     * @return int
     */
    public static function count_paths(string $pathfilter = ''): int {
        global $DB;

        $dbman = $DB->get_manager();

        if ($dbman->table_exists(self::TABLE) && $DB->count_records(self::TABLE) > 0) {
            if (!empty($pathfilter)) {
                return $DB->count_records_select(self::TABLE, "open_path LIKE :p", ['p' => $pathfilter]);
            }
            return $DB->count_records(self::TABLE);
        }

        // Fallback to BizLMS.
        if ($dbman->table_exists('local_learningplan')) {
            return $DB->count_records('local_learningplan');
        }

        return 0;
    }
}
