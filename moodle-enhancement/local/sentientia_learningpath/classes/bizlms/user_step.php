<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_learningplan_user to local_sentientia_learningpath_users (mapping doc, section 17).
 *
 * MAP, grouped by (plan, user) because the target is UNIQUE on that pair. A learner row is copied as data:
 * no enrolment into any course, no completion, no message. The status follows the mapping doc, section 17,
 * "Status mapping":
 *
 *  - BizLMS status 1 (completed): Completed (2). timecompleted is the completion date, or NULL with a warning
 *    when BizLMS stored none.
 *  - BizLMS NULL or 0: In progress (1) when any course of the path is completed for the learner
 *    (decision learningplan.not_completed = derive_in_progress), otherwise Enrolled (0).
 *
 * Completion is read from course_completions and is never written or recomputed.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_step extends step {

    /** Most plans whose "has completed a course" set is kept in memory at once. */
    private const CACHE_MAX = 50;

    /** @var array<int, array<int, bool>> Legacy plan id => user ids with at least one path course completed. */
    private array $started = [];

    public function key(): string {
        return 'learningplan.user';
    }

    public function sourcetable(): string {
        return importer::SRC_USER;
    }

    public function targettable(): string {
        return importer::USERS;
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['planid', 'userid'];
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        return ['id', 'planid', 'userid', 'status', 'completiondate', 'timecreated', 'timemodified', 'usercreated',
            'usermodified'];
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[importer::SRC_PLAN, '']];
    }

    /**
     * @param \stdClass[] $rows Every row of one (plan, learner) pair, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $planid = (int) $rows[0]->planid;
        $userid = (int) $rows[0]->userid;
        $out = [];

        $pathid = $ctx->map->resolve(importer::SRC_PLAN, $planid);
        if ($pathid === null) {
            return $this->skip_all($rows, 'orphan_plan');
        }
        // A deleted user is still a user row: their enrolment is history and is imported (readers hide them).
        if ($userid <= 0 || !$ctx->lookups->user_exists($userid)) {
            return $this->skip_all($rows, 'orphan_user', 'user_not_found');
        }

        $winner = $this->winner($rows);
        foreach ($rows as $row) {
            if ((int) $row->id !== (int) $winner->id) {
                $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_enrolment');
            }
        }

        $warnings = [];
        $completed = $this->is_completed($winner);
        $completiondate = plan_rules::positive_or_null($winner->completiondate ?? null);
        $timecompleted = null;
        if ($completed) {
            $status = 2;
            $timecompleted = $completiondate;
            if ($timecompleted === null) {
                $warnings[] = 'completed_without_date';
            }
        } else if ($ctx->decision('learningplan.not_completed') === 'derive_in_progress'
                && $this->has_started($ctx, $planid, $userid)) {
            $status = 1;
        } else {
            $status = 0;
        }

        // The enrolment date is the earliest of the merged rows.
        $created = 0;
        foreach ($rows as $row) {
            $t = plan_rules::int_value($row->timecreated ?? 0);
            if ($t > 0 && ($created === 0 || $t < $created)) {
                $created = $t;
            }
        }
        $modified = plan_rules::int_value($winner->timemodified ?? 0);
        if ($modified <= 0) {
            $modified = $created;
        }

        $outcome = outcome::insert((int) $winner->id, importer::USERS, (object) [
            'pathid' => $pathid,
            'userid' => $userid,
            'status' => $status,
            'enrolledby' => plan_rules::user_id($winner->usercreated ?? 0),
            'timecreated' => $created,
            'timemodified' => $modified,
            'timecompleted' => $timecompleted,
        ]);
        foreach ($warnings as $code) {
            $outcome->warn($code);
        }
        $out[] = $outcome;
        return $out;
    }

    /**
     * Every row of the group skipped for one reason.
     *
     * @param \stdClass[] $rows
     * @param string $reason
     * @param string $detail
     * @return outcome[]
     */
    private function skip_all(array $rows, string $reason, string $detail = ''): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = outcome::skip((int) $row->id, $reason, $detail);
        }
        return $out;
    }

    /**
     * BizLMS status 1 is "completed".
     *
     * @param \stdClass $row
     * @return bool
     */
    private function is_completed(\stdClass $row): bool {
        return (string) ($row->status ?? '') === '1';
    }

    /**
     * The row that carries the pair: a completed row with the earliest completion date, else the lowest id.
     *
     * @param \stdClass[] $rows
     * @return \stdClass
     */
    private function winner(array $rows): \stdClass {
        $winner = null;
        foreach ($rows as $row) {
            if ($winner === null) {
                $winner = $row;
                continue;
            }
            $winnercompleted = $this->is_completed($winner);
            $rowcompleted = $this->is_completed($row);
            if ($rowcompleted && !$winnercompleted) {
                $winner = $row;
            } else if ($rowcompleted && $winnercompleted) {
                // Earlier non-empty completion date wins; ids ascend, so a tie keeps the earlier row.
                $a = plan_rules::positive_or_null($winner->completiondate ?? null);
                $b = plan_rules::positive_or_null($row->completiondate ?? null);
                if ($b !== null && ($a === null || $b < $a)) {
                    $winner = $row;
                }
            }
        }
        return $winner;
    }

    /**
     * Has the user completed any course of the plan? One query per plan, kept for the plan's other learners.
     *
     * @param context $ctx
     * @param int $planid Legacy plan id.
     * @param int $userid
     * @return bool
     */
    private function has_started(context $ctx, int $planid, int $userid): bool {
        global $DB;
        if (!isset($this->started[$planid])) {
            if (count($this->started) >= self::CACHE_MAX) {
                array_shift($this->started);
            }
            $set = [];
            $courseids = plan_source::course_ids($ctx, $planid);
            if ($courseids) {
                [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'lpcc');
                $users = $DB->get_fieldset_sql(
                    "SELECT DISTINCT userid FROM {course_completions} WHERE timecompleted > 0 AND course {$insql}",
                    $params);
                $set = array_fill_keys(array_map('intval', $users), true);
            }
            $this->started[$planid] = $set;
        }
        return isset($this->started[$planid][$userid]);
    }
}
