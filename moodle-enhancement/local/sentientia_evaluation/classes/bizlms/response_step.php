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
 * Who answered. An anonymous answer stays anonymous in its own row: user id 0 and no subject. BizLMS's link from
 * the completion to a person stays in the legacy tables, which this step does not touch, and the import adds one
 * trace of its own: for the completion that implies a person's assignment (below), the map holds the anonymous
 * response (sub-key empty) and that person's assignment (sub-key assign) under the SAME completion id, and the
 * assign row names the person. So the map and the assign rows can tie an anonymous response to a person at the
 * database level (decision evaluation.legacy_anonymous_linkage, pending the legacy-table privacy ADR, which has to
 * cover them as well as the legacy tables).
 *
 * A completion is anonymous when it says so (anonymous_response = 1), when its form is anonymous (the flag, made
 * sticky by form_facts), or when it has no user (a guest). Otherwise the responder is evaluatedby when BizLMS
 * recorded one (the supervisor who filled a supervisor form in) and the completion's user when not; on a
 * supervisor form (evaluationmode SP) the person evaluated is kept as subject_userid.
 *
 * Two points where this goes beyond the letter of mapping doc section 18, both recorded in the plugin state card.
 * (1) Sticky anonymity reaches a completion that BizLMS stamped as named (anonymous_response = 2) when its form
 * ever held an anonymous answer: the more protective reading, and the one evaluation_manager::identity_protected()
 * applies to the form anyway. (2) A self evaluation whose evaluatedby names a user that no longer exists is not
 * dropped: the person evaluated is the person who answered there, so the completion's user is the responder
 * (warning responder_not_found). A supervisor form is still skipped (orphan_user), because the completion's user is
 * the person being evaluated and must not be shown as having answered.
 *
 * Free text is kept whole. response_data is a TEXT column (LONGTEXT on MySQL), BizLMS kept the answer in a
 * LONGTEXT as well, and a cut answer cannot be recovered once the legacy tables are dropped.
 *
 * The assignment beside it. A completion with no local_evaluation_users row for its form and person would leave
 * that person with no assignment, so the FIRST IMPORTED completion of the pair also creates a responded assignment
 * (sub-key assign), on the same rules as assignment_step, with the times cut to the day on an identity-protected
 * form. "Imported" matters: if the lowest completion is skipped (no time, an unknown responder) the next one that is
 * imported carries the assignment, so the person is not left with a response and no assignment.
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

        // The rules that skip a completion live in form_facts::import_problem(), so the implied assignment can be
        // keyed on the first completion that is imported. Sentientia reads timesubmitted 0 as a trigger-queue shell,
        // not a response, so a completion with no time of its own takes the form's, or is skipped.
        [$problem, $submitted] = $this->facts->import_problem($ctx, $row, $form);
        if ($problem === 'no_timestamp') {
            return [outcome::skip($id, 'no_timestamp', 'no_source_time')];
        }
        if ($problem === 'orphan_user') {
            return [outcome::skip($id, 'orphan_user', 'user_not_found')];
        }
        $derived = (int) $row->timemodified <= 0;

        $completionuser = (int) $row->userid;
        $anonymous = $this->facts->anonymous_final($ctx, $form) || $completionuser <= 0
            || (int) ($row->anonymous_response ?? 0) === 1;

        $userid = 0;
        $subject = null;
        $subjectunknown = false;
        $responderunknown = false;
        if (!$anonymous) {
            $supervised = (string) ($form->evaluationmode ?? 'SE') === 'SP';
            $evaluatedby = (int) ($row->evaluatedby ?? 0);
            $userid = $evaluatedby > 0 ? $evaluatedby : $completionuser;
            if (!$ctx->lookups->user_exists($userid)) {
                // import_problem() let this through, so it is a self evaluation: whoever was recorded as filling it
                // in has gone, and the person evaluated is the person who answered. Keep the answers rather than
                // drop them.
                $userid = $completionuser;
                $responderunknown = true;
            }
            if ($supervised && $completionuser !== $userid) {
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
        // A single-feature dry run without the classroom or program feature: the response keeps no classroom or
        // program, and the report says why instead of leaving it silent. Apply runs require those features first.
        $deferredparents = [];
        if ($plugin === 'classroom' && $instance > 0 && $classroomid === null && $ctx->is_deferred(importer::SRC_CLASSROOM)) {
            $deferredparents[] = 'deferred:' . importer::SRC_CLASSROOM;
        }
        if ($plugin === 'program' && $instance > 0 && $programid === null && $ctx->is_deferred(importer::SRC_PROGRAM)) {
            $deferredparents[] = 'deferred:' . importer::SRC_PROGRAM;
        }

        $set = $this->facts->answers($ctx, $row, $form);
        $response = outcome::insert($id, importer::T_RESPONSES, (object) [
            'evaluationid' => $target,
            'userid' => $userid,
            'subject_userid' => $subject,
            'courseid' => $courseid > 0 ? $courseid : null,
            'programid' => $programid,
            'classroomid' => $classroomid,
            // An object even when empty: Sentientia keeps "questionid => answer", never a list.
            'response_data' => json_encode((object) $set->data, JSON_INVALID_UTF8_SUBSTITUTE),
            'timesubmitted' => $submitted,
        ]);
        if ($derived) {
            $response->warn('derived_timestamp');
        }
        if ($subjectunknown) {
            $response->warn('subject_not_found');
        }
        if ($responderunknown) {
            $response->warn('responder_not_found');
        }
        if ($courseunknown) {
            $response->warn('course_not_found');
        }
        foreach ($deferredparents as $warning) {
            $response->warn($warning);
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
     * Only the first IMPORTED completion of a (form, person) pair creates it, so a person who answered a repeatable
     * form three times still has one assignment, and a first completion that is skipped (no time, an unknown
     * responder) does not leave the pair without one; and only when local_evaluation_users has no row for the pair,
     * because then assignment_step owns it.
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
        if ($pair->hasusers || $pair->firstimportedid !== (int) $row->id) {
            return null;
        }
        $protected = $this->facts->identity_protected($ctx, $form);
        $created = $protected ? $this->facts->day_start($ctx, $submitted) : $submitted;
        // The latest time among the completions that are imported, not among every legacy one.
        $last = $pair->lastimportedtime ?: $submitted;
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
