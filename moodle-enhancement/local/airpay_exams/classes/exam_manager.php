<?php
namespace local_airpay_exams;

defined('MOODLE_INTERNAL') || die();

/**
 * Exam manager — queries against the online tests table.
 *
 * Replaces direct queries against {local_onlinetests} found in
 * core_renderer.php (lines 1719, 1738) for access control.
 *
 * @package    local_airpay_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exam_manager {

    private const TABLE = 'local_airpay_exams';
    private const LEGACY_TABLE = 'local_onlinetests';

    /**
     * Count online exams (for dashboard stat card).
     *
     * Replaces dashboard.php line 342.
     *
     * @return int
     */
    public static function count_exams(): int {
        global $DB;

        $table = self::resolve_table();
        return $DB->count_records($table);
    }

    /**
     * Get exam record by course module ID.
     *
     * Replaces core_renderer.php line 1719:
     *   SELECT lo.* FROM {local_onlinetests} AS lo
     *   JOIN {course_modules} AS cm ON cm.instance=lo.quizid ...
     *
     * @param int $cmid  Course module ID
     * @return object|false
     */
    public static function get_by_course_module(int $cmid) {
        global $DB;

        $quizmoduleid = $DB->get_field('modules', 'id', ['name' => 'quiz']);
        if (!$quizmoduleid) {
            return false;
        }

        $table = self::resolve_table();

        return $DB->get_record_sql(
            "SELECT e.* FROM {{$table}} e
               JOIN {course_modules} cm ON cm.instance = e.quizid AND cm.module = :modid
              WHERE cm.id = :cmid",
            ['modid' => $quizmoduleid, 'cmid' => $cmid]
        );
    }

    /**
     * Get exam record by quiz attempt ID.
     *
     * Replaces core_renderer.php line 1738:
     *   SELECT lo.id, lo.costcenterid, lo.departmentid FROM {local_onlinetests} AS lo
     *   JOIN {quiz_attempts} AS qa ON qa.quiz = lo.quizid ...
     *
     * @param int $attemptid
     * @return object|false
     */
    public static function get_by_attempt(int $attemptid) {
        global $DB;

        $table = self::resolve_table();

        return $DB->get_record_sql(
            "SELECT e.id, e.costcenterid, e.departmentid
               FROM {{$table}} e
               JOIN {quiz_attempts} qa ON qa.quiz = e.quizid
              WHERE qa.id = :attemptid",
            ['attemptid' => $attemptid]
        );
    }

    private static function resolve_table(): string {
        global $DB;
        $dbman = $DB->get_manager();

        if ($dbman->table_exists(self::TABLE) && $DB->count_records(self::TABLE) > 0) {
            return self::TABLE;
        }
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return self::LEGACY_TABLE;
        }
        return self::TABLE;
    }
}
