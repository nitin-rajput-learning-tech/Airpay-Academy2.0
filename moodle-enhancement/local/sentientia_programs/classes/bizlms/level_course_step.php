<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_program_level_courses -> local_sentientia_programs_courses (MAP).
 *
 * Grouped by level, so the courses of a level can be ranked (BizLMS never wrote `position` for level courses,
 * it ordered by id: program.php:815-821,899) and a course listed twice collapses into one row, as the target's
 * unique key (levelid, courseid) requires. The lowest id wins.
 *
 * `mandatory` comes from the level's criteria: a course the criteria list is mandatory, one it leaves out is not
 * (mapping doc, "local_program_level_courses -> courses").
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class level_course_step extends base_step {

    public function key(): string {
        return 'program.course';
    }

    public function sourcetable(): string {
        return 'local_program_level_courses';
    }

    public function targettable(): string {
        return self::T_COURSES;
    }

    public function group_by(): array {
        return ['levelid'];
    }

    public function columns(): array {
        return ['programid', 'levelid', 'courseid', 'timecreated'];
    }

    public function transform(array $rows, context $ctx): array {
        $first = reset($rows);
        $legacylevel = (int) $first->levelid;
        $leveltarget = $ctx->map->resolve('local_program_levels', $legacylevel);
        // The level row names its program; a course row's own programid can disagree and loses.
        $programid = $this->data($ctx)->level_program($legacylevel);
        $criteria = $programid === null ? null : $this->data($ctx)->level_criteria($programid, $legacylevel);

        $out = [];
        $winners = [];
        $rank = 0;
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $courseid = (int) $row->courseid;
            if ($courseid <= 1) {
                // 0 is the column default; 1 is the front page, which Sentientia refuses as a level course.
                $out[] = outcome::skip($id, 'invalid_course');
            } else if (!$ctx->lookups->course_exists($courseid)) {
                $out[] = outcome::skip($id, 'orphan_course');
            } else if ($leveltarget === null) {
                // BizLMS deleted the level (orphan_level), or the import chose not to keep it (its program was
                // skipped, or the level was): parent_skipped, with the level's own reason as the detail.
                [$reason, $detail] = $this->parent_gone($ctx, 'local_program_levels', $legacylevel, 'orphan_level');
                $out[] = outcome::skip($id, $reason, $detail);
            } else if (isset($winners[$courseid])) {
                $out[] = outcome::merge($id, $winners[$courseid], 'dup_level_course');
            } else {
                $winners[$courseid] = $id;
                $out[] = outcome::insert($id, self::T_COURSES, (object) [
                    'levelid' => $leveltarget,
                    'courseid' => $courseid,
                    'sortorder' => $rank++,
                    'mandatory' => rules::course_mandatory($criteria, $courseid),
                    'timecreated' => (int) $row->timecreated,
                ]);
            }
        }
        return $out;
    }
}
