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

    /** @var string Trainers of a classroom, one row each (ADR-032: BizLMS local_classroom_trainers). */
    private const TRAINERS_TABLE = 'local_sentientia_classroom_trainers';
    /** @var string Courses linked to a classroom (ADR-032: BizLMS local_classroom_courses). */
    private const COURSES_TABLE = 'local_sentientia_classroom_courses';
    /** @var string Waiting list. */
    private const WAITLIST_TABLE = 'local_sentientia_classroom_waitlist';

    // The BizLMS fallbacks that used to live here (a whole-table fallback to {local_classroom} and
    // {local_classroom_sessions} while the Sentientia table was empty, and a per-id fallback) are gone
    // (ADR-032, classroom code fix 3). The BizLMS classroom importer moves that history into the
    // Sentientia tables, so nothing reads a legacy table any more; the fallbacks also sorted the legacy
    // sessions table by a column it does not have.

    /**
     * Count classrooms, optionally scoped by tenant path.
     *
     * Replaces dashboard.php lines 335-339.
     *
     * @param string $pathfilter  a tenant/org PATH such as '/1' or '/1/2' (NOT a LIKE
     *                            pattern); matched exact-or-descendant. Empty = all.
     * @return int
     */
    public static function count_classrooms(string $pathfilter = ''): int {
        global $DB;

        if (!empty($pathfilter)) {
            [$psql, $pargs] = \local_sentientia_platform\tenant::path_descendant_filter(
                $pathfilter, '', 'open_path', 'cc');
            return $DB->count_records_select(self::TABLE, $psql, $pargs);
        }

        return $DB->count_records(self::TABLE);
    }

    /**
     * Count the classrooms the CALLER may see: all of them for a cross-tenant caller, their own tenant's
     * for anyone else, none for a caller with no tenant (ADR-031: fail closed). The dashboards use this
     * instead of an unscoped count of a legacy table (classroom code fix 11).
     *
     * @return int
     */
    public static function count_classrooms_for_caller(): int {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return self::count_classrooms('');
        }
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null || $scope === '') {
            return 0;
        }
        return self::count_classrooms($scope);
    }

    /**
     * Get a classroom record by ID.
     *
     * @param int $id
     * @return object|false
     */
    public static function get(int $id) {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * Get sessions for a classroom.
     *
     * @param int $classroomid
     * @return array
     */
    public static function get_sessions(int $classroomid): array {
        global $DB;

        return $DB->get_records(self::SESSION_TABLE, ['classroomid' => $classroomid],
            'sessiondate ASC, starttime ASC, id ASC');
    }

    /**
     * Get a session by ID (for QR attendance).
     *
     * @param int $sessionid
     * @return object|false
     */
    public static function get_session(int $sessionid) {
        global $DB;

        return $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADR-031 (2026-09-25) — tenant guard for everything named by id
    // ═══════════════════════════════════════════════════════════════════
    //
    // :view, :update, :attendance and :create default to the manager
    // archetype, and every tenant admin holds a manager-archetype role at
    // system context. Until 2026-09-25 the web services and forms checked
    // only the capability and then acted on whatever classroomid/sessionid
    // the client sent, so any tenant admin could read or rewrite every
    // tenant's classrooms, rosters and attendance. A capability says WHAT;
    // these say WHERE. Call one after require_capability() wherever an id
    // comes in.

    /**
     * Refuse unless the classroom is in the caller's tenant.
     *
     * Cross-tenant callers (site admin, local/sentientia_platform:crosstenant)
     * pass. Anyone else needs a resolvable tenant AND a classroom whose
     * open_path lies inside it. A classroom with no open_path is
     * cross-tenant-only: every tenant's list hides it (path_filter never
     * matches NULL), so it must not open by id either -
     * tenant::require_path_access() on its own lets '' through.
     *
     * @param \stdClass $classroom  a record carrying open_path
     * @param int|null  $userid     the caller; defaults to $USER
     * @throws \moodle_exception error_outoftenant
     */
    public static function assert_classroom_in_scope(\stdClass $classroom, ?int $userid = null): void {
        global $DB, $USER;
        $current = (int) ($USER->id ?? 0);
        $userid = $userid ?? $current;
        if (\local_sentientia_platform\tenant::is_cross_tenant($userid)) {
            return;
        }
        $caller = ($userid === $current) ? $USER
            : $DB->get_record('user', ['id' => $userid], 'id, open_path');
        $path = trim((string) ($classroom->open_path ?? ''));
        if ($path === '' || !$caller
                || \local_sentientia_platform\tenant::scope_path($caller) === null) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access($path,
            $userid === $current ? null : $userid);
    }

    /**
     * Load a classroom by id and refuse unless it is in the caller's tenant.
     *
     * @throws \moodle_exception error_outoftenant (or dml_missing_record_exception)
     */
    public static function require_classroom_access(int $classroomid, ?int $userid = null): \stdClass {
        global $DB;
        $classroom = $DB->get_record(self::TABLE, ['id' => $classroomid], '*', MUST_EXIST);
        self::assert_classroom_in_scope($classroom, $userid);
        return $classroom;
    }

    /**
     * Load a session and its classroom; refuse unless the classroom is in
     * the caller's tenant.
     *
     * @return \stdClass[] [$session, $classroom]
     * @throws \moodle_exception error_outoftenant (or dml_missing_record_exception)
     */
    public static function require_session_access(int $sessionid): array {
        global $DB;
        $session = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], '*', MUST_EXIST);
        $classroom = self::require_classroom_access((int) $session->classroomid);
        return [$session, $classroom];
    }

    /**
     * May $userid open and take attendance for this session?
     *
     * Owner decision 2026-09-30: the Sentientia/BizLMS `trainer` role is archetype `teacher`,
     * and :view / :attendance are granted at system context, so on the capability alone a
     * trainer could open and mark EVERY classroom session in their tenant. Now anyone who
     * does not hold local/sentientia_classroom:update (the manager archetype, which the
     * tenant administrator role is; site admins always pass) may do so only for a session
     * they are the assigned trainer of: the user in the session's own `trainerid`
     * ({local_sentientia_classroom_sessions}.trainerid, nullable) or in its classroom's
     * `trainerid` ({local_sentientia_classroom}.trainerid, nullable), or any other trainer the
     * classroom lists (a co-trainer: a row of {local_sentientia_classroom_trainers}, filled by
     * the ADR-032 BizLMS import, so a classroom with trainers T1 and T2 lets both run every one
     * of its sessions even when the session itself names only T1). A classroom and session
     * with no trainer are therefore open to managers only.
     *
     * Why :update and not :manage (follow-up 2026-09-30): the BizLMS `trainer` role (id 10 on
     * the local prod-data copy, archetype teacher) holds :manage there - the import maps the
     * legacy local/classroom:manageclassroom grant onto it - but neither :create nor :update.
     * Keyed on :manage, that role would be exempt from this rule on real data. The roles that
     * are meant to run every session (the manager archetype, the tenant administrator role 9)
     * hold :update, so :update tells them apart; a role holding :manage without :update is
     * restricted to its own sessions.
     *
     * This is about WHO within the tenant. It does not replace the ADR-031 tenant guard:
     * call it after require_session_access(), which proves the classroom is in the caller's
     * tenant; require_attendance_access() does both.
     *
     * @param \stdClass $session   a row of the sessions table
     * @param \stdClass $classroom a row of the classrooms table
     * @param int|null $userid     defaults to the current user
     */
    public static function may_run_session(\stdClass $session, \stdClass $classroom, ?int $userid = null): bool {
        global $DB, $USER;
        $userid = $userid ?? (int) ($USER->id ?? 0);
        if ($userid <= 0) {
            return false;
        }
        if (has_capability('local/sentientia_classroom:update', \context_system::instance(), $userid)) {
            return true;
        }
        if ($userid === (int) ($session->trainerid ?? 0)
                || $userid === (int) ($classroom->trainerid ?? 0)) {
            return true;
        }
        // A co-trainer: listed on the classroom but neither its primary trainer nor the session's.
        // The table exists once the import schema (upgrade step 2026093002) has run, so look only
        // then; before it nobody is a co-trainer.
        return $DB->get_manager()->table_exists(self::TRAINERS_TABLE)
            && $DB->record_exists(self::TRAINERS_TABLE, [
                'classroomid' => (int) $classroom->id,
                'trainerid' => $userid,
            ]);
    }

    /**
     * The guard for every attendance entry point (the grid page, the QR page and the
     * attendance web services): the ADR-031 tenant guard, then the assigned-trainer rule.
     *
     * @return \stdClass[] [$session, $classroom]
     * @throws \moodle_exception error_outoftenant, or error_nottrainer when the caller holds
     *                           no :update and is not the session's or classroom's trainer
     */
    public static function require_attendance_access(int $sessionid): array {
        [$session, $classroom] = self::require_session_access($sessionid);
        if (!self::may_run_session($session, $classroom)) {
            throw new \moodle_exception('error_nottrainer', 'local_sentientia_classroom');
        }
        return [$session, $classroom];
    }

    /**
     * Refuse unless every named user is in the caller's tenant (ADR-031
     * rule 5: a write that names a user checks the target). One query for
     * any number of ids. Cross-tenant callers pass.
     *
     * @param int[] $userids
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_users_in_scope(array $userids): void {
        $userids = self::clean_userids($userids);
        if (empty($userids)) {
            return;
        }
        if (count(self::users_in_scope($userids)) !== count($userids)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
    }

    /**
     * The subset of $userids that lies in the caller's tenant (one query).
     * Cross-tenant callers get every (positive, de-duplicated) id back; a
     * caller with no tenant gets none. A user with no open_path is never in
     * a scoped caller's tenant.
     *
     * For batch writes that must keep the in-tenant part of a request
     * rather than refuse all of it (bulk_mark_attendance).
     *
     * @param int[] $userids
     * @return int[]
     */
    public static function users_in_scope(array $userids): array {
        global $DB;
        $userids = self::clean_userids($userids);
        if (empty($userids) || \local_sentientia_platform\tenant::is_cross_tenant()) {
            return $userids;
        }
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null || $scope === '') {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'scu');
        [$tsql, $targs] = \local_sentientia_platform\tenant::path_descendant_filter(
            $scope, 'u', 'open_path', 'sct');
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT u.id FROM {user} u WHERE u.id $insql AND $tsql",
            $inparams + $targs));
    }

    /** @return int[] positive, de-duplicated, re-indexed */
    private static function clean_userids(array $userids): array {
        return array_values(array_unique(array_filter(array_map('intval', $userids),
            fn($id) => $id > 0)));
    }

    /**
     * Refuse removing $userid from a classroom the caller has already been
     * proved to own (require_classroom_access()), unless the user is either
     * in the caller's tenant or ALREADY on that classroom's roster.
     *
     * Removing someone from your own classroom does not reach into another
     * tenant, so a scoped admin may clean a legacy out-of-tenant or pathless
     * learner off their own roster (one a site admin, an approval flow or the
     * pre-ADR-031 fail-open put there). Naming anyone else keeps the ADR-031
     * rule 5 refusal. Cross-tenant callers pass.
     *
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_unenrol_target(int $classroomid, int $userid): void {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        if ($DB->get_manager()->table_exists(self::USERS_TABLE)
                && $DB->record_exists(self::USERS_TABLE, ['classroomid' => $classroomid, 'userid' => $userid])) {
            return;
        }
        \local_sentientia_platform\tenant::require_same_tenant_user($userid);
    }

    /**
     * WHERE fragment over {user} $alias for a ROSTER READ (who is on a
     * classroom, its attendance, its waitlist).
     *
     * ADR-031 follow-up (2026-09-25): require_classroom_access() proves the
     * classroom is the caller's, but its roster can still hold other tenants'
     * or pathless learners - enrolled by a site admin, a request/approval
     * flow, or the pre-ADR-031 fail-open - and every roster read listed their
     * names, emails, employee ids and designations to the tenant admin.
     * $callerscope = true limits the read to the caller's tenant (path_filter:
     * '1=1' cross-tenant, '1=0' no tenant). The web services and pages pass
     * true; the default false keeps library callers (cron, privacy, unit
     * tests with no user) unchanged.
     *
     * @return array{0: string, 1: array}
     */
    public static function roster_scope(bool $callerscope, string $alias = 'u'): array {
        return $callerscope ? \local_sentientia_platform\tenant::path_filter($alias) : ['1=1', []];
    }

    /**
     * The open_path a classroom should carry when the CALLER saves it with
     * org $costcenterid, refusing an org outside their tenant.
     *
     * Cross-tenant callers keep the old behaviour: the org's path, or null
     * for "no specific organisation". A scoped caller may only pick an org
     * inside their own tenant, and "no specific organisation" stamps their
     * tenant root - so no tenant user can create a classroom that no tenant
     * owns (or move one into another tenant).
     *
     * @return string|null  null = no path (cross-tenant callers only)
     * @throws \moodle_exception error_outoftenant
     */
    public static function org_path_for_caller(int $costcenterid): ?string {
        global $DB;
        $org = $costcenterid > 0
            ? $DB->get_record('local_sentientia_org', ['id' => $costcenterid], 'id, path')
            : false;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return ($org && (string) $org->path !== '') ? (string) $org->path : null;
        }
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null || $scope === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        if ($costcenterid <= 0) {
            return $scope;
        }
        $path = $org ? rtrim((string) $org->path, '/') : '';
        if ($path === '' || ($path !== $scope && strpos($path, $scope . '/') !== 0)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return (string) $org->path;
    }

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations (classroom-level)
    // ═══════════════════════════════════════════════════════════════════

    /** Status values matching install.xml comment. */
    public const STATUS_CANCELLED = 0;
    public const STATUS_ACTIVE    = 1;
    public const STATUS_COMPLETED = 2;
    /** Draft: BizLMS "new", a classroom not started yet (ADR-032). 3 and 4 are never used for it. */
    public const STATUS_DRAFT     = 5;
    /** On hold (ADR-032). 3 and 4 are never used for it: BizLMS used them for other meanings. */
    public const STATUS_ON_HOLD   = 6;

    /** Language string of each status. */
    private const STATUS_STRINGS = [
        self::STATUS_CANCELLED => 'status_cancelled',
        self::STATUS_ACTIVE    => 'status_active',
        self::STATUS_COMPLETED => 'status_completed',
        self::STATUS_DRAFT     => 'status_draft',
        self::STATUS_ON_HOLD   => 'status_onhold',
    ];

    /** Badge class of each status. */
    private const STATUS_BADGES = [
        self::STATUS_CANCELLED => 'badge-secondary',
        self::STATUS_ACTIVE    => 'badge-success',
        self::STATUS_COMPLETED => 'badge-info',
        self::STATUS_DRAFT     => 'badge-light',
        self::STATUS_ON_HOLD   => 'badge-warning',
    ];

    /**
     * Every status a classroom may have, in the order the pages list them.
     *
     * @return int[]
     */
    public static function statuses(): array {
        return [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_CANCELLED,
            self::STATUS_DRAFT, self::STATUS_ON_HOLD];
    }

    /**
     * The name of a status, in the user's language.
     *
     * @param int $status
     * @return string 'Unknown' for a value no page offers
     */
    public static function status_label(int $status): string {
        return get_string(self::STATUS_STRINGS[$status] ?? 'status_unknown', 'local_sentientia_classroom');
    }

    /**
     * The badge class of a status.
     *
     * @param int $status
     * @return string
     */
    public static function status_badge(int $status): string {
        return self::STATUS_BADGES[$status] ?? 'badge-secondary';
    }

    /**
     * Has a classroom in this status ended (cancelled or completed)?
     *
     * @param int $status
     * @return bool
     */
    public static function is_final(int $status): bool {
        return $status === self::STATUS_CANCELLED || $status === self::STATUS_COMPLETED;
    }

    /**
     * Did the BizLMS import create or adopt this row? Such a row is history (ADR-032,
     * framework.protect_imported_history): the pages that delete or unenrol refuse it.
     *
     * A site whose platform plugin has no import map yet has nothing imported.
     *
     * @param string $table Target table name without prefix.
     * @param int $id
     * @return bool
     */
    public static function is_imported(string $table, int $id): bool {
        global $DB;
        if (!class_exists('\local_sentientia_platform\bizlms\provenance')
                || !$DB->get_manager()->table_exists(\local_sentientia_platform\bizlms\legacymap::TABLE)) {
            return false;
        }
        return \local_sentientia_platform\bizlms\provenance::is_imported($table, $id);
    }

    /**
     * Is the reader surface for imported history switched on (default OFF)? It covers the overview's
     * training dates, linked courses, trainers and logo, the roster's completion columns and the learner's
     * "My classrooms" page. The flag only controls what is SHOWN; the import, the tenant rules and the
     * history protection do not depend on it.
     *
     * @return bool
     */
    public static function history_enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled('sentientia.classroom.import_history');
    }

    /**
     * Create a new classroom.
     *
     * @param object $data Form data: name, description, costcenterid, location, capacity, trainerid
     * @return int  New classroom ID
     * @throws \moodle_exception
     */
    public static function create(object $data, ?string $fallbackpath = null): int {
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
        // 0 is unlimited, as it is in BizLMS and in waitlist_manager::auto_promote() (classroom code fix 5).
        $record->capacity     = max(0, (int) ($data->capacity ?? 30));
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
        // ADR-031: a scoped caller's "no specific organisation" still belongs
        // to their tenant (see org_path_for_caller()); null keeps the old
        // behaviour for cross-tenant callers and internal callers.
        if (empty($record->open_path) && $fallbackpath !== null && $fallbackpath !== '') {
            $record->open_path = $fallbackpath;
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
    public static function update(int $id, object $data, ?string $fallbackpath = null): bool {
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
            // ADR-031: see create() - a scoped caller never leaves a classroom pathless.
            $record->open_path = $org ? $org->path : ($fallbackpath ?? '');
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

        if (!in_array($status, self::statuses(), true)) {
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

        // ADR-032 (framework.protect_imported_history): a classroom the BizLMS import brought in, with its
        // sessions, roster, attendance and waiting list, is history. Nothing deletes it from here.
        if (self::is_imported(self::TABLE, $id)) {
            throw new \moodle_exception('error_protected_history', 'local_sentientia_classroom');
        }

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
            // The waiting list and the two tables the BizLMS import added (trainers, linked courses) hold
            // nothing without their classroom. Guarded like the roster: a site may not have them yet.
            foreach ([self::WAITLIST_TABLE, self::TRAINERS_TABLE, self::COURSES_TABLE] as $child) {
                if ($dbman->table_exists($child)) {
                    $DB->delete_records($child, ['classroomid' => $id]);
                }
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
    public static function sanitize_url(?string $url): ?string {
        // The rule lives in url_rule so the BizLMS importer (whose static scan bans session_manager) applies
        // exactly the same one to the links BizLMS stored (ADR-032, classroom code fix 13).
        return url_rule::sanitize($url);
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

        // ADR-032: an imported session and the attendance recorded for it are history.
        if (self::is_imported(self::SESSION_TABLE, $sessionid)) {
            throw new \moodle_exception('error_protected_history', 'local_sentientia_classroom');
        }

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
        // Same set the roster lists: the import keeps the rows of deleted users as history, and they are not
        // shown (a count that included them would not match the list).
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::USERS_TABLE . "} cu
               JOIN {user} u ON u.id = cu.userid
              WHERE cu.classroomid = :cid AND u.deleted = 0", ['cid' => $classroomid]);
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
     * Is this roster row one the BizLMS import brought in that carries NO history yet, and may it be removed?
     *
     * Owner decision framework.protect_imported_history_pending_enrolments (2026-10-07, LRN-10): an admin may unenrol
     * an imported enrolment that has no completion, progress or attendance, on an active classroom. That is a routine
     * BizLMS action (the 762 April learners still pending on the 17 active plans are of this kind), and refusing it
     * would leave the admin unable to remove a learner who was enrolled by mistake. Everything that carries history
     * stays blocked: a completed row, a row with hours, a row with any attendance mark (even "absent": it is a
     * record), and any row of a classroom that is not active (draft, on hold, cancelled, completed). The BizLMS row
     * stays in the legacy tables.
     *
     * @param \stdClass|false|null $classroom The classroom row.
     * @param \stdClass $roster The roster row (local_sentientia_classroom_users).
     * @return bool True when the row carries no history and the classroom is active.
     */
    public static function imported_roster_is_pending($classroom, \stdClass $roster): bool {
        global $DB;
        if (!$classroom || (int) $classroom->status !== self::STATUS_ACTIVE) {
            return false;
        }
        if ((int) ($roster->completion_status ?? 0) !== 0 || !empty($roster->timecompleted) || !empty($roster->hours)) {
            return false;
        }
        $sessionids = $DB->get_fieldset_select(self::SESSION_TABLE, 'id', 'classroomid = :cid',
            ['cid' => (int) $roster->classroomid]);
        if ($sessionids) {
            [$insql, $inparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'prs');
            if ($DB->record_exists_select(self::ATTENDANCE_TABLE, "userid = :uid AND sessionid $insql",
                    array_merge($inparams, ['uid' => (int) $roster->userid]))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Unenrol a user from a classroom roster. Also removes their attendance
     * across all sessions of this classroom.
     *
     * An enrolment the BizLMS import brought in is history and is refused (framework.protect_imported_history),
     * except one that carries none yet (imported_roster_is_pending()). A completed learner's row is refused whether
     * imported or not: their completion goes with the roster row.
     *
     * @throws \moodle_exception error_protected_history
     */
    public static function unenrol_user(int $classroomid, int $userid): bool {
        global $DB;

        // ADR-032 (framework.protect_imported_history): removing an imported learner would also delete the
        // attendance the import brought in, and a completed learner's completion goes with the roster row.
        // (An imported waiting-list place is not history: the pages delete it like any other queue entry.)
        // LRN-10 (2026-10-07): an imported row with no completion, hours or attendance, on an active classroom, is
        // not history yet and may be removed.
        $roster = $DB->get_record(self::USERS_TABLE, ['classroomid' => $classroomid, 'userid' => $userid]);
        if ($roster && (int) ($roster->completion_status ?? 0) === 1) {
            throw new \moodle_exception('error_protected_history', 'local_sentientia_classroom');
        }
        if ($roster && self::is_imported(self::USERS_TABLE, (int) $roster->id)
                && !self::imported_roster_is_pending($DB->get_record(self::TABLE, ['id' => $classroomid]), $roster)) {
            throw new \moodle_exception('error_protected_history', 'local_sentientia_classroom');
        }
        // The attendance has its own provenance. An imported learner whose roster row the import skipped,
        // and who was put on the roster since, has a roster row that is not history but attendance rows
        // that are: removing the roster row removes them too, so refuse for them as well.
        $sessionids = $DB->get_fieldset_select(self::SESSION_TABLE, 'id',
            'classroomid = :cid', ['cid' => $classroomid]);
        if (!empty($sessionids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'pah');
            $attendanceids = $DB->get_fieldset_select(self::ATTENDANCE_TABLE, 'id',
                "userid = :uid AND sessionid $insql", array_merge($inparams, ['uid' => $userid]));
            foreach ($attendanceids as $attendanceid) {
                if (self::is_imported(self::ATTENDANCE_TABLE, (int) $attendanceid)) {
                    throw new \moodle_exception('error_protected_history', 'local_sentientia_classroom');
                }
            }
        }

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
     * @param bool $callerscope ADR-031: only learners in the caller's tenant
     *                          (see roster_scope()); web services pass true
     * @return array  Each row: id (rosterid), userid, firstname, lastname,
     *                email, enrolled_at, optional open_employeeid/designation.
     */
    public static function get_enrolled_users(int $classroomid, string $search = '',
                                              string $sort = 'lastname', string $sortdir = 'ASC',
                                              int $offset = 0, int $limit = 100,
                                              bool $callerscope = false): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return [];
        }

        $cols = $DB->get_columns('user');
        $extra = '';
        if (isset($cols['open_employeeid'])) { $extra .= ', u.open_employeeid'; }
        if (isset($cols['open_designation'])) { $extra .= ', u.open_designation'; }

        [$scopesql, $scopeparams] = self::roster_scope($callerscope, 'u');
        $where = ['cu.classroomid = :cid', 'u.deleted = 0', $scopesql];
        $params = ['cid' => $classroomid] + $scopeparams;
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
                       cu.completion_status, cu.timecompleted AS completed_at, cu.hours,
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
     *
     * @param bool $callerscope ADR-031: count only learners in the caller's
     *                          tenant, matching get_enrolled_users()
     */
    public static function count_enrolled_filtered(int $classroomid, string $search = '',
                                                   bool $callerscope = false): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return 0;
        }

        [$scopesql, $scopeparams] = self::roster_scope($callerscope, 'u');
        $where = ['cu.classroomid = :cid', 'u.deleted = 0', $scopesql];
        $params = ['cid' => $classroomid] + $scopeparams;
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
    // Imported history readers (ADR-032, classroom code fix 12)
    // ═══════════════════════════════════════════════════════════════════
    //
    // What the BizLMS import brought in that no page showed: every trainer of a
    // classroom, the courses it is linked to, and a learner's own classrooms. The
    // pages that show them sit behind sentientia.classroom.import_history (default
    // OFF); these functions only read.

    /**
     * Every trainer of a classroom: the primary trainer first, then the others (the classroom's trainers
     * table). Users that no longer exist or are deleted are left out.
     *
     * Not tenant-scoped on purpose (accepted in review round 1): the caller has already passed
     * require_classroom_access() for this classroom, the names are those its own tenant attached, and the
     * primary trainer's name has always been shown the same way. Filtering by the trainer's own open_path
     * would hide a trainer whose user has no path, which an imported user may not have.
     *
     * @param int $classroomid
     * @return \stdClass[] user rows, the primary trainer first
     */
    public static function get_trainers(int $classroomid): array {
        global $DB;

        $userids = [];
        $primary = (int) $DB->get_field(self::TABLE, 'trainerid', ['id' => $classroomid]);
        if ($primary > 0) {
            $userids[] = $primary;
        }
        if ($DB->get_manager()->table_exists(self::TRAINERS_TABLE)) {
            $others = $DB->get_records_select(self::TRAINERS_TABLE, 'classroomid = :cid',
                ['cid' => $classroomid], 'id ASC', 'id, trainerid');
            foreach ($others as $other) {
                $userids[] = (int) $other->trainerid;
            }
        }
        $userids = array_values(array_unique(array_filter($userids)));
        if (!$userids) {
            return [];
        }
        $users = $DB->get_records_list('user', 'id', $userids);
        $out = [];
        foreach ($userids as $userid) {
            if (isset($users[$userid]) && empty($users[$userid]->deleted)) {
                $out[] = $users[$userid];
            }
        }
        return $out;
    }

    /**
     * The courses a classroom is linked to.
     *
     * @param int $classroomid
     * @return \stdClass[] rows with id, fullname and visible of each course, by name
     */
    public static function get_linked_courses(int $classroomid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists(self::COURSES_TABLE)) {
            return [];
        }
        return array_values($DB->get_records_sql(
            "SELECT c.id, c.fullname, c.visible
               FROM {" . self::COURSES_TABLE . "} cc
               JOIN {course} c ON c.id = cc.courseid
              WHERE cc.classroomid = :cid
           ORDER BY c.fullname ASC, c.id ASC", ['cid' => $classroomid]));
    }

    /**
     * The classrooms a learner is on the roster of, newest training first.
     *
     * This is the learner's OWN data, so it is not tenant-scoped: a person sees where they were enrolled. A
     * draft classroom and a hidden one are not shown to the people on it.
     *
     * @param int $userid
     * @return \stdClass[] id, name, location, status, trainingstart, trainingend, completion_status,
     *         completed_at, hours and enrolled_at of each
     */
    public static function get_user_classrooms(int $userid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists(self::USERS_TABLE)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal(
            [self::STATUS_CANCELLED, self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_ON_HOLD],
            SQL_PARAMS_NAMED, 'ucst');
        return array_values($DB->get_records_sql(
            "SELECT c.id, c.name, c.location, c.status, c.trainingstart, c.trainingend,
                    cu.completion_status, cu.timecompleted AS completed_at, cu.hours,
                    cu.timecreated AS enrolled_at
               FROM {" . self::USERS_TABLE . "} cu
               JOIN {" . self::TABLE . "} c ON c.id = cu.classroomid
              WHERE cu.userid = :uid AND c.visible = 1 AND c.status $insql
           ORDER BY COALESCE(c.trainingstart, c.timecreated) DESC, c.id DESC",
            ['uid' => $userid] + $inparams));
    }

    /**
     * The sessions of some classrooms with one learner's attendance at each.
     *
     * @param int $userid
     * @param int[] $classroomids
     * @return array<int, \stdClass[]> classroom id => sessions (id, title, starttime, endtime, location,
     *         attendance), oldest first; attendance is null when nobody marked the learner
     */
    public static function get_user_sessions(int $userid, array $classroomids): array {
        global $DB;

        $classroomids = array_values(array_unique(array_filter(array_map('intval', $classroomids))));
        if (!$classroomids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($classroomids, SQL_PARAMS_NAMED, 'usc');
        $rows = $DB->get_records_sql(
            "SELECT s.id, s.classroomid, s.title, s.starttime, s.endtime, s.location, a.status AS attendance
               FROM {" . self::SESSION_TABLE . "} s
          LEFT JOIN {" . self::ATTENDANCE_TABLE . "} a ON a.sessionid = s.id AND a.userid = :uid
              WHERE s.classroomid $insql
           ORDER BY s.starttime ASC, s.id ASC", ['uid' => $userid] + $inparams);
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->classroomid][] = $row;
        }
        return $out;
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
        global $DB;

        self::assert_valid_attendance_status($status);
        $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], 'id', MUST_EXIST);

        self::write_mark($sessionid, $userid, $status, $notes, 0);
        return $status;
    }

    /**
     * @throws \moodle_exception invalidattendancestatus
     */
    private static function assert_valid_attendance_status(int $status): void {
        $valid = [self::ATT_ABSENT, self::ATT_PRESENT, self::ATT_LATE, self::ATT_EXCUSED];
        if (!in_array($status, $valid, true)) {
            throw new \moodle_exception('invalidattendancestatus', 'local_sentientia_classroom');
        }
    }

    /**
     * Would writing $status over $row wipe out a newer mark somebody else made?
     *
     * The attendance grid sends an Absent only for a learner the trainer touched. If a
     * learner scanned the QR code (or another trainer marked them) after the grid was
     * loaded, that Save must not turn the newer mark back into Absent, so $loadedat is the
     * time the grid was loaded. A mark is kept, not overwritten, when ALL of these hold:
     * $loadedat is set, the trainer is sending Absent, the stored row is not Absent, it
     * was written at or after $loadedat, and it was written by somebody other than the
     * current user (the trainer's own earlier Save is never "newer"). Any other change, a
     * deliberate Late or Excused over a newer row included, is written.
     *
     * Used twice per write: on the row read just before the write, and again on the row
     * that won when the insert lost the race to a QR scan (write_attendance_row()).
     *
     * @param \stdClass|null $row the stored row, or null when there is none
     * @param int $status the status the trainer is sending
     * @param int $loadedat unix time the grid was loaded; 0 turns the guard off
     * @return bool true when the stored row must be kept as it is
     */
    private static function keeps_newer_mark(?\stdClass $row, int $status, int $loadedat): bool {
        global $USER;

        return $row !== null
            && $loadedat > 0
            && $status === self::ATT_ABSENT
            && (int) $row->status !== self::ATT_ABSENT
            && (int) $row->timemodified >= $loadedat
            && (int) $row->markedby !== (int) ($USER->id ?? 0);
    }

    /**
     * Write one trainer mark, unless it would wipe out a newer mark somebody else made.
     *
     * See keeps_newer_mark() for the rule. The row is read immediately before it is
     * written, so the window for another writer to slip in between is one statement, not
     * the length of the whole grid Save; and the rule is applied again to the row that
     * wins if the insert is refused (write_attendance_row()), so even that window cannot
     * turn a learner's scan back into Absent.
     *
     * @param int $loadedat unix time the grid was loaded; 0 turns the guard off
     * @return bool true when the row was written, false when a newer mark was kept
     */
    private static function write_mark(int $sessionid, int $userid, int $status, string $notes,
                                        int $loadedat): bool {
        global $DB;

        $existing = $DB->get_record(self::ATTENDANCE_TABLE, ['sessionid' => $sessionid, 'userid' => $userid]);
        $existing = $existing ?: null;
        if (self::keeps_newer_mark($existing, $status, $loadedat)) {
            return false;
        }
        return self::write_attendance_row($sessionid, $userid, $status, $notes, $existing, $loadedat);
    }

    /**
     * Upsert the attendance row, given the row the caller just read (null = none).
     *
     * A QR scan can insert the row between the caller's read and this insert. The unique
     * (sessionid, userid) index then refuses the insert. That must not fail the save (and,
     * inside bulk_mark_attendance()'s transaction, roll back every other mark in it), so
     * the row that won is read again. The keep-the-newer-mark rule is applied to THAT row:
     * if it is the learner's scan and the trainer is sending Absent for a grid that was
     * loaded before it, the scan stands and false is returned; otherwise the row is
     * updated. Moodle's database layer rolls a failed statement back on its own (and the
     * mysqli layer runs transactions at READ COMMITTED, so the re-read sees the row that
     * won), so the surrounding transaction stays usable.
     *
     * @param \stdClass|null $existing the stored row, or null when the caller found none
     * @param int $loadedat unix time the grid was loaded; 0 turns the keep rule off
     * @return bool true when the row was written, false when a newer mark was kept
     * @throws \dml_write_exception when the insert fails and no row exists (not the index race)
     */
    private static function write_attendance_row(int $sessionid, int $userid, int $status,
                                                  string $notes, ?\stdClass $existing,
                                                  int $loadedat = 0): bool {
        global $DB, $USER;

        $now = time();
        $markedby = (int) ($USER->id ?? 0);

        if (!$existing) {
            try {
                $DB->insert_record(self::ATTENDANCE_TABLE, (object) [
                    'sessionid'    => $sessionid,
                    'userid'       => $userid,
                    'status'       => $status,
                    'markedby'     => $markedby,
                    'notes'        => $notes,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
                return true;
            } catch (\dml_write_exception $e) {
                $existing = $DB->get_record(self::ATTENDANCE_TABLE,
                    ['sessionid' => $sessionid, 'userid' => $userid]);
                if (!$existing) {
                    throw $e;   // not the unique-index race: a real write failure.
                }
                // The row that won may be a learner's scan the trainer's grid never saw.
                if (self::keeps_newer_mark($existing, $status, $loadedat)) {
                    return false;
                }
            }
        }

        $existing->status       = $status;
        $existing->markedby     = $markedby;
        $existing->notes        = $notes;
        $existing->timemodified = $now;
        $DB->update_record(self::ATTENDANCE_TABLE, $existing);
        return true;
    }

    /** record_qr_attendance(): a new Present row was written. */
    public const SCAN_RECORDED = 'recorded';
    /** record_qr_attendance(): the learner already has a mark (any status) for this session; nothing was written. */
    public const SCAN_ALREADY = 'already';
    /** record_qr_attendance(): no such session (or its classroom is gone) in the Sentientia tables. */
    public const SCAN_NO_SESSION = 'nosession';
    /** record_qr_attendance(): the learner is not on the classroom roster. */
    public const SCAN_NOT_ENROLLED = 'notenrolled';
    /** record_qr_attendance(): the classroom is cancelled; nothing was written. */
    public const SCAN_CANCELLED = 'cancelled';
    /** record_qr_attendance(): the scan came before the window opened; nothing was written. */
    public const SCAN_TOO_EARLY = 'tooearly';
    /** record_qr_attendance(): the scan came after the window closed (or the session has no usable time); nothing was written. */
    public const SCAN_TOO_LATE = 'toolate';

    /** Note stored on a row the QR flow writes, so a trainer can tell it from a hand-marked one. */
    private const QR_NOTE = 'Marked by QR scan';

    /** How long before a session starts, and after it ends, a QR scan still counts: 30 minutes. */
    public const SCAN_GRACE = 30 * MINSECS;

    /** Config (plugin) name and key of the per-site secret the QR tokens are signed with. */
    private const QR_SECRET_COMPONENT = 'local_sentientia_classroom';
    private const QR_SECRET_KEY = 'qrsecret';

    /**
     * The per-site secret QR tokens are signed with, created on first use.
     *
     * It is a random 64-character value kept in the plugin's config (no schema, no
     * version bump) and never shown in any setting. It replaces $CFG->passwordsaltmain,
     * which Moodle does not create on a new install (config-dist.php: "no longer used in
     * new installations"), where the old token was a plain sha256 of the session id and
     * the hour that anyone could work out. To rotate it, delete the config row: every
     * QR code on screen stops working and the next page view makes a new secret.
     */
    private static function qr_secret(): string {
        $secret = (string) get_config(self::QR_SECRET_COMPONENT, self::QR_SECRET_KEY);
        if ($secret === '') {
            set_config(self::QR_SECRET_KEY, random_string(64), self::QR_SECRET_COMPONENT);
            $secret = (string) get_config(self::QR_SECRET_COMPONENT, self::QR_SECRET_KEY);
        }
        return $secret;
    }

    /**
     * The QR token for a session, for the hour that contains $time (rotates hourly, in the
     * server's PHP timezone, which is also the clock the trainer page's countdown uses).
     *
     * @param int $sessionid
     * @param int $time a unix time; time() for the token to show now
     * @return string 64 hex characters
     */
    public static function qr_token(int $sessionid, int $time): string {
        return hash_hmac('sha256', $sessionid . '|' . date('Y-m-d-H', $time), self::qr_secret());
    }

    /**
     * Whether $token is this session's token for the current hour or the previous one (the
     * grace period that lets a scan started just before the hour finish just after it).
     * A token for another session, an older hour, or one signed without the site secret
     * is refused.
     *
     * @param int $sessionid
     * @param string $token what the learner's scan URL carried
     * @param int|null $now unix time to check against (tests); null = now
     */
    public static function qr_token_is_valid(int $sessionid, string $token, ?int $now = null): bool {
        if ($token === '') {
            return false;
        }
        $now = $now ?? time();
        // Compare both hours (no early exit) so the answer does not depend on which one matched.
        $current = hash_equals(self::qr_token($sessionid, $now), $token);
        $previous = hash_equals(self::qr_token($sessionid, $now - HOURSECS), $token);
        return $current || $previous;
    }

    /**
     * When a QR scan counts for a session: from SCAN_GRACE before it starts to SCAN_GRACE
     * after it ends.
     *
     * The end is the session's endtime. A session without a usable one (none, or not after
     * the start) ends at the end of the day it starts on (the table keeps no duration).
     * One with no start either uses its sessiondate, and then the whole day counts.
     *
     * @param \stdClass $session a row of the Sentientia sessions table
     * @return int[]|null [opens, closes] as unix times, or null when the session has no
     *                    time at all (no start and no date): nothing can be scanned for it
     */
    public static function scan_window_for(\stdClass $session): ?array {
        $start = (int) ($session->starttime ?? 0);
        $end = (int) ($session->endtime ?? 0);
        $day = $start > 0 ? $start : (int) ($session->sessiondate ?? 0);
        if ($day <= 0) {
            return null;
        }
        $endofday = usergetmidnight($day) + DAYSECS - 1;
        if ($start <= 0) {
            $start = usergetmidnight($day);
            $end = $endofday;
        } else if ($end <= $start) {
            $end = $endofday;
        }
        return [$start - self::SCAN_GRACE, $end + self::SCAN_GRACE];
    }

    /**
     * scan_window_for() for a session id (qr_scan.php shows the times when it refuses a scan).
     *
     * @return int[]|null [opens, closes], or null when the session does not exist or has no time
     */
    public static function get_scan_window(int $sessionid): ?array {
        global $DB;
        $session = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid]);
        return $session ? self::scan_window_for($session) : null;
    }

    /**
     * Record a learner's own attendance from a QR scan (qr_scan.php).
     *
     * The page has already checked the login and the rotating QR token. This
     * does the rest, against the Sentientia tables only: the session and its
     * classroom must exist, the classroom must lie in the learner's tenant
     * (ADR-031), and the learner must be on the classroom roster. The row it
     * writes is the one get_session_attendance() reads back: same table, same
     * (sessionid, userid) key, status ATT_PRESENT.
     *
     * The checks run in this order, and the first that fails decides the answer:
     *  1. no such session or classroom: SCAN_NO_SESSION;
     *  2. the classroom is in another tenant: error_outoftenant (an exception);
     *  3. the learner is not on the roster: SCAN_NOT_ENROLLED;
     *  4. the classroom is cancelled (STATUS_CANCELLED): SCAN_CANCELLED. A session has no
     *     status of its own in the schema, so the classroom's is the only cancel flag;
     *  5. the learner already has a mark of ANY status: SCAN_ALREADY;
     *  6. the scan time is outside scan_window_for(): SCAN_TOO_EARLY or SCAN_TOO_LATE.
     * Only then is a Present row written. A stranger is therefore never told the classroom
     * is cancelled, and a learner who already has a mark is told so whatever the time.
     *
     * The trainer's mark wins (owner decision 2026-09-30): a scan never changes an existing
     * attendance row, whatever its status, Absent included. An Absent only exists because the
     * trainer set it (the attendance grid sends a mark only for a learner the trainer touched,
     * so a learner nobody touched has no row and can still scan), and a learner must not be
     * able to overturn it by opening a forwarded QR link. The learner's own repeat scan is
     * SCAN_ALREADY too. Two writers landing together (a double tap, or a trainer grid save)
     * hit the unique (sessionid, userid) index; the loser reads the row that won and answers
     * SCAN_ALREADY.
     *
     * Deliberately does not use get_session(): that falls back to the legacy
     * {local_classroom_sessions} table, and an id from that table has no
     * Sentientia session behind it, so a row written against it would never
     * show up in the attendance grid.
     *
     * @param int $sessionid
     * @param int $userid    the learner who scanned (the current user)
     * @param int|null $now  unix time of the scan (tests); null = now
     * @return string one of the SCAN_* constants
     * @throws \moodle_exception error_outoftenant when the classroom is outside the learner's tenant
     */
    public static function record_qr_attendance(int $sessionid, int $userid, ?int $now = null): string {
        global $DB;

        $now = $now ?? time();

        $session = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid]);
        $classroom = $session ? $DB->get_record(self::TABLE, ['id' => $session->classroomid]) : false;
        if (!$session || !$classroom) {
            return self::SCAN_NO_SESSION;
        }

        // ADR-031: the classroom must be in the scanning learner's tenant.
        self::assert_classroom_in_scope($classroom, $userid);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)
                || !$DB->record_exists(self::USERS_TABLE,
                    ['classroomid' => (int) $classroom->id, 'userid' => $userid])) {
            return self::SCAN_NOT_ENROLLED;
        }

        if ((int) $classroom->status === self::STATUS_CANCELLED) {
            return self::SCAN_CANCELLED;
        }

        // Any existing mark, of any status, stands: the trainer's mark wins.
        if ($DB->record_exists(self::ATTENDANCE_TABLE, ['sessionid' => $sessionid, 'userid' => $userid])) {
            return self::SCAN_ALREADY;
        }

        $window = self::scan_window_for($session);
        if ($window === null || $now > $window[1]) {
            return self::SCAN_TOO_LATE;
        }
        if ($now < $window[0]) {
            return self::SCAN_TOO_EARLY;
        }

        try {
            $DB->insert_record(self::ATTENDANCE_TABLE, (object) [
                'sessionid'    => $sessionid,
                'userid'       => $userid,
                'status'       => self::ATT_PRESENT,
                'markedby'     => $userid,
                'notes'        => self::QR_NOTE,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        } catch (\dml_write_exception $e) {
            // The other half of a double tap, or a trainer grid save, got there first.
            // Whatever it wrote stands.
            if ($DB->record_exists(self::ATTENDANCE_TABLE, ['sessionid' => $sessionid, 'userid' => $userid])) {
                return self::SCAN_ALREADY;
            }
            throw $e;
        }
        return self::SCAN_RECORDED;
    }

    /**
     * Bulk mark attendance for a session (the attendance grid's Save).
     *
     * @param int $sessionid
     * @param array $marks  [['userid' => int, 'status' => int, 'notes' => string], ...]
     * @param int $loadedat unix time the grid was loaded, so a Save cannot wipe out a mark made
     *                      after that (a QR scan, another trainer): see write_mark(). 0 = no
     *                      guard, every mark is written (what this method did before).
     * @param int|null $kept set to how many marks were NOT written because a newer mark stood
     * @param int[]|null $keptusers set to [userid => status] of the rows that were kept, so
     *                      the caller can show the learner's real mark
     * @param array|null $newermarks set to [userid => status] of every row of the session that
     *                      somebody other than the current user wrote at or after $loadedat,
     *                      read inside the same transaction as the writes (see
     *                      get_marks_by_others_since()). The grid shows these before it takes
     *                      the save time as its new load time, so a learner who scanned while
     *                      the trainer was ticking other rows is not silently overwritten by a
     *                      later Save. It includes the kept rows.
     * @param bool $callerscope ADR-031: limit $newermarks to learners in the caller's tenant
     *                      (the web service passes true, like the grid page)
     * @return int Count of rows upserted.
     */
    public static function bulk_mark_attendance(int $sessionid, array $marks, int $loadedat = 0,
                                                 ?int &$kept = null, ?array &$keptusers = null,
                                                 ?array &$newermarks = null, bool $callerscope = false): int {
        global $DB;

        $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], 'id', MUST_EXIST);

        $count = 0;
        $kept = 0;
        $keptusers = [];
        $newermarks = [];
        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($marks as $m) {
                $uid = (int) ($m['userid'] ?? 0);
                $st  = (int) ($m['status'] ?? self::ATT_ABSENT);
                $notes = (string) ($m['notes'] ?? '');
                if ($uid <= 0) { continue; }
                self::assert_valid_attendance_status($st);
                if (self::write_mark($sessionid, $uid, $st, $notes, $loadedat)) {
                    $count++;
                } else {
                    $kept++;
                    $keptusers[$uid] = (int) $DB->get_field(self::ATTENDANCE_TABLE, 'status',
                        ['sessionid' => $sessionid, 'userid' => $uid]);
                }
            }
            // After the writes, in the same transaction: whatever anyone else marked since the
            // grid was loaded is now known, and about to be shown to the trainer.
            $newermarks = self::get_marks_by_others_since($sessionid, $loadedat, $callerscope);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $newermarks = [];
            $tx->rollback($e);
        }
        return $count;
    }

    /** Seconds before the grid's load time from which get_marks_by_others_since() reads. */
    public const NEWER_MARK_SLACK = 2;

    /**
     * The rows of a session that somebody other than the current user wrote at or after $since.
     *
     * A grid Save sends only the rows the trainer touched, so a learner the trainer did not
     * touch can scan the QR code between the grid load and the Save without the Save ever
     * seeing them. Returning them lets the grid show them (and take the save time as its new
     * load time) with the trainer having actually seen every mark made before it; without
     * this, the next Save would treat the scan as old news and could overwrite it. Only
     * learners on the classroom roster count, like the grid.
     *
     * The read starts NEWER_MARK_SLACK seconds before $since. A scan stamps its
     * `timemodified` with time() when it begins and commits a moment later; on a slow
     * request that commit can land after the grid (or the previous Save) read the table, with
     * a stamp a second or two older than the load time the page then holds. Read strictly from
     * $since, that scan would be neither on screen nor reported by any later Save. With the
     * slack it is reported on the next Save. The price is that a mark the grid already showed
     * can be handed back once more; the grid then shows the status it already shows.
     *
     * @param int $since unix time (normally the grid's load time); 0 or less returns nothing
     * @param bool $callerscope ADR-031: only learners in the caller's tenant (roster_scope())
     * @return int[] [userid => status]
     */
    public static function get_marks_by_others_since(int $sessionid, int $since, bool $callerscope = false): array {
        global $DB, $USER;

        if ($since <= 0 || !$DB->get_manager()->table_exists(self::USERS_TABLE)) {
            return [];
        }
        $session = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], 'id, classroomid', MUST_EXIST);

        [$scopesql, $scopeparams] = self::roster_scope($callerscope, 'u');
        $sql = "SELECT a.userid, a.status
                  FROM {" . self::ATTENDANCE_TABLE . "} a
                  JOIN {" . self::USERS_TABLE . "} cu ON cu.userid = a.userid AND cu.classroomid = :cid
                  JOIN {user} u ON u.id = a.userid
                 WHERE a.sessionid = :sid AND a.timemodified >= :since AND a.markedby <> :me
                   AND $scopesql
              ORDER BY a.userid ASC";
        $rows = $DB->get_records_sql($sql, [
            'cid' => (int) $session->classroomid,
            'sid' => $sessionid,
            'since' => $since - self::NEWER_MARK_SLACK,
            'me' => (int) ($USER->id ?? 0),
        ] + $scopeparams);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->userid] = (int) $r->status;
        }
        return $out;
    }

    /**
     * Get attendance for a session — every roster member, joined with their
     * attendance row (or default ABSENT if not yet marked).
     *
     * @param int  $sessionid
     * @param bool $callerscope ADR-031: only learners in the caller's tenant
     *                          (see roster_scope()); attendance.php and the
     *                          web service pass true, so the grid - and the
     *                          marks its Save sends - never name another
     *                          tenant's learner
     * @return array  Each row: userid, firstname, lastname, email, status,
     *                status_label, marked_at, notes, has_mark. A learner with no
     *                stored row reads as status Absent with has_mark false: the grid
     *                shows them as Absent but only writes a row once the trainer
     *                changes them.
     */
    public static function get_session_attendance(int $sessionid, bool $callerscope = false): array {
        global $DB;
        $dbman = $DB->get_manager();

        $session = $DB->get_record(self::SESSION_TABLE, ['id' => $sessionid], '*', MUST_EXIST);

        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return [];
        }

        [$scopesql, $scopeparams] = self::roster_scope($callerscope, 'u');
        $sql = "SELECT u.id AS userid, u.firstname, u.lastname, u.email,
                       COALESCE(a.status, 0) AS status,
                       a.timemodified AS marked_at,
                       a.notes AS notes
                  FROM {" . self::USERS_TABLE . "} cu
                  JOIN {user} u ON u.id = cu.userid
             LEFT JOIN {" . self::ATTENDANCE_TABLE . "} a
                       ON a.sessionid = :sid AND a.userid = cu.userid
                 WHERE cu.classroomid = :cid AND u.deleted = 0 AND $scopesql
              ORDER BY u.lastname ASC, u.firstname ASC";
        $rows = $DB->get_records_sql($sql, [
            'sid' => $sessionid,
            'cid' => (int) $session->classroomid,
        ] + $scopeparams);

        $labels = [
            self::ATT_ABSENT  => 'Absent',
            self::ATT_PRESENT => 'Present',
            self::ATT_LATE    => 'Late',
            self::ATT_EXCUSED => 'Excused',
        ];
        foreach ($rows as $r) {
            $r->has_mark = $r->marked_at !== null;
            $r->status_label = $labels[(int) $r->status] ?? 'Absent';
            $r->marked_at_human = $r->marked_at
                ? userdate((int) $r->marked_at, '%d %b %Y %H:%M')
                : '';
        }
        return $rows;
    }
}
