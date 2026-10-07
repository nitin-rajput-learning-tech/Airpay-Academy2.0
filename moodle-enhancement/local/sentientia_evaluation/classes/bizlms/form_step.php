<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_evaluations -> local_sentientia_evaluation (ADR-032, mapping doc section 18). PRESERVE: the form keeps
 * its BizLMS id.
 *
 * Why the id is kept. Rows the import does not rewrite store it: core {event} rows of plugin local_evaluation
 * (plugin_instance), local_classroom.trainingfeedbackid and local_classroom_trainers.feedback_id (the trainer
 * feedback form of a classroom), and local_emaillogs.moduleid for feedback e-mails. A new id would point all of
 * them at the wrong form. (The mapping doc planned new ids; the ADR's id-strategy table overrides it.)
 *
 * What a form becomes:
 *
 *  - ARCHIVED and manual, always (decision evaluation.open_forms = archived). An active form would reopen
 *    answering with no assignment check, and a form with a trigger would queue invitations for the course and
 *    classroom events it names. So status 2, trigger_event manual, days_after 0, notify_admin_on_response 0.
 *  - anonymous 1 when BizLMS said so (value 1) or when any completion was anonymous: the flag is sticky (decision
 *    evaluation.sticky_anonymity = whole_form: every answer of such a form is then imported anonymous).
 *  - evaluationmode SE or SP, as BizLMS had it (EV-17): the form itself says it is a supervisor evaluation.
 *  - a soft-deleted form (deleted = 1) is not imported: BizLMS purged its values and hid it everywhere.
 *  - tenant: an organisation, by the rule in the mapping doc (tenant_scope): the form's own path, then the root
 *    BizLMS kept in costcenterid, then the path of the classroom it belongs to. No organisation: the form keeps no
 *    path and costcenterid 0, so only cross-tenant callers see it (decision tenant.unresolved.evaluation). The user
 *    who last edited the form is NOT a clue (decision evaluation.tenant_editor_fallback = not_used): BizLMS showed
 *    such a form to site administrators only, and a guessed tenant would show its named answers to others.
 *
 * Not copied: type, evaluationtype, plugin, instance, visible, course, department and audience columns,
 * publish_stats, autonumbering, completionsubmit, page_after_submit, usermodified. Nothing in Sentientia reads
 * them and the legacy table keeps them.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_step extends step {

    /** Step key. */
    public const KEY = 'evaluation.forms';

    /** Sentientia status: archived (evaluation_manager::STATUS_ARCHIVED). */
    public const STATUS_ARCHIVED = 2;

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
        return importer::SRC_FORMS;
    }

    public function targettable(): string {
        return importer::T_FORMS;
    }

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    /**
     * Where the BizLMS form id lives in rows this import does not rewrite.
     *
     * @return array<array{0: string, 1: string, 2?: string}>
     */
    public function external_refs(): array {
        return [
            ['event', 'plugin_instance', "plugin = 'local_evaluation'"],
            ['local_classroom', 'trainingfeedbackid'],
            ['local_classroom_trainers', 'feedback_id'],
            ['local_emaillogs', 'moduleid', "moduletype = 'feedback'"],
        ];
    }

    /**
     * The tables that name a form by its id. Forms keep their BizLMS ids, so a row left in one of them under the id of
     * a legacy form that has no Sentientia form yet (a rehearsal, or the old delete(), which left assignment and
     * trigger rows behind) would attach to the imported form: stale rows would show as BizLMS history, and an
     * assignment for the same person would collide with the unique key (evaluationid, userid, trigger_event,
     * source_id) and roll the whole feature back. The framework counts them in preflight and blocks.
     *
     * @return array<array{0: string, 1: string}>
     */
    public function target_children(): array {
        return [
            [importer::T_QUESTIONS, 'evaluationid'], [importer::T_RESPONSES, 'evaluationid'],
            [importer::T_ASSIGN, 'evaluationid'], [importer::T_TRIGGERS, 'evaluationid'],
        ];
    }

    /**
     * No script ever copied a form header, so nothing at a legacy id is an adoptable copy: an occupied id is a
     * collision and blocks the feature. (An empty signature adopts nothing.)
     *
     * @return array<int|string, string>
     */
    public function adopt_signature(): array {
        return [];
    }

    /**
     * The organisation maps (classroom) are read to find the classroom a trainer feedback form belongs to.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[importer::SRC_CLASSROOM, '']];
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map_row($row, $ctx);
        }
        return $out;
    }

    /**
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome
     */
    private function map_row(\stdClass $row, context $ctx): outcome {
        $id = (int) $row->id;
        if ((int) ($row->deleted ?? 0) === 1) {
            return outcome::archive($id, 'deleted_form');
        }

        // Tenant: the form's own path, then the root BizLMS kept in costcenterid, then the classroom it belongs to.
        // Nothing else: a form that none of those places is imported pathless (decision evaluation.tenant_editor_fallback
        // = not_used, EV-TENANT). It is never filed under the tenant of whoever last edited it, because that is a
        // guess, and a guessed tenant would show the form's named answers to administrators BizLMS never showed them to.
        $rawpath = trim((string) ($row->open_path ?? ''));
        [$classroompath, $deferredparent] = $this->classroom_path($ctx, $row);
        [$path, $costcenterid, $method] = tenant_scope::resolve($ctx, [
            'open_path' => $rawpath === '' ? null : $rawpath,
            'costcenterid' => tenant_scope::root_path($row->costcenterid ?? null),
            'classroom' => $classroompath,
        ]);

        $created = $this->facts->created($ctx, $row);
        $modified = (int) ($row->timemodified ?? 0);
        $derived = false;
        if ($modified <= 0 && $created > 0) {
            $modified = $created;
            $derived = true;
        }

        $fields = new \stdClass();
        $fields->name = $ctx->text->fit(trim(answer_mapper::utf8((string) $row->name)), 254, 'name');
        $fields->description = $this->description($row);
        $fields->kirkpatrick_level = 1;
        $fields->trigger_event = 'manual';
        $fields->days_after = 0;
        $fields->costcenterid = $costcenterid;
        $fields->open_path = $path;
        $fields->status = self::STATUS_ARCHIVED;
        $fields->anonymous = $this->facts->anonymous_final($ctx, $row) ? 1 : 0;
        // EV-17: the form says it is a supervisor evaluation. The source column is declared SE or SP (any other
        // value is an unknown_enum blocker before the run, unless the owner mapped it), and an absent column is a
        // self evaluation. This is what lets Sentientia keep the person evaluated from being told they "responded"
        // on an ANONYMOUS supervisor form, whose responses keep no subject, and on an old completion that names no
        // evaluator. importer::mode_of() reads the value the way verify's imported_form_mode_mismatch reads it in
        // SQL (surrounding spaces and case ignored), so a mapped 'sp ' is SP here and in the check.
        $fields->evaluationmode = importer::mode_of($row->evaluationmode ?? null);
        $fields->timeopen = max(0, (int) ($row->timeopen ?? 0));
        $fields->timeclose = max(0, (int) ($row->timeclose ?? 0));
        $fields->multiple_submit = (int) ($row->multiple_submit ?? 0) === 1 ? 1 : 0;
        // One message to every site administrator per response: never on an imported form.
        $fields->notify_admin_on_response = 0;
        $fields->timecreated = $created > 0 ? $created : $modified;
        $fields->timemodified = $modified;

        $outcome = outcome::insert($id, importer::T_FORMS, $fields)->tenant_method($method);
        if ($deferredparent !== null) {
            // A single-feature dry run without the classroom feature: the form falls back to its other tenant clues,
            // and the report says why instead of leaving a silent fallback.
            $outcome->warn($deferredparent);
        }
        if ($derived) {
            $outcome->warn('derived_timestamp');
        }
        if ($created <= 0 && $modified <= 0) {
            $outcome->warn('no_source_timestamp');
        }
        if (trim((string) $row->name) === '') {
            $outcome->warn('empty_name');
        }
        if ((int) ($row->anonymous ?? 0) !== 1 && $fields->anonymous === 1) {
            $outcome->warn('anonymity_made_sticky');
        }
        return $outcome;
    }

    /**
     * The form's introduction as plain text. Intro files are not carried (BizLMS kept them under its own
     * component), so the @@PLUGINFILE@@ references are dropped rather than left dangling.
     *
     * @param \stdClass $row
     * @return string
     */
    private function description(\stdClass $row): string {
        $intro = answer_mapper::utf8((string) ($row->intro ?? ''));
        $intro = (string) preg_replace('~@@PLUGINFILE@@[^"\'\s<>)]*~', '', $intro);
        if (trim($intro) === '') {
            return '';
        }
        return answer_mapper::utf8(content_to_text($intro, (int) ($row->introformat ?? FORMAT_HTML)));
    }

    /**
     * The path of the classroom a trainer feedback form belongs to (plugin classroom, instance the classroom id).
     *
     * The classroom feature is a dependency, so its rows are in Sentientia by now: the classroom id is resolved
     * through the map (never assumed equal), and the path is the one the classroom importer wrote.
     *
     * @param context $ctx
     * @param \stdClass $row
     * @return array{0: string|null, 1: string|null} [the classroom's path or null, a warning code or null]. The
     *         warning is deferred:local_classroom when the classroom has no map entry because the classroom
     *         feature is neither complete nor simulated in this run (a single-feature dry run): the path is null
     *         then, so the caller's other tenant clues apply, exactly as before; only the report is told why.
     */
    private function classroom_path(context $ctx, \stdClass $row): array {
        if ((string) ($row->plugin ?? '') !== 'classroom' || (int) ($row->instance ?? 0) <= 0) {
            return [null, null];
        }
        $target = $ctx->map->resolve(importer::SRC_CLASSROOM, (int) $row->instance);
        if ($target === null && $ctx->is_deferred(importer::SRC_CLASSROOM)) {
            return [null, 'deferred:' . importer::SRC_CLASSROOM];
        }
        if ($target === null || $target <= 0 || !$ctx->legacy->exists(importer::CLASSROOM_TARGET)
                || !$ctx->legacy->has_column(importer::CLASSROOM_TARGET, 'open_path')) {
            return [null, null];
        }
        $found = $ctx->legacy->fetch(importer::CLASSROOM_TARGET, [$target], ['id', 'open_path']);
        $path = isset($found[$target]) ? trim((string) $found[$target]->open_path) : '';
        return [$path === '' ? null : $path, null];
    }
}
