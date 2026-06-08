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

    /**
     * Get all quizzes available for picker, formatted as 'Quiz Name (Course Name)'.
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

        if ($record->costcenterid > 0) {
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

        $record = (object) ['id' => $id, 'timemodified' => time()];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->quizid) && $data->quizid != $existing->quizid) {
            if (!$DB->record_exists('quiz', ['id' => $data->quizid])) {
                throw new \moodle_exception('invalidquiz', 'local_sentientia_exams');
            }
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
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /** Toggle exam active/inactive. */
    public static function toggle_status(int $id, ?bool $active = null): bool {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $id], 'id, status', MUST_EXIST);
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
        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        return true;
    }
}
