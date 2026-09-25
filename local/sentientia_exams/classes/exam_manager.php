<?php
namespace local_sentientia_exams;

defined('MOODLE_INTERNAL') || die();

/**
 * Exam manager — queries against the online tests table.
 *
 * Replaces direct queries against {local_onlinetests} found in
 * core_renderer.php (lines 1719, 1738) for access control.
 *
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exam_manager {

    private const TABLE = 'local_sentientia_exams';
    private const LEGACY_TABLE = 'local_onlinetests';

    /**
     * Count online exams (for dashboard stat card).
     *
     * Replaces dashboard.php line 342.
     *
     * @return int
     */
    public static function count_exams(): int {
        global $DB;

        $table = self::resolve_table();
        return $DB->count_records($table);
    }

    /**
     * ADR-031: exam counts for the index KPI tiles, scoped exactly like
     * list_exams (cross-tenant: every exam; scoped: the caller's tenant;
     * no tenant: none). The tiles used to count every tenant's exams.
     *
     * @param int|null $status STATUS_* filter, or null for all
     * @return int
     */
    public static function count_scoped(?int $status = null): int {
        global $DB;
        [$tsql, $params] = \local_sentientia_platform\tenant::path_filter('e');
        $where = $tsql;
        if ($status !== null) {
            $where .= ' AND e.status = :exstatus';
            $params['exstatus'] = $status;
        }
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {" . self::TABLE . "} e WHERE {$where}", $params);
    }

    /**
     * Get exam record by course module ID.
     *
     * Replaces core_renderer.php line 1719:
     *   SELECT lo.* FROM {local_onlinetests} AS lo
     *   JOIN {course_modules} AS cm ON cm.instance=lo.quizid ...
     *
     * @param int $cmid  Course module ID
     * @return object|false
     */
    public static function get_by_course_module(int $cmid) {
        global $DB;

        $quizmoduleid = $DB->get_field('modules', 'id', ['name' => 'quiz']);
        if (!$quizmoduleid) {
            return false;
        }

        $table = self::resolve_table();

        return $DB->get_record_sql(
            "SELECT e.* FROM {{$table}} e
               JOIN {course_modules} cm ON cm.instance = e.quizid AND cm.module = :modid
              WHERE cm.id = :cmid",
            ['modid' => $quizmoduleid, 'cmid' => $cmid]
        );
    }

    /**
     * Get exam record by quiz attempt ID.
     *
     * Replaces core_renderer.php line 1738:
     *   SELECT lo.id, lo.costcenterid, lo.departmentid FROM {local_onlinetests} AS lo
     *   JOIN {quiz_attempts} AS qa ON qa.quiz = lo.quizid ...
     *
     * @param int $attemptid
     * @return object|false
     */
    public static function get_by_attempt(int $attemptid) {
        global $DB;

        $table = self::resolve_table();

        return $DB->get_record_sql(
            "SELECT e.id, e.costcenterid, e.departmentid
               FROM {{$table}} e
               JOIN {quiz_attempts} qa ON qa.quiz = e.quizid
              WHERE qa.id = :attemptid",
            ['attemptid' => $attemptid]
        );
    }

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

    // ═══════════════════════════════════════════════════════════════════
    // Admin CRUD operations
    // ═══════════════════════════════════════════════════════════════════

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE   = 1;

    // ═══════════════════════════════════════════════════════════════════
    // ADR-031 tenant scope (2026-09-25)
    //
    // :view, :manage and :enrol say WHAT a caller may do, never WHERE.
    // Every holder is a tenant admin (a manager-archetype role at system
    // context) unless tenant::is_cross_tenant() says otherwise, so every
    // read or write that names an exam checks the exam against the
    // caller's tenant here. Until 2026-09-25 any holder could open, edit,
    // deactivate or delete any tenant's exam by id.
    // ═══════════════════════════════════════════════════════════════════

    /**
     * ADR-031: refuse unless the current user may act on this exam.
     *
     * Cross-tenant callers (site admin, :crosstenant) always may. Anyone
     * else only when the exam's open_path lies in their own tenant. An exam
     * with no open_path ("No specific organisation", or an org that has
     * gone) cannot be shown to be in anyone's tenant, so it is left to
     * cross-tenant callers: tenant::require_path_access() alone lets an
     * empty path through.
     *
     * @param \stdClass $exam exam record carrying open_path
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_exam_access(\stdClass $exam): void {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        $path = rtrim(trim((string) ($exam->open_path ?? '')), '/');
        if ($path === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access($path);
    }

    /**
     * ADR-031: refuse a quiz whose course belongs to another tenant.
     *
     * The exam's tenant and its quiz's course tenant are independent, so a
     * tenant admin could otherwise wrap another tenant's quiz in an exam of
     * their own and read its attempts. A legacy course with no open_path
     * (listed for every tenant) passes: view.php scopes every learner row to
     * the caller's tenant regardless.
     *
     * @param int $quizid
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_quiz_in_scope(int $quizid): void {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        $path = (string) $DB->get_field_sql(
            "SELECT c.open_path
               FROM {quiz} q
               JOIN {course} c ON c.id = q.course
              WHERE q.id = :qid",
            ['qid' => $quizid]);
        $path = rtrim(trim($path), '/');
        if ($path !== '') {
            \local_sentientia_platform\tenant::require_path_access($path);
        }
    }

    /**
     * ADR-031: the [costcenterid, open_path] a scoped caller may write.
     *
     * Only for callers who are NOT cross-tenant (they keep the old
     * behaviour). The organisation must be inside the caller's own tenant;
     * "No specific organisation" (0) gives the exam the caller's tenant
     * root instead of the no-open_path exam their own list could never
     * show. A caller with no resolvable tenant writes nothing.
     *
     * @param int $orgid local_sentientia_org.id, or 0
     * @return array{0: int, 1: string} [costcenterid, open_path]
     * @throws \moodle_exception error_outoftenant
     */
    private static function scoped_org(int $orgid): array {
        global $DB;
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null || $scope === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        if ($orgid <= 0) {
            return [0, $scope];
        }
        $org = $DB->get_record('local_sentientia_org', ['id' => $orgid], 'id, path');
        $path = $org ? rtrim(trim((string) $org->path), '/') : '';
        if ($path === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access($path);
        return [(int) $org->id, (string) $org->path];
    }

    /**
     * Get all quizzes available for picker, formatted as 'Quiz Name (Course Name)'.
     *
     * ADR-031: a scoped caller sees only quizzes in their own tenant's
     * courses (plus legacy courses with no open_path); the picker used to
     * list every tenant's quizzes and course names.
     */
    public static function get_quiz_options(array $exclude_quizids = []): array {
        global $DB;

        $where = "c.id > 1 AND c.visible = 1";
        $params = [];
        if (!empty($exclude_quizids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($exclude_quizids, SQL_PARAMS_NAMED, 'qid', false);
            $where .= " AND q.id $insql";
            $params = $inparams;
        }
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null) {
            // Scoped caller with no tenant: offer nothing (fail closed).
            return [0 => '— Select a quiz to register as exam —'];
        }
        if ($scope !== '') {
            [$tsql, $targs] = \local_sentientia_platform\tenant::path_descendant_filter(
                $scope, 'c', 'open_path', 'exq');
            $where .= " AND (c.open_path IS NULL OR c.open_path = '' OR {$tsql})";
            $params = array_merge($params, $targs);
        }

        $rows = $DB->get_records_sql(
            "SELECT q.id AS quizid, q.name AS quizname, c.fullname AS coursename
               FROM {quiz} q
               JOIN {course} c ON c.id = q.course
              WHERE $where
           ORDER BY c.fullname ASC, q.name ASC",
            $params, 0, 500);

        $options = [0 => '— Select a quiz to register as exam —'];
        foreach ($rows as $r) {
            $options[$r->quizid] = format_string($r->quizname) . ' (' . format_string($r->coursename) . ')';
        }
        return $options;
    }

    /** Get quizzes already registered as exams. */
    public static function get_registered_quiz_ids(): array {
        global $DB;
        return $DB->get_fieldset_select(self::TABLE, 'quizid', '1=1');
    }

    /**
     * Create an exam wrapper record around an existing Moodle quiz.
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name) || empty($data->quizid)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_exams');
        }

        if (!$DB->record_exists('quiz', ['id' => $data->quizid])) {
            throw new \moodle_exception('invalidquiz', 'local_sentientia_exams');
        }

        if ($DB->record_exists(self::TABLE, ['quizid' => $data->quizid])) {
            throw new \moodle_exception('quizalreadyregistered', 'local_sentientia_exams');
        }

        // P1 #23 — categoryid is a tagging field (FK to mdl_course_categories).
        // We validate the referenced category exists so admins don't end up
        // with orphan ids if the categories table changes underneath us.
        $categoryid = (int) ($data->categoryid ?? 0);
        if ($categoryid > 0
                && !$DB->record_exists('course_categories', ['id' => $categoryid])) {
            throw new \moodle_exception('invalidcategory', 'local_sentientia_exams');
        }

        $record = (object) [
            'name'         => trim($data->name),
            'quizid'       => (int) $data->quizid,
            'costcenterid' => (int) ($data->costcenterid ?? 0),
            'departmentid' => (int) ($data->departmentid ?? 0),
            'categoryid'   => $categoryid,
            'duration'     => isset($data->duration) && $data->duration > 0 ? (int) $data->duration : null,
            'passinggrade' => isset($data->passinggrade) ? max(0, min(100, (float) $data->passinggrade)) : null,
            'status'       => (int) ($data->status ?? self::STATUS_ACTIVE),
            'visible'      => isset($data->visible) ? (int) $data->visible : 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];

        if (!\local_sentientia_platform\tenant::is_cross_tenant()) {
            // ADR-031: a scoped caller creates only inside their own tenant,
            // around a quiz from their own tenant's courses.
            self::require_quiz_in_scope($record->quizid);
            [$record->costcenterid, $record->open_path] = self::scoped_org($record->costcenterid);
        } else if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing exam wrapper record.
     */
    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        // ADR-031: the exam being edited must be in the caller's tenant.
        self::require_exam_access($existing);
        $crosstenant = \local_sentientia_platform\tenant::is_cross_tenant();

        $record = (object) ['id' => $id, 'timemodified' => time()];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->quizid) && $data->quizid != $existing->quizid) {
            if (!$DB->record_exists('quiz', ['id' => $data->quizid])) {
                throw new \moodle_exception('invalidquiz', 'local_sentientia_exams');
            }
            self::require_quiz_in_scope((int) $data->quizid);
            if ($DB->record_exists_select(self::TABLE,
                'quizid = :qid AND id != :id', ['qid' => $data->quizid, 'id' => $id])) {
                throw new \moodle_exception('quizalreadyregistered', 'local_sentientia_exams');
            }
            $record->quizid = (int) $data->quizid;
        }
        if (isset($data->costcenterid)) $record->costcenterid = (int) $data->costcenterid;
        if (isset($data->duration))     $record->duration = $data->duration > 0 ? (int) $data->duration : null;
        if (isset($data->passinggrade)) $record->passinggrade = max(0, min(100, (float) $data->passinggrade));
        if (isset($data->status))       $record->status = (int) $data->status;
        if (isset($data->visible))      $record->visible = (int) $data->visible;
        // P1 #23 — category update with same FK validation as create().
        if (isset($data->categoryid)) {
            $catid = (int) $data->categoryid;
            if ($catid > 0
                    && !$DB->record_exists('course_categories', ['id' => $catid])) {
                throw new \moodle_exception('invalidcategory', 'local_sentientia_exams');
            }
            $record->categoryid = $catid;
        }

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            if (!$crosstenant) {
                // ADR-031: a scoped caller may only re-home inside their tenant.
                [$record->costcenterid, $record->open_path] = self::scoped_org($record->costcenterid);
            } else {
                $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
                $record->open_path = $org ? $org->path : '';
            }
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /** Toggle exam active/inactive. */
    public static function toggle_status(int $id, ?bool $active = null): bool {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $id], 'id, status, open_path', MUST_EXIST);
        // ADR-031: only an exam in the caller's tenant.
        self::require_exam_access($existing);
        $newstate = $active ?? !((bool) $existing->status);
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'status' => $newstate ? self::STATUS_ACTIVE : self::STATUS_INACTIVE,
            'timemodified' => time(),
        ]);
        return $newstate;
    }

    /** Delete wrapper record (does NOT touch the underlying Moodle quiz). */
    public static function delete(int $id): bool {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        // ADR-031: only an exam in the caller's tenant.
        self::require_exam_access($existing);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        return true;
    }
}
