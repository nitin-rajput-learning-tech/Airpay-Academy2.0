<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * Evaluation manager — CRUD for evaluation forms (containers).
 *
 * Questions and responses live in companion tables; their CRUD will be
 * added in a follow-up. For now this manages the form-level metadata.
 *
 * @package    local_sentientia_evaluation
 */
class evaluation_manager {

    private const TABLE          = 'local_sentientia_evaluation';
    private const QUESTIONS_TABLE = 'local_sentientia_evaluation_questions';
    private const RESPONSES_TABLE = 'local_sentientia_evaluation_responses';
    // P1 #37 (2026-05-20) — assignments table.
    private const ASSIGN_TABLE   = 'local_sentientia_evaluation_assign';
    // P1 #41 (2026-05-20) — template library.
    private const TEMPLATE_TABLE = 'local_sentientia_evaluation_template';

    /** Status values matching install.xml. */
    public const STATUS_DRAFT    = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_ARCHIVED = 2;

    /** Kirkpatrick evaluation levels. */
    public const KIRKPATRICK_LEVELS = [
        1 => 'Level 1 — Reaction (did learners enjoy it?)',
        2 => 'Level 2 — Learning (did they learn the content?)',
        3 => 'Level 3 — Behaviour (did they apply it on the job?)',
        4 => 'Level 4 — Results (did business outcomes change?)',
    ];

    /** Trigger events that fire the evaluation. */
    public const TRIGGER_EVENTS = [
        'manual'              => 'Manual — admin sends to specific users',
        'course_completion'   => 'After course completion',
        'program_completion'  => 'After program completion',
        'classroom_end'       => 'After classroom session ends',
    ];

    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    public static function count_evaluations(?int $status = null): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) return 0;
        if ($status === null) {
            return $DB->count_records(self::TABLE);
        }
        return $DB->count_records(self::TABLE, ['status' => $status]);
    }

    public static function count_responses(?int $evaluationid = null): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) return 0;
        if ($evaluationid !== null) {
            return $DB->count_records(self::RESPONSES_TABLE, ['evaluationid' => $evaluationid]);
        }
        return $DB->count_records(self::RESPONSES_TABLE);
    }

    public static function count_questions(int $evaluationid): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::QUESTIONS_TABLE)) return 0;
        return $DB->count_records(self::QUESTIONS_TABLE, ['evaluationid' => $evaluationid]);
    }

    /**
     * Create an evaluation form.
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_evaluation');
        }

        $level = (int) ($data->kirkpatrick_level ?? 1);
        if (!array_key_exists($level, self::KIRKPATRICK_LEVELS)) {
            throw new \moodle_exception('invalidkirkpatricklevel', 'local_sentientia_evaluation');
        }

        $trigger = $data->trigger_event ?? 'manual';
        if (!array_key_exists($trigger, self::TRIGGER_EVENTS)) {
            throw new \moodle_exception('invalidtrigger', 'local_sentientia_evaluation');
        }

        // P1 #17 (2026-05-16) — time window + multiple-submit. Default 0 (no
        // constraint) so existing callers and import paths keep working
        // unchanged.
        $timeopen        = max(0, (int) ($data->timeopen        ?? 0));
        $timeclose       = max(0, (int) ($data->timeclose       ?? 0));
        $multiple_submit = isset($data->multiple_submit) ? (int) $data->multiple_submit : 0;

        // Reject obvious misconfiguration where the window is inverted.
        if ($timeopen > 0 && $timeclose > 0 && $timeclose < $timeopen) {
            throw new \moodle_exception('eval_window_inverted', 'local_sentientia_evaluation');
        }

        // P1 #19 — opt-in admin notification on every response.
        $notify_admin = isset($data->notify_admin_on_response)
            ? (int) $data->notify_admin_on_response : 0;

        $record = (object) [
            'name'                     => trim($data->name),
            'description'              => $data->description ?? '',
            'kirkpatrick_level'        => $level,
            'trigger_event'            => $trigger,
            'days_after'               => max(0, (int) ($data->days_after ?? 0)),
            'costcenterid'             => (int) ($data->costcenterid ?? 0),
            'status'                   => (int) ($data->status ?? self::STATUS_DRAFT),
            'anonymous'                => isset($data->anonymous) ? (int) $data->anonymous : 0,
            'timeopen'                 => $timeopen,
            'timeclose'                => $timeclose,
            'multiple_submit'          => $multiple_submit,
            'notify_admin_on_response' => $notify_admin,
            'timecreated'              => time(),
            'timemodified'             => time(),
        ];

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $record = (object) ['id' => $id, 'timemodified' => time()];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->description))  $record->description = $data->description;
        if (isset($data->kirkpatrick_level)) {
            $level = (int) $data->kirkpatrick_level;
            if (!array_key_exists($level, self::KIRKPATRICK_LEVELS)) {
                throw new \moodle_exception('invalidkirkpatricklevel', 'local_sentientia_evaluation');
            }
            $record->kirkpatrick_level = $level;
        }
        if (isset($data->trigger_event)) {
            if (!array_key_exists($data->trigger_event, self::TRIGGER_EVENTS)) {
                throw new \moodle_exception('invalidtrigger', 'local_sentientia_evaluation');
            }
            $record->trigger_event = $data->trigger_event;
        }
        if (isset($data->days_after))   $record->days_after = max(0, (int) $data->days_after);
        if (isset($data->costcenterid)) $record->costcenterid = (int) $data->costcenterid;
        if (isset($data->status))       $record->status = (int) $data->status;
        if (isset($data->anonymous))    $record->anonymous = (int) $data->anonymous;

        // P1 #17 — time-window + multiple-submit fields.
        if (property_exists($data, 'timeopen')) {
            $record->timeopen = max(0, (int) $data->timeopen);
        }
        if (property_exists($data, 'timeclose')) {
            $record->timeclose = max(0, (int) $data->timeclose);
        }
        if (property_exists($data, 'multiple_submit')) {
            $record->multiple_submit = (int) $data->multiple_submit;
        }
        if (property_exists($data, 'notify_admin_on_response')) {
            $record->notify_admin_on_response = (int) $data->notify_admin_on_response;
        }

        // Validate window post-merge (compare against existing for fields
        // the caller didn't touch).
        $effective_open  = $record->timeopen  ?? (int) ($existing->timeopen  ?? 0);
        $effective_close = $record->timeclose ?? (int) ($existing->timeclose ?? 0);
        if ($effective_open > 0 && $effective_close > 0
                && $effective_close < $effective_open) {
            throw new \moodle_exception('eval_window_inverted', 'local_sentientia_evaluation');
        }

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * P1 #17 — Is the evaluation currently within its availability window?
     *
     * Returns true if:
     *   - timeopen  == 0 OR now >= timeopen   AND
     *   - timeclose == 0 OR now <  timeclose
     *
     * Pure function — does not check status (the caller still needs to
     * confirm STATUS_ACTIVE). Pass `now` for deterministic tests.
     */
    public static function is_open_now(object $eval, int $now = 0): bool {
        if ($now <= 0) {
            $now = time();
        }
        $open  = (int) ($eval->timeopen  ?? 0);
        $close = (int) ($eval->timeclose ?? 0);
        if ($open  > 0 && $now < $open)  return false;
        if ($close > 0 && $now >= $close) return false;
        return true;
    }

    public static function change_status(int $id, int $status): int {
        global $DB;
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_sentientia_evaluation');
        }
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'status' => $status,
            'timemodified' => time(),
        ]);
        return $status;
    }

    /**
     * Delete an evaluation form. Cascades through questions + responses.
     */
    public static function delete(int $id): bool {
        global $DB;
        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::QUESTIONS_TABLE, ['evaluationid' => $id]);
            $DB->delete_records(self::RESPONSES_TABLE, ['evaluationid' => $id]);
            $DB->delete_records(self::TABLE, ['id' => $id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Question CRUD (sub-entity of evaluation form)
    // ═══════════════════════════════════════════════════════════════════

    /** Question types and their display labels. */
    public const QUESTION_TYPES = [
        'rating'             => '5-point rating (1=Strongly Disagree → 5=Strongly Agree)',
        'nps'                => 'NPS (0-10 likelihood)',
        'yesno'              => 'Yes / No',
        'multichoice'        => 'Multiple choice (pick one)',
        // P1 #18 (2026-05-16) — closes audit item #3.
        'multichoice_multi'  => 'Multiple choice (check all that apply)',
        // P1 #18 (2026-05-16) — closes audit item #6.
        'numeric'            => 'Number (integer with optional min / max)',
        'text'               => 'Free text response',
    ];

    /**
     * Returns true if the question type stores its option set (or numeric
     * bounds) in the `options` JSON column. Centralised so create/update
     * + the form keep in lockstep when we add more option-bearing types.
     */
    private static function needs_options(string $type): bool {
        return in_array($type, ['multichoice', 'multichoice_multi', 'numeric'], true);
    }

    public static function get_questions(int $evaluationid): array {
        global $DB;
        return $DB->get_records(self::QUESTIONS_TABLE,
            ['evaluationid' => $evaluationid], 'sortorder ASC, id ASC');
    }

    // ─────────────────────────────────────────────────────────────────
    // Phase G.1 (2026-05-08) — evaluation TEMPLATE import / export
    //
    // Lets admins move an evaluation form definition (without responses)
    // between tenants or environments via a JSON file. Export + Import
    // are inverses — the JSON shape is the contract.
    // ─────────────────────────────────────────────────────────────────

    /** JSON shape version — bump when fields change incompatibly. */
    public const TEMPLATE_FORMAT_VERSION = 1;

    /**
     * Build a portable JSON template payload from one evaluation.
     *
     * @return array{
     *   format: int,
     *   exported_at: int,
     *   evaluation: array{name:string, description:string,
     *                     kirkpatrick_level:int, trigger_event:string,
     *                     days_after:int, anonymous:int},
     *   questions: list<array{questiontype:string, questiontext:string,
     *                          options:array, required:int, sortorder:int}>
     * }
     */
    public static function export_template(int $evaluationid): array {
        global $DB;
        $eval = $DB->get_record(self::TABLE, ['id' => $evaluationid],
            '*', MUST_EXIST);
        $questions = self::get_questions($evaluationid);

        $payload = [
            'format'      => self::TEMPLATE_FORMAT_VERSION,
            'exported_at' => time(),
            'evaluation'  => [
                'name'              => (string) $eval->name,
                'description'       => (string) ($eval->description ?? ''),
                'kirkpatrick_level' => (int) $eval->kirkpatrick_level,
                'trigger_event'     => (string) $eval->trigger_event,
                'days_after'        => (int) $eval->days_after,
                'anonymous'         => (int) $eval->anonymous,
            ],
            'questions'   => [],
        ];

        foreach ($questions as $q) {
            $payload['questions'][] = [
                'questiontype' => (string) $q->questiontype,
                'questiontext' => (string) $q->questiontext,
                'options'      => self::decode_options($q->options),
                'required'     => (int) $q->required,
                'anonymous'    => (int) ($q->anonymous ?? 0),
                'sortorder'    => (int) $q->sortorder,
            ];
        }
        return $payload;
    }

    /**
     * Import a template payload (decoded from JSON) into a new evaluation.
     * Returns the new evaluation ID. Does NOT import responses.
     *
     * @param array $payload    Same shape as export_template returns.
     * @param int   $costcenterid  Tenant to assign the new evaluation to.
     * @param int   $status        Initial status (default DRAFT).
     * @return array{id:int, name:string, question_count:int}
     */
    public static function import_template(array $payload,
                                            int $costcenterid = 0,
                                            int $status = self::STATUS_DRAFT): array {
        global $DB;

        if (!isset($payload['format']) || (int) $payload['format'] > self::TEMPLATE_FORMAT_VERSION) {
            throw new \invalid_parameter_exception(
                'Unsupported template format (version '
                . ($payload['format'] ?? 'missing') . ')');
        }
        $eval_data = $payload['evaluation'] ?? null;
        $questions = $payload['questions'] ?? [];
        if (!is_array($eval_data) || !is_array($questions)) {
            throw new \invalid_parameter_exception(
                'Malformed template — missing evaluation or questions');
        }

        $tx = $DB->start_delegated_transaction();
        try {
            $newid = self::create((object) [
                'name'              => trim((string) ($eval_data['name'] ?? 'Imported evaluation')),
                'description'       => (string) ($eval_data['description'] ?? ''),
                'kirkpatrick_level' => (int) ($eval_data['kirkpatrick_level'] ?? 1),
                'trigger_event'     => (string) ($eval_data['trigger_event'] ?? 'manual'),
                'days_after'        => (int) ($eval_data['days_after'] ?? 0),
                'anonymous'         => (int) ($eval_data['anonymous'] ?? 0),
                'costcenterid'      => $costcenterid,
                'status'            => $status,
            ]);

            $sortorder = 0;
            foreach ($questions as $q) {
                if (empty($q['questiontext'])) continue;
                $sortorder++;
                self::create_question((object) [
                    'evaluationid' => $newid,
                    'questiontype' => (string) ($q['questiontype'] ?? 'rating'),
                    'questiontext' => (string) $q['questiontext'],
                    // create_question expects newline-separated text
                    // (parse_options() splits on \n). We stored the
                    // options as an array in the JSON template, so
                    // re-stringify them here.
                    'options'      => isset($q['options']) && is_array($q['options'])
                        ? implode("\n", array_map('strval',
                            array_values($q['options'])))
                        : '',
                    'required'     => isset($q['required']) ? (int) $q['required'] : 1,
                    'anonymous'    => isset($q['anonymous']) ? (int) $q['anonymous'] : 0,
                    'sortorder'    => (int) ($q['sortorder'] ?? $sortorder),
                ]);
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        $imported = $DB->get_record(self::TABLE, ['id' => $newid], 'id, name');
        return [
            'id'             => (int) $imported->id,
            'name'           => format_string($imported->name),
            'question_count' => (int) self::count_questions((int) $imported->id),
        ];
    }

    public static function get_question(int $questionid) {
        global $DB;
        return $DB->get_record(self::QUESTIONS_TABLE, ['id' => $questionid]);
    }

    /**
     * Create a question. Auto-assigns sortorder = max + 1 within evaluation.
     */
    public static function create_question(object $data): int {
        global $DB;

        if (empty($data->evaluationid) || empty($data->questiontext) || empty($data->questiontype)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_evaluation');
        }

        if (!array_key_exists($data->questiontype, self::QUESTION_TYPES)) {
            throw new \moodle_exception('invalidquestiontype', 'local_sentientia_evaluation');
        }

        if (!$DB->record_exists(self::TABLE, ['id' => $data->evaluationid])) {
            throw new \moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
        }

        // P1 #18 — both multichoice variants need an options list; numeric
        // optionally stores {min, max} in the same column.
        $options_json = self::build_question_options_json($data);

        // Auto-increment sortorder.
        $maxsort = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {" . self::QUESTIONS_TABLE . "} WHERE evaluationid = :eid",
            ['eid' => $data->evaluationid]);

        // P1 #30 (2026-05-20) — conditional dependency. Validate the
        // parent is a sibling AND not self-referential. Cycle detection
        // is unnecessary at create time because the new row has no id
        // yet, so no chain can lead back to it.
        $depends_on_qid   = null;
        $depends_on_value = null;
        if (!empty($data->depends_on_qid)) {
            $depends_on_qid = (int) $data->depends_on_qid;
            self::validate_dep_parent($depends_on_qid,
                (int) $data->evaluationid, null);
            // depends_on_value can be empty string (meaning "any non-empty
            // parent answer triggers showing this question"). Trim
            // whitespace but preserve empty-string-means-anything.
            $depends_on_value = isset($data->depends_on_value)
                ? trim((string) $data->depends_on_value) : '';
            if ($depends_on_value === '') {
                $depends_on_value = null;
            }
        }

        $record = (object) [
            'evaluationid' => (int) $data->evaluationid,
            'questiontype' => $data->questiontype,
            'questiontext' => trim($data->questiontext),
            'options'      => $options_json,
            'required'     => isset($data->required) ? (int) $data->required : 1,
            // Phase G.2 (2026-05-08) — per-question anonymous toggle.
            'anonymous'    => isset($data->anonymous) ? (int) $data->anonymous : 0,
            'depends_on_qid'   => $depends_on_qid,
            'depends_on_value' => $depends_on_value,
            'sortorder'    => isset($data->sortorder) ? (int) $data->sortorder : ((int) $maxsort + 1),
            'timecreated'  => time(),
        ];

        return $DB->insert_record(self::QUESTIONS_TABLE, $record);
    }

    /**
     * P1 #30 — validate that $parent_qid is acceptable as a dependency
     * parent for a question in $evaluationid. The optional $self_qid
     * lets update_question() pass its own id to exclude self-cycles.
     *
     * Rules:
     *   1. parent must exist
     *   2. parent must live in the same evaluation
     *   3. parent must not be the child itself (only meaningful on update)
     *   4. following parent.depends_on_qid recursively must not loop back
     *      to the child (cycle detection)
     *
     * Throws moodle_exception on any rule violation.
     */
    public static function validate_dep_parent(int $parent_qid,
                                                 int $evaluationid,
                                                 ?int $self_qid): void {
        global $DB;
        if ($parent_qid <= 0) {
            throw new \moodle_exception('dep_invalid_parent',
                'local_sentientia_evaluation');
        }
        if ($self_qid !== null && $parent_qid === $self_qid) {
            throw new \moodle_exception('dep_self_reference',
                'local_sentientia_evaluation');
        }
        $parent = $DB->get_record(self::QUESTIONS_TABLE,
            ['id' => $parent_qid], 'id, evaluationid, depends_on_qid');
        if (!$parent) {
            throw new \moodle_exception('dep_invalid_parent',
                'local_sentientia_evaluation');
        }
        if ((int) $parent->evaluationid !== $evaluationid) {
            throw new \moodle_exception('dep_parent_other_evaluation',
                'local_sentientia_evaluation');
        }
        // Walk the parent's chain. If we ever see $self_qid the dep
        // would cycle. Use a visited-set guard in case the existing
        // data has a pre-existing cycle (shouldn't happen because we
        // validate at write time, but defensive).
        if ($self_qid !== null) {
            $visited = [];
            $cursor = (int) ($parent->depends_on_qid ?? 0);
            while ($cursor > 0 && !isset($visited[$cursor])) {
                if ($cursor === $self_qid) {
                    throw new \moodle_exception('dep_cycle',
                        'local_sentientia_evaluation');
                }
                $visited[$cursor] = true;
                $cursor = (int) $DB->get_field(self::QUESTIONS_TABLE,
                    'depends_on_qid', ['id' => $cursor]) ?: 0;
            }
        }
    }

    public static function update_question(int $id, object $data): bool {
        global $DB;
        $existing = $DB->get_record(self::QUESTIONS_TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id];

        if (isset($data->questiontype)) {
            if (!array_key_exists($data->questiontype, self::QUESTION_TYPES)) {
                throw new \moodle_exception('invalidquestiontype', 'local_sentientia_evaluation');
            }
            $record->questiontype = $data->questiontype;
        }
        if (isset($data->questiontext)) $record->questiontext = trim($data->questiontext);
        if (isset($data->required))     $record->required = (int) $data->required;
        if (isset($data->anonymous))    $record->anonymous = (int) $data->anonymous;
        if (isset($data->sortorder))    $record->sortorder = (int) $data->sortorder;

        // P1 #18 — Re-derive the options JSON if the type or option
        // metadata changed. For numeric we also accept numeric_min /
        // numeric_max via build_question_options_json().
        $finaltype = $record->questiontype ?? $existing->questiontype;
        $options_was_touched = isset($data->options)
            || isset($data->numeric_min) || isset($data->numeric_max);
        if (self::needs_options($finaltype) && $options_was_touched) {
            // Merge the type into the synthetic payload so the helper
            // dispatches correctly.
            $payload = clone $data;
            $payload->questiontype = $finaltype;
            $record->options = self::build_question_options_json($payload);
        } else if (isset($record->questiontype) && !self::needs_options($finaltype)) {
            // Type changed to one that doesn't store options — wipe the
            // stale JSON so analysis surfaces don't dereference dead data.
            $record->options = null;
        }

        // P1 #30 — conditional dependency on update. property_exists()
        // distinguishes "caller intentionally cleared the dep" (null)
        // from "caller didn't touch this field" (key absent).
        if (property_exists($data, 'depends_on_qid')) {
            $new_parent = $data->depends_on_qid !== null && $data->depends_on_qid !== ''
                ? (int) $data->depends_on_qid : null;
            if ($new_parent === null) {
                $record->depends_on_qid   = null;
                $record->depends_on_value = null;
            } else {
                self::validate_dep_parent($new_parent,
                    (int) $existing->evaluationid, $id);
                $record->depends_on_qid = $new_parent;
                $depends_on_value = isset($data->depends_on_value)
                    ? trim((string) $data->depends_on_value) : '';
                $record->depends_on_value = $depends_on_value === ''
                    ? null : $depends_on_value;
            }
        } else if (property_exists($data, 'depends_on_value')
                && (int) ($existing->depends_on_qid ?? 0) > 0) {
            // Caller updated just the value (keeping the same parent).
            $v = trim((string) $data->depends_on_value);
            $record->depends_on_value = $v === '' ? null : $v;
        }

        $DB->update_record(self::QUESTIONS_TABLE, $record);
        return true;
    }

    /**
     * P1 #18 — Build the `options` JSON column for a question payload.
     * Returns null when the type doesn't carry options.
     *
     *  - multichoice / multichoice_multi → ["Opt A", "Opt B", ...]
     *  - numeric                          → {"min": int|null, "max": int|null}
     *
     * @throws \moodle_exception when options are malformed for the type.
     */
    private static function build_question_options_json(object $data): ?string {
        $type = (string) ($data->questiontype ?? '');

        if ($type === 'multichoice' || $type === 'multichoice_multi') {
            $opts = self::parse_options($data->options ?? '');
            if (count($opts) < 2) {
                throw new \moodle_exception('multichoice_needs_options',
                    'local_sentientia_evaluation');
            }
            return json_encode(array_values($opts));
        }

        if ($type === 'numeric') {
            // Empty string = "no constraint". We store null in JSON so the
            // shape stays stable and the form can distinguish "unset" from
            // "zero is the bound".
            $min = (isset($data->numeric_min) && $data->numeric_min !== '')
                ? (int) $data->numeric_min : null;
            $max = (isset($data->numeric_max) && $data->numeric_max !== '')
                ? (int) $data->numeric_max : null;
            if ($min !== null && $max !== null && $max < $min) {
                throw new \moodle_exception('numeric_min_max_invalid',
                    'local_sentientia_evaluation');
            }
            return json_encode(['min' => $min, 'max' => $max]);
        }

        return null;
    }

    /**
     * P1 #18 — Decode the numeric `{min, max}` from a question's options
     * JSON. Returns ['min' => int|null, 'max' => int|null].
     */
    public static function decode_numeric_bounds(?string $options_json): array {
        if (!$options_json) {
            return ['min' => null, 'max' => null];
        }
        $decoded = json_decode($options_json, true);
        if (!is_array($decoded)) {
            return ['min' => null, 'max' => null];
        }
        return [
            'min' => isset($decoded['min']) && $decoded['min'] !== null
                ? (int) $decoded['min'] : null,
            'max' => isset($decoded['max']) && $decoded['max'] !== null
                ? (int) $decoded['max'] : null,
        ];
    }

    public static function delete_question(int $id): bool {
        global $DB;
        $DB->get_record(self::QUESTIONS_TABLE, ['id' => $id], '*', MUST_EXIST);
        $DB->delete_records(self::QUESTIONS_TABLE, ['id' => $id]);
        return true;
    }

    /**
     * Reorder questions — accepts an ordered array of question IDs.
     * Each ID's sortorder is set to its index in the array.
     */
    public static function reorder_questions(int $evaluationid, array $ordered_ids): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $sortorder = 0;
            foreach ($ordered_ids as $qid) {
                $qid = (int) $qid;
                if (!$DB->record_exists(self::QUESTIONS_TABLE,
                    ['id' => $qid, 'evaluationid' => $evaluationid])) {
                    continue;
                }
                $DB->set_field(self::QUESTIONS_TABLE, 'sortorder', $sortorder, ['id' => $qid]);
                $sortorder++;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return true;
    }

    /** Parse options text (one per line) into clean array. */
    public static function parse_options(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $opts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $opts[] = $line;
            }
        }
        return $opts;
    }

    /** Decode stored options JSON back to array. */
    public static function decode_options(?string $json): array {
        if (empty($json)) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Response submission + retrieval (learner + admin views)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Has the user already submitted this evaluation in a way that
     * should block a fresh submission?
     *
     * Returns false (= "user may submit again") when:
     *   - the evaluation is anonymous (we don't tie responses to users
     *     anyway, so re-submission is a no-op for identity), or
     *   - the evaluation has multiple_submit=1 (P1 #17 — pulse surveys
     *     explicitly allow re-submission).
     *
     * Otherwise checks for an existing response row.
     */
    public static function has_user_responded(int $evaluationid, int $userid): bool {
        global $DB;
        $eval = self::get($evaluationid);
        if (!$eval || (int) $eval->anonymous === 1) {
            return false;
        }
        // P1 #17 — pulse surveys.
        if ((int) ($eval->multiple_submit ?? 0) === 1) {
            return false;
        }
        return $DB->record_exists(self::RESPONSES_TABLE,
            ['evaluationid' => $evaluationid, 'userid' => $userid]);
    }

    /**
     * Submit a response. Validates each answer against its question type.
     */
    public static function submit_response(int $evaluationid, int $userid,
                                            array $answers, array $context = []): int {
        global $DB;

        $eval = self::get($evaluationid);
        if (!$eval) {
            throw new \moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
        }
        if ((int) $eval->status !== self::STATUS_ACTIVE) {
            throw new \moodle_exception('evaluationnotactive', 'local_sentientia_evaluation');
        }

        // P1 #17 — gate on the configured availability window. Admins
        // marking an evaluation "active" no longer have to manually
        // archive it when its window closes.
        if (!self::is_open_now($eval)) {
            $now = time();
            $open  = (int) ($eval->timeopen  ?? 0);
            $close = (int) ($eval->timeclose ?? 0);
            if ($open > 0 && $now < $open) {
                throw new \moodle_exception('evaluationnotyetopen',
                    'local_sentientia_evaluation', '', userdate($open));
            }
            if ($close > 0 && $now >= $close) {
                throw new \moodle_exception('evaluationclosed',
                    'local_sentientia_evaluation', '', userdate($close));
            }
        }

        if ((int) $eval->anonymous !== 1 && $userid > 0) {
            if (self::has_user_responded($evaluationid, $userid)) {
                throw new \moodle_exception('alreadyresponded', 'local_sentientia_evaluation');
            }
        }

        $questions = self::get_questions($evaluationid);
        if (empty($questions)) {
            throw new \moodle_exception('evaluationhasnoquestions', 'local_sentientia_evaluation');
        }

        // P1 #30 — determine which questions are VISIBLE given the
        // answers submitted so far. A question with a dependency whose
        // parent answer doesn't match is treated as hidden and its
        // answer is forced to null (not validated, not stored). This
        // is the server-side counterpart to the JS show/hide on the
        // respond page: clients can't bypass dependency-required by
        // crafting a payload that includes the hidden question's answer.
        $visible = self::compute_visibility_map($questions, $answers);

        $cleaned = [];
        foreach ($questions as $q) {
            if (empty($visible[$q->id])) {
                // Hidden by dependency → answer is null regardless of payload.
                $cleaned[$q->id] = null;
                continue;
            }
            $raw = $answers[$q->id] ?? null;
            $clean = self::validate_answer($q, $raw);
            $cleaned[$q->id] = $clean;
        }

        $stored_userid = ((int) $eval->anonymous === 1) ? 0 : $userid;

        $record = (object) [
            'evaluationid'  => $evaluationid,
            'userid'        => $stored_userid,
            'courseid'      => isset($context['courseid'])    ? (int) $context['courseid']    : null,
            'programid'     => isset($context['programid'])   ? (int) $context['programid']   : null,
            'classroomid'   => isset($context['classroomid']) ? (int) $context['classroomid'] : null,
            'response_data' => json_encode($cleaned),
            'timesubmitted' => time(),
        ];

        $responseid = $DB->insert_record(self::RESPONSES_TABLE, $record);

        // P1 #37 (2026-05-20) — mark any matching assignments as
        // 'responded'. Anonymous responses still mark assignments
        // because the assignment uses the ACTUAL userid (the responder
        // who clicked submit), not the stored userid (which is 0 for
        // anonymous). Wrapped in try/catch so a missing assignment row
        // doesn't poison submission.
        if ($userid > 0) {
            try {
                self::mark_assignments_responded((int) $eval->id, (int) $userid);
            } catch (\Throwable $e) {
                debugging('mark_assignments_responded failed: ' . $e->getMessage(),
                    DEBUG_NORMAL);
            }
        }

        // P1 #19 — opt-in admin notification. Wrapped in try/catch so a
        // misconfigured message provider can never break submission.
        if ((int) ($eval->notify_admin_on_response ?? 0) === 1) {
            try {
                self::notify_admins_of_response($eval, $responseid,
                    $stored_userid, (int) $userid);
            } catch (\Throwable $e) {
                debugging('notify_admins_of_response failed: ' . $e->getMessage(),
                    DEBUG_NORMAL);
            }
        }

        return $responseid;
    }

    /**
     * P1 #37 (2026-05-20) — record an assignment.
     *
     * Idempotent: the UNIQUE index on (evaluationid, userid,
     * trigger_event, source_id) catches re-assigns. We do a
     * record_exists pre-check (cheaper than catching the exception);
     * if the row exists we just return its id. If it exists but is
     * 'expired', we re-open it to 'assigned' (an admin re-assigning
     * an expired evaluation is a deliberate action).
     *
     * @param int      $evaluationid
     * @param int      $userid
     * @param string   $trigger_event
     * @param int      $source_id           0 for manual
     * @param int|null $assigned_by_userid  null for auto
     * @param int|null $due_at              optional deadline
     * @return int  Row id (existing or new).
     */
    public static function ensure_assignment(int $evaluationid, int $userid,
                                               string $trigger_event = 'manual',
                                               int $source_id = 0,
                                               ?int $assigned_by_userid = null,
                                               ?int $due_at = null): int {
        global $DB;

        $existing = $DB->get_record(self::ASSIGN_TABLE, [
            'evaluationid' => $evaluationid,
            'userid'       => $userid,
            'trigger_event' => $trigger_event,
            'source_id'    => $source_id,
        ]);
        if ($existing) {
            // Re-open an expired assignment — admin re-assignment is
            // deliberate. Don't touch 'responded' rows; if the user
            // has already responded, the assignment is already closed.
            if ($existing->status === 'expired') {
                $DB->update_record(self::ASSIGN_TABLE, (object) [
                    'id'           => $existing->id,
                    'status'       => 'assigned',
                    'due_at'       => $due_at,
                    'timemodified' => time(),
                ]);
            }
            return (int) $existing->id;
        }

        $now = time();
        return (int) $DB->insert_record(self::ASSIGN_TABLE, (object) [
            'evaluationid'       => $evaluationid,
            'userid'             => $userid,
            'trigger_event'      => $trigger_event,
            'source_id'          => $source_id,
            'status'             => 'assigned',
            'assigned_by_userid' => $assigned_by_userid,
            'due_at'             => $due_at,
            'timecreated'        => $now,
            'timemodified'       => $now,
        ]);
    }

    /**
     * P1 #37 — close every open assignment for (evaluation, user)
     * when the user submits a response. Idempotent — called from
     * submit_response after the response row is inserted.
     *
     * A single user can have multiple open assignments for one
     * evaluation (e.g. course_completion source=X AND
     * program_completion source=Y both auto-assigned them). All
     * matching rows flip to 'responded' on a single submission —
     * one submission satisfies all outstanding assignments.
     */
    public static function mark_assignments_responded(int $evaluationid,
                                                       int $userid): int {
        global $DB;
        $now = time();
        // get_records first so we can return the count; bulk UPDATE
        // would be one query but losing the count costs us testability.
        $rows = $DB->get_records(self::ASSIGN_TABLE, [
            'evaluationid' => $evaluationid,
            'userid'       => $userid,
            'status'       => 'assigned',
        ]);
        foreach ($rows as $row) {
            $DB->update_record(self::ASSIGN_TABLE, (object) [
                'id'           => $row->id,
                'status'       => 'responded',
                'responded_at' => $now,
                'timemodified' => $now,
            ]);
        }
        return count($rows);
    }

    /**
     * P1 #37 — query helper for the show-non-respondents page (future
     * P1 #38). Returns assignment rows joined to user details, filtered
     * by status.
     *
     * @param int    $evaluationid
     * @param string $status  'assigned' (default) | 'responded' | 'expired'
     * @return array<int, \stdClass>
     */
    public static function list_assignments(int $evaluationid,
                                              string $status = 'assigned'): array {
        global $DB;
        return $DB->get_records_sql("
            SELECT a.id, a.userid, a.trigger_event, a.source_id,
                   a.status, a.due_at, a.responded_at, a.timecreated,
                   u.firstname, u.lastname, u.email
              FROM {" . self::ASSIGN_TABLE . "} a
              JOIN {user} u ON u.id = a.userid
             WHERE a.evaluationid = :eid
               AND a.status = :status
               AND u.deleted = 0
          ORDER BY a.timecreated DESC, u.lastname ASC", [
            'eid'    => $evaluationid,
            'status' => $status,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // P1 #41 (2026-05-20) — DB-backed template library.
    // ═══════════════════════════════════════════════════════════════════
    //
    // The Phase G.1 JSON export/import already produces a self-describing
    // template payload. The template library is a thin DB cache of those
    // payloads: save a row, look it up later, hand it back to
    // `import_template()` for reuse. No new payload schema; the row
    // stores the exact same JSON that export_template() produces.

    /**
     * Save an existing evaluation as a reusable template.
     *
     * @param int    $evaluationid     Source evaluation
     * @param string $template_name    Display name for the template
     * @param string $template_desc    Short description shown in the picker
     * @param int    $createdby_userid Acting admin (recorded for audit)
     * @param int    $costcenterid     Originating tenant (0 = global)
     * @param bool   $ispublic         True = visible across tenants
     * @return int  New template row id
     */
    public static function save_template_from_evaluation(int $evaluationid,
                                                           string $template_name,
                                                           string $template_desc,
                                                           int $createdby_userid,
                                                           int $costcenterid = 0,
                                                           bool $ispublic = false): int {
        global $DB;
        if (trim($template_name) === '') {
            throw new \moodle_exception('template_name_required',
                'local_sentientia_evaluation');
        }
        $payload = self::export_template($evaluationid);
        $now = time();
        return (int) $DB->insert_record(self::TEMPLATE_TABLE, (object) [
            'name'             => trim($template_name),
            'description'      => $template_desc,
            'payload'          => json_encode($payload),
            'createdby_userid' => $createdby_userid,
            'costcenterid'     => $costcenterid,
            'ispublic'         => $ispublic ? 1 : 0,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    /**
     * Create a new evaluation from a saved template.
     * Returns the array `import_template()` returns (id, name, question_count).
     */
    public static function create_evaluation_from_template(int $templateid,
                                                             int $target_costcenterid = 0,
                                                             int $status = self::STATUS_DRAFT): array {
        global $DB;
        $row = $DB->get_record(self::TEMPLATE_TABLE, ['id' => $templateid],
            '*', MUST_EXIST);
        $payload = json_decode((string) $row->payload, true);
        if (!is_array($payload)) {
            throw new \moodle_exception('template_payload_corrupt',
                'local_sentientia_evaluation');
        }
        return self::import_template($payload, $target_costcenterid, $status);
    }

    /**
     * Get templates visible to the given tenant. Returns ALL ispublic=1
     * rows + the caller-tenant's own rows.
     *
     * @param int $caller_costcenterid  0 = siteadmin → return everything
     * @return array<int, \stdClass>
     */
    public static function list_templates(int $caller_costcenterid = 0): array {
        global $DB;
        if ($caller_costcenterid === 0) {
            return $DB->get_records(self::TEMPLATE_TABLE, null,
                'timemodified DESC');
        }
        return $DB->get_records_sql(
            "SELECT * FROM {" . self::TEMPLATE_TABLE . "}
              WHERE ispublic = 1
                 OR costcenterid = :cid
           ORDER BY ispublic DESC, timemodified DESC",
            ['cid' => $caller_costcenterid]);
    }

    /** Delete a template row. */
    public static function delete_template(int $templateid): bool {
        global $DB;
        $DB->get_record(self::TEMPLATE_TABLE, ['id' => $templateid],
            '*', MUST_EXIST);
        $DB->delete_records(self::TEMPLATE_TABLE, ['id' => $templateid]);
        return true;
    }

    /**
     * P1 #19 — Send a Moodle notification to every siteadmin announcing
     * that a new response has come in.
     *
     * - Recipients: get_admins() (each admin can opt out per-channel via
     *   their own notification preferences — the message provider is
     *   exposed in the user-profile UI).
     * - Anonymous responses: subject/body omit the responder name and
     *   include "(anonymous)" instead.
     * - Body links to the admin's responses view, not the learner's.
     */
    private static function notify_admins_of_response(\stdClass $eval,
                                                       int $responseid,
                                                       int $stored_userid,
                                                       int $actual_userid): void {
        global $DB, $CFG;

        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        // Respect anonymity at notification time too — even if the
        // RESPONSES_TABLE stored userid=0, an admin who knows when the
        // response landed could still cross-reference logs. Best policy:
        // never expose responder identity in the notification when
        // anonymous=1.
        $is_anonymous = ((int) $eval->anonymous === 1);
        if ($is_anonymous || $stored_userid === 0) {
            $responder_label = get_string('eval_response_responder_anonymous',
                'local_sentientia_evaluation');
        } else {
            $responder = $DB->get_record('user',
                ['id' => $stored_userid > 0 ? $stored_userid : $actual_userid]);
            $responder_label = $responder
                ? fullname($responder) . ' <' . $responder->email . '>'
                : get_string('eval_response_responder_unknown',
                    'local_sentientia_evaluation');
        }

        $eval_name = format_string($eval->name);
        $url = new \moodle_url('/local/sentientia_evaluation/responses.php',
            ['id' => (int) $eval->id]);
        $url_str = $url->out(false);

        $subject = get_string('eval_response_subject',
            'local_sentientia_evaluation', $eval_name);

        $body_plain = get_string('eval_response_body_plain',
            'local_sentientia_evaluation', (object) [
                'evalname'  => $eval_name,
                'responder' => $responder_label,
                'url'       => $url_str,
            ]);
        $body_html = get_string('eval_response_body_html',
            'local_sentientia_evaluation', (object) [
                'evalname'  => $eval_name,
                'responder' => s($responder_label),
                'url'       => $url_str,
            ]);
        $small = get_string('eval_response_small',
            'local_sentientia_evaluation', $eval_name);

        foreach ($admins as $admin) {
            $msg = new \core\message\message();
            $msg->component         = 'local_sentientia_evaluation';
            $msg->name              = 'evaluation_response';
            $msg->userfrom          = \core_user::get_noreply_user();
            $msg->userto            = $admin;
            $msg->subject           = $subject;
            $msg->fullmessage       = $body_plain;
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml   = $body_html;
            $msg->smallmessage      = $small;
            $msg->notification      = 1;
            $msg->contexturl        = $url_str;
            $msg->contexturlname    = get_string('viewresponses',
                'local_sentientia_evaluation');
            message_send($msg);
        }
    }

    /**
     * P1 #30 — compute which question ids are visible given the
     * answer payload so far. A question is visible iff:
     *   - it has no dependency, OR
     *   - its parent is itself visible AND the parent's answer matches.
     *
     * The matching rule:
     *   - depends_on_value is null → "show when parent has any non-empty answer"
     *   - depends_on_value is set  → "show when parent's answer === this value"
     *     (string-equality after both sides are cast to string and trimmed)
     *
     * Parent visibility is computed before the child's because we walk
     * the questions list in order — and `get_questions()` returns them
     * sorted by sortorder ASC, so admins who put a child before its
     * parent in the sort order get the obvious bug (we don't try to
     * topologically sort here; that's an authoring error).
     *
     * @param array $questions  Indexed by sortorder (from get_questions).
     * @param array $answers    Map of qid → raw submitted answer.
     * @return array<int,bool>  qid → visible?
     */
    public static function compute_visibility_map(array $questions,
                                                    array $answers): array {
        // Index questions by id for O(1) parent lookup.
        $byid = [];
        foreach ($questions as $q) {
            $byid[(int) $q->id] = $q;
        }

        $visible = [];
        foreach ($questions as $q) {
            $qid = (int) $q->id;
            $parent_qid = (int) ($q->depends_on_qid ?? 0);
            if ($parent_qid <= 0) {
                $visible[$qid] = true;
                continue;
            }
            // If the parent isn't in the same evaluation (orphaned
            // foreign key after a delete) treat the child as hidden:
            // we can't evaluate the dependency, so showing it would
            // confuse the learner.
            if (!isset($byid[$parent_qid])) {
                $visible[$qid] = false;
                continue;
            }
            // Parent must itself be visible.
            if (empty($visible[$parent_qid])) {
                $visible[$qid] = false;
                continue;
            }
            $parent_raw = $answers[$parent_qid] ?? null;
            if ($parent_raw === null || $parent_raw === ''
                    || (is_array($parent_raw) && empty($parent_raw))) {
                // Parent unanswered → child stays hidden until parent fills.
                $visible[$qid] = false;
                continue;
            }
            $needed = $q->depends_on_value;
            if ($needed === null || $needed === '') {
                // "Any non-empty answer triggers" mode.
                $visible[$qid] = true;
                continue;
            }
            // String-equality match. Multichoice_multi parents pass an
            // array — show child when ANY selected option matches.
            if (is_array($parent_raw)) {
                $visible[$qid] = in_array((string) $needed, array_map(
                    fn($v) => (string) $v, $parent_raw), true);
            } else {
                $visible[$qid] = (trim((string) $parent_raw)
                    === trim((string) $needed));
            }
        }
        return $visible;
    }

    /**
     * Validate a single answer against its question type.
     * @throws \moodle_exception  On invalid answer for a required question
     */
    private static function validate_answer(object $question, $raw) {
        $required = (int) ($question->required ?? 1) === 1;

        if ($raw === null || $raw === '') {
            if ($required) {
                throw new \moodle_exception('answer_required', 'local_sentientia_evaluation',
                    '', $question->questiontext);
            }
            return null;
        }

        switch ($question->questiontype) {
            case 'rating':
                $v = (int) $raw;
                if ($v < 1 || $v > 5) {
                    throw new \moodle_exception('invalid_rating', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return $v;
            case 'nps':
                $v = (int) $raw;
                if ($v < 0 || $v > 10) {
                    throw new \moodle_exception('invalid_nps', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return $v;
            case 'yesno':
                $v = strtolower(trim((string) $raw));
                if (!in_array($v, ['yes', 'no', '1', '0', 'true', 'false'], true)) {
                    throw new \moodle_exception('invalid_yesno', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return ($v === 'yes' || $v === '1' || $v === 'true') ? 'yes' : 'no';
            case 'multichoice':
                $opts = self::decode_options($question->options);
                $raw = trim((string) $raw);
                if (!in_array($raw, $opts, true)) {
                    throw new \moodle_exception('invalid_multichoice', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return $raw;
            case 'multichoice_multi':
                // P1 #18 — accepts either a real JSON-decoded array
                // (preferred path from the AJAX submit handler) or a
                // delimited string ("A|B|C") as a fallback. Each value
                // must be in the allowed options list.
                $opts = self::decode_options($question->options);
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $raw = $decoded;
                    } else {
                        $raw = array_filter(array_map('trim', explode('|', $raw)),
                            fn($x) => $x !== '');
                    }
                }
                if (!is_array($raw)) {
                    throw new \moodle_exception('invalid_multichoice_multi',
                        'local_sentientia_evaluation', '', $question->questiontext);
                }
                $clean = [];
                foreach ($raw as $v) {
                    $v = trim((string) $v);
                    if ($v === '') continue;
                    if (!in_array($v, $opts, true)) {
                        throw new \moodle_exception('invalid_multichoice_multi',
                            'local_sentientia_evaluation', '', $question->questiontext);
                    }
                    $clean[] = $v;
                }
                if ($required && empty($clean)) {
                    throw new \moodle_exception('answer_required',
                        'local_sentientia_evaluation', '', $question->questiontext);
                }
                // De-duplicate to keep aggregate stats honest.
                return array_values(array_unique($clean));
            case 'numeric':
                // P1 #18 — must parse cleanly to int + respect optional
                // min/max bounds from the question's options JSON.
                if (!is_numeric($raw)) {
                    throw new \moodle_exception('invalid_numeric',
                        'local_sentientia_evaluation', '', $question->questiontext);
                }
                $v = (int) $raw;
                $bounds = self::decode_numeric_bounds($question->options ?? null);
                if ($bounds['min'] !== null && $v < $bounds['min']) {
                    $a = (object) [
                        'q'   => $question->questiontext,
                        'min' => $bounds['min'],
                    ];
                    throw new \moodle_exception('invalid_numeric_below_min',
                        'local_sentientia_evaluation', '', $a);
                }
                if ($bounds['max'] !== null && $v > $bounds['max']) {
                    $a = (object) [
                        'q'   => $question->questiontext,
                        'max' => $bounds['max'],
                    ];
                    throw new \moodle_exception('invalid_numeric_above_max',
                        'local_sentientia_evaluation', '', $a);
                }
                return $v;
            case 'text':
                return trim((string) $raw);
            default:
                return null;
        }
    }

    /**
     * Get aggregate stats for each question (for admin response viewer).
     */
    public static function get_response_stats(int $evaluationid): array {
        global $DB;

        $questions = self::get_questions($evaluationid);
        $responses = $DB->get_records(self::RESPONSES_TABLE,
            ['evaluationid' => $evaluationid], 'timesubmitted DESC');

        $stats = [];
        foreach ($questions as $q) {
            $stats[$q->id] = self::init_stats_bucket($q);
        }

        foreach ($responses as $r) {
            $data = json_decode($r->response_data, true);
            if (!is_array($data)) continue;
            foreach ($data as $qid => $answer) {
                $qid = (int) $qid;
                if (!isset($stats[$qid])) continue;
                if ($answer === null || $answer === '') continue;
                self::accumulate_stat($stats[$qid], $questions[$qid] ?? null, $answer);
            }
        }

        foreach ($stats as $qid => &$bucket) {
            $q = $questions[$qid] ?? null;
            if (!$q) continue;
            self::finalise_stats($bucket, $q);
        }
        unset($bucket);

        return $stats;
    }

    private static function init_stats_bucket(object $q): array {
        switch ($q->questiontype) {
            case 'rating':
                return ['type' => 'rating', 'count' => 0, 'sum' => 0,
                        'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
            case 'nps':
                return ['type' => 'nps', 'count' => 0, 'detractors' => 0,
                        'passives' => 0, 'promoters' => 0, 'sum' => 0];
            case 'yesno':
                return ['type' => 'yesno', 'count' => 0, 'yes' => 0, 'no' => 0];
            case 'multichoice':
                $opts = self::decode_options($q->options);
                $dist = [];
                foreach ($opts as $o) $dist[$o] = 0;
                return ['type' => 'multichoice', 'count' => 0, 'distribution' => $dist];
            case 'multichoice_multi':
                // P1 #18 — same shape as multichoice (per-option distribution)
                // but each response can populate multiple buckets. Track
                // response count (people answering) AND total picks
                // (selections) separately so the analysis surface can
                // distinguish them.
                $opts = self::decode_options($q->options);
                $dist = [];
                foreach ($opts as $o) $dist[$o] = 0;
                return ['type' => 'multichoice_multi', 'count' => 0,
                        'total_picks' => 0, 'distribution' => $dist];
            case 'numeric':
                // P1 #18 — running min/max/sum so finalise can compute avg.
                $bounds = self::decode_numeric_bounds($q->options ?? null);
                return ['type' => 'numeric', 'count' => 0, 'sum' => 0,
                        'min_seen' => null, 'max_seen' => null,
                        'bound_min' => $bounds['min'], 'bound_max' => $bounds['max']];
            case 'text':
                return ['type' => 'text', 'count' => 0, 'samples' => []];
            default:
                return ['type' => 'unknown', 'count' => 0];
        }
    }

    private static function accumulate_stat(array &$bucket, ?object $q, $answer): void {
        if (!$q) return;
        $bucket['count']++;
        switch ($q->questiontype) {
            case 'rating':
                $v = (int) $answer;
                if ($v >= 1 && $v <= 5) {
                    $bucket['sum'] += $v;
                    $bucket['distribution'][$v]++;
                }
                break;
            case 'nps':
                $v = (int) $answer;
                if ($v >= 0 && $v <= 10) {
                    $bucket['sum'] += $v;
                    if ($v <= 6) $bucket['detractors']++;
                    else if ($v <= 8) $bucket['passives']++;
                    else $bucket['promoters']++;
                }
                break;
            case 'yesno':
                if ($answer === 'yes') $bucket['yes']++;
                else if ($answer === 'no') $bucket['no']++;
                break;
            case 'multichoice':
                $a = (string) $answer;
                if (isset($bucket['distribution'][$a])) {
                    $bucket['distribution'][$a]++;
                }
                break;
            case 'multichoice_multi':
                // P1 #18 — `count` is incremented once already (one person
                // answered); we add each selected option to the
                // distribution and bump total_picks for the per-pick rate.
                $picks = is_array($answer) ? $answer
                    : (is_string($answer) && ($d = json_decode($answer, true)) && is_array($d) ? $d : []);
                foreach ($picks as $a) {
                    $a = (string) $a;
                    if (isset($bucket['distribution'][$a])) {
                        $bucket['distribution'][$a]++;
                        $bucket['total_picks']++;
                    }
                }
                break;
            case 'numeric':
                if (!is_numeric($answer)) break;
                $v = (int) $answer;
                $bucket['sum'] += $v;
                if ($bucket['min_seen'] === null || $v < $bucket['min_seen']) {
                    $bucket['min_seen'] = $v;
                }
                if ($bucket['max_seen'] === null || $v > $bucket['max_seen']) {
                    $bucket['max_seen'] = $v;
                }
                break;
            case 'text':
                if (count($bucket['samples']) < 5) {
                    $bucket['samples'][] = (string) $answer;
                }
                break;
        }
    }

    private static function finalise_stats(array &$bucket, object $q): void {
        switch ($q->questiontype) {
            case 'rating':
                $bucket['avg'] = $bucket['count'] > 0
                    ? round($bucket['sum'] / $bucket['count'], 2) : 0;
                break;
            case 'nps':
                if ($bucket['count'] > 0) {
                    $promoter_pct  = ($bucket['promoters']  / $bucket['count']) * 100;
                    $detractor_pct = ($bucket['detractors'] / $bucket['count']) * 100;
                    $bucket['nps_score'] = round($promoter_pct - $detractor_pct);
                    $bucket['avg'] = round($bucket['sum'] / $bucket['count'], 1);
                } else {
                    $bucket['nps_score'] = 0;
                    $bucket['avg'] = 0;
                }
                break;
            case 'yesno':
                $bucket['yes_pct'] = $bucket['count'] > 0
                    ? round(($bucket['yes'] / $bucket['count']) * 100) : 0;
                break;
            case 'multichoice_multi':
                // P1 #18 — average picks per respondent (1.0 = everyone
                // picked exactly one option, 2.5 = avg of 2-3 picks each).
                $bucket['avg_picks'] = $bucket['count'] > 0
                    ? round($bucket['total_picks'] / $bucket['count'], 2) : 0;
                break;
            case 'numeric':
                // P1 #18 — compute avg only if any responses came in;
                // leave min_seen/max_seen as null when count=0 so the
                // analysis surface can render "—" rather than "0".
                $bucket['avg'] = $bucket['count'] > 0
                    ? round($bucket['sum'] / $bucket['count'], 2) : 0;
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // FILTERED RESPONSES (G-05)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Build a (where_sql, params) pair for response filtering.
     *
     * Recognised filters:
     *   - evaluationid (int)
     *   - date_from, date_to (unix ts) — bounds on timesubmitted
     *   - courseid, programid, classroomid (int) — context match
     *
     * The caller composes these into their own SELECT.
     *
     * @param array $filters
     * @return array [string $where, array $params]
     */
    public static function build_response_filter(array $filters): array {
        global $DB;
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['evaluationid'])) {
            $where[] = 'r.evaluationid = :evid';
            $params['evid'] = (int) $filters['evaluationid'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'r.timesubmitted >= :dfrom';
            $params['dfrom'] = (int) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'r.timesubmitted <= :dto';
            $params['dto'] = (int) $filters['date_to'];
        }
        if (!empty($filters['courseid'])) {
            $where[] = 'r.courseid = :cid';
            $params['cid'] = (int) $filters['courseid'];
        }
        if (!empty($filters['programid'])) {
            $where[] = 'r.programid = :pid';
            $params['pid'] = (int) $filters['programid'];
        }
        if (!empty($filters['classroomid'])) {
            $where[] = 'r.classroomid = :crid';
            $params['crid'] = (int) $filters['classroomid'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Count responses matching the filter set.
     */
    public static function count_responses_filtered(array $filters): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) {
            return 0;
        }
        [$where, $params] = self::build_response_filter($filters);
        return (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {" . self::RESPONSES_TABLE . "} r WHERE $where", $params);
    }

    /**
     * Get filtered responses (raw) for a single evaluation. Caller still
     * passes evaluationid in the filter; this enforces it for safety so
     * cross-evaluation queries go through a different path.
     *
     * @return array  Each row: id, evaluationid, userid, courseid,
     *                programid, classroomid, response_data (JSON), timesubmitted
     */
    public static function get_responses_filtered(array $filters,
                                                  int $offset = 0, int $limit = 0): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) {
            return [];
        }
        [$where, $params] = self::build_response_filter($filters);
        $sql = "SELECT r.* FROM {" . self::RESPONSES_TABLE . "} r
                 WHERE $where
              ORDER BY r.timesubmitted DESC, r.id DESC";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Get response stats for an evaluation, restricted by the same filter
     * set used elsewhere (date range, course/program/classroom context).
     *
     * Same shape as get_response_stats() but driven by raw responses query.
     */
    public static function get_response_stats_filtered(int $evaluationid, array $filters): array {
        global $DB;

        // Force evaluationid into the filter.
        $filters['evaluationid'] = $evaluationid;

        $questions = self::get_questions($evaluationid);
        $responses = self::get_responses_filtered($filters);

        $stats = [];
        foreach ($questions as $q) {
            $stats[$q->id] = self::init_stats_bucket($q);
        }
        foreach ($responses as $r) {
            $data = json_decode($r->response_data, true);
            if (!is_array($data)) { continue; }
            foreach ($data as $qid => $answer) {
                $qid = (int) $qid;
                if (!isset($stats[$qid])) { continue; }
                if ($answer === null || $answer === '') { continue; }
                self::accumulate_stat($stats[$qid], $questions[$qid] ?? null, $answer);
            }
        }
        foreach ($stats as $qid => &$bucket) {
            $q = $questions[$qid] ?? null;
            if (!$q) { continue; }
            self::finalise_stats($bucket, $q);
        }
        unset($bucket);

        return [
            'response_count' => count($responses),
            'questions'      => $stats,
        ];
    }

    /**
     * Cross-evaluation Kirkpatrick aggregation. Buckets responses by the
     * parent evaluation's `kirkpatrick_level` and returns:
     *   per_level => [
     *     evaluation_count,
     *     response_count,
     *     avg_rating (across all rating qs),
     *     avg_nps   (across all nps qs),
     *   ]
     *
     * Optional filter: same shape as build_response_filter (date_from,
     * date_to, courseid, programid, classroomid).
     */
    public static function get_kirkpatrick_summary(array $filters = []): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) {
            return [];
        }

        $summary = [];
        foreach (array_keys(self::KIRKPATRICK_LEVELS) as $level) {
            $summary[$level] = [
                'level'            => $level,
                'level_label'      => self::KIRKPATRICK_LEVELS[$level],
                'evaluation_count' => 0,
                'response_count'   => 0,
                'rating_sum'       => 0,
                'rating_count'     => 0,
                'avg_rating'       => 0,
                'nps_sum'          => 0,
                'nps_count'        => 0,
                'avg_nps'          => 0,
                'nps_promoters'    => 0,
                'nps_detractors'   => 0,
                'nps_score'        => 0,
            ];
        }

        // Count evaluations per level (no filter — these are top-level).
        $eval_counts = $DB->get_records_sql(
            "SELECT kirkpatrick_level AS lvl, COUNT(*) AS c
               FROM {" . self::TABLE . "}
              GROUP BY kirkpatrick_level");
        foreach ($eval_counts as $row) {
            $lvl = (int) $row->lvl;
            if (isset($summary[$lvl])) {
                $summary[$lvl]['evaluation_count'] = (int) $row->c;
            }
        }

        // Walk responses, decoding answers and accumulating per-question stats
        // into the right Kirkpatrick bucket.
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) {
            return $summary;
        }

        [$where, $params] = self::build_response_filter($filters);

        $sql = "SELECT r.id, r.response_data, e.kirkpatrick_level
                  FROM {" . self::RESPONSES_TABLE . "} r
                  JOIN {" . self::TABLE . "} e ON e.id = r.evaluationid
                 WHERE $where";
        $rs = $DB->get_recordset_sql($sql, $params);

        // Cache question types per evaluation to avoid N+1 lookups.
        $qcache = [];
        foreach ($rs as $row) {
            $lvl = (int) $row->kirkpatrick_level;
            if (!isset($summary[$lvl])) { continue; }
            $summary[$lvl]['response_count']++;

            $answers = json_decode($row->response_data, true);
            if (!is_array($answers)) { continue; }

            foreach ($answers as $qid => $answer) {
                $qid = (int) $qid;
                if (!isset($qcache[$qid])) {
                    $qcache[$qid] = $DB->get_field(self::QUESTIONS_TABLE,
                        'questiontype', ['id' => $qid]);
                }
                $qtype = $qcache[$qid] ?? null;
                if (!$qtype) { continue; }

                if ($qtype === 'rating') {
                    $v = (int) $answer;
                    if ($v >= 1 && $v <= 5) {
                        $summary[$lvl]['rating_sum'] += $v;
                        $summary[$lvl]['rating_count']++;
                    }
                } else if ($qtype === 'nps') {
                    $v = (int) $answer;
                    if ($v >= 0 && $v <= 10) {
                        $summary[$lvl]['nps_sum'] += $v;
                        $summary[$lvl]['nps_count']++;
                        if ($v <= 6) {
                            $summary[$lvl]['nps_detractors']++;
                        } else if ($v >= 9) {
                            $summary[$lvl]['nps_promoters']++;
                        }
                    }
                }
            }
        }
        $rs->close();

        // Finalise averages + NPS scores per level.
        foreach ($summary as $lvl => &$row) {
            if ($row['rating_count'] > 0) {
                $row['avg_rating'] = round($row['rating_sum'] / $row['rating_count'], 2);
            }
            if ($row['nps_count'] > 0) {
                $row['avg_nps'] = round($row['nps_sum'] / $row['nps_count'], 1);
                $promoter_pct  = ($row['nps_promoters']  / $row['nps_count']) * 100;
                $detractor_pct = ($row['nps_detractors'] / $row['nps_count']) * 100;
                $row['nps_score'] = round($promoter_pct - $detractor_pct);
            }
        }
        unset($row);

        return $summary;
    }

    /**
     * Build a CSV-friendly row representation of a single response.
     *
     * The row has one column per question (in sortorder) plus context
     * columns (Date, User, Email, Course, Program, Classroom).
     * Anonymous evaluations leave User/Email blank.
     *
     * @param object $response  raw response row from DB
     * @param array  $questions ordered question records (from get_questions)
     * @param object $eval      parent evaluation record
     * @return array  row of strings
     */
    public static function response_to_csv_row(object $response, array $questions,
                                                object $eval): array {
        global $DB;

        $row = [];
        $row[] = userdate((int) $response->timesubmitted, '%Y-%m-%d %H:%M');

        // Phase G.2 (2026-05-08) — when any question in the form is
        // anonymous, hide the responder identity for the whole row to
        // prevent correlation attacks. Otherwise honour the eval-level
        // anonymous flag.
        $any_anonymous_q = false;
        foreach ($questions as $q) {
            if ((int) ($q->anonymous ?? 0) === 1) {
                $any_anonymous_q = true;
                break;
            }
        }

        if ((int) $eval->anonymous === 1 || (int) $response->userid === 0) {
            $row[] = '(anonymous)';
            $row[] = '';
        } else if ($any_anonymous_q) {
            $row[] = '(question-anonymous)';
            $row[] = '';
        } else {
            $u = \core_user::get_user((int) $response->userid, 'id, firstname, lastname, email');
            $row[] = $u ? fullname($u) : '(deleted user)';
            $row[] = $u ? $u->email : '';
        }

        // Context columns.
        $row[] = $response->courseid    ? (string) $response->courseid    : '';
        $row[] = $response->programid   ? (string) $response->programid   : '';
        $row[] = $response->classroomid ? (string) $response->classroomid : '';

        // Per-question answers in the canonical sort order.
        $answers = json_decode((string) $response->response_data, true);
        if (!is_array($answers)) {
            $answers = [];
        }
        foreach ($questions as $q) {
            $a = $answers[$q->id] ?? '';
            if (is_array($a)) {
                $a = implode(' | ', array_map('strval', $a));
            }
            $row[] = (string) $a;
        }

        return $row;
    }

    /**
     * CSV header row matching response_to_csv_row().
     *
     * @param array $questions ordered question records (from get_questions)
     * @return array
     */
    public static function csv_header_row(array $questions): array {
        $header = ['Submitted', 'Respondent', 'Email', 'Course ID', 'Program ID', 'Classroom ID'];
        $i = 1;
        foreach ($questions as $q) {
            $label = 'Q' . $i . ': ' . trim((string) $q->questiontext);
            // Trim down to keep column header readable.
            if (mb_strlen($label) > 80) {
                $label = mb_substr($label, 0, 77) . '...';
            }
            $header[] = $label;
            $i++;
        }
        return $header;
    }
}
