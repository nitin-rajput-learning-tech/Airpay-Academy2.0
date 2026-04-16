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
}
