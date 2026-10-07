<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_classroom_courses -> local_sentientia_classroom_courses (MAP, grouped by classroom and course).
 *
 * The courses a classroom is linked to. The target is unique on (classroomid, courseid), so rows that repeat
 * a pair are merged into the lowest id. The pre-test and post-test ids and the course duration of the BizLMS
 * row are not copied: nothing in Sentientia reads them.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_step extends step {

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.courses';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_classroom_courses';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_classroom_courses';
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['classroomid', 'courseid'];
    }

    /**
     * The parent maps this step resolves, loaded once instead of one query per distinct parent.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_classroom', '']];
    }

    /**
     * @param \stdClass[] $rows One classroom and course, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $winner = reset($rows);
        $classroomid = $ctx->map->resolve('local_classroom', mapping::int($winner, 'classroomid'));
        $courseid = mapping::int($winner, 'courseid');

        $reason = null;
        if ($classroomid === null) {
            $reason = 'orphan_classroom';
        } else if ($courseid <= 0 || !$ctx->lookups->course_exists($courseid)) {
            // BizLMS stored 0 for a classroom with no course.
            $reason = 'orphan_course';
        }
        if ($reason !== null) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, $reason);
            }
            return $out;
        }

        $created = PHP_INT_MAX;
        $modified = 0;
        foreach ($rows as $row) {
            $rowcreated = mapping::time_created($row);
            $created = min($created, $rowcreated);
            $modified = max($modified, mapping::time_modified($row, $rowcreated));
        }
        $out = [outcome::insert((int) $winner->id, 'local_sentientia_classroom_courses', (object) [
            'classroomid' => $classroomid,
            'courseid' => $courseid,
            'timecreated' => $created,
            'timemodified' => max($created, $modified),
        ])];
        foreach (array_slice($rows, 1) as $row) {
            $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_natural_key');
        }
        return $out;
    }
}
