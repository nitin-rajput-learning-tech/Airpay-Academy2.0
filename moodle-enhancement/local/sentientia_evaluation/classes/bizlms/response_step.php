<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_evaluation_completed -> local_sentientia_evaluation_responses (ADR-032, mapping doc section 18). MAP.
 *
 * One completion becomes one response whose response_data holds its answers (the value rows are read here and
 * accounted for by the value step, which folds each into this row). The shape is the one
 * evaluation_manager::submit_response() writes: a JSON object keyed by the NEW question id, every imported
 * question present, null where nobody answered.
 *
 * Who answered. An anonymous answer stays anonymous: user id 0 and no subject, and BizLMS's own link from the
 * completion to a person stays in the legacy table and is never copied. A completion is anonymous when it says
 * so (anonymous_response = 1), when its form is anonymous (the flag, made sticky by form_facts), or when it has
 * no user (a guest). Otherwise the responder is evaluatedby when BizLMS recorded one (the supervisor who filled a
 * supervisor form in) and the completion's user when not; on a supervisor form (evaluationmode SP) the person
 * evaluated is kept as subject_userid.
 *
 * The assignment beside it. A completion with no local_evaluation_users row for its form and person would leave
 * that person with no assignment, so the FIRST completion of the pair also creates a responded assignment
 * (sub-key assign), on the same rules as assignment_step, with the times cut to the day on an identity-protected
 * form.
 *
 * Never submit_response(): it sends administrators a message per response, checks the form is open and stamps
 * "now". This step only returns rows.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response_step extends step {

    /** Step key. */
    public const KEY = 'evaluation.responses';

    /** Longest free-text answer kept, in characters. A response is one TEXT cell; the full value stays in the legacy table. */
    public const ANSWER_MAX = 10000;

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
        return importer::SRC_COMPLETED;
    }

    public function targettable(): string {
        return importer::T_RESPONSES;
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [
            [importer::SRC_FORMS, ''], [importer::SRC_ITEMS, ''], [importer::SRC_CLASSROOM, ''],
            [importer::SRC_PROGRAM, ''],
        ];
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            foreach ($this->map_row($row, $ctx) as $outcome) {
                $out[] = $outcome;
            }
        }
        return $out;
    }

    /**
     * One completion: its response and, sometimes, the assignment it implies.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome[]
     */
    private function map_row(\stdClass $row, context $ctx): array {
        $id = (int) $row->id;
        $formid = (int) $row->evaluation;

        [$target, $problem] = $this->facts->form_target($ctx, $formid);
        $form = $target === null ? null : $this->facts->form($ctx, $formid);
        if ($target === null || $form === null) {
            return [($problem ?? 'orphan_form') === 'orphan_form' ? outcome::skip($id, 'orphan_form', 'form_not_found')
                : outcome::archive($id, 'parent_deleted')];
        }

        $submitted = (int) $row->timemodified;
        $derived = false;
        if ($submitted <= 0) {
            // Sentientia reads timesubmitted 0 as a trigger-queue shell, not a response, so it must be real.
            $submitted = (int) ($form->timemodified ?? 0);
            $derived = true;
        }
        if ($submitted <= 0) {
            return [outcome::skip($id, 'no_timestamp', 'no_source_time')];
        }

        $completionuser = (int) $row->userid;
        $anonymous = $this->facts->anonymous_final($ctx, $form) || $completionuser <= 0
            || (int) ($row->anonymous_response ?? 0) === 1;

        $userid = 0;
        $subject = null;
        $subjectunknown = false;
        if (!$anonymous) {
            $evaluatedby = (int) ($row->evaluatedby ?? 0);
            $userid = $evaluatedby > 0 ? $evaluatedby : $completionuser;
            if (!$ctx->lookups->user_exists($userid)) {
                return [outcome::skip($id, 'orphan_user', 'user_not_found')];
            }
            if ((string) ($form->evaluationmode ?? 'SE') === 'SP' && $completionuser !== $userid) {
                if ($ctx->lookups->user_exists($completionuser)) {
                    $subject = $completionuser;
                } else {
                    $subjectunknown = true;
                }
            }
        }

        $courseid = (int) ($row->courseid ?? 0);
        $courseunknown = false;
        if ($courseid > 0 && !$ctx->lookups->course_exists($courseid)) {
            $courseunknown = true;
            $courseid = 0;
        }
        $plugin = (string) ($form->plugin ?? '');
        $instance = (int) ($form->instance ?? 0);
        $classroomid = ($plugin === 'classroom' && $instance > 0) ? $ctx->map->resolve(importer::SRC_CLASSROOM, $instance) : null;
        $programid = ($plugin === 'program' && $instance > 0) ? $ctx->map->resolve(importer::SRC_PROGRAM, $instance) : null;

        $set = $this->facts->answers($ctx, $row, $form);
        $data = [];
        foreach ($set->data as $questionid => $answer) {
            $data[$questionid] = is_string($answer) ? $ctx->text->fit($answer, self::ANSWER_MAX, 'answer') : $answer;
        }

        $response = outcome::insert($id, importer::T_RESPONSES, (object) [
            'evaluationid' => $target,
            'userid' => $userid,
            'subject_userid' => $subject,
            'courseid' => $courseid > 0 ? $courseid : null,
            'programid' => $programid,
            'classroomid' => $classroomid,
            // An object even when empty: Sentientia keeps "questionid => answer", never a list.
            'response_data' => json_encode((object) $data, JSON_INVALID_UTF8_SUBSTITUTE),
            'timesubmitted' => $submitted,
        ]);
        if ($derived) {
            $response->warn('derived_timestamp');
        }
        if ($subjectunknown) {
            $response->warn('subject_not_found');
        }
        if ($courseunknown) {
            $response->warn('course_not_found');
        }
        if ($set->rejected) {
            $response->warn('rejected_values');
        }
        $out = [$response];

        $assignment = $this->assignment($ctx, $row, $form, $target, $submitted);
        if ($assignment !== null) {
            $out[] = $assignment;
        }
        return $out;
    }

    /**
     * The assignment a completion implies when BizLMS never recorded one, or null.
     *
     * Only the first completion of a (form, person) pair creates it, so a person who answered a repeatable form
     * three times still has one assignment; and only when local_evaluation_users has no row for the pair, because
     * then assignment_step owns it.
     *
     * @param context $ctx
     * @param \stdClass $row The completion.
     * @param \stdClass $form Legacy form row.
     * @param int $target The form's Sentientia id.
     * @param int $submitted The time this completion's response carries.
     * @return outcome|null
     */
    private function assignment(context $ctx, \stdClass $row, \stdClass $form, int $target, int $submitted): ?outcome {
        $person = (int) $row->userid;
        if ($person <= 0 || !$ctx->lookups->user_exists($person)) {
            return null;
        }
        $pair = $this->facts->pair($ctx, (int) $row->evaluation, $person);
        if ($pair->hasusers || $pair->firstid !== (int) $row->id) {
            return null;
        }
        $protected = $this->facts->identity_protected($ctx, $form);
        $created = $protected ? $this->facts->day_start($ctx, $submitted) : $submitted;
        $last = $pair->lasttime > 0 ? $pair->lasttime : $submitted;
        return outcome::insert((int) $row->id, importer::T_ASSIGN, (object) [
            'evaluationid' => $target,
            'userid' => $person,
            'trigger_event' => 'manual',
            'source_id' => 0,
            'status' => 'responded',
            'assigned_by_userid' => null,
            'due_at' => null,
            'responded_at' => $protected ? $this->facts->day_start($ctx, $last) : $last,
            'timecreated' => $created,
            'timemodified' => $created,
        ], 'assign');
    }
}
