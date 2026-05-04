<?php
namespace local_airpay_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * Learning path manager — CRUD and progress queries.
 *
 * Replaces BizLMS local_learningplan functionality.
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_manager {

    private const TABLE = 'local_airpay_learningpath';
    private const COURSES_TABLE = 'local_airpay_learningpath_courses';
    private const USERS_TABLE = 'local_airpay_learningpath_users';

    /**
     * Get a learning path by ID.
     *
     * @param int $pathid
     * @return object|false
     */
    public static function get(int $pathid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $pathid]);
    }

    /**
     * Get all courses in a learning path (ordered).
     *
     * @param int $pathid
     * @return array  Course records with sortorder
     */
    public static function get_courses(int $pathid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT lpc.*, c.fullname, c.shortname, c.visible
               FROM {" . self::COURSES_TABLE . "} lpc
               JOIN {course} c ON c.id = lpc.courseid
              WHERE lpc.pathid = :pathid
           ORDER BY lpc.sortorder ASC",
            ['pathid' => $pathid]
        );
    }

    /**
     * Check if a user is enrolled in a learning path.
     *
     * @param int $pathid
     * @param int $userid
     * @return bool
     */
    public static function is_enrolled(int $pathid, int $userid): bool {
        global $DB;

        // Check Airpay table first.
        if ($DB->record_exists(self::USERS_TABLE, ['pathid' => $pathid, 'userid' => $userid])) {
            return true;
        }

        // Fallback: check BizLMS table.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_learningplan_user')) {
            return $DB->record_exists('local_learningplan_user', [
                'planid' => $pathid,
                'userid' => $userid,
            ]);
        }

        return false;
    }

    /**
     * Get user's progress through a learning path.
     *
     * @param int $pathid
     * @param int $userid
     * @return object  {total_courses, completed_courses, percentage}
     */
    public static function get_user_progress(int $pathid, int $userid): object {
        global $DB;

        $courses = self::get_courses($pathid);
        $total = count($courses);
        $completed = 0;

        foreach ($courses as $pathcourse) {
            if ($DB->record_exists_select('course_completions',
                "course = :cid AND userid = :uid AND timecompleted > 0",
                ['cid' => $pathcourse->courseid, 'uid' => $userid])) {
                $completed++;
            }
        }

        return (object) [
            'total_courses'     => $total,
            'completed_courses' => $completed,
            'percentage'        => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Count learning paths for a tenant.
     *
     * @param string $pathfilter  e.g. "/1/%" or empty for all
     * @return int
     */
    public static function count_paths(string $pathfilter = ''): int {
        global $DB;

        $dbman = $DB->get_manager();

        if ($dbman->table_exists(self::TABLE) && $DB->count_records(self::TABLE) > 0) {
            if (!empty($pathfilter)) {
                return $DB->count_records_select(self::TABLE, "open_path LIKE :p", ['p' => $pathfilter]);
            }
            return $DB->count_records(self::TABLE);
        }

        // Fallback to BizLMS.
        if ($dbman->table_exists('local_learningplan')) {
            return $DB->count_records('local_learningplan');
        }

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations
    // ═══════════════════════════════════════════════════════════════════

    /** Status values matching install.xml. */
    public const STATUS_ARCHIVED = 0;
    public const STATUS_ACTIVE   = 1;

    /**
     * Create a new learning path.
     *
     * @param object $data  name, description, costcenterid
     * @return int  New path ID
     * @throws \moodle_exception
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_learningpath');
        }

        $record = new \stdClass();
        $record->name         = trim($data->name);
        $record->description  = $data->description ?? '';
        $record->costcenterid = (int) ($data->costcenterid ?? 0);
        $record->departmentid = (int) ($data->departmentid ?? 0);
        $record->status       = (int) ($data->status ?? self::STATUS_ACTIVE);
        $record->visible      = isset($data->visible) ? (int) $data->visible : 1;
        $record->timecreated  = time();
        $record->timemodified = time();

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing learning path.
     */
    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id, 'timemodified' => time()];

        $fields = ['name', 'description', 'costcenterid', 'departmentid', 'status', 'visible'];
        foreach ($fields as $field) {
            if (isset($data->$field)) {
                $record->$field = $data->$field;
            }
        }

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Toggle path status (active/archived).
     */
    public static function toggle_status(int $id, ?bool $active = null): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], 'id, status', MUST_EXIST);
        $newstatus = $active ?? ($existing->status != self::STATUS_ACTIVE);
        $newval = $newstatus ? self::STATUS_ACTIVE : self::STATUS_ARCHIVED;

        $DB->update_record(self::TABLE, (object) [
            'id'           => $id,
            'status'       => $newval,
            'timemodified' => time(),
        ]);

        return $newstatus;
    }

    /**
     * Delete a learning path and all its course assignments + enrollments.
     */
    public static function delete(int $id): bool {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::COURSES_TABLE, ['pathid' => $id]);
            $DB->delete_records(self::USERS_TABLE, ['pathid' => $id]);
            $DB->delete_records(self::TABLE, ['id' => $id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }
}
