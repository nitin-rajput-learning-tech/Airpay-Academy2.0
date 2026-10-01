<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_bc_level_completions -> local_sentientia_programs_lvlcomp (MAP), completed rows only.
 *
 * Grouped by (levelid, userid): the target has a unique key on it and BizLMS did not. Rows that are not
 * completed carry no completion (an admin reset leaves exactly such a row, program.php:1669-1670), so each is
 * archived with a reason; they are never filtered out of the read, because every source row needs one outcome
 * (ADR-032, id strategy 1).
 *
 * A completion needs an imported enrolment of the same learner in the same program: unenrolling deleted the
 * enrolment row and left the level rows behind (program.php:716-717).
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class level_completion_step extends base_step {

    public function key(): string {
        return 'program.levelcompletion';
    }

    public function sourcetable(): string {
        return 'local_bc_level_completions';
    }

    public function targettable(): string {
        return self::T_LVLCOMP;
    }

    public function group_by(): array {
        return ['levelid', 'userid'];
    }

    public function columns(): array {
        return ['programid', 'levelid', 'userid', 'completion_status', 'completiondate', 'bclcids',
            'timecreated', 'timemodified'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        $done = [];
        foreach ($rows as $row) {
            if ((int) $row->completion_status === 1) {
                $done[] = $row;
            } else {
                $out[] = outcome::archive((int) $row->id, 'level_not_completed');
            }
        }
        if (!$done) {
            return $out;
        }

        // The most recent stored completion wins; the lowest id breaks a tie.
        usort($done, static function (\stdClass $a, \stdClass $b): int {
            if ((int) $a->completiondate !== (int) $b->completiondate) {
                return (int) $b->completiondate <=> (int) $a->completiondate;
            }
            return (int) $a->id <=> (int) $b->id;
        });
        $winner = $done[0];
        $legacylevel = (int) $winner->levelid;
        $userid = (int) $winner->userid;
        $data = $this->data($ctx);

        // The level row says which program the completion belongs to; the completion's own programid can disagree.
        $legacyprogram = $data->level_program($legacylevel) ?? (int) $winner->programid;
        $programtarget = $ctx->map->resolve('local_program', $legacyprogram);
        $leveltarget = $ctx->map->resolve('local_program_levels', $legacylevel);

        $reason = null;
        $detail = '';
        if ($programtarget === null) {
            // Deleted by BizLMS (orphan_program) or not kept by the import (parent_skipped).
            [$reason, $detail] = $this->parent_gone($ctx, 'local_program', $legacyprogram, 'orphan_program');
        } else if ($leveltarget === null) {
            [$reason, $detail] = $this->parent_gone($ctx, 'local_program_levels', $legacylevel, 'orphan_level');
        } else if (!$ctx->lookups->user_exists($userid)) {
            $reason = 'orphan_user';
        } else if (!$this->enrolled($legacyprogram, $userid, $ctx)) {
            $reason = 'no_enrolment';
        }
        if ($reason !== null) {
            foreach ($done as $row) {
                $out[] = outcome::skip((int) $row->id, $reason, $detail);
            }
            return $out;
        }

        $created = min(array_map(static fn(\stdClass $r): int => (int) $r->timecreated, $done));
        $timecompleted = $data->level_completion_date($legacyprogram, $legacylevel, $userid,
            (int) $winner->completiondate);
        $courseids = trim((string) $winner->bclcids);

        $result = outcome::insert((int) $winner->id, self::T_LVLCOMP, (object) [
            'programid' => $programtarget,
            'levelid' => $leveltarget,
            'userid' => $userid,
            'status' => 1,
            'timecompleted' => $timecompleted,
            'completedcourseids' => $courseids === '' ? null : $courseids,
            'source' => 'bizlms',
            'timecreated' => $created,
            'timemodified' => rules::time_or($winner->timemodified, $created),
        ]);
        if ($timecompleted === null) {
            $result->warn('completion_date_unknown');
        }
        if ((int) $winner->timemodified <= 0) {
            $result->warn('derived_timestamp');
        }
        $out[] = $result;
        foreach (array_slice($done, 1) as $row) {
            $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_level_completion');
        }
        return $out;
    }

    /**
     * Does the learner have an enrolment row in the program that the import kept?
     *
     * @param int $legacyprogram
     * @param int $userid
     * @param context $ctx
     * @return bool
     */
    private function enrolled(int $legacyprogram, int $userid, context $ctx): bool {
        $ids = $this->data($ctx)->enrolment_ids($legacyprogram, $userid);
        if (!$ids) {
            return false;
        }
        foreach ($ctx->map->resolve_many('local_program_users', $ids) as $target) {
            if ($target !== null) {
                return true;
            }
        }
        return false;
    }
}
