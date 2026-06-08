<?php
namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * Classroom session manager — CRUD, counts, attendance queries.
 *
 * Replaces direct queries against {local_classroom} and
 * {local_classroom_sessions} found in dashboard.php and qr_attendance.php.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_manager {

    /** @var string Primary table. */
    private const TABLE = 'local_sentientia_classroom';
    private const SESSION_TABLE = 'local_sentientia_classroom_sessions';
    private const ATTENDANCE_TABLE = 'local_sentientia_classroom_attendance';
    private const USERS_TABLE = 'local_sentientia_classroom_users';

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
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_classroom');
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
        // P1 batch (2026-05-16) — enrolment-window dates. Empty input → NULL.
        $record->startdate    = !empty($data->startdate) ? (int) $data->startdate : null;
        $record->enddate      = !empty($data->enddate)   ? (int) $data->enddate   : null;
        $record->timecreated  = time();
        $record->timemodified = time();

        // Derive open_path from costcenterid.
        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
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

        // P1 batch (2026-05-16) — startdate / enddate added.
        $fields = ['name', 'description', 'costcenterid', 'departmentid',
                   'trainerid', 'location', 'capacity', 'status', 'visible',
                   'startdate', 'enddate'];
        foreach ($fields as $field) {
            if (isset($data->$field)) {
                // Empty/0 date input → NULL so "no enrolment window" is
                // distinguishable from "epoch zero".
                if (in_array($field, ['startdate', 'enddate'], true)
                    && empty($data->$field)) {
                    $record->$field = null;
                } else {
                    $record->$field = $data->$field;
                }
            }
        }

        // Update open_path if costcenter changed.
        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
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
        global $DB, $USER;

        if (!in_array($status, [self::STATUS_CANCELLED, self::STATUS_ACTIVE, self::STATUS_COMPLETED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_sentientia_classroom');
        }

        // Read previous status so we only emit on a real transition.
        $previous = (int) $DB->get_field(self::TABLE, 'status', ['id' => $id]);

        $DB->update_record(self::TABLE, (object) [
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
        ]);

        // W1-9 (2026-05-15) — emit classroom_completed event on transition
        // INTO completed state. The W1-5 evaluation observer listens to this
        // and fans out post-training feedback forms to attendees.
        if ($status === self::STATUS_COMPLETED && $previous !== self::STATUS_COMPLETED) {
            try {
                \local_sentientia_classroom\event\classroom_completed::create([
                    'context'  => \context_system::instance(),
                    'objectid' => $id,
                    'userid'   => (int) ($USER->id ?? 0),
                    'other'    => ['classroomid' => $id],
                ])->trigger();
            } catch (\Throwable $e) {
                // Audit logging must not break the state change itself.
                debugging('local_sentientia_classroom: failed to emit classroom_completed event: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

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
            // Delete classroom roster (G-02 added table — guard for fresh-install timing).
            $dbman = $DB->get_manager();
            if ($dbman->table_exists(self::USERS_TABLE)) {
                $DB->delete_records(self::USERS_TABLE, ['classroomid' => $id]);
            }
            // Delete classroom.
            $DB->delete_records(self::TABLE, ['id' => $id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }

    // ═══════════════════════════════════════════════════════════════════
    // SESSION CRUD (G-02)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Count sessions for a classroom (used by tab badges + overview stats).
     */
    public static function count_sessions(int $classroomid): int {
        global $DB;
        return (int) $DB->count_records(self::SESSION_TABLE, ['classroomid' => $classroomid]);
    }

    /**
     * Create a session for a classroom.
     *
     * @param int $classroomid
     * @param object $data sessiondate, starttime, endtime, location, title, trainerid, notes
     * @return int New session ID
     * @throws \moodle_exception
     */
    public static function create_session(int $classroomid, object $data): int {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $classroomid], 'id', MUST_EXIST);

        $start = (int) ($data->starttime ?? 0);
        $end   = (int) ($data->endtime ?? 0);
        if ($start <= 0 || $end <= 0) {
            throw new \moodle_exception('invalidsessiontime', 'local_sentientia_classroom');
        }
        if ($end <= $start) {
            throw new \moodle_exception('endbeforestart', 'local_sentientia_classroom');
        }

        $now = time();
        $record = new \stdClass();
        $record->classroomid  = $classroomid;
        $record->title        = trim((string) ($data->title ?? ''));
        // Default sessiondate = day of starttime if not provided.
        $record->sessiondate  = (int) ($data->sessiondate ?? $start);
        $record->starttime    = $start;
        $record->endtime      = $end;
        $record->location     = trim((string) ($data->location ?? ''));
        $tid = (int) ($data->trainerid ?? 0);
        $record->trainerid    = $tid > 0 ? $tid : null;
        $record->notes        = (string) ($data->notes ?? '');
        // W1-7 (2026-05-15) — virtual meeting + recording URLs.
        $record->meeting_url   = self::sanitize_url($data->meeting_url   ?? null);
        $record->recording_url = self::sanitize_url($data->recording_url ?? null);
        $record->timecreated  = $now;
        $record->timemodified = $now;

        return $DB->insert_record(self::SESSION_TABLE, $record);
    }

    /**
     * W1-7 (2026-05-15) — minimal URL sanitiser for session_meeting_url +
     * session_recording_url. Returns null for empty/invalid input.
     *
     * Accepts only http(s) URLs. Anything else is rejected — pasting a
     * `javascript:` or `data:` URI silently fails so the column never
     * stores a click-through XSS payload.
     */
    private static function sanitize_url(?string $url): ?string {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $parts = parse_url($url);
        if (!$parts || !isset($parts['scheme']) || !isset($parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        // Cap length to schema (1024 chars).
        return mb_substr($url, 0, 1024);
    }

    /**
     * Update an existing session.
     */
    public static function update_session(int $sessionid, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], '*', MUST_EXIST);

        $record = (object) ['id' => $sessionid, 'timemodified' => time()];
        $fields = ['title', 'sessiondate', 'starttime', 'endtime', 'location', 'trainerid', 'notes'];
        foreach ($fields as $f) {
            if (isset($data->$f)) {
                $record->$f = $data->$f;
            }
        }
        // W1-7 (2026-05-15) — URL fields go through sanitiser.
        if (property_exists($data, 'meeting_url')) {
            $record->meeting_url = self::sanitize_url($data->meeting_url);
        }
        if (property_exists($data, 'recording_url')) {
            $record->recording_url = self::sanitize_url($data->recording_url);
        }

        // Validate time range using either new or existing values.
        $newstart = $record->starttime ?? $existing->starttime;
        $newend   = $record->endtime   ?? $existing->endtime;
        if ((int) $newend <= (int) $newstart) {
            throw new \moodle_exception('endbeforestart', 'local_sentientia_classroom');
        }

        $DB->update_record(self::SESSION_TABLE, $record);
        return true;
    }

    /**
     * Delete a session and its attendance records (atomic).
     */
    public static function delete_session(int $sessionid): bool {
        global $DB;
        $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], 'id', MUST_EXIST);

        $tx = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::ATTENDANCE_TABLE, ['sessionid' => $sessionid]);
            $DB->delete_records(self::SESSION_TABLE,    ['id' => $sessionid]);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════
    // CLASSROOM ROSTER (enrolment) — G-02
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Count users on a classroom roster (for tab badges + overview).
     */
    public static function count_enrolled(int $classroomid): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return 0;
        }
        return (int) $DB->count_records(self::USERS_TABLE, ['classroomid' => $classroomid]);
    }

    /**
     * Enrol one or more users into a classroom roster. Idempotent.
     *
     * @param int   $classroomid
     * @param int[] $userids
     * @return int Count of users newly added.
     * @throws \moodle_exception
     */
    public static function enrol_users(int $classroomid, array $userids): int {
        global $DB, $USER;

        $DB->get_record(self::TABLE, ['id' => $classroomid], 'id', MUST_EXIST);

        $userids = array_unique(array_filter(array_map('intval', $userids), fn($id) => $id > 0));
        if (empty($userids)) {
            return 0;
        }

        // Reject system + non-existent + deleted users.
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $valid_ids = $DB->get_fieldset_select('user', 'id',
            "id $insql AND deleted = 0 AND id > 2", $inparams);
        if (empty($valid_ids)) {
            return 0;
        }

        // Skip already-enrolled.
        [$insql2, $inparams2] = $DB->get_in_or_equal($valid_ids, SQL_PARAMS_NAMED, 'uid2');
        $existing = $DB->get_fieldset_select(self::USERS_TABLE, 'userid',
            "classroomid = :cid AND userid $insql2",
            array_merge($inparams2, ['cid' => $classroomid]));
        $to_add = array_values(array_diff($valid_ids, $existing));
        if (empty($to_add)) {
            return 0;
        }

        $now = time();
        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($to_add as $uid) {
                $DB->insert_record(self::USERS_TABLE, (object) [
                    'classroomid'  => $classroomid,
                    'userid'       => (int) $uid,
                    'enrolledby'   => (int) ($USER->id ?? 0),
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        return count($to_add);
    }

    /**
     * Unenrol a user from a classroom roster. Also removes their attendance
     * across all sessions of this classroom.
     */
    public static function unenrol_user(int $classroomid, int $userid): bool {
        global $DB;

        $tx = $DB->start_delegated_transaction();
        try {
            // Remove attendance for this user across all sessions of this classroom.
            $sessionids = $DB->get_fieldset_select(self::SESSION_TABLE, 'id',
                'classroomid = :cid', ['cid' => $classroomid]);
            if (!empty($sessionids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'sid');
                $DB->delete_records_select(self::ATTENDANCE_TABLE,
                    "userid = :uid AND sessionid $insql",
                    array_merge($inparams, ['uid' => $userid]));
            }
            // Remove from roster.
            $DB->delete_records(self::USERS_TABLE,
                ['classroomid' => $classroomid, 'userid' => $userid]);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        // Phase 3 B.4 (2026-05-11) — auto-promote head of waiting list.
        // Runs AFTER the transaction commits so the seat is genuinely free.
        if (class_exists('\\local_sentientia_classroom\\waitlist_manager')) {
            try {
                \local_sentientia_classroom\waitlist_manager::auto_promote($classroomid);
            } catch (\Throwable $e) {
                debugging('Waitlist auto-promote failed: ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }
        return true;
    }

    /**
     * Get enrolled users for a classroom with optional search/sort/page.
     *
     * @return array  Each row: id (rosterid), userid, firstname, lastname,
     *                email, enrolled_at, optional open_employeeid/designation.
     */
    public static function get_enrolled_users(int $classroomid, string $search = '',
                                              string $sort = 'lastname', string $sortdir = 'ASC',
                                              int $offset = 0, int $limit = 100): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return [];
        }

        $cols = $DB->get_columns('user');
        $extra = '';
        if (isset($cols['open_employeeid'])) { $extra .= ', u.open_employeeid'; }
        if (isset($cols['open_designation'])) { $extra .= ', u.open_designation'; }

        $where = ['cu.classroomid = :cid'];
        $params = ['cid' => $classroomid];
        if (!empty($search)) {
            $term = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':s2', false) . ' OR ' .
                $DB->sql_like('u.email', ':s3', false) . ')';
            $params['s1'] = $params['s2'] = $params['s3'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $allowed_sorts = ['firstname', 'lastname', 'email', 'timecreated'];
        $sortcol = in_array($sort, $allowed_sorts, true) ? $sort : 'lastname';
        $sortcol = ($sortcol === 'timecreated') ? 'cu.timecreated' : "u.{$sortcol}";
        $dir = strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT cu.id, cu.userid, cu.timecreated AS enrolled_at,
                       u.firstname, u.lastname, u.email{$extra}
                  FROM {" . self::USERS_TABLE . "} cu
                  JOIN {user} u ON u.id = cu.userid
                 WHERE $wheresql
              ORDER BY $sortcol $dir, cu.id ASC";

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Count rows that match the same filter as get_enrolled_users — used by
     * the WS list endpoint for pagination "total".
     */
    public static function count_enrolled_filtered(int $classroomid, string $search = ''): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return 0;
        }

        $where = ['cu.classroomid = :cid'];
        $params = ['cid' => $classroomid];
        if (!empty($search)) {
            $term = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':s2', false) . ' OR ' .
                $DB->sql_like('u.email', ':s3', false) . ')';
            $params['s1'] = $params['s2'] = $params['s3'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        return (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {" . self::USERS_TABLE . "} cu
                JOIN {user} u ON u.id = cu.userid
              WHERE $wheresql", $params);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ATTENDANCE — G-02
    // ═══════════════════════════════════════════════════════════════════

    public const ATT_ABSENT  = 0;
    public const ATT_PRESENT = 1;
    public const ATT_LATE    = 2;
    public const ATT_EXCUSED = 3;

    /**
     * Mark attendance for a single (session, user) pair. Upserts.
     *
     * @return int  Status that was persisted.
     * @throws \moodle_exception
     */
    public static function mark_attendance(int $sessionid, int $userid, int $status,
                                            string $notes = ''): int {
        global $DB, $USER;

        $valid = [self::ATT_ABSENT, self::ATT_PRESENT, self::ATT_LATE, self::ATT_EXCUSED];
        if (!in_array($status, $valid, true)) {
            throw new \moodle_exception('invalidattendancestatus', 'local_sentientia_classroom');
        }

        $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], 'id', MUST_EXIST);

        $now = time();
        $existing = $DB->get_record(self::ATTENDANCE_TABLE,
            ['sessionid' => $sessionid, 'userid' => $userid]);

        if ($existing) {
            $existing->status       = $status;
            $existing->markedby     = (int) ($USER->id ?? 0);
            $existing->notes        = $notes;
            $existing->timemodified = $now;
            $DB->update_record(self::ATTENDANCE_TABLE, $existing);
        } else {
            $DB->insert_record(self::ATTENDANCE_TABLE, (object) [
                'sessionid'    => $sessionid,
                'userid'       => $userid,
                'status'       => $status,
                'markedby'     => (int) ($USER->id ?? 0),
                'notes'        => $notes,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        return $status;
    }

    /**
     * Bulk mark attendance for a session.
     *
     * @param int $sessionid
     * @param array $marks  [['userid' => int, 'status' => int, 'notes' => string], ...]
     * @return int Count of rows upserted.
     */
    public static function bulk_mark_attendance(int $sessionid, array $marks): int {
        global $DB;

        $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], 'id', MUST_EXIST);

        $count = 0;
        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($marks as $m) {
                $uid = (int) ($m['userid'] ?? 0);
                $st  = (int) ($m['status'] ?? self::ATT_ABSENT);
                $notes = (string) ($m['notes'] ?? '');
                if ($uid <= 0) { continue; }
                self::mark_attendance($sessionid, $uid, $st, $notes);
                $count++;
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return $count;
    }

    /**
     * Get attendance for a session — every roster member, joined with their
     * attendance row (or default ABSENT if not yet marked).
     *
     * @return array  Each row: userid, firstname, lastname, email, status,
     *                status_label, marked_at, notes.
     */
    public static function get_session_attendance(int $sessionid): array {
        global $DB;
        $dbman = $DB->get_manager();

        $session = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], '*', MUST_EXIST);

        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return [];
        }

        $sql = "SELECT u.id AS userid, u.firstname, u.lastname, u.email,
                       COALESCE(a.status, 0) AS status,
                       a.timemodified AS marked_at,
                       a.notes AS notes
                  FROM {" . self::USERS_TABLE . "} cu
                  JOIN {user} u ON u.id = cu.userid
             LEFT JOIN {" . self::ATTENDANCE_TABLE . "} a
                       ON a.sessionid = :sid AND a.userid = cu.userid
                 WHERE cu.classroomid = :cid
              ORDER BY u.lastname ASC, u.firstname ASC";
        $rows = $DB->get_records_sql($sql, [
            'sid' => $sessionid,
            'cid' => (int) $session->classroomid,
        ]);

        $labels = [
            self::ATT_ABSENT  => 'Absent',
            self::ATT_PRESENT => 'Present',
            self::ATT_LATE    => 'Late',
            self::ATT_EXCUSED => 'Excused',
        ];
        foreach ($rows as $r) {
            $r->status_label = $labels[(int) $r->status] ?? 'Absent';
            $r->marked_at_human = $r->marked_at
                ? userdate((int) $r->marked_at, '%d %b %Y %H:%M')
                : '';
        }
        return $rows;
    }
}
