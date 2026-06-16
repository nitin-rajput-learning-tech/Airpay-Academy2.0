<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent\tool;

use local_sentientia_assistant\agent\tool;
use local_sentientia_assistant\agent\tool_result;
use local_sentientia_assistant\agent\invalid_tool_args;

defined('MOODLE_INTERNAL') || die();

/**
 * Tool: book the CURRENT learner onto an ILT / classroom (reuses
 * local_sentientia_classroom's roster table {local_sentientia_classroom_users}).
 *
 * Guard chain (enforced by tool::authorise_and_run):
 *   - validate_args: classroomid positive int; the classroom plugin and
 *     its roster table must exist; the classroom must exist and be active
 *     (status=1).
 *   - capability:    local/sentientia_assistant:bookilt
 *   - tenant:        the classroom's costcenterid (+ open_path) tenant root
 *     must be the acting user's tenant. No cross-tenant booking.
 *   - idempotency:   already on the roster → OUTCOME_NOOP. Also re-checks
 *     capacity at execute time and refuses to overbook.
 *   - execute:       insert a roster row inside a transaction.
 *
 * The classroom plugin is reused via a soft dependency: if it (or its
 * table) is absent in a given Sentientia deployment, the tool reports
 * gracefully rather than fataling.
 *
 * @package local_sentientia_assistant
 */
class book_ilt_session extends tool {

    /** Roster table owned by local_sentientia_classroom. */
    private const ROSTER_TABLE = 'local_sentientia_classroom_users';
    /** Classroom table owned by local_sentientia_classroom. */
    private const CLASSROOM_TABLE = 'local_sentientia_classroom';

    public function name(): string {
        return 'book_ilt_session';
    }

    public function capability(): string {
        return 'local/sentientia_assistant:bookilt';
    }

    public function label(): string {
        return get_string('tool_book_ilt', 'local_sentientia_assistant');
    }

    public function schema(): array {
        return [
            'name'        => $this->name(),
            'description' => 'Book the current learner onto an instructor-led training (ILT) classroom by its id.',
            'args'        => [
                'classroomid' => 'integer — the id of the classroom/ILT to book onto',
            ],
        ];
    }

    /** Is the classroom plugin (and its roster table) available in this deployment? */
    private function classroom_available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::CLASSROOM_TABLE)
            && $DB->get_manager()->table_exists(self::ROSTER_TABLE);
    }

    protected function validate_args(array $rawargs, int $userid): array {
        global $DB;

        if (!$this->classroom_available()) {
            // Soft dependency missing — treat as invalid (the agent will
            // surface a graceful "ILT booking unavailable" message).
            throw new invalid_tool_args('classroom_plugin_absent');
        }

        $classroomid = isset($rawargs['classroomid']) ? (int) $rawargs['classroomid'] : 0;
        if ($classroomid <= 0) {
            throw new invalid_tool_args('bad_classroomid');
        }

        $classroom = $DB->get_record(self::CLASSROOM_TABLE, ['id' => $classroomid],
            'id, costcenterid, open_path, capacity, status');
        if (!$classroom || (int) $classroom->status !== 1) {
            throw new invalid_tool_args('classroom_missing_or_inactive');
        }

        return [
            'classroomid'  => $classroomid,
            'costcenterid' => (int) $classroom->costcenterid,
            'open_path'    => (string) ($classroom->open_path ?? ''),
            'capacity'     => (int) $classroom->capacity,
        ];
    }

    protected function resource_tenant(array $args, int $userid): int {
        // Prefer the explicit costcenterid; fall back to open_path's root.
        $cc = (int) ($args['costcenterid'] ?? 0);
        if ($cc > 0) {
            return $cc;
        }
        $path = trim((string) ($args['open_path'] ?? ''), '/');
        if ($path === '') {
            return 0;
        }
        $first = explode('/', $path)[0] ?? '';
        return ctype_digit($first) ? (int) $first : 0;
    }

    protected function is_noop(array $args, int $userid): bool {
        global $DB;
        return $DB->record_exists(self::ROSTER_TABLE, [
            'classroomid' => $args['classroomid'],
            'userid'      => $userid,
        ]);
    }

    protected function execute(array $args, int $userid): tool_result {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            // Capacity guard — count current roster and refuse to overbook.
            // 0 capacity means "unlimited" by classroom convention.
            $capacity = (int) $args['capacity'];
            if ($capacity > 0) {
                $current = $DB->count_records(self::ROSTER_TABLE,
                    ['classroomid' => $args['classroomid']]);
                if ($current >= $capacity) {
                    $transaction->allow_commit();
                    return new tool_result(
                        tool_result::OUTCOME_FAILED,
                        get_string('tool_book_full', 'local_sentientia_assistant'),
                        false
                    );
                }
            }

            // Re-check idempotency inside the transaction to avoid a race.
            if ($DB->record_exists(self::ROSTER_TABLE,
                    ['classroomid' => $args['classroomid'], 'userid' => $userid])) {
                $transaction->allow_commit();
                return new tool_result(
                    tool_result::OUTCOME_NOOP,
                    get_string('agent_noop', 'local_sentientia_assistant'),
                    false
                );
            }

            $now = time();
            $DB->insert_record(self::ROSTER_TABLE, (object) [
                'classroomid' => $args['classroomid'],
                'userid'      => $userid,
                'enrolledby'  => $userid, // self-service booking via the copilot.
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return new tool_result(
            tool_result::OUTCOME_EXECUTED,
            get_string('tool_book_done', 'local_sentientia_assistant'),
            true
        );
    }
}
