<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

/**
 * Program manager — CRUD for certification programs.
 *
 * Handles top-level program operations. Levels and course assignments
 * are managed via separate methods within this class for now (can be
 * extracted to level_manager.php as the feature grows).
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_manager {

    private const TABLE          = 'local_sentientia_programs';
    private const LEVELS_TABLE   = 'local_sentientia_programs_levels';
    private const COURSES_TABLE  = 'local_sentientia_programs_courses';
    private const USERS_TABLE    = 'local_sentientia_programs_users';

    /** Status values matching install.xml. */
    public const STATUS_DRAFT    = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_ARCHIVED = 2;

    /**
     * Get a program by ID.
     */
    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADR-031 (2026-09-25) — tenant guard for everything named by id
    // ═══════════════════════════════════════════════════════════════════
    //
    // :view, :enrol, :update and :create default to the manager archetype,
    // and every tenant admin holds a manager-archetype role at system
    // context. Until 2026-09-25 the web services and forms checked only the
    // capability and then acted on whatever programid/levelid the client
    // sent: any tenant admin could read every tenant's program rosters
    // (names, emails, employee ids) and archive, restructure, enrol into or
    // unenrol from every tenant's programs. A capability says WHAT; these
    // say WHERE. Call one after require_capability() wherever an id comes in.

    /**
     * Refuse unless the program is in the caller's tenant.
     *
     * Cross-tenant callers (site admin, local/sentientia_platform:crosstenant)
     * pass. Anyone else needs a resolvable tenant AND a program whose
     * open_path lies inside it. A program with no open_path is
     * cross-tenant-only: every tenant's list hides it (path_filter never
     * matches NULL), so it must not open by id either -
     * tenant::require_path_access() on its own lets '' through.
     *
     * @param \stdClass $program a record carrying open_path
     * @param int|null  $userid  the caller; defaults to $USER
     * @throws \moodle_exception error_outoftenant
     */
    public static function assert_program_in_scope(\stdClass $program, ?int $userid = null): void {
        global $DB, $USER;
        $current = (int) ($USER->id ?? 0);
        $userid = $userid ?? $current;
        if (\local_sentientia_platform\tenant::is_cross_tenant($userid)) {
            return;
        }
        $caller = ($userid === $current) ? $USER
            : $DB->get_record('user', ['id' => $userid], 'id, open_path');
        $path = trim((string) ($program->open_path ?? ''));
        if ($path === '' || !$caller
                || \local_sentientia_platform\tenant::scope_path($caller) === null) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access($path,
            $userid === $current ? null : $userid);
    }

    /**
     * Load a program by id and refuse unless it is in the caller's tenant.
     *
     * @throws \moodle_exception error_outoftenant (or dml_missing_record_exception)
     */
    public static function require_program_access(int $programid, ?int $userid = null): \stdClass {
        global $DB;
        $program = $DB->get_record(self::TABLE, ['id' => $programid], '*', MUST_EXIST);
        self::assert_program_in_scope($program, $userid);
        return $program;
    }

    /**
     * Load a level and its program; refuse unless the program is in the
     * caller's tenant.
     *
     * @return \stdClass[] [$level, $program]
     * @throws \moodle_exception error_outoftenant (or dml_missing_record_exception)
     */
    public static function require_level_access(int $levelid): array {
        global $DB;
        $level = $DB->get_record(self::LEVELS_TABLE, ['id' => $levelid], '*', MUST_EXIST);
        $program = self::require_program_access((int) $level->programid);
        return [$level, $program];
    }

    /**
     * Refuse unless every named user is in the caller's tenant (ADR-031: a
     * write that names a user checks the target). One query for any number
     * of ids. Cross-tenant callers pass.
     *
     * @param int[] $userids
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_users_in_scope(array $userids): void {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids),
            fn($id) => $id > 0)));
        if (empty($userids)) {
            return;
        }
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null || $scope === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'prsu');
        [$tsql, $targs] = \local_sentientia_platform\tenant::path_descendant_filter(
            $scope, 'u', 'open_path', 'prst');
        $inscope = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user} u WHERE u.id $insql AND $tsql",
            $inparams + $targs);
        if ($inscope !== count($userids)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
    }

    /**
     * Refuse removing $userid from a program the caller has already been
     * proved to own (require_program_access()), unless the user is either in
     * the caller's tenant or ALREADY on that program's roster.
     *
     * Removing someone from your own program does not reach into another
     * tenant, so a scoped admin may clean a legacy out-of-tenant or pathless
     * learner off their own roster (one a site admin, an approval flow, the
     * pre-ADR-031 cohort path or fail-open put there). Naming anyone else
     * keeps the ADR-031 rule 5 refusal. Cross-tenant callers pass.
     *
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_unenrol_target(int $programid, int $userid): void {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        if ($DB->get_manager()->table_exists(self::USERS_TABLE)
                && $DB->record_exists(self::USERS_TABLE, ['programid' => $programid, 'userid' => $userid])) {
            return;
        }
        \local_sentientia_platform\tenant::require_same_tenant_user($userid);
    }

    /**
     * WHERE fragment over {user} $alias for a ROSTER READ (who is on a
     * program).
     *
     * ADR-031 follow-up (2026-09-25): require_program_access() proves the
     * program is the caller's, but its roster can still hold other tenants'
     * or pathless learners - enrolled by a site admin, an approval flow, or
     * the pre-fix cohort path and no-tenant fail-open - and list_program_users
     * listed their names, emails, employee ids and designations to the
     * tenant admin. $callerscope = true limits the read to the caller's
     * tenant (path_filter: '1=1' cross-tenant, '1=0' no tenant). The web
     * service passes true; the default false keeps library callers (cron,
     * privacy, unit tests with no user) unchanged.
     *
     * @return array{0: string, 1: array}
     */
    public static function roster_scope(bool $callerscope, string $alias = 'u'): array {
        return $callerscope ? \local_sentientia_platform\tenant::path_filter($alias) : ['1=1', []];
    }

    /**
     * WHERE fragment for the courses the caller may put on a level.
     *
     * Cross-tenant: every course. Scoped: courses in their tenant tree,
     * legacy courses with no open_path (the same tolerance
     * local_sentientia_courses' own list applies), and courses shared to
     * their tenant. No tenant: nothing. Until 2026-09-25 the picker listed
     * up to 5000 courses from every tenant, hidden ones included.
     *
     * @param string $alias course table alias
     * @return array{0: string, 1: array}
     */
    public static function course_scope_sql(string $alias = 'c'): array {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return ['1=1', []];
        }
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null || $scope === '') {
            return ['1=0', []];
        }
        [$sql, $params] = \local_sentientia_platform\tenant::path_descendant_filter(
            $scope, $alias, 'open_path', 'prcs', true);
        if ($DB->get_manager()->table_exists('local_sentientia_courses_tenant_share')) {
            $sql = "($sql OR EXISTS (SELECT 1 FROM {local_sentientia_courses_tenant_share} prsh
                                     WHERE prsh.courseid = {$alias}.id
                                       AND prsh.tenant_id = :prcstenant
                                       AND prsh.status = :prcsstatus))";
            $params['prcstenant'] = (int) substr($scope, 1);
            $params['prcsstatus'] = 'active';
        }
        return [$sql, $params];
    }

    /**
     * Refuse unless every named course is one course_scope_sql() allows.
     *
     * @param int[] $courseids
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_courses_in_scope(array $courseids): void {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids),
            fn($id) => $id > 0)));
        if (empty($courseids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'prcc');
        [$csql, $cparams] = self::course_scope_sql('c');
        $inscope = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {course} c WHERE c.id $insql AND $csql",
            $inparams + $cparams);
        if ($inscope !== count($courseids)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
    }

    /**
     * The open_path a program should carry when the CALLER saves it with org
     * $costcenterid, refusing an org outside their tenant.
     *
     * Cross-tenant callers keep the old behaviour: the org's path, or null
     * for "no specific organisation". A scoped caller may only pick an org in
     * their own tenant, and "no specific organisation" stamps their tenant
     * root - so no tenant user can create a program no tenant owns (which
     * every tenant could then open by id), or move one into another tenant.
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
        $orgpath = $org ? rtrim((string) $org->path, '/') : '';
        if ($orgpath === '' || ($orgpath !== $scope && strpos($orgpath, $scope . '/') !== 0)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return (string) $org->path;
    }

    /**
     * Count programs, optionally tenant-scoped.
     */
    public static function count_programs(string $pathfilter = ''): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) {
            return 0;
        }
        if (!empty($pathfilter)) {
            return $DB->count_records_select(self::TABLE, "open_path LIKE :p", ['p' => $pathfilter]);
        }
        return $DB->count_records(self::TABLE);
    }

    /**
     * Count levels for a program.
     */
    public static function count_levels(int $programid): int {
        global $DB;
        return $DB->count_records(self::LEVELS_TABLE, ['programid' => $programid]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Create a new program.
     *
     * @param object $data  name, description, costcenterid, completion_required
     * @return int  New program ID
     * @throws \moodle_exception
     */
    public static function create(object $data, ?string $fallbackpath = null): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_programs');
        }

        $record = new \stdClass();
        $record->name                = trim($data->name);
        $record->description         = $data->description ?? '';
        // P1 #9 (2026-05-16) — descriptionformat + start/end dates.
        $record->descriptionformat   = (int) ($data->descriptionformat ?? FORMAT_HTML);
        $record->costcenterid        = (int) ($data->costcenterid ?? 0);
        $record->status              = (int) ($data->status ?? self::STATUS_DRAFT);
        $record->visible             = isset($data->visible) ? (int) $data->visible : 1;
        $record->completion_required = isset($data->completion_required) ? (int) $data->completion_required : 1;
        $record->startdate           = !empty($data->startdate) ? (int) $data->startdate : null;
        $record->enddate             = !empty($data->enddate)   ? (int) $data->enddate   : null;
        $record->timecreated         = time();
        $record->timemodified        = time();

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }
        // ADR-031: a scoped caller's "no specific organisation" still belongs
        // to their tenant (see org_path_for_caller()); null keeps the old
        // behaviour for cross-tenant and internal callers.
        if (empty($record->open_path) && $fallbackpath !== null && $fallbackpath !== '') {
            $record->open_path = $fallbackpath;
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing program.
     */
    public static function update(int $id, object $data, ?string $fallbackpath = null): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id, 'timemodified' => time()];
        // P1 #9 (2026-05-16) — descriptionformat + start/end dates.
        $fields = ['name', 'description', 'descriptionformat',
                   'costcenterid', 'status', 'visible', 'completion_required',
                   'startdate', 'enddate'];
        foreach ($fields as $field) {
            if (isset($data->$field)) {
                if (in_array($field, ['startdate', 'enddate'], true)
                    && empty($data->$field)) {
                    $record->$field = null;
                } else {
                    $record->$field = $data->$field;
                }
            }
        }

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            // ADR-031: see create() - a scoped caller never leaves a program unowned.
            $record->open_path = $org ? $org->path : ($fallbackpath ?? '');
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Change program status.
     */
    public static function change_status(int $id, int $status): int {
        global $DB;

        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_sentientia_programs');
        }

        $DB->update_record(self::TABLE, (object) [
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
        ]);
        return $status;
    }

    /**
     * Delete a program and all its levels, course assignments, enrollments.
     */
    public static function delete(int $id): bool {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $transaction = $DB->start_delegated_transaction();
        try {
            // Get level IDs for cascade.
            $levelids = $DB->get_fieldset_select(self::LEVELS_TABLE,
                'id', 'programid = :pid', ['pid' => $id]);

            // Delete course assignments per level.
            if (!empty($levelids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($levelids, SQL_PARAMS_NAMED, 'lid');
                $DB->delete_records_select(self::COURSES_TABLE, "levelid $insql", $inparams);
            }

            // Delete levels.
            $DB->delete_records(self::LEVELS_TABLE, ['programid' => $id]);

            // Delete enrollments.
            $DB->delete_records(self::USERS_TABLE, ['programid' => $id]);

            // Delete program.
            $DB->delete_records(self::TABLE, ['id' => $id]);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }

    // ═══════════════════════════════════════════════════════════════════
    // LEVEL CRUD (G-03)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Get levels for a program in sortorder.
     */
    public static function get_levels(int $programid): array {
        global $DB;
        return $DB->get_records(self::LEVELS_TABLE, ['programid' => $programid],
            'sortorder ASC, id ASC');
    }

    /**
     * Get a single level by ID.
     */
    public static function get_level(int $levelid) {
        global $DB;
        return $DB->get_record(self::LEVELS_TABLE, ['id' => $levelid]);
    }

    /**
     * Create a level for a program. Auto-assigns next sortorder slot.
     *
     * @param int $programid
     * @param object $data  name, description, completion_required
     * @return int New level ID
     * @throws \moodle_exception
     */
    public static function create_level(int $programid, object $data): int {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $programid], 'id', MUST_EXIST);

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_programs');
        }

        // Determine next sortorder.
        $next = (int) $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), -1) + 1
               FROM {" . self::LEVELS_TABLE . "}
              WHERE programid = :pid", ['pid' => $programid]);

        $record = new \stdClass();
        $record->programid           = $programid;
        $record->name                = trim((string) $data->name);
        $record->description         = (string) ($data->description ?? '');
        $record->sortorder           = $next;
        $record->completion_required = isset($data->completion_required) ? (int) $data->completion_required : 1;
        $record->timecreated         = time();

        return $DB->insert_record(self::LEVELS_TABLE, $record);
    }

    /**
     * Update an existing level.
     */
    public static function update_level(int $levelid, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::LEVELS_TABLE, ['id' => $levelid], '*', MUST_EXIST);

        $record = (object) ['id' => $levelid];
        $fields = ['name', 'description', 'completion_required'];
        foreach ($fields as $f) {
            if (isset($data->$f)) {
                $record->$f = $data->$f;
            }
        }
        // Don't allow changing programid via update — preserved from $existing implicitly.
        $DB->update_record(self::LEVELS_TABLE, $record);
        return true;
    }

    /**
     * Delete a level. Cascades to its course assignments.
     * Reflows sortorder of remaining sibling levels to remove gaps.
     */
    public static function delete_level(int $levelid): bool {
        global $DB;

        $level = $DB->get_record(self::LEVELS_TABLE, ['id' => $levelid], '*', MUST_EXIST);
        $programid = (int) $level->programid;

        $tx = $DB->start_delegated_transaction();
        try {
            // Cascade course assignments.
            $DB->delete_records(self::COURSES_TABLE, ['levelid' => $levelid]);
            // Delete the level itself.
            $DB->delete_records(self::LEVELS_TABLE, ['id' => $levelid]);
            // Reflow sortorder for remaining siblings.
            self::reflow_levels($programid);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return true;
    }

    /**
     * Reorder levels within a program. Caller passes ordered IDs;
     * unknown/outsider IDs are silently dropped, missing IDs left at end.
     *
     * @return int Count of levels reordered.
     */
    public static function reorder_levels(int $programid, array $levelids): int {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $programid], 'id', MUST_EXIST);

        // Get the canonical set of levels for this program.
        $existing = $DB->get_fieldset_select(self::LEVELS_TABLE, 'id',
            'programid = :pid', ['pid' => $programid]);
        $existing_set = array_flip(array_map('intval', $existing));

        $next = 0;
        $count = 0;
        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($levelids as $lid) {
                $lid = (int) $lid;
                if (!isset($existing_set[$lid])) {
                    continue;   // skip outsider — sortorder counter NOT incremented
                }
                $DB->set_field(self::LEVELS_TABLE, 'sortorder', $next, ['id' => $lid]);
                unset($existing_set[$lid]);   // mark as placed
                $next++;
                $count++;
            }
            // Anything remaining in $existing_set wasn't mentioned by caller — append in id order.
            foreach (array_keys($existing_set) as $lid) {
                $DB->set_field(self::LEVELS_TABLE, 'sortorder', $next, ['id' => $lid]);
                $next++;
                $count++;
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return $count;
    }

    /**
     * Reflow sortorder for a program's levels — eliminates gaps after a
     * delete. Internal helper; called from delete_level().
     */
    private static function reflow_levels(int $programid): void {
        global $DB;
        $levels = $DB->get_records(self::LEVELS_TABLE, ['programid' => $programid],
            'sortorder ASC, id ASC', 'id');
        $i = 0;
        foreach ($levels as $level) {
            $DB->set_field(self::LEVELS_TABLE, 'sortorder', $i, ['id' => $level->id]);
            $i++;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // COURSE-PER-LEVEL CRUD (G-03)
    // ═══════════════════════════════════════════════════════════════════
    // PREREQ ENFORCEMENT (Phase F.1, 2026-05-08) — sequential progression
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Has the user completed every mandatory course in this level?
     *
     * A level is considered "completed" if every course with mandatory=1
     * has a course_completions row with timecompleted > 0 for this user.
     */
    public static function is_level_completed_by_user(int $levelid,
                                                       int $userid): bool {
        global $DB;
        $mandatory_courseids = $DB->get_fieldset_select(self::COURSES_TABLE,
            'courseid', 'levelid = :lid AND mandatory = 1',
            ['lid' => $levelid]);
        if (empty($mandatory_courseids)) {
            // Level has no mandatory courses — treat as completed.
            return true;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($mandatory_courseids,
            SQL_PARAMS_NAMED, 'mc');
        $params = array_merge($inparams, ['uid' => $userid]);
        $completed = (int) $DB->get_field_sql(
            "SELECT COUNT(DISTINCT course)
               FROM {course_completions}
              WHERE userid = :uid AND timecompleted > 0
                AND course $insql", $params);
        return $completed >= count($mandatory_courseids);
    }

    /**
     * Is the level unlocked for this user?
     *
     * Returns true iff every preceding level (lower sortorder) marked as
     * completion_required = 1 has been completed by the user. The first
     * level (smallest sortorder) is always unlocked.
     */
    public static function is_level_unlocked_for_user(int $levelid,
                                                       int $userid): bool {
        global $DB;
        $level = $DB->get_record(self::LEVELS_TABLE, ['id' => $levelid]);
        if (!$level) {
            return false;
        }
        $earlier = $DB->get_records_sql(
            "SELECT id, completion_required
               FROM {" . self::LEVELS_TABLE . "}
              WHERE programid = :pid AND sortorder < :so
           ORDER BY sortorder ASC",
            ['pid' => $level->programid, 'so' => $level->sortorder]);
        foreach ($earlier as $prev) {
            if ((int) $prev->completion_required === 1
                && !self::is_level_completed_by_user((int) $prev->id, $userid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build a user-facing program-progress view: every level annotated
     * with locked / unlocked / completed status + per-course progress.
     *
     * Returns:
     *   ['levels' => [
     *       'id', 'name', 'sortorder', 'completion_required',
     *       'locked', 'completed', 'in_progress',
     *       'mandatory_total', 'mandatory_completed',
     *       'pct',
     *       'courses' => [{courseid, fullname, completed, mandatory}]
     *     ],
     *    'current_level_id' => int|null,
     *    'overall_pct'      => 0..100,
     *    'completed_levels' => int,
     *    'total_levels'     => int]
     */
    public static function get_user_program_state(int $programid,
                                                   int $userid): array {
        $levels_raw = self::get_levels($programid);
        $levels = [];
        $first_unlocked_in_progress = null;
        $completed_levels = 0;
        $total_levels = count($levels_raw);

        foreach ($levels_raw as $lvl) {
            $courses = self::get_level_courses((int) $lvl->id);
            $mandatory_total = 0;
            $mandatory_completed = 0;
            $course_rows = [];
            foreach ($courses as $c) {
                $is_mandatory = (int) $c->mandatory === 1;
                $is_done = false;
                if ($is_mandatory) {
                    $mandatory_total++;
                    $is_done = self::user_completed_course((int) $userid,
                        (int) $c->courseid);
                    if ($is_done) $mandatory_completed++;
                } else {
                    $is_done = self::user_completed_course((int) $userid,
                        (int) $c->courseid);
                }
                $course_rows[] = [
                    'courseid'  => (int) $c->courseid,
                    'fullname'  => format_string($c->fullname),
                    'mandatory' => $is_mandatory,
                    'completed' => $is_done,
                    'viewurl'   => (new \moodle_url('/course/view.php',
                        ['id' => $c->courseid]))->out(false),
                ];
            }

            $level_completed = self::is_level_completed_by_user((int) $lvl->id,
                $userid);
            $level_unlocked = self::is_level_unlocked_for_user((int) $lvl->id,
                $userid);
            if ($level_completed) $completed_levels++;
            $in_progress = $level_unlocked && !$level_completed
                && $mandatory_completed > 0;
            if ($in_progress && $first_unlocked_in_progress === null) {
                $first_unlocked_in_progress = (int) $lvl->id;
            }

            $pct = $mandatory_total > 0
                ? (int) round(($mandatory_completed / $mandatory_total) * 100) : 0;
            $levels[] = [
                'id'                  => (int) $lvl->id,
                'name'                => format_string($lvl->name),
                'sortorder'           => (int) $lvl->sortorder,
                'completion_required' => (int) $lvl->completion_required === 1,
                'locked'              => !$level_unlocked,
                'completed'           => $level_completed,
                'in_progress'         => $in_progress,
                'mandatory_total'     => $mandatory_total,
                'mandatory_completed' => $mandatory_completed,
                'pct'                 => $pct,
                'courses'             => $course_rows,
            ];
        }

        // current_level_id = first in-progress, or first unlocked-not-completed.
        if ($first_unlocked_in_progress === null) {
            foreach ($levels as $l) {
                if (!$l['locked'] && !$l['completed']) {
                    $first_unlocked_in_progress = $l['id'];
                    break;
                }
            }
        }

        $overall_pct = $total_levels > 0
            ? (int) round(($completed_levels / $total_levels) * 100) : 0;

        return [
            'levels'           => $levels,
            'current_level_id' => $first_unlocked_in_progress,
            'overall_pct'      => $overall_pct,
            'completed_levels' => $completed_levels,
            'total_levels'     => $total_levels,
        ];
    }

    /** Helper — has user completed this course? */
    private static function user_completed_course(int $userid, int $courseid): bool {
        global $DB;
        return $DB->record_exists_select('course_completions',
            'userid = :uid AND course = :cid AND timecompleted > 0',
            ['uid' => $userid, 'cid' => $courseid]);
    }

    // ═══════════════════════════════════════════════════════════════════

    /**
     * Count courses assigned to a level.
     */
    public static function count_level_courses(int $levelid): int {
        global $DB;
        return (int) $DB->count_records(self::COURSES_TABLE, ['levelid' => $levelid]);
    }

    /**
     * Get courses assigned to a level (joined with course table).
     */
    public static function get_level_courses(int $levelid): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT lc.id, lc.courseid, lc.sortorder, lc.mandatory,
                    c.fullname, c.shortname, c.visible AS course_visible
               FROM {" . self::COURSES_TABLE . "} lc
               JOIN {course} c ON c.id = lc.courseid
              WHERE lc.levelid = :lid
           ORDER BY lc.sortorder ASC, c.fullname ASC",
            ['lid' => $levelid]);
    }

    /**
     * Bulk-assign courses to a level. Idempotent — already-assigned skipped.
     * Appends to sortorder.
     *
     * @return int Count of courses newly added.
     */
    public static function assign_courses_to_level(int $levelid, array $courseids): int {
        global $DB;

        $DB->get_record(self::LEVELS_TABLE, ['id' => $levelid], 'id', MUST_EXIST);

        $courseids = array_unique(array_filter(array_map('intval', $courseids), fn($id) => $id > 1));
        if (empty($courseids)) {
            return 0;
        }

        // Validate course existence + skip already-assigned.
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $valid_ids = $DB->get_fieldset_select('course', 'id',
            "id $insql AND id > 1", $inparams);
        if (empty($valid_ids)) {
            return 0;
        }

        [$insql2, $inparams2] = $DB->get_in_or_equal($valid_ids, SQL_PARAMS_NAMED, 'cid2');
        $existing = $DB->get_fieldset_select(self::COURSES_TABLE, 'courseid',
            "levelid = :lid AND courseid $insql2",
            array_merge($inparams2, ['lid' => $levelid]));
        $to_add = array_values(array_diff($valid_ids, $existing));
        if (empty($to_add)) {
            return 0;
        }

        // Determine starting sortorder.
        $next = (int) $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), -1) + 1
               FROM {" . self::COURSES_TABLE . "}
              WHERE levelid = :lid", ['lid' => $levelid]);

        $now = time();
        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($to_add as $cid) {
                $DB->insert_record(self::COURSES_TABLE, (object) [
                    'levelid'     => $levelid,
                    'courseid'    => (int) $cid,
                    'sortorder'   => $next++,
                    'mandatory'   => 1,
                    'timecreated' => $now,
                ]);
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return count($to_add);
    }

    /**
     * Unassign a single course from a level. No-op if not assigned.
     */
    public static function unassign_course_from_level(int $levelid, int $courseid): bool {
        global $DB;
        $DB->delete_records(self::COURSES_TABLE,
            ['levelid' => $levelid, 'courseid' => $courseid]);
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════
    // PROGRAM ENROLMENT (G-03)
    // ═══════════════════════════════════════════════════════════════════

    /** Enrolment status values. */
    public const ENROL_NEW         = 0;
    public const ENROL_INPROGRESS  = 1;
    public const ENROL_COMPLETED   = 2;

    /**
     * Count users enrolled in a program.
     */
    public static function count_enrolled(int $programid): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return 0;
        }
        return (int) $DB->count_records(self::USERS_TABLE, ['programid' => $programid]);
    }

    /**
     * Count enrolments matching a search filter (for paginated WS).
     *
     * @param bool $callerscope ADR-031: count only learners in the caller's
     *                          tenant (roster_scope()), matching get_enrolled_users()
     */
    public static function count_enrolled_filtered(int $programid, string $search = '',
                                                   bool $callerscope = false): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return 0;
        }

        [$scopesql, $scopeparams] = self::roster_scope($callerscope, 'u');
        $where = ['pu.programid = :pid', $scopesql];
        $params = ['pid' => $programid] + $scopeparams;
        if (!empty($search)) {
            $term = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':s2', false) . ' OR ' .
                $DB->sql_like('u.email', ':s3', false) . ')';
            $params['s1'] = $params['s2'] = $params['s3'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        return (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {" . self::USERS_TABLE . "} pu
                JOIN {user} u ON u.id = pu.userid
              WHERE $wheresql", $params);
    }

    /**
     * Phase F.3 (2026-05-08) — mass-enrol all members of a Moodle cohort.
     *
     * Pulls cohort_members → user IDs → delegates to enrol_users() for
     * the existing idempotent pathway.
     *
     * Cohorts are site-wide and may mix tenants. enrol_users() does NOT
     * scope users by tenant, so a caller acting for one tenant must pass
     * $scopepath (their tenant root, tenant::scope_path()): only members
     * inside it are counted and enrolled (ADR-031). Until 2026-09-25 the
     * cohort form enrolled every member, so a tenant admin could pull
     * another tenant's users into their program. Null/'' = every member
     * (cross-tenant and internal callers).
     *
     * @param int         $programid  Target program
     * @param int         $cohortid   Source cohort
     * @param string|null $scopepath  only enrol members under this path
     * @return array{cohort_size:int, newly_enrolled:int, already_enrolled:int}
     */
    public static function enrol_cohort(int $programid, int $cohortid, ?string $scopepath = null): array {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $programid], 'id', MUST_EXIST);
        $DB->get_record('cohort', ['id' => $cohortid], 'id', MUST_EXIST);

        if ($scopepath !== null && $scopepath !== '') {
            [$tsql, $targs] = \local_sentientia_platform\tenant::path_descendant_filter(
                $scopepath, 'u', 'open_path', 'ecs');
            $member_ids = $DB->get_fieldset_sql(
                "SELECT cm.userid
                   FROM {cohort_members} cm
                   JOIN {user} u ON u.id = cm.userid
                  WHERE cm.cohortid = :cid AND $tsql",
                ['cid' => $cohortid] + $targs);
        } else {
            $member_ids = $DB->get_fieldset_select('cohort_members', 'userid',
                'cohortid = :cid', ['cid' => $cohortid]);
        }
        $cohort_size = count($member_ids);
        if ($cohort_size === 0) {
            return [
                'cohort_size'      => 0,
                'newly_enrolled'   => 0,
                'already_enrolled' => 0,
            ];
        }

        // Count already-enrolled BEFORE we add — so we can report it.
        [$insql, $inparams] = $DB->get_in_or_equal($member_ids,
            SQL_PARAMS_NAMED, 'mid');
        $already = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {" . self::USERS_TABLE . "}
              WHERE programid = :pid AND userid $insql",
            array_merge($inparams, ['pid' => $programid]));

        $newly = self::enrol_users($programid, $member_ids);
        return [
            'cohort_size'      => $cohort_size,
            'newly_enrolled'   => $newly,
            'already_enrolled' => $already,
        ];
    }

    /**
     * The cohorts enrol_program_cohort offers the caller, with the number of
     * members enrol_cohort() would actually take (the caller's tenant's).
     *
     * Cross-tenant: every visible cohort, full member count (as before).
     * Scoped: only visible cohorts with at least one member in their tenant,
     * counting only those members. No tenant: none.
     *
     * ADR-031 follow-up (2026-09-25): the "has a member in my tenant" test
     * used to run in PHP AFTER the query's LIMIT, so on a site with more than
     * $limit visible cohorts a tenant's own cohorts could fall off the picker
     * behind other tenants' ones. It is now part of the WHERE clause, so the
     * limit applies to the caller's cohorts only.
     *
     * @param int $limit most cohorts to list
     * @return \stdClass[] id => {id, name, idnumber, member_count}, by name
     */
    public static function cohort_options(int $limit = 500): array {
        global $DB;
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null) {
            return [];
        }
        // '' (cross-tenant) = no restriction; '/N' = members inside tenant N.
        [$msql, $mparams] = \local_sentientia_platform\tenant::path_descendant_filter(
            $scope, 'u', 'open_path', 'pcom');
        $where = 'c.visible = 1';
        $params = $mparams;
        if ($scope !== '') {
            [$esql, $eparams] = \local_sentientia_platform\tenant::path_descendant_filter(
                $scope, 'eu', 'open_path', 'pcoe');
            $where .= " AND EXISTS (SELECT 1
                                      FROM {cohort_members} ecm
                                      JOIN {user} eu ON eu.id = ecm.userid
                                     WHERE ecm.cohortid = c.id AND $esql)";
            $params += $eparams;
        }
        return $DB->get_records_sql(
            "SELECT c.id, c.name, c.idnumber,
                    (SELECT COUNT(*) FROM {cohort_members} cm
                       JOIN {user} u ON u.id = cm.userid
                      WHERE cm.cohortid = c.id AND $msql) AS member_count
               FROM {cohort} c
              WHERE $where
           ORDER BY c.name ASC, c.id ASC", $params, 0, $limit);
    }

    /**
     * Enrol one or more users. Idempotent. Rejects deleted/system users.
     *
     * @return int Count newly enrolled.
     */
    public static function enrol_users(int $programid, array $userids): int {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $programid], 'id', MUST_EXIST);

        $userids = array_unique(array_filter(array_map('intval', $userids), fn($id) => $id > 0));
        if (empty($userids)) {
            return 0;
        }

        // Validate users.
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $valid_ids = $DB->get_fieldset_select('user', 'id',
            "id $insql AND deleted = 0 AND id > 2", $inparams);
        if (empty($valid_ids)) {
            return 0;
        }

        // Skip already-enrolled.
        [$insql2, $inparams2] = $DB->get_in_or_equal($valid_ids, SQL_PARAMS_NAMED, 'uid2');
        $existing = $DB->get_fieldset_select(self::USERS_TABLE, 'userid',
            "programid = :pid AND userid $insql2",
            array_merge($inparams2, ['pid' => $programid]));
        $to_add = array_values(array_diff($valid_ids, $existing));
        if (empty($to_add)) {
            return 0;
        }

        $now = time();
        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($to_add as $uid) {
                $DB->insert_record(self::USERS_TABLE, (object) [
                    'programid'      => $programid,
                    'userid'         => (int) $uid,
                    'currentlevelid' => null,
                    'status'         => self::ENROL_NEW,
                    'timecreated'    => $now,
                    'timecompleted'  => null,
                ]);
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return count($to_add);
    }

    /**
     * Unenrol a user from a program. No-op if not enrolled.
     */
    public static function unenrol_user(int $programid, int $userid): bool {
        global $DB;
        $DB->delete_records(self::USERS_TABLE,
            ['programid' => $programid, 'userid' => $userid]);
        return true;
    }

    /**
     * Get enrolled users with optional search/sort/page.
     *
     * @return array  Each row: id, userid, firstname, lastname, email,
     *                status, currentlevelid, timecreated, timecompleted,
     *                optional open_employeeid/designation.
     * @param bool $callerscope ADR-031: only learners in the caller's tenant
     *                          (roster_scope()); the web service passes true
     */
    public static function get_enrolled_users(int $programid, string $search = '',
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
        $where = ['pu.programid = :pid', $scopesql];
        $params = ['pid' => $programid] + $scopeparams;
        if (!empty($search)) {
            $term = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':s2', false) . ' OR ' .
                $DB->sql_like('u.email', ':s3', false) . ')';
            $params['s1'] = $params['s2'] = $params['s3'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $allowed_sorts = ['firstname', 'lastname', 'email', 'status', 'timecreated'];
        $sortcol = in_array($sort, $allowed_sorts, true) ? $sort : 'lastname';
        if ($sortcol === 'timecreated') {
            $sortcol = 'pu.timecreated';
        } else if ($sortcol === 'status') {
            $sortcol = 'pu.status';
        } else {
            $sortcol = "u.{$sortcol}";
        }
        $dir = strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT pu.id, pu.userid, pu.currentlevelid, pu.status,
                       pu.timecreated AS enrolled_at, pu.timecompleted,
                       u.firstname, u.lastname, u.email{$extra}
                  FROM {" . self::USERS_TABLE . "} pu
                  JOIN {user} u ON u.id = pu.userid
                 WHERE $wheresql
              ORDER BY $sortcol $dir, pu.id ASC";

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }
}
