<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * Evaluation manager — CRUD for evaluation forms (containers).
 *
 * Questions and responses live in companion tables; their CRUD will be
 * added in a follow-up. For now this manages the form-level metadata.
 *
 * @package    local_airpay_evaluation
 */
class evaluation_manager {

    private const TABLE          = 'local_airpay_evaluation';
    private const QUESTIONS_TABLE = 'local_airpay_evaluation_questions';
    private const RESPONSES_TABLE = 'local_airpay_evaluation_responses';

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
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_evaluation');
        }

        $level = (int) ($data->kirkpatrick_level ?? 1);
        if (!array_key_exists($level, self::KIRKPATRICK_LEVELS)) {
            throw new \moodle_exception('invalidkirkpatricklevel', 'local_airpay_evaluation');
        }

        $trigger = $data->trigger_event ?? 'manual';
        if (!array_key_exists($trigger, self::TRIGGER_EVENTS)) {
            throw new \moodle_exception('invalidtrigger', 'local_airpay_evaluation');
        }

        $record = (object) [
            'name'              => trim($data->name),
            'description'       => $data->description ?? '',
            'kirkpatrick_level' => $level,
            'trigger_event'     => $trigger,
            'days_after'        => max(0, (int) ($data->days_after ?? 0)),
            'costcenterid'      => (int) ($data->costcenterid ?? 0),
            'status'            => (int) ($data->status ?? self::STATUS_DRAFT),
            'anonymous'         => isset($data->anonymous) ? (int) $data->anonymous : 0,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ];

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
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
                throw new \moodle_exception('invalidkirkpatricklevel', 'local_airpay_evaluation');
            }
            $record->kirkpatrick_level = $level;
        }
        if (isset($data->trigger_event)) {
            if (!array_key_exists($data->trigger_event, self::TRIGGER_EVENTS)) {
                throw new \moodle_exception('invalidtrigger', 'local_airpay_evaluation');
            }
            $record->trigger_event = $data->trigger_event;
        }
        if (isset($data->days_after))   $record->days_after = max(0, (int) $data->days_after);
        if (isset($data->costcenterid)) $record->costcenterid = (int) $data->costcenterid;
        if (isset($data->status))       $record->status = (int) $data->status;
        if (isset($data->anonymous))    $record->anonymous = (int) $data->anonymous;

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    public static function change_status(int $id, int $status): int {
        global $DB;
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_airpay_evaluation');
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
        'rating'      => '5-point rating (1=Strongly Disagree → 5=Strongly Agree)',
        'nps'         => 'NPS (0-10 likelihood)',
        'yesno'       => 'Yes / No',
        'multichoice' => 'Multiple choice (one answer)',
        'text'        => 'Free text response',
    ];

    public static function get_questions(int $evaluationid): array {
        global $DB;
        return $DB->get_records(self::QUESTIONS_TABLE,
            ['evaluationid' => $evaluationid], 'sortorder ASC, id ASC');
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
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_evaluation');
        }

        if (!array_key_exists($data->questiontype, self::QUESTION_TYPES)) {
            throw new \moodle_exception('invalidquestiontype', 'local_airpay_evaluation');
        }

        if (!$DB->record_exists(self::TABLE, ['id' => $data->evaluationid])) {
            throw new \moodle_exception('invalidevaluation', 'local_airpay_evaluation');
        }

        // Multichoice requires options.
        $options_json = null;
        if ($data->questiontype === 'multichoice') {
            $opts = self::parse_options($data->options ?? '');
            if (count($opts) < 2) {
                throw new \moodle_exception('multichoice_needs_options', 'local_airpay_evaluation');
            }
            $options_json = json_encode($opts);
        }

        // Auto-increment sortorder.
        $maxsort = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {" . self::QUESTIONS_TABLE . "} WHERE evaluationid = :eid",
            ['eid' => $data->evaluationid]);

        $record = (object) [
            'evaluationid' => (int) $data->evaluationid,
            'questiontype' => $data->questiontype,
            'questiontext' => trim($data->questiontext),
            'options'      => $options_json,
            'required'     => isset($data->required) ? (int) $data->required : 1,
            'sortorder'    => isset($data->sortorder) ? (int) $data->sortorder : ((int) $maxsort + 1),
            'timecreated'  => time(),
        ];

        return $DB->insert_record(self::QUESTIONS_TABLE, $record);
    }

    public static function update_question(int $id, object $data): bool {
        global $DB;
        $existing = $DB->get_record(self::QUESTIONS_TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id];

        if (isset($data->questiontype)) {
            if (!array_key_exists($data->questiontype, self::QUESTION_TYPES)) {
                throw new \moodle_exception('invalidquestiontype', 'local_airpay_evaluation');
            }
            $record->questiontype = $data->questiontype;
        }
        if (isset($data->questiontext)) $record->questiontext = trim($data->questiontext);
        if (isset($data->required))     $record->required = (int) $data->required;
        if (isset($data->sortorder))    $record->sortorder = (int) $data->sortorder;

        $finaltype = $record->questiontype ?? $existing->questiontype;
        if ($finaltype === 'multichoice' && isset($data->options)) {
            $opts = self::parse_options($data->options);
            if (count($opts) < 2) {
                throw new \moodle_exception('multichoice_needs_options', 'local_airpay_evaluation');
            }
            $record->options = json_encode($opts);
        } else if (isset($record->questiontype) && $record->questiontype !== 'multichoice') {
            $record->options = null;
        }

        $DB->update_record(self::QUESTIONS_TABLE, $record);
        return true;
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
}
