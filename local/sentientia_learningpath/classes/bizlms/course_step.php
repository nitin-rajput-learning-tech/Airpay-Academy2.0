<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_learningplan_courses to local_sentientia_learningpath_courses (mapping doc, section 17).
 *
 * MAP. The step groups by plan, not by (plan, course) as the mapping doc's unique-key note suggests, because
 * the target keeps a dense 0..n-1 sort order per path and that needs every course of the path in one place.
 * Inside the group the same (plan, course) pair is still folded into one row, which is what the target's
 * UNIQUE (pathid, courseid) needs.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_step extends step {

    public function key(): string {
        return 'learningplan.course';
    }

    public function sourcetable(): string {
        return importer::SRC_COURSE;
    }

    public function targettable(): string {
        return importer::COURSES;
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['planid'];
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        // moduletype is not read: the source_spec enum makes preflight refuse anything but '' and 'course'.
        return ['id', 'planid', 'courseid', 'sortorder', 'nextsetoperator', 'timecreated', 'timemodified',
            'usercreated', 'usermodified'];
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[importer::SRC_PLAN, '']];
    }

    /**
     * @param \stdClass[] $rows Every course row of one plan, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        $planid = (int) $rows[0]->planid;
        $pathid = $ctx->map->resolve(importer::SRC_PLAN, $planid);
        if ($pathid === null) {
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, 'orphan_plan');
            }
            return $out;
        }

        // Rows that can be imported, keyed by Moodle course.
        $bycourse = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $courseid = (int) ($row->courseid ?? 0);
            if ($courseid <= 0) {
                $out[] = outcome::skip($id, 'no_course');
            } else if ($courseid === 1 || !$ctx->lookups->course_exists($courseid)) {
                // Course 1 is the site, not a learner-facing course; the reader inner-joins course.
                $out[] = outcome::skip($id, 'orphan_course', 'course_missing');
            } else {
                $bycourse[$courseid][] = $row;
            }
        }
        if (!$bycourse) {
            return $out;
        }

        // One row per course: the lowest non-NULL sort order wins, then the lowest id; the rest are merged.
        $winners = [];
        foreach ($bycourse as $courseid => $candidates) {
            usort($candidates, static fn(\stdClass $a, \stdClass $b): int => self::compare($a, $b));
            $winner = array_shift($candidates);
            $winners[$courseid] = $winner;
            foreach ($candidates as $duplicate) {
                $out[] = outcome::merge((int) $duplicate->id, (int) $winner->id, 'dup_course');
            }
        }

        // Dense 0..n-1 order: rows with a sort order first (sort order, then id), then the NULLs in id order.
        $ordered = array_values($winners);
        usort($ordered, static fn(\stdClass $a, \stdClass $b): int => self::compare($a, $b));

        $pathcreated = plan_source::plan_timecreated($ctx, $planid);
        foreach ($ordered as $position => $row) {
            [$mandatory, $known] = plan_rules::mandatory_from_operator($row->nextsetoperator ?? null);
            [$created, $modified] = plan_rules::times($row, $pathcreated);
            $outcome = outcome::insert((int) $row->id, importer::COURSES, (object) [
                'pathid' => $pathid,
                'courseid' => (int) $row->courseid,
                'sortorder' => $position,
                'mandatory' => $mandatory,
                'is_remedial' => 0,
                'is_accelerator' => 0,
                'remedial_for_courseid' => null,
                'usercreated' => plan_rules::user_id($row->usercreated ?? 0),
                'usermodified' => plan_rules::user_id($row->usermodified ?? 0),
                'timecreated' => $created,
                'timemodified' => $modified,
            ]);
            if (!$known) {
                $outcome->warn('unknown_operator');
            }
            $out[] = $outcome;
        }
        return $out;
    }

    /**
     * Order two course rows: a row with a sort order before one without (NULL sorts last), then by sort order,
     * then by id. The same rule picks the winner of a duplicate and orders the path.
     *
     * @param \stdClass $a
     * @param \stdClass $b
     * @return int
     */
    private static function compare(\stdClass $a, \stdClass $b): int {
        $sa = $a->sortorder ?? null;
        $sb = $b->sortorder ?? null;
        if (($sa === null) !== ($sb === null)) {
            return $sa === null ? 1 : -1;
        }
        if ($sa !== null && (int) $sa !== (int) $sb) {
            return (int) $sa <=> (int) $sb;
        }
        return (int) $a->id <=> (int) $b->id;
    }
}
