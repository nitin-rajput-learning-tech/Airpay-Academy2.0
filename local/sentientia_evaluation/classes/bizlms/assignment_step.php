<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_evaluation_users -> local_sentientia_evaluation_assign (ADR-032, mapping doc section 18). MAP, grouped.
 *
 * A BizLMS row says "this person was asked to answer this form". The source has no unique key (writers guarded
 * with record_exists), so the same person can appear twice for a form. The unit here is the (form, person) pair:
 * the row with the earliest timecreated wins and keeps its creator, the others are MERGED into it and stay in the
 * legacy table. Without that, the unique index of the target would reject the second row and roll back the batch.
 *
 * Status. BizLMS never wrote local_evaluation_users.status (users_assign.php), so it is ignored. A pair is
 * 'responded' when a completion exists for the form and the person, and 'expired' otherwise: the form is imported
 * archived, so nothing can still be answered, and expire_assignments only touches 'assigned' rows.
 *
 * responded_at is the latest completion time of the pair. On an identity-protected form it is cut to the start of
 * its day, because the anonymous response beside it carries the same minute and list_assignments() would hand
 * the two to anyone who could read both.
 *
 * A trainer feedback form (plugin classroom) becomes a classroom_end assignment for that classroom; every other
 * form is manual.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assignment_step extends step {

    /** Step key. */
    public const KEY = 'evaluation.assignments';

    /** @var form_facts */
    private form_facts $facts;

    /**
     * @param form_facts $facts
     */
    public function __construct(form_facts $facts) {
        $this->facts = $facts;
    }

    public function key(): string {
        return self::KEY;
    }

    public function sourcetable(): string {
        return importer::SRC_USERS;
    }

    public function targettable(): string {
        return importer::T_ASSIGN;
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['evaluationid', 'userid'];
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[importer::SRC_FORMS, ''], [importer::SRC_CLASSROOM, '']];
    }

    /**
     * @param \stdClass[] $rows The rows of one (form, person) pair, by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $first = $rows[0];
        $formid = (int) $first->evaluationid;
        $userid = (int) $first->userid;

        [$target, $problem] = $this->facts->form_target($ctx, $formid);
        $form = $target === null ? null : $this->facts->form($ctx, $formid);
        if ($target === null || $form === null) {
            $problem = $problem ?? 'orphan_form';
            $out = [];
            foreach ($rows as $row) {
                $out[] = $problem === 'orphan_form' ? outcome::skip((int) $row->id, 'orphan_form', 'form_not_found')
                    : outcome::archive((int) $row->id, 'parent_deleted');
            }
            return $out;
        }
        if ($userid <= 0 || !$ctx->lookups->user_exists($userid)) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, 'orphan_assignee', 'user_not_found');
            }
            return $out;
        }

        $winner = $rows[0];
        foreach ($rows as $row) {
            if (self::rank($row) < self::rank($winner)) {
                $winner = $row;
            }
        }

        $trigger = 'manual';
        $source = 0;
        $classroomwarning = null;
        if ((string) ($form->plugin ?? '') === 'classroom' && (int) ($form->instance ?? 0) > 0) {
            $classroom = $ctx->map->resolve(importer::SRC_CLASSROOM, (int) $form->instance);
            if ($classroom !== null) {
                $trigger = 'classroom_end';
                $source = (int) $classroom;
            } else {
                // No map entry for the classroom: it is either missing, or (a single-feature dry run) the classroom
                // feature is neither complete nor simulated here, which is not the data's fault.
                $classroomwarning = $ctx->is_deferred(importer::SRC_CLASSROOM)
                    ? 'deferred:' . importer::SRC_CLASSROOM : 'classroom_unresolved';
            }
        }

        $pair = $this->facts->pair($ctx, $formid, $userid);
        $responded = $pair->count > 0;
        $respondedat = null;
        if ($responded && $pair->lasttime > 0) {
            $respondedat = $this->facts->identity_protected($ctx, $form)
                ? $this->facts->day_start($ctx, $pair->lasttime) : $pair->lasttime;
        }

        $created = (int) $winner->timecreated;
        $modified = (int) $winner->timemodified;
        $derived = false;
        if ($created <= 0) {
            $created = $modified > 0 ? $modified : $this->facts->created($ctx, $form);
            $derived = true;
        }
        if ($modified <= 0) {
            $modified = $created;
            $derived = true;
        }

        $creator = (int) $winner->creatorid;
        $assigner = null;
        $assignerunknown = false;
        if ($creator > 0) {
            if ($ctx->lookups->user_exists($creator)) {
                $assigner = $creator;
            } else {
                $assignerunknown = true;
            }
        }
        $close = (int) ($form->timeclose ?? 0);

        $outcome = outcome::insert((int) $winner->id, importer::T_ASSIGN, (object) [
            'evaluationid' => $target,
            'userid' => $userid,
            'trigger_event' => $trigger,
            'source_id' => $source,
            'status' => $responded ? 'responded' : 'expired',
            'assigned_by_userid' => $assigner,
            'due_at' => $close > 0 ? $close : null,
            'responded_at' => $respondedat,
            'timecreated' => $created,
            'timemodified' => $modified,
        ]);
        if ($derived) {
            $outcome->warn('derived_timestamp');
        }
        if ($classroomwarning !== null) {
            $outcome->warn($classroomwarning);
        }
        if ($assignerunknown) {
            $outcome->warn('assigner_not_found');
        }
        $out = [$outcome];
        foreach ($rows as $row) {
            if ((int) $row->id !== (int) $winner->id) {
                $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_assignment');
            }
        }
        return $out;
    }

    /**
     * Which row of a pair wins: the earliest timecreated, a missing one last, the lowest id on a tie.
     *
     * @param \stdClass $row
     * @return array{0: int, 1: int}
     */
    private static function rank(\stdClass $row): array {
        $created = (int) $row->timecreated;
        return [$created > 0 ? $created : PHP_INT_MAX, (int) $row->id];
    }
}
