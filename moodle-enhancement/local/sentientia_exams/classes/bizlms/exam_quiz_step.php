<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\step;

/**
 * What the two exams steps have in common (ADR-032, mapping doc section 9).
 *
 * A BizLMS online exam is a COURSE that carries open_module = 'online_exams' and open_coursetype = 1, in the
 * singleactivity format, and holds a quiz. Both steps therefore read the quizzes of those courses, grouped by
 * course: one group is one exam course, and the group's quiz rows are the quizzes the course holds.
 *
 * Why the physical source is the quiz table and not the course table. The mapping doc names the accounting unit
 * '#course.online_exams' (course rows). Moodle's course.cacherev is rewritten on EVERY course by a cache purge, and
 * the source fingerprint (count, max id, CRC over all columns) would then read a purge between a crash and
 * --resume as "the source changed" and refuse to resume. A quiz row only changes when somebody edits the quiz. The
 * map key keeps the doc's shape all the same: sourceid is the COURSE id, and the primary row is the course's
 * lowest quiz.
 *
 * A step is pure: it returns outcomes and never writes (ADR-032).
 *
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class exam_quiz_step extends step {

    /** Value of course.open_module that marks a BizLMS online exam course (BZ local/onlineexams/classes/external.php:150). */
    public const MODULE_EXAMS = 'online_exams';

    /** Value of course.open_coursetype of an exam (or forum) pseudo-course (BZ local/onlineexams/classes/external.php:161). */
    public const COURSETYPE_PSEUDO = 1;

    /** The physical table of both steps. */
    public const SOURCE_TABLE = 'quiz';

    /**
     * Group the quizzes of one course: a derived group has to say how rows group.
     *
     * @return string[]
     */
    public function group_by(): array {
        return ['course'];
    }

    /**
     * The quiz columns the steps read.
     *
     * @return string[]
     */
    public function columns(): array {
        return ['id', 'course', 'name', 'timelimit', 'timeclose', 'timecreated', 'timemodified'];
    }

    /**
     * Only the quizzes of exam courses. A database without the BizLMS course columns has no exam courses,
     * so nothing is read (1 = 0) instead of failing on a column that is not there.
     *
     * @return array{0: string, 1: array}
     */
    public function source_filter(): array {
        return self::quiz_filter();
    }

    /**
     * The filter of the quiz table, for steps, preflight and verify alike.
     *
     * @return array{0: string, 1: array}
     */
    public static function quiz_filter(): array {
        if (!self::has_markers()) {
            return ['1 = 0', []];
        }
        [$sql, $params] = self::course_markers('ec');
        return ['t.course IN (SELECT ec.id FROM {course} ec WHERE ec.id > 1 AND ' . $sql . ')', $params];
    }

    /**
     * The filter of the course table (alias t) for exam courses.
     *
     * @return array{0: string, 1: array}
     */
    public static function course_filter(): array {
        if (!self::has_markers()) {
            return ['1 = 0', []];
        }
        [$sql, $params] = self::course_markers('t');
        return ['t.id > 1 AND ' . $sql, $params];
    }

    /**
     * Does the course table carry the BizLMS markers? Read fresh each time: the answer is the database's, and
     * the database layer caches the column list.
     *
     * @return bool
     */
    public static function has_markers(): bool {
        global $DB;
        $columns = $DB->get_columns('course');
        return isset($columns['open_module']) && isset($columns['open_coursetype']);
    }

    /**
     * The exam markers of a course row.
     *
     * @param string $alias Alias of the course table.
     * @return array{0: string, 1: array}
     */
    private static function course_markers(string $alias): array {
        return [
            "{$alias}.open_module = :exmarkmodule AND {$alias}.open_coursetype = :exmarktype",
            ['exmarkmodule' => self::MODULE_EXAMS, 'exmarktype' => self::COURSETYPE_PSEUDO],
        ];
    }

    /**
     * Repair text that is not valid UTF-8. The writer refuses a value that is not, which would stop a whole batch;
     * the original bytes stay in the legacy table, so the row is imported with the bad bytes replaced and a warning
     * says so.
     *
     * @param string|null $value
     * @return array{0: string, 1: bool} [text, repaired]
     */
    protected static function clean_text(?string $value): array {
        $value = (string) $value;
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return [$value, false];
        }
        return [mb_scrub($value, 'UTF-8'), true];
    }

    /**
     * The quiz rows of a group in id order: the lowest id is the course's primary quiz in both steps.
     *
     * @param \stdClass[] $rows
     * @return \stdClass[]
     */
    protected static function by_id(array $rows): array {
        $rows = array_values($rows);
        usort($rows, static fn(\stdClass $a, \stdClass $b): int => (int) $a->id <=> (int) $b->id);
        return $rows;
    }
}
