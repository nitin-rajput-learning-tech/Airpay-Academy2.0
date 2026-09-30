<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_plan_course_status to local_sentientia_lp_course_status (mapping doc, section 17).
 *
 * Nothing in BizLMS writes or reads this table and its production row count is expected to be zero, so the
 * columns are copied as they are: status and percentage are raw integers no code defines. When a (plan,
 * course, learner) triple occurs more than once the latest row (highest id) carries the triple, because the
 * table is a status log and the last write is the current state; the earlier rows are merged.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_status_step extends step {

    public function key(): string {
        return 'learningplan.course_status';
    }

    public function sourcetable(): string {
        return importer::SRC_STATUS;
    }

    public function targettable(): string {
        return importer::STATUS;
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['planid', 'courseid', 'userid'];
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        return ['id', 'planid', 'courseid', 'userid', 'status', 'percentage', 'startdate', 'completiondate',
            'timecreated', 'timemodified', 'usercreated', 'usermodified'];
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[importer::SRC_PLAN, '']];
    }

    /**
     * @param \stdClass[] $rows Every row of one (plan, course, learner) triple, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $first = $rows[0];
        $planid = (int) $first->planid;
        $courseid = (int) $first->courseid;
        $userid = (int) $first->userid;

        $reason = null;
        $detail = '';
        $pathid = $ctx->map->resolve(importer::SRC_PLAN, $planid);
        if ($pathid === null) {
            $reason = 'orphan_plan';
        } else if ($userid <= 0 || !$ctx->lookups->user_exists($userid)) {
            $reason = 'orphan_user';
            $detail = 'user_not_found';
        } else if ($courseid <= 1 || !$ctx->lookups->course_exists($courseid)) {
            $reason = 'orphan_course';
            $detail = 'course_missing';
        }
        $out = [];
        if ($reason !== null) {
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, $reason, $detail);
            }
            return $out;
        }

        $winner = end($rows);
        foreach ($rows as $row) {
            if ((int) $row->id !== (int) $winner->id) {
                $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_course_status');
            }
        }
        [$created, $modified] = plan_rules::times($winner);
        $out[] = outcome::insert((int) $winner->id, importer::STATUS, (object) [
            'pathid' => $pathid,
            'courseid' => $courseid,
            'userid' => $userid,
            'status' => plan_rules::int_value($winner->status ?? 0),
            'percentage' => plan_rules::int_value($winner->percentage ?? 0),
            'startdate' => plan_rules::positive_or_null($winner->startdate ?? null),
            'completiondate' => plan_rules::positive_or_null($winner->completiondate ?? null),
            'usercreated' => plan_rules::user_id($winner->usercreated ?? 0),
            'usermodified' => plan_rules::user_id($winner->usermodified ?? 0),
            'timecreated' => $created,
            'timemodified' => $modified,
        ]);
        return $out;
    }
}
