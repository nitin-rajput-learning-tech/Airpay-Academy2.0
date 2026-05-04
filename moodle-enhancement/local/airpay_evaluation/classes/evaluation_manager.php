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
}
