<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer as framework_importer;
use local_sentientia_platform\bizlms\legacy_reader;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * The evaluation feature: BizLMS local_evaluation -> local_sentientia_evaluation (ADR-032, mapping doc section 18).
 *
 * A form, its questions, its templates, its assignments and its answers land together, and stay together: the
 * feature is atomic() and, under the framework's threshold, commits as one piece or not at all.
 *
 * What is imported, and how each source table is handled (steps run in this order):
 *
 *  1. local_evaluations       -> local_sentientia_evaluation         form_step, PRESERVE (ids kept)
 *  2. local_evaluation_template -> ..._template                      template_step
 *  3. local_evaluation_item   -> ..._questions, or folded into a template's payload   question_step
 *     (recompute) dependency_step sets each conditional question's parent
 *  4. local_evaluation_users  -> ..._assign                          assignment_step
 *  5. local_evaluation_completed -> ..._responses (+ an assign row where BizLMS had none)   response_step
 *  6. local_evaluation_value  -> folded into its response's response_data                  value_step
 *
 * Declined, with the reason in declined_tables(): the two draft tables and the dead sitecourse map.
 *
 * Read the choices the owner made in docs/cutover/bizlms-import-decisions.json (keys below): every imported form
 * is ARCHIVED and manual, weighted multichoice becomes plain multichoice, an anonymous supervisor form hides its
 * subject, anonymous answers stay anonymous, the trainer's name is not added to trainer feedback forms, and an
 * imported form is read-only (evaluation_manager refuses to edit, re-status or delete it).
 *
 * Nothing here sends a message, fires an event, enrols or queues anything; the importer only returns rows.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements framework_importer {

    /** Feature key. */
    public const FEATURE = 'evaluation';

    /** Owning plugin. */
    public const COMPONENT = 'local_sentientia_evaluation';

    /**
     * The plugin version that adds responses.subject_userid, the column the importer writes and the privacy
     * provider declares. The registry refuses to run the importer below it.
     */
    public const REQUIRES_VERSION = 2026093001;

    /** BizLMS tables this feature claims. */
    public const SRC_FORMS = 'local_evaluations';
    public const SRC_ITEMS = 'local_evaluation_item';
    public const SRC_TEMPLATES = 'local_evaluation_template';
    public const SRC_COMPLETED = 'local_evaluation_completed';
    public const SRC_VALUES = 'local_evaluation_value';
    public const SRC_USERS = 'local_evaluation_users';

    /** BizLMS tables owned by the classroom and program features, read through the map only. */
    public const SRC_CLASSROOM = 'local_classroom';
    public const SRC_PROGRAM = 'local_program';

    /** Sentientia tables this feature writes. */
    public const T_FORMS = 'local_sentientia_evaluation';
    public const T_QUESTIONS = 'local_sentientia_evaluation_questions';
    public const T_RESPONSES = 'local_sentientia_evaluation_responses';
    public const T_TEMPLATES = 'local_sentientia_evaluation_template';
    public const T_ASSIGN = 'local_sentientia_evaluation_assign';

    /** The classroom feature's target table, whose rows carry the path a trainer feedback form is scoped by. */
    public const CLASSROOM_TARGET = 'local_sentientia_classroom';

    /** Owner choices (docs/cutover/bizlms-import-decisions.json), each with the only value this code implements. */
    public const DECISIONS = [
        'tenant.unresolved.evaluation' => ['pathless',
            'A form with no resolvable organisation keeps no path and costcenterid 0: cross-tenant callers only, reported.'],
        'evaluation.open_forms' => ['archived',
            'Every imported form is archived and manual; an active one would reopen answering with no assignment check.'],
        'evaluation.multichoicerated' => ['multichoice',
            'Weighted multichoice becomes plain multichoice; the weights stay in the legacy table.'],
        'evaluation.sp_anonymous_subject' => ['hidden',
            'An anonymous supervisor form hides the subject too, since the subject could identify the respondent.'],
        'evaluation.legacy_anonymous_linkage' => ['untouched_pending_legacy_privacy_adr',
            'BizLMS links anonymous answers to people in the legacy tables; the import neither copies nor alters that.'],
        'evaluation.trainer_feedback_form_names' => ['keep_bizlms_name',
            'Trainer feedback forms keep their BizLMS name; the trainer is not added.'],
        'evaluation.imported_forms_read_only' => [true,
            'An imported form is read-only: evaluation_manager refuses to edit, re-status or delete it.'],
    ];

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return self::COMPONENT;
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * org for the tenant path, classroom and program for the ids the trainer and program feedback forms point at
     * (map rows of local_classroom and local_program are read through the context).
     *
     * @return string[]
     */
    public function depends(): array {
        return ['org', 'classroom', 'program'];
    }

    /**
     * @return array<string, source_spec>
     */
    public function sources(): array {
        return [
            self::SRC_FORMS => new source_spec(self::SRC_FORMS, true, [
                // EVALUATION_ANONYMOUS_YES = 1, EVALUATION_ANONYMOUS_NO = 2 (BizLMS local/evaluation/lib.php).
                'anonymous' => ['1' => 'anonymous', '2' => 'named'],
                'deleted' => ['0' => 'kept', '1' => 'soft deleted'],
                'evaluationmode' => ['SE' => 'self evaluation', 'SP' => 'supervisor evaluation'],
            ]),
            self::SRC_ITEMS => new source_spec(self::SRC_ITEMS, true, [
                'typ' => array_fill_keys(array_merge(answer_mapper::QUESTION_TYPES, answer_mapper::NON_QUESTION_TYPES),
                    'item type'),
            ]),
            self::SRC_TEMPLATES => new source_spec(self::SRC_TEMPLATES, true, [
                'ispublic' => ['0' => 'private', '1' => 'public'],
            ]),
            self::SRC_COMPLETED => new source_spec(self::SRC_COMPLETED, true, [
                // BizLMS copies the FORM's anonymous flag into the completion (classes/completion.php), so a named
                // answer to a named form holds 2 (EVALUATION_ANONYMOUS_NO), not 0; 0 is the column default. The April
                // production data holds 2. Only 1 means anonymous (form_facts, response_step).
                'anonymous_response' => ['0' => 'unset (named)', '1' => 'anonymous', '2' => 'named'],
            ]),
            self::SRC_VALUES => new source_spec(self::SRC_VALUES),
            self::SRC_USERS => new source_spec(self::SRC_USERS),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function declined_tables(): array {
        return [
            'local_eval_completedtmp' => 'unfinished drafts, deleted when the form is submitted; guestid is a session key and is never copied',
            'local_eval_valuetmp' => 'the answers of those drafts',
            'local_eval_sitecourse_map' => 'dead table with no reader; any row in it blocks the import until looked at',
        ];
    }

    /**
     * @return string[]
     */
    public function target_tables(): array {
        return [self::T_FORMS, self::T_QUESTIONS, self::T_RESPONSES, self::T_TEMPLATES, self::T_ASSIGN];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        return [];
    }

    /**
     * Only the form carries a path. Questions, responses and assignments inherit their tenant through
     * evaluationid, and the template keeps an organisation id, not a path.
     *
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [self::T_FORMS => 'open_path'];
    }

    /**
     * @return reason[]
     */
    public function reasons(): array {
        return [
            // Deliberately not imported; nothing for the owner to decide.
            new reason('deleted_form', false, false),
            new reason('parent_deleted', false, false),
            new reason('not_a_question', false, false),
            new reason('in_template_payload', false, false),
            new reason('in_response_data', false, false),
            new reason('dup_assignment', false, false),
            new reason('item_not_imported', false, false),
            new reason('duplicate_value', false, false),
            new reason('response_not_imported', false, false),
            // Data the import could not carry. Parity exits 2 until the owner has looked at the count.
            new reason('value_not_valid', false, true),
            new reason('orphan_form', false, true),
            new reason('orphan_template', false, true),
            new reason('orphan_item', false, true),
            new reason('orphan_user', false, true),
            new reason('orphan_assignee', false, true),
            new reason('orphan_completed', false, true),
            new reason('no_timestamp', false, true),
            new reason('unmapped_enum', false, true),
        ];
    }

    /**
     * @return decision[]
     */
    public function decisions(): array {
        $decisions = [];
        foreach (self::DECISIONS as $key => [$value, $description]) {
            $decisions[] = new decision($key, $description, true, null, [$value]);
        }
        return $decisions;
    }

    public function atomic(): bool {
        return true;
    }

    /**
     * The order matters: a form before what belongs to it, templates before their items, questions before the
     * answers that name them, the dependency pass after all questions.
     *
     * @return array<\local_sentientia_platform\bizlms\step|\local_sentientia_platform\bizlms\recompute_step>
     */
    public function steps(): array {
        $facts = new form_facts();
        return [
            new form_step($facts),
            new template_step(),
            new question_step($facts),
            new dependency_step(),
            new assignment_step($facts),
            new response_step($facts),
            new value_step($facts),
        ];
    }

    /**
     * Read-only. Counts and warns about the shape of the tables; the rows that cannot be imported are decided one
     * by one by the steps and reported with a reason.
     *
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        $pf = new preflight();

        // Reading a decision is what makes the framework report one that is missing or unfinished; the runner has
        // already turned that into a blocker, so the exception is only swallowed here.
        foreach (array_keys(self::DECISIONS) as $key) {
            try {
                $ctx->decision($key);
            } catch (blocked $e) {
                unset($e);
            }
        }

        // The sitecourse map has no writer or reader in BizLMS. A row in it is something this map knows nothing about.
        if ($ctx->legacy->exists('local_eval_sitecourse_map')) {
            $rows = $ctx->legacy->count('local_eval_sitecourse_map');
            if ($rows > 0) {
                $pf->block('sitecourse_map_has_rows:' . $rows);
            }
        }
        foreach (['local_eval_completedtmp', 'local_eval_valuetmp'] as $table) {
            if ($ctx->legacy->exists($table)) {
                $rows = $ctx->legacy->count($table);
                $pf->count('drafts:' . $table, $rows);
                if ($rows > 0) {
                    $pf->warn('drafts_not_imported:' . $table . ':' . $rows);
                }
            }
        }

        // A table or column that is missing is already a blocker (the framework reports it before this runs), so
        // every count below is guarded rather than left to fail the whole preflight.
        if ($ctx->legacy->exists(self::SRC_FORMS) && $ctx->legacy->has_column(self::SRC_FORMS, 'deleted')) {
            $pf->count('forms_deleted', $ctx->legacy->count(self::SRC_FORMS, ['t.deleted = 1', []]));
        }
        if ($ctx->legacy->exists(self::SRC_COMPLETED) && $ctx->legacy->has_column(self::SRC_COMPLETED, 'anonymous_response')) {
            $pf->count('completions_anonymous', $ctx->legacy->count(self::SRC_COMPLETED,
                ['t.anonymous_response = 1', []]));
        }

        // Rows whose parent does not exist. Each is skipped with a reason, not lost silently; the count tells the
        // owner before the run how many to expect.
        $orphans = [
            [self::SRC_ITEMS, self::SRC_FORMS, 't.evaluation > 0 AND NOT EXISTS (SELECT 1 FROM {' . self::SRC_FORMS
                . '} f WHERE f.id = t.evaluation)'],
            [self::SRC_COMPLETED, self::SRC_FORMS, 'NOT EXISTS (SELECT 1 FROM {' . self::SRC_FORMS
                . '} f WHERE f.id = t.evaluation)'],
            [self::SRC_USERS, self::SRC_FORMS, 'NOT EXISTS (SELECT 1 FROM {' . self::SRC_FORMS
                . '} f WHERE f.id = t.evaluationid)'],
            [self::SRC_VALUES, self::SRC_COMPLETED, 'NOT EXISTS (SELECT 1 FROM {' . self::SRC_COMPLETED
                . '} c WHERE c.id = t.completed)'],
        ];
        foreach ($orphans as [$table, $parent, $condition]) {
            if (!$ctx->legacy->exists($table) || !$ctx->legacy->exists($parent)) {
                continue;
            }
            $count = $ctx->legacy->count($table, [$condition, []]);
            if ($count > 0) {
                $pf->count('orphans:' . $table, $count);
                $pf->warn('orphan_rows:' . $table . ':' . $count);
            }
        }

        // The framework's tenant check reads EVERY row of the form table after the load, native ones too. A native
        // form whose path is not a valid tenant path would fail that check after all the work; say so now.
        $invalid = $this->native_forms_with_an_invalid_path($ctx);
        if ($invalid) {
            $pf->block('native_form_invalid_path:' . count($invalid) . ' ids=' . implode(',', array_slice($invalid, 0, 20)));
        }
        return $pf;
    }

    /**
     * Native forms (ids the source does not use) whose open_path would fail the tenant check.
     *
     * @param context $ctx
     * @return int[]
     */
    private function native_forms_with_an_invalid_path(context $ctx): array {
        global $DB;
        if (!$DB->get_manager()->table_exists(self::T_FORMS) || !$ctx->legacy->exists(self::SRC_FORMS)) {
            return [];
        }
        $sourceids = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page(self::SRC_FORMS, $after, legacy_reader::MAX_PAGE, ['id']);
            foreach ($page as $id => $row) {
                $sourceids[(int) $id] = true;
                $after = (int) $id;
            }
        } while (count($page) === legacy_reader::MAX_PAGE);

        $invalid = [];
        $after = 0;
        do {
            $page = $DB->get_records_select(self::T_FORMS, 'id > :after AND open_path IS NOT NULL',
                ['after' => $after], 'id ASC', 'id, open_path', 0, 2000);
            foreach ($page as $row) {
                $after = (int) $row->id;
                if (!isset($sourceids[$after]) && !self::path_is_valid((string) $row->open_path)) {
                    $invalid[] = $after;
                }
            }
        } while (count($page) === 2000);
        return $invalid;
    }

    /**
     * Is this stored value a normalised tenant path whose root is a registered tenant? (The same test the
     * framework's tenant verify applies.)
     *
     * @param string $value
     * @return bool
     */
    private static function path_is_valid(string $value): bool {
        $path = tenant_resolver::normalise($value);
        if ($path === null || $path !== $value) {
            return false;
        }
        try {
            \local_sentientia_platform\tenant::assert_valid((int) explode('/', ltrim($path, '/'))[0]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Read-only, after the load. The framework has already checked the accounting identity and the tenant paths;
     * these are the invariants of this feature that an administrator or a mapping mistake could break. Each is a
     * count of rows that break it.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        if ($ctx->dryrun) {
            // A dry run writes no target row, so there is nothing to read.
            return [];
        }
        $map = '{' . legacymap::TABLE . '}';
        $imported = "m.feature = :feature AND m.subkey = '' AND m.outcome IN ('imported', 'adopted')";
        $base = ['feature' => self::FEATURE];
        $checks = [
            // An imported form is archived and manual, with no trigger delay and no admin message.
            'imported_form_not_archived_and_manual' => [
                "SELECT COUNT(1) FROM {$map} m JOIN {" . self::T_FORMS . "} e ON e.id = m.targetid
                  WHERE {$imported} AND m.targettable = :t
                    AND (e.status <> :archived OR e.trigger_event <> :manual OR e.days_after <> 0
                         OR e.notify_admin_on_response <> 0)",
                $base + ['t' => self::T_FORMS, 'archived' => form_step::STATUS_ARCHIVED, 'manual' => 'manual'],
            ],
            // A response has its form, a real submission time, and on an anonymous form no name and no subject.
            'imported_response_inconsistent' => [
                "SELECT COUNT(1) FROM {$map} m JOIN {" . self::T_RESPONSES . "} r ON r.id = m.targetid
             LEFT JOIN {" . self::T_FORMS . "} e ON e.id = r.evaluationid
                  WHERE {$imported} AND m.targettable = :t
                    AND (e.id IS NULL OR r.timesubmitted <= 0
                         OR (e.anonymous = 1 AND (r.userid <> 0 OR r.subject_userid IS NOT NULL)))",
                $base + ['t' => self::T_RESPONSES],
            ],
            // A question has its form, is never anonymous on its own, and depends only on a question of the same form.
            'imported_question_inconsistent' => [
                "SELECT COUNT(1) FROM {$map} m JOIN {" . self::T_QUESTIONS . "} q ON q.id = m.targetid
             LEFT JOIN {" . self::T_FORMS . "} e ON e.id = q.evaluationid
             LEFT JOIN {" . self::T_QUESTIONS . "} p ON p.id = q.depends_on_qid
                  WHERE {$imported} AND m.targettable = :t
                    AND (e.id IS NULL OR q.anonymous <> 0
                         OR (q.depends_on_qid IS NOT NULL AND (p.id IS NULL OR p.evaluationid <> q.evaluationid)))",
                $base + ['t' => self::T_QUESTIONS],
            ],
            // An assignment has its form and one of the two trigger/status pairs the import writes. Sub-rows (the
            // assignment a completion implies) carry a sub-key, so this one does not filter on it.
            'imported_assignment_inconsistent' => [
                "SELECT COUNT(1) FROM {$map} m JOIN {" . self::T_ASSIGN . "} a ON a.id = m.targetid
             LEFT JOIN {" . self::T_FORMS . "} e ON e.id = a.evaluationid
                  WHERE m.feature = :feature AND m.outcome IN ('imported', 'adopted') AND m.targettable = :t
                    AND (e.id IS NULL OR a.status NOT IN ('responded', 'expired')
                         OR a.trigger_event NOT IN ('manual', 'classroom_end'))",
                $base + ['t' => self::T_ASSIGN],
            ],
        ];

        $failures = [];
        foreach ($checks as $code => [$sql, $params]) {
            $count = (int) $DB->count_records_sql($sql, $params);
            if ($count > 0) {
                $failures[] = $code . ':' . $count;
            }
        }
        return $failures;
    }

    /**
     * Outside any transaction. The runner has already reset the sequence of the form table, the only PRESERVE
     * target. Nothing else to do: intro files are not carried (BizLMS kept them under its own component and no
     * Sentientia reader shows them), and the import never purges a cache.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
    }
}
