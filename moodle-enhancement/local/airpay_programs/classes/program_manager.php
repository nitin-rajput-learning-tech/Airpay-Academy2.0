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

namespace local_airpay_programs;

defined('MOODLE_INTERNAL') || die();

/**
 * Program manager — CRUD for certification programs.
 *
 * Handles top-level program operations. Levels and course assignments
 * are managed via separate methods within this class for now (can be
 * extracted to level_manager.php as the feature grows).
 *
 * @package    local_airpay_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_manager {

    private const TABLE          = 'local_airpay_programs';
    private const LEVELS_TABLE   = 'local_airpay_programs_levels';
    private const COURSES_TABLE  = 'local_airpay_programs_courses';
    private const USERS_TABLE    = 'local_airpay_programs_users';

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
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_programs');
        }

        $record = new \stdClass();
        $record->name                = trim($data->name);
        $record->description         = $data->description ?? '';
        $record->costcenterid        = (int) ($data->costcenterid ?? 0);
        $record->status              = (int) ($data->status ?? self::STATUS_DRAFT);
        $record->visible             = isset($data->visible) ? (int) $data->visible : 1;
        $record->completion_required = isset($data->completion_required) ? (int) $data->completion_required : 1;
        $record->timecreated         = time();
        $record->timemodified        = time();

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing program.
     */
    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id, 'timemodified' => time()];
        $fields = ['name', 'description', 'costcenterid', 'status', 'visible', 'completion_required'];
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
     * Change program status.
     */
    public static function change_status(int $id, int $status): int {
        global $DB;

        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_airpay_programs');
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
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_programs');
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
     */
    public static function count_enrolled_filtered(int $programid, string $search = ''): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::USERS_TABLE)) {
            return 0;
        }

        $where = ['pu.programid = :pid'];
        $params = ['pid' => $programid];
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
     */
    public static function get_enrolled_users(int $programid, string $search = '',
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

        $where = ['pu.programid = :pid'];
        $params = ['pid' => $programid];
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
