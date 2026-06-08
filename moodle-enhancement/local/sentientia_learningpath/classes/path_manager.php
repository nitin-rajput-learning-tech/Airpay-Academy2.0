<?php
namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * Learning path manager — CRUD and progress queries.
 *
 * Replaces BizLMS local_learningplan functionality.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_manager {

    private const TABLE = 'local_sentientia_learningpath';
    private const COURSES_TABLE = 'local_sentientia_learningpath_courses';
    private const USERS_TABLE = 'local_sentientia_learningpath_users';

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
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_learningpath');
        }

        $record = new \stdClass();
        $record->name         = trim($data->name);
        $record->description  = $data->description ?? '';
        // P1 batch (2026-05-16) — descriptionformat + start/end dates.
        $record->descriptionformat = (int) ($data->descriptionformat ?? FORMAT_HTML);
        $record->costcenterid = (int) ($data->costcenterid ?? 0);
        $record->departmentid = (int) ($data->departmentid ?? 0);
        $record->status       = (int) ($data->status ?? self::STATUS_ACTIVE);
        $record->visible      = isset($data->visible) ? (int) $data->visible : 1;
        $record->startdate    = !empty($data->startdate) ? (int) $data->startdate : null;
        $record->enddate      = !empty($data->enddate)   ? (int) $data->enddate   : null;
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

        // P1 batch (2026-05-16) — startdate/enddate/descriptionformat added.
        $fields = ['name', 'description', 'descriptionformat',
                   'costcenterid', 'departmentid', 'status', 'visible',
                   'startdate', 'enddate'];
        foreach ($fields as $field) {
            if (isset($data->$field)) {
                // Empty date-selectors arrive as 0 — store NULL instead so
                // queries like `enddate IS NULL` work.
                if (in_array($field, ['startdate', 'enddate'], true)
                    && empty($data->$field)) {
                    $record->$field = null;
                } else {
                    $record->$field = $data->$field;
                }
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

    // ═══════════════════════════════════════════════════════════════════
    // Course assignment (G-04)
    // Manage which courses are part of this learning path, and their order.
    // ═══════════════════════════════════════════════════════════════════

    /** Status values for path-user enrolment. */
    public const ENROL_NEW        = 0;
    public const ENROL_INPROGRESS = 1;
    public const ENROL_COMPLETED  = 2;

    /**
     * Assign one or more courses to a learning path. Idempotent — courses already
     * on the path are silently skipped (the UNIQUE (pathid, courseid) index
     * enforces this at the DB level).
     *
     * Newly-added courses go to the END of the existing sort order. Caller can
     * reorder afterwards via reorder_courses().
     *
     * @param int   $pathid
     * @param int[] $courseids
     * @return int  Count of courses actually inserted (excluding skips).
     * @throws \moodle_exception  If path doesn't exist.
     */
    public static function assign_courses(int $pathid, array $courseids): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $DB->get_record(self::TABLE, ['id' => $pathid], 'id', MUST_EXIST);

        if (empty($courseids)) {
            return 0;
        }

        // Validate that course IDs exist (avoid foreign key violation noise).
        // Skip site course (id=1) — it's the global Moodle "site" pseudo-course
        // and not a learner-facing course.
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $valid_courses = $DB->get_fieldset_select('course',
            'id',
            "id $insql AND id > 1",
            $inparams);

        if (empty($valid_courses)) {
            return 0;
        }

        // Find the current max sortorder so we can append.
        $max_sort = (int) $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), -1) FROM {" . self::COURSES_TABLE . "} WHERE pathid = :p",
            ['p' => $pathid]);

        // Find courses already assigned (so we can skip them and report accurate count).
        [$insql2, $inparams2] = $DB->get_in_or_equal($valid_courses, SQL_PARAMS_NAMED, 'vcid');
        $inparams2['p'] = $pathid;
        $already_assigned = $DB->get_fieldset_select(self::COURSES_TABLE,
            'courseid',
            "pathid = :p AND courseid $insql2",
            $inparams2);
        $already_assigned = array_flip(array_map('intval', $already_assigned));

        $inserted = 0;
        $newcourseids = [];
        $now = time();
        $sortorder = $max_sort + 1;

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($valid_courses as $courseid) {
                $cid = (int) $courseid;
                if (isset($already_assigned[$cid])) {
                    continue;
                }
                $DB->insert_record(self::COURSES_TABLE, (object) [
                    'pathid'      => $pathid,
                    'courseid'    => $cid,
                    'sortorder'   => $sortorder++,
                    'mandatory'   => 1,
                    'timecreated' => $now,
                ]);
                $inserted++;
                $newcourseids[] = $cid;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        // W1-2 (2026-05-15) — back-fill enrolments: if this path already has
        // users assigned, enrol each of them into the newly-added course(s).
        // Without this, learners on the path would silently miss new courses
        // added after their assignment date.
        if ($inserted > 0 && !empty($newcourseids)) {
            $existing_users = $DB->get_fieldset_select(self::USERS_TABLE,
                'userid', 'pathid = :p', ['p' => $pathid]);
            foreach ($existing_users as $uid) {
                foreach ($newcourseids as $cid) {
                    self::enrol_user_in_path_course((int) $uid, (int) $cid);
                }
            }
        }

        // Touch the parent path's timemodified so list views show the change.
        if ($inserted > 0) {
            $DB->set_field(self::TABLE, 'timemodified', $now, ['id' => $pathid]);
        }

        return $inserted;
    }

    /**
     * Remove a single course from a learning path.
     *
     * Note: enrolled users keep their progress on that course (we don't touch
     * mdl_course_completions). Users assigned to the path stay assigned —
     * just one less mandatory course in their sequence.
     *
     * @param int $pathid
     * @param int $courseid
     * @return bool  True if removed; false if it wasn't on the path.
     * @throws \moodle_exception  If path doesn't exist.
     */
    public static function unassign_course(int $pathid, int $courseid): bool {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $pathid], 'id', MUST_EXIST);

        $existed = $DB->record_exists(self::COURSES_TABLE,
            ['pathid' => $pathid, 'courseid' => $courseid]);

        if (!$existed) {
            return false;
        }

        $DB->delete_records(self::COURSES_TABLE,
            ['pathid' => $pathid, 'courseid' => $courseid]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $pathid]);

        return true;
    }

    /**
     * Reorder the courses within a path. Pass an ordered array of course IDs;
     * each course's sortorder is set to its index in the array (0-based).
     *
     * Course IDs in the array but NOT on the path are ignored. Courses on the
     * path but NOT in the array keep their existing sortorder (no change).
     *
     * @param int   $pathid
     * @param int[] $ordered_course_ids
     * @return int  Count of rows updated
     * @throws \moodle_exception  If path doesn't exist.
     */
    public static function reorder_courses(int $pathid, array $ordered_course_ids): int {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $pathid], 'id', MUST_EXIST);

        if (empty($ordered_course_ids)) {
            return 0;
        }

        $updated = 0;
        $transaction = $DB->start_delegated_transaction();
        try {
            $sortorder = 0;
            foreach ($ordered_course_ids as $courseid) {
                $cid = (int) $courseid;
                $existing = $DB->get_record(self::COURSES_TABLE,
                    ['pathid' => $pathid, 'courseid' => $cid], 'id, sortorder');
                if (!$existing) {
                    continue;
                }
                if ((int) $existing->sortorder !== $sortorder) {
                    $DB->set_field(self::COURSES_TABLE, 'sortorder', $sortorder,
                        ['id' => $existing->id]);
                    $updated++;
                }
                $sortorder++;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        if ($updated > 0) {
            $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $pathid]);
        }

        return $updated;
    }

    // ═══════════════════════════════════════════════════════════════════
    // User enrolment (G-04)
    // Assign learners to a learning path. Status starts at NEW; cron will
    // promote to INPROGRESS once first course completion is detected, and
    // to COMPLETED once all mandatory courses are finished.
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Bulk-enrol users in a learning path. Idempotent — users already enrolled
     * are silently skipped (UNIQUE (pathid, userid) index enforces).
     *
     * W1-2 BizLMS parity (2026-05-15): in addition to inserting the path-user
     * row, this also enrols each newly-assigned user into every Moodle course
     * on the path via the standard `manual` enrol plugin. Without this step,
     * learners assigned to a path see the path in their dashboard but cannot
     * open any of its courses — which is what BizLMS solved with a dedicated
     * `enrol_learningplan` plugin. We use `manual` instead because every Airpay
     * course already has a manual instance, and it lets unenrolment + role
     * checks go through Moodle's stock paths.
     *
     * Course enrolment failures are tolerated: the path-user row is still
     * created, the count of "fully-enrolled users" is returned, and any
     * per-course failures are logged via `debugging()` for the admin to
     * investigate. The path-user row is the source of truth for "user is on
     * path"; the course enrolments are the means by which they access content.
     *
     * @param int   $pathid
     * @param int[] $userids
     * @return int  Count of users actually enrolled (excluding already-enrolled)
     * @throws \moodle_exception  If path doesn't exist.
     */
    public static function enrol_users(int $pathid, array $userids): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $DB->get_record(self::TABLE, ['id' => $pathid], 'id', MUST_EXIST);

        if (empty($userids)) {
            return 0;
        }

        // Validate user IDs (must exist + not deleted + not system/guest).
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $valid_users = $DB->get_fieldset_select('user',
            'id',
            "id $insql AND deleted = 0 AND id > 2",
            $inparams);

        if (empty($valid_users)) {
            return 0;
        }

        // Find users already enrolled.
        [$insql2, $inparams2] = $DB->get_in_or_equal($valid_users, SQL_PARAMS_NAMED, 'vuid');
        $inparams2['p'] = $pathid;
        $already_enrolled = $DB->get_fieldset_select(self::USERS_TABLE,
            'userid',
            "pathid = :p AND userid $insql2",
            $inparams2);
        $already_enrolled = array_flip(array_map('intval', $already_enrolled));

        // W1-2: fetch the path's courses up-front so we can enrol the new
        // users into each one. Empty paths (no courses yet) are valid —
        // users just get the path-user row and no course enrolments.
        $pathcourseids = $DB->get_fieldset_select(self::COURSES_TABLE,
            'courseid', 'pathid = :p', ['p' => $pathid]);

        $enrolled = 0;
        $now = time();

        // Path-user inserts go in a transaction. Course enrolments do NOT —
        // they call Moodle's enrol plugin which manages its own transactions
        // and we don't want a single bad course (e.g. manual plugin disabled)
        // to roll back the entire path-user batch.
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($valid_users as $userid) {
                $uid = (int) $userid;
                if (isset($already_enrolled[$uid])) {
                    continue;
                }
                $DB->insert_record(self::USERS_TABLE, (object) [
                    'pathid'      => $pathid,
                    'userid'      => $uid,
                    'status'      => self::ENROL_NEW,
                    'timecreated' => $now,
                ]);
                $enrolled++;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        // W1-2: enrol the newly-inserted users into each course on the path.
        // Done after the transaction commits so each enrolment is independent.
        if ($enrolled > 0 && !empty($pathcourseids)) {
            foreach ($valid_users as $userid) {
                $uid = (int) $userid;
                if (isset($already_enrolled[$uid])) {
                    continue;
                }
                foreach ($pathcourseids as $courseid) {
                    self::enrol_user_in_path_course($uid, (int) $courseid);
                }
            }
        }

        if ($enrolled > 0) {
            $DB->set_field(self::TABLE, 'timemodified', $now, ['id' => $pathid]);
        }

        return $enrolled;
    }

    /**
     * W1-2 (2026-05-15): enrol a single user into a single course on a path
     * via the `manual` enrol plugin. Safe to call repeatedly — `manual` is
     * idempotent and updates timestart/end on an existing user_enrolments row
     * rather than creating duplicates.
     *
     * Failure modes (all logged via debugging() and return false):
     *   - course no longer exists (deleted between path setup and enrol)
     *   - manual enrol plugin disabled site-wide
     *   - manual enrol instance disabled on that specific course
     *   - user already a teacher/admin in the course (is_enrolled() short-circuits)
     *
     * @param int $userid
     * @param int $courseid
     * @return bool  True if enrolled or already-enrolled; false on misconfig
     */
    private static function enrol_user_in_path_course(int $userid, int $courseid): bool {
        try {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                debugging("local_sentientia_learningpath: course $courseid context missing — skipping enrol of user $userid",
                    DEBUG_DEVELOPER);
                return false;
            }
            // Already enrolled via manual, learningplan, or any other plugin?
            // Don't add a redundant row — Moodle's enrol API handles dups but
            // is_enrolled() also includes admins/teachers we don't want to
            // overwrite roles for.
            if (is_enrolled($context, $userid)) {
                return true;
            }
            // null roleid = use instance default (typically `student`).
            // timestart = now so the course shows on the dashboard immediately.
            // timeend = 0 (no expiry).
            return (bool) enrol_try_internal_enrol($courseid, $userid, null, time(), 0);
        } catch (\Throwable $e) {
            debugging("local_sentientia_learningpath: failed to enrol user $userid in course $courseid: "
                . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Unenrol a single user from a learning path.
     *
     * Note: This does NOT unenrol the user from the underlying Moodle courses.
     * Their enrolment in those courses (if any) survives. Their progress in
     * those courses survives. Only the path-level association is removed.
     *
     * @param int $pathid
     * @param int $userid
     * @return bool  True if unenrolled; false if user wasn't on the path.
     * @throws \moodle_exception  If path doesn't exist.
     */
    public static function unenrol_user(int $pathid, int $userid): bool {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $pathid], 'id', MUST_EXIST);

        $existed = $DB->record_exists(self::USERS_TABLE,
            ['pathid' => $pathid, 'userid' => $userid]);

        if (!$existed) {
            return false;
        }

        $DB->delete_records(self::USERS_TABLE,
            ['pathid' => $pathid, 'userid' => $userid]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $pathid]);

        return true;
    }

    /**
     * Get the users enrolled in a learning path with their progress + names.
     * Returns an array of objects suitable for the shared datatable.
     *
     * Supports search (firstname/lastname/email) + pagination.
     *
     * @param int    $pathid
     * @param string $search    Term to filter by (LIKE-escaped)
     * @param int    $page      0-indexed
     * @param int    $perpage
     * @return array  ['total' => int, 'rows' => array]
     */
    public static function get_path_users(int $pathid, string $search = '',
                                           int $page = 0, int $perpage = 25): array {
        global $DB;

        $where = ['lpu.pathid = :pid', 'u.deleted = 0'];
        $params = ['pid' => $pathid];

        if (!empty($search)) {
            $term = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' .
                $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname',  ':s2', false) . ' OR ' .
                $DB->sql_like('u.email',     ':s3', false) .
            ')';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {" . self::USERS_TABLE . "} lpu
               JOIN {user} u ON u.id = lpu.userid
              WHERE $wheresql", $params);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT lpu.id AS rowid, lpu.userid, lpu.status, lpu.timecreated, lpu.timecompleted,
                        u.firstname, u.lastname, u.email, u.open_employeeid, u.open_designation
                   FROM {" . self::USERS_TABLE . "} lpu
                   JOIN {user} u ON u.id = lpu.userid
                  WHERE $wheresql
               ORDER BY u.lastname ASC, u.firstname ASC, lpu.id ASC",
                $params, $page * $perpage, $perpage);

            $statusmap = [
                self::ENROL_NEW        => 'Enrolled',
                self::ENROL_INPROGRESS => 'In Progress',
                self::ENROL_COMPLETED  => 'Completed',
            ];
            $cssmap = [
                self::ENROL_NEW        => 'badge-secondary',
                self::ENROL_INPROGRESS => 'badge-info',
                self::ENROL_COMPLETED  => 'badge-success',
            ];

            foreach ($records as $r) {
                $rows[] = (object) [
                    'id'          => (int) $r->userid,
                    'fullname'    => fullname($r),
                    'employeeid'  => $r->open_employeeid ?: '—',
                    'email'       => $r->email,
                    'designation' => $r->open_designation ?: '—',
                    'enrolled'    => userdate($r->timecreated, '%d %b %Y'),
                    'completed'   => $r->timecompleted ? userdate($r->timecompleted, '%d %b %Y') : '—',
                    'statuslabel' => $statusmap[(int) $r->status] ?? 'Unknown',
                    'statuscss'   => $cssmap[(int) $r->status] ?? 'badge-secondary',
                ];
            }
        }

        return ['total' => $total, 'rows' => $rows];
    }
}
