<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_manager.
 *
 * @package   local_sentientia_manager
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Give local_sentientia_mgr_allocations the keys its data model needs, exactly
 * as db/install.xml declares them since 2026092600 (decision 5, 2026-09-26).
 *
 * The table holds four kinds of allocation. A course allocation is written
 * with item_type 'course', itemid = the course id, and the legacy courseid
 * column = the same id. A classroom, program or learning-path allocation is
 * written with its item_type and itemid, and courseid 0. The course-only first
 * schema (step 2026050800) made (userid, courseid) UNIQUE, and no later step
 * relaxed it. Because every typed allocation has courseid 0, a user could hold
 * only ONE of them: the second one (a program after a classroom, say) failed
 * with a database duplicate-key error.
 *
 * The data model needs one allocation per (userid, item_type, itemid). Step
 * 2026051500 already added that UNIQUE index (idx_user_item), and it covers
 * courses too, because a course row carries itemid = courseid. So this helper
 * does four things:
 *
 *   1. Resolves rows that would break (userid, item_type, itemid). It keeps the
 *      oldest (smallest timecreated, then smallest id) and deletes the rest,
 *      with one line per deleted row. A course row whose itemid was never
 *      filled (0, with courseid set) counts as an allocation of its courseid.
 *      The database groups the rows itself, so "the same key" means what the
 *      unique index will mean on that database and collation. A healthy site
 *      has no such rows, because idx_user_item already refuses them. This step
 *      is for a site where that index has gone missing.
 *   2. Gives those never-filled course rows itemid = courseid, so the unique
 *      index covers them as well. Step 2026051500 did this for every row that
 *      existed then, and no code has written such a row since.
 *   3. Makes idx_user_item exist and be UNIQUE.
 *   4. Keeps idx_user_course as a plain, NON-unique lookup index. Its
 *      uniqueness is dropped, but the (userid, courseid) access path that
 *      create_allocation()'s duplicate check reads stays in place.
 *
 * You can run it on any site, more than once. A fresh install, or a site it
 * has already fixed, needs nothing, and gets [] back.
 *
 * Used by upgrade step 2026092600. It is a function so that
 * tests/allocation_index_test.php can test it without replaying the upgrade.
 *
 * @param database_manager $dbman
 * @return string[] what was changed, one line each (empty when nothing was)
 */
function local_sentientia_manager_fix_allocation_keys(database_manager $dbman): array {
    global $DB;
    $tablename = 'local_sentientia_mgr_allocations';
    $table = new xmldb_table($tablename);
    $changed = [];

    // The itemid a row really allocates: a never-filled course row (itemid 0,
    // courseid set) allocates its courseid.
    $effectiveitemid = "CASE WHEN item_type = :coursetype AND itemid = 0 AND courseid > 0
                             THEN courseid ELSE itemid END";

    // 1. Rows that share a key. Collect the groups first, then write.
    $groups = [];
    $rs = $DB->get_recordset_sql(
        "SELECT k.userid, k.item_type, k.effitemid, COUNT(1) AS n
           FROM (SELECT userid, item_type, $effectiveitemid AS effitemid
                   FROM {local_sentientia_mgr_allocations}) k
       GROUP BY k.userid, k.item_type, k.effitemid
         HAVING COUNT(1) > 1",
        ['coursetype' => 'course']);
    foreach ($rs as $group) {
        $groups[] = $group;
    }
    $rs->close();

    $transaction = $DB->start_delegated_transaction();
    try {
        foreach ($groups as $group) {
            // Oldest first. array_values(), not array_shift() on the id-keyed
            // result: array_shift() renumbers integer keys.
            $rows = array_values($DB->get_records_sql(
                "SELECT id, managerid, userid, item_type, itemid, courseid, status, timecreated
                   FROM {local_sentientia_mgr_allocations}
                  WHERE userid = :userid
                    AND item_type = :itemtype
                    AND $effectiveitemid = :itemid
               ORDER BY timecreated ASC, id ASC",
                [
                    'userid'     => (int) $group->userid,
                    'itemtype'   => (string) $group->item_type,
                    'itemid'     => (int) $group->effitemid,
                    'coursetype' => 'course',
                ]));
            if (count($rows) < 2) {
                continue;
            }
            $kept = $rows[0];
            $duplicates = array_slice($rows, 1);
            $DB->delete_records_list($tablename, 'id',
                array_map(fn($row) => (int) $row->id, $duplicates));
            foreach ($duplicates as $row) {
                $changed[] = sprintf('removed duplicate allocation id=%d (userid=%d, item_type=%s, '
                    . 'itemid=%d, courseid=%d, managerid=%d, status=%s, timecreated=%d); kept id=%d',
                    (int) $row->id, (int) $row->userid, (string) $row->item_type,
                    (int) $row->itemid, (int) $row->courseid, (int) $row->managerid,
                    (string) $row->status, (int) $row->timecreated, (int) $kept->id);
            }
        }

        // 2. Fill itemid on the course rows that never had it. This runs after
        // the deletes, so no surviving row can collide.
        $unfilled = "item_type = :coursetype AND itemid = 0 AND courseid > 0";
        $count = $DB->count_records_select($tablename, $unfilled, ['coursetype' => 'course']);
        if ($count > 0) {
            $DB->execute("UPDATE {local_sentientia_mgr_allocations}
                             SET itemid = courseid
                           WHERE $unfilled", ['coursetype' => 'course']);
            $changed[] = "set itemid = courseid on {$count} course allocation(s) written without it";
        }
        $transaction->allow_commit();
    } catch (\Throwable $e) {
        $transaction->rollback($e);
    }

    // 3. idx_user_item: present, and UNIQUE.
    $useritem = ['userid', 'item_type', 'itemid'];
    $state = local_sentientia_manager_index_uniqueness($tablename, $useritem);
    if ($state !== true) {
        $index = new xmldb_index('idx_user_item', XMLDB_INDEX_UNIQUE, $useritem);
        if ($state === false) {
            // drop_index() drops every index on exactly these columns.
            $dbman->drop_index($table, $index);
            $changed[] = 'dropped the non-unique index on (userid, item_type, itemid)';
        }
        $dbman->add_index($table, $index);
        $changed[] = 'created the unique index idx_user_item (userid, item_type, itemid)';
    }

    // 4. idx_user_course: present, and NOT unique.
    $usercourse = ['userid', 'courseid'];
    $state = local_sentientia_manager_index_uniqueness($tablename, $usercourse);
    if ($state !== false) {
        $index = new xmldb_index('idx_user_course', XMLDB_INDEX_NOTUNIQUE, $usercourse);
        if ($state === true) {
            $dbman->drop_index($table, $index);
            $changed[] = 'dropped the unique index idx_user_course (userid, courseid)';
        }
        $dbman->add_index($table, $index);
        $changed[] = 'added idx_user_course (userid, courseid) as a non-unique index';
    }

    return $changed;
}

/**
 * Is there a unique index on exactly these columns of $tablename?
 *
 * database_manager::index_exists() matches an index by its columns only, and
 * does not check whether it is unique. This helper also reports uniqueness.
 * Column order is ignored, the same way index_exists() ignores it.
 *
 * @param string $tablename without prefix
 * @param string[] $fields
 * @return bool|null true: a UNIQUE index covers exactly these columns;
 *                   false: only non-unique ones do; null: no index does
 */
function local_sentientia_manager_index_uniqueness(string $tablename, array $fields): ?bool {
    global $DB;
    $state = null;
    foreach ($DB->get_indexes($tablename) as $index) {
        $columns = array_values($index['columns']);
        if (count($columns) !== count($fields) || array_diff($columns, $fields)
                || array_diff($fields, $columns)) {
            continue;
        }
        if (!empty($index['unique'])) {
            return true;
        }
        $state = false;
    }
    return $state;
}
