<?php
namespace local_airpay_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * Classroom session manager — CRUD, counts, attendance queries.
 *
 * Replaces direct queries against {local_classroom} and
 * {local_classroom_sessions} found in dashboard.php and qr_attendance.php.
 *
 * @package    local_airpay_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_manager {

    /** @var string Primary table. */
    private const TABLE = 'local_airpay_classroom';
    private const SESSION_TABLE = 'local_airpay_classroom_sessions';
    private const ATTENDANCE_TABLE = 'local_airpay_classroom_attendance';

    /** @var string Legacy BizLMS table. */
    private const LEGACY_TABLE = 'local_classroom';
    private const LEGACY_SESSION_TABLE = 'local_classroom_sessions';

    /**
     * Count classrooms, optionally scoped by tenant path.
     *
     * Replaces dashboard.php lines 335-339.
     *
     * @param string $pathfilter  e.g. "/1/%" or empty for all
     * @return int
     */
    public static function count_classrooms(string $pathfilter = ''): int {
        global $DB;

        $table = self::resolve_table();

        if (!empty($pathfilter)) {
            return $DB->count_records_select($table, "open_path LIKE :p", ['p' => $pathfilter]);
        }

        return $DB->count_records($table);
    }

    /**
     * Get a classroom record by ID.
     *
     * @param int $id
     * @return object|false
     */
    public static function get(int $id) {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        if ($record) {
            return $record;
        }

        return self::legacy_get($id);
    }

    /**
     * Get sessions for a classroom.
     *
     * @param int $classroomid
     * @return array
     */
    public static function get_sessions(int $classroomid): array {
        global $DB;

        $table = self::resolve_session_table();
        return $DB->get_records($table, ['classroomid' => $classroomid], 'sessiondate ASC');
    }

    /**
     * Get a session by ID (for QR attendance).
     *
     * Replaces qr_attendance.php query against {local_classroom_sessions}.
     *
     * @param int $sessionid
     * @return object|false
     */
    public static function get_session(int $sessionid) {
        global $DB;

        $record = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid]);
        if ($record) {
            return $record;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_SESSION_TABLE)) {
            return $DB->get_record(self::LEGACY_SESSION_TABLE, ['id' => $sessionid]);
        }

        return false;
    }

    /**
     * Determine which table to use (prefers Airpay, falls back to BizLMS).
     *
     * @return string
     */
    private static function resolve_table(): string {
        global $DB;
        $dbman = $DB->get_manager();

        if ($dbman->table_exists(self::TABLE) && $DB->count_records(self::TABLE) > 0) {
            return self::TABLE;
        }
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return self::LEGACY_TABLE;
        }
        return self::TABLE;
    }

    private static function resolve_session_table(): string {
        global $DB;
        $dbman = $DB->get_manager();

        if ($dbman->table_exists(self::SESSION_TABLE) && $DB->count_records(self::SESSION_TABLE) > 0) {
            return self::SESSION_TABLE;
        }
        if ($dbman->table_exists(self::LEGACY_SESSION_TABLE)) {
            return self::LEGACY_SESSION_TABLE;
        }
        return self::SESSION_TABLE;
    }

    private static function legacy_get(int $id) {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::LEGACY_TABLE)) {
            return false;
        }
        return $DB->get_record(self::LEGACY_TABLE, ['id' => $id]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations (classroom-level)
    // ═══════════════════════════════════════════════════════════════════

    /** Status values matching install.xml comment. */
    public const STATUS_CANCELLED = 0;
    public const STATUS_ACTIVE    = 1;
    public const STATUS_COMPLETED = 2;

    /**
     * Create a new classroom.
     *
     * @param object $data Form data: name, description, costcenterid, location, capacity, trainerid
     * @return int  New classroom ID
     * @throws \moodle_exception
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_classroom');
        }

        $record = new \stdClass();
        $record->name         = trim($data->name);
        $record->description  = $data->description ?? '';
        $record->costcenterid = (int) ($data->costcenterid ?? 0);
        $record->departmentid = (int) ($data->departmentid ?? 0);
        $record->trainerid    = (int) ($data->trainerid ?? 0);
        $record->location     = $data->location ?? '';
        $record->capacity     = max(1, (int) ($data->capacity ?? 30));
        $record->status       = (int) ($data->status ?? self::STATUS_ACTIVE);
        $record->visible      = isset($data->visible) ? (int) $data->visible : 1;
        $record->timecreated  = time();
        $record->timemodified = time();

        // Derive open_path from costcenterid.
        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing classroom.
     *
     * @param int $id
     * @param object $data
     * @return bool
     * @throws \moodle_exception
     */
    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id, 'timemodified' => time()];

        $fields = ['name', 'description', 'costcenterid', 'departmentid',
                   'trainerid', 'location', 'capacity', 'status', 'visible'];
        foreach ($fields as $field) {
            if (isset($data->$field)) {
                $record->$field = $data->$field;
            }
        }

        // Update open_path if costcenter changed.
        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Change classroom status (active/cancelled/completed).
     *
     * @param int $id
     * @param int $status  STATUS_* constant
     * @return int  New status
     * @throws \moodle_exception
     */
    public static function change_status(int $id, int $status): int {
        global $DB;

        if (!in_array($status, [self::STATUS_CANCELLED, self::STATUS_ACTIVE, self::STATUS_COMPLETED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_airpay_classroom');
        }

        $DB->update_record(self::TABLE, (object) [
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
        ]);
        return $status;
    }

    /**
     * Delete a classroom and all its sessions/attendance.
     *
     * @param int $id
     * @return bool
     * @throws \moodle_exception
     */
    public static function delete(int $id): bool {
        global $DB;

        $classroom = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $transaction = $DB->start_delegated_transaction();
        try {
            // Delete attendance records for all sessions.
            $sessionids = $DB->get_fieldset_select(self::SESSION_TABLE,
                'id', 'classroomid = :cid', ['cid' => $id]);
            if (!empty($sessionids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'sid');
                $DB->delete_records_select(self::ATTENDANCE_TABLE, "sessionid $insql", $inparams);
            }
            // Delete sessions.
            $DB->delete_records(self::SESSION_TABLE, ['classroomid' => $id]);
            // Delete classroom.
            $DB->delete_records(self::TABLE, ['id' => $id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }
}
