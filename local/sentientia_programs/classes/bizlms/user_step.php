<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_program_users -> local_sentientia_programs_users (MAP).
 *
 * Grouped by (programid, userid): BizLMS had no unique key and no duplicate check on enrolment
 * (program.php:611-632), while the target has one. A completed row with the latest completion date wins, the
 * earliest enrolment time is kept, and the others are merged.
 *
 * currentlevelid is not set here: it depends on the level completions, which load later (recompute step).
 * The completion time of a learner BizLMS marked completed without a date comes from their level
 * completions, never from timemodified, which the level cron bumped on every recalculation
 * (completion.php:311,335).
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_step extends base_step {

    public function key(): string {
        return 'program.user';
    }

    public function sourcetable(): string {
        return 'local_program_users';
    }

    public function targettable(): string {
        return self::T_USERS;
    }

    public function group_by(): array {
        return ['programid', 'userid'];
    }

    public function columns(): array {
        return ['programid', 'userid', 'levelids', 'completion_status', 'completiondate', 'usercreated',
            'timecreated', 'timemodified'];
    }

    public function transform(array $rows, context $ctx): array {
        $first = reset($rows);
        $programid = (int) $first->programid;
        $userid = (int) $first->userid;

        $target = $ctx->map->resolve('local_program', $programid);
        if ($target === null) {
            [$reason, $detail] = $this->parent_gone($ctx, 'local_program', $programid, 'orphan_program');
            return $this->skip_all($rows, $reason, $detail);
        }
        if (!$ctx->lookups->user_exists($userid)) {
            return $this->skip_all($rows, 'orphan_user');
        }
        if ($ctx->decision('program.deleted_users') === 'skip' && !$ctx->lookups->user_active($userid)) {
            return $this->skip_all($rows, 'deleted_user');
        }

        $ordered = array_values($rows);
        usort($ordered, static function (\stdClass $a, \stdClass $b): int {
            $doneA = (int) $a->completion_status === 1 ? 1 : 0;
            $doneB = (int) $b->completion_status === 1 ? 1 : 0;
            if ($doneA !== $doneB) {
                return $doneB <=> $doneA;
            }
            if ($doneA === 1 && (int) $a->completiondate !== (int) $b->completiondate) {
                return (int) $b->completiondate <=> (int) $a->completiondate;
            }
            return (int) $a->id <=> (int) $b->id;
        });
        $winner = $ordered[0];

        $created = min(array_map(static fn(\stdClass $r): int => (int) $r->timecreated, $ordered));
        $warnings = [];
        $completed = false;
        $timecompleted = null;

        if ((int) $winner->completion_status === 1) {
            if ((int) $winner->completiondate > 0) {
                $completed = true;
                $timecompleted = (int) $winner->completiondate;
            } else if ($ctx->decision('program.completed_without_date') === 'completed_flagged') {
                // BizLMS' lists said "not completed" for this learner, its certificate download said completed
                // (renderer.php:780-787). Completed, flagged, with the date their level completions give.
                $completed = true;
                $warnings[] = 'completed_without_date';
                $timecompleted = $this->date_from_level_completions($programid, $userid, $ctx);
                if ($timecompleted === null) {
                    $warnings[] = 'completion_date_unknown';
                }
            }
        }

        $status = rules::USER_ENROLLED;
        if ($completed) {
            $status = rules::USER_COMPLETED;
        } else if ($this->has_progress($ordered, $programid, $userid, $ctx)) {
            $status = rules::USER_INPROGRESS;
        }

        $result = outcome::insert((int) $winner->id, self::T_USERS, (object) [
            'programid' => $target,
            'userid' => $userid,
            'currentlevelid' => null,
            'status' => $status,
            'timecreated' => $created,
            'timecompleted' => $timecompleted,
            'enrolledby' => max(0, (int) $winner->usercreated),
            'timemodified' => rules::time_or($winner->timemodified, $created),
        ]);
        if ((int) $winner->timemodified <= 0) {
            $warnings[] = 'derived_timestamp';
        }
        foreach ($warnings as $warning) {
            $result->warn($warning);
        }

        $out = [$result];
        foreach ($ordered as $index => $row) {
            if ($index > 0) {
                $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_user_row');
            }
        }
        return $out;
    }

    /**
     * Has the learner done anything in the program? A level list on the enrolment row, a completed level row,
     * or a completed course that belongs to one of the program's levels.
     *
     * @param \stdClass[] $rows The learner's enrolment rows in the program.
     * @param int $programid
     * @param int $userid
     * @param context $ctx
     * @return bool
     */
    private function has_progress(array $rows, int $programid, int $userid, context $ctx): bool {
        foreach ($rows as $row) {
            if (trim((string) $row->levelids) !== '') {
                return true;
            }
        }
        $data = $this->data($ctx);
        if ($data->completed_levels($programid, $userid)) {
            return true;
        }
        $courses = $data->program_course_ids($programid);
        return $courses && $data->course_completions($userid, $courses);
    }

    /**
     * The latest completion date among the learner's completed levels that the import keeps.
     *
     * @param int $programid
     * @param int $userid
     * @param context $ctx
     * @return int|null
     */
    private function date_from_level_completions(int $programid, int $userid, context $ctx): ?int {
        $data = $this->data($ctx);
        $latest = null;
        foreach ($data->completed_levels($programid, $userid) as $row) {
            $legacylevel = (int) $row->levelid;
            if ($ctx->map->resolve('local_program_levels', $legacylevel) === null) {
                continue;
            }
            $levelprogram = $data->level_program($legacylevel) ?? $programid;
            $date = $data->level_completion_date($levelprogram, $legacylevel, $userid, (int) $row->completiondate);
            if ($date !== null && ($latest === null || $date > $latest)) {
                $latest = $date;
            }
        }
        return $latest;
    }
}
