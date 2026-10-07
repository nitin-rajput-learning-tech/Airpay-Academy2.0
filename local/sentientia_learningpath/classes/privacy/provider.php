<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider of local_sentientia_learningpath.
 *
 * ADR-032 (2026-09-30): the BizLMS learningplan import adds columns that name a person - who created or edited
 * a path and a path course (usercreated, usermodified), who enrolled a learner (enrolledby) - and the table
 * local_sentientia_lp_course_status (userid-keyed). All of them are declared here, exported, and erased or
 * anonymised:
 *
 *  - A learner's own rows (enrolments, course status, adaptive log) are exported and deleted with the learner.
 *  - An actor column that points at the user is exported under "authored" and set to 0 (unknown) when the user
 *    is erased or anonymised. The path, course and enrolment rows themselves are configuration and records,
 *    not the actor's personal data, and stay.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** Per-path enrolment / completion table (userid-keyed). */
    private const TABLE_USERS = 'local_sentientia_learningpath_users';
    /** Adaptive-journey decision log (userid-keyed), added 2026061600. */
    private const TABLE_ADAPTIVE = 'local_sentientia_lp_adaptive_log';
    /** The paths (usercreated, usermodified), added 2026093001. */
    private const TABLE_PATHS = 'local_sentientia_learningpath';
    /** The courses on a path (usercreated, usermodified), added 2026093001. */
    private const TABLE_COURSES = 'local_sentientia_learningpath_courses';
    /** Per-course status rows of a learner, added 2026093001 (userid-keyed). */
    private const TABLE_STATUS = 'local_sentientia_lp_course_status';

    /**
     * Every column that names a user, per table. The userid-keyed tables are the learner's own data; the others
     * are actor columns.
     */
    private const USER_COLUMNS = [
        self::TABLE_USERS => ['userid', 'enrolledby'],
        self::TABLE_ADAPTIVE => ['userid'],
        self::TABLE_STATUS => ['userid', 'usercreated', 'usermodified'],
        self::TABLE_PATHS => ['usercreated', 'usermodified'],
        self::TABLE_COURSES => ['usercreated', 'usermodified'],
    ];

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE_USERS,
            [
                'pathid'        => 'privacy:metadata:lp:pathid',
                'userid'        => 'privacy:metadata:lp:userid',
                'status'        => 'privacy:metadata:lp:status',
                'enrolledby'    => 'privacy:metadata:lp:enrolledby',
                'timecreated'   => 'privacy:metadata:lp:timecreated',
                'timemodified'  => 'privacy:metadata:lp:timemodified',
                'timecompleted' => 'privacy:metadata:lp:timecompleted',
            ],
            'privacy:metadata:lp');
        $collection->add_database_table(self::TABLE_ADAPTIVE,
            [
                'userid'      => 'privacy:metadata:lp_adaptive_log:userid',
                'pathid'      => 'privacy:metadata:lp_adaptive_log:pathid',
                'pivot_type'  => 'privacy:metadata:lp_adaptive_log:pivot_type',
                'quiz_score'  => 'privacy:metadata:lp_adaptive_log:quiz_score',
                'timecreated' => 'privacy:metadata:lp_adaptive_log:timecreated',
            ],
            'privacy:metadata:lp_adaptive_log');
        $collection->add_database_table(self::TABLE_STATUS,
            [
                'pathid'         => 'privacy:metadata:lp_course_status:pathid',
                'courseid'       => 'privacy:metadata:lp_course_status:courseid',
                'userid'         => 'privacy:metadata:lp_course_status:userid',
                'status'         => 'privacy:metadata:lp_course_status:status',
                'percentage'     => 'privacy:metadata:lp_course_status:percentage',
                'startdate'      => 'privacy:metadata:lp_course_status:startdate',
                'completiondate' => 'privacy:metadata:lp_course_status:completiondate',
                'usercreated'    => 'privacy:metadata:lp_course_status:usercreated',
                'usermodified'   => 'privacy:metadata:lp_course_status:usermodified',
                'timecreated'    => 'privacy:metadata:lp_course_status:timecreated',
                'timemodified'   => 'privacy:metadata:lp_course_status:timemodified',
            ],
            'privacy:metadata:lp_course_status');
        $collection->add_database_table(self::TABLE_PATHS,
            [
                'usercreated'  => 'privacy:metadata:lp_path:usercreated',
                'usermodified' => 'privacy:metadata:lp_path:usermodified',
            ],
            'privacy:metadata:lp_path');
        $collection->add_database_table(self::TABLE_COURSES,
            [
                'usercreated'  => 'privacy:metadata:lp_course:usercreated',
                'usermodified' => 'privacy:metadata:lp_course:usermodified',
            ],
            'privacy:metadata:lp_course');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $dbman = $DB->get_manager();
        foreach (self::USER_COLUMNS as $table => $columns) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if ($DB->record_exists($table, [$column => $userid])) {
                    $contextlist->add_system_context();
                    return $contextlist;
                }
            }
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $dbman = $DB->get_manager();
        $context = \context_system::instance();

        if ($dbman->table_exists(self::TABLE_USERS)) {
            $rows = $DB->get_records(self::TABLE_USERS, ['userid' => $userid]);
            if (!empty($rows)) {
                writer::with_context($context)->export_data(
                    ['sentientia_learningpath'],
                    (object) ['assignments' => array_values((array) $rows)]);
            }
            // Enrolments this user made for other people: the path and the time, never the learner.
            $made = $DB->get_records_select(self::TABLE_USERS, 'enrolledby = :u AND userid <> :u2',
                ['u' => $userid, 'u2' => $userid], 'id ASC', 'id, pathid, timecreated');
            if (!empty($made)) {
                writer::with_context($context)->export_data(
                    ['sentientia_learningpath', 'enrolments_made'],
                    (object) ['enrolments' => array_values((array) $made)]);
            }
        }
        if ($dbman->table_exists(self::TABLE_STATUS)) {
            $statusrows = $DB->get_records(self::TABLE_STATUS, ['userid' => $userid]);
            if (!empty($statusrows)) {
                writer::with_context($context)->export_data(
                    ['sentientia_learningpath', 'course_status'],
                    (object) ['course_status' => array_values((array) $statusrows)]);
            }
        }
        if ($dbman->table_exists(self::TABLE_ADAPTIVE)) {
            $logrows = $DB->get_records(self::TABLE_ADAPTIVE, ['userid' => $userid]);
            if (!empty($logrows)) {
                writer::with_context($context)->export_data(
                    ['sentientia_learningpath', 'adaptive_log'],
                    (object) ['decisions' => array_values((array) $logrows)]);
            }
        }
        // Paths and path courses this user created or last changed.
        $authored = [];
        foreach ([self::TABLE_PATHS => 'id, name, timecreated, timemodified',
                  self::TABLE_COURSES => 'id, pathid, courseid, timecreated, timemodified'] as $table => $fields) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            $rows = $DB->get_records_select($table, 'usercreated = :c OR usermodified = :m',
                ['c' => $userid, 'm' => $userid], 'id ASC', $fields);
            if (!empty($rows)) {
                $authored[$table] = array_values((array) $rows);
            }
        }
        if (!empty($authored)) {
            writer::with_context($context)->export_data(
                ['sentientia_learningpath', 'authored'], (object) $authored);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $dbman = $DB->get_manager();
        foreach ([self::TABLE_USERS, self::TABLE_ADAPTIVE, self::TABLE_STATUS] as $table) {
            if ($dbman->table_exists($table)) {
                $DB->delete_records($table);
            }
        }
        // The paths and their courses are configuration: they stay, without the people who made them.
        foreach ([self::TABLE_PATHS, self::TABLE_COURSES] as $table) {
            if ($dbman->table_exists($table)) {
                foreach (['usercreated', 'usermodified'] as $column) {
                    $DB->set_field_select($table, $column, 0, "$column <> 0");
                }
            }
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = (int) $contextlist->get_user()->id;
        $dbman = $DB->get_manager();
        foreach ([self::TABLE_USERS, self::TABLE_ADAPTIVE, self::TABLE_STATUS] as $table) {
            if ($dbman->table_exists($table)) {
                $DB->delete_records($table, ['userid' => $userid]);
            }
        }
        self::clear_actor_columns([$userid], false);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $dbman = $DB->get_manager();
        foreach (self::USER_COLUMNS as $table => $columns) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            foreach ($columns as $column) {
                $userids = $DB->get_fieldset_select($table, 'DISTINCT ' . $column, $column . ' > 0');
                if (!empty($userids)) {
                    $userlist->add_users($userids);
                }
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        $dbman = $DB->get_manager();
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        foreach ([self::TABLE_USERS, self::TABLE_ADAPTIVE, self::TABLE_STATUS] as $table) {
            if ($dbman->table_exists($table)) {
                $DB->delete_records_select($table, "userid $insql", $inparams);
            }
        }
        self::clear_actor_columns(array_map('intval', $userids), false);
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        // Keep local_sentientia_learningpath_users: it is the path enrolment
        // and COMPLETION record (status 2 + timecompleted), and paths carry
        // time-bounded compliance windows. It stays keyed to the anonymised
        // user row, as does local_sentientia_lp_course_status. The adaptive
        // log is kept for the same reason (its quiz scores are the audit trail
        // of each pivot), minus its free text.
        $userid = (int) $contextlist->get_user()->id;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::TABLE_ADAPTIVE)) {
            $DB->set_field(self::TABLE_ADAPTIVE, 'decision_notes', null, ['userid' => $userid]);
        }
        // Who created, edited or enrolled is the actor's personal data: those columns lose the user.
        self::clear_actor_columns([$userid], true);
    }

    /**
     * Set the actor columns that point at these users to 0 (unknown).
     *
     * The userid columns are never touched here. enrolledby is cleared only where it points at somebody other
     * than the enrolled learner: a self-enrolled row stays keyed to its own (anonymised) user when $keepself.
     * When the user row itself is being deleted, the learner's rows are already gone.
     *
     * @param int[] $userids
     * @param bool $keepself Keep enrolledby on rows the user enrolled themselves.
     * @return void
     */
    private static function clear_actor_columns(array $userids, bool $keepself): void {
        global $DB;
        $userids = array_values(array_filter($userids, static fn(int $id): bool => $id > 0));
        if (!$userids) {
            return;
        }
        $dbman = $DB->get_manager();
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'act');
        foreach ([self::TABLE_PATHS, self::TABLE_COURSES, self::TABLE_STATUS] as $table) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            foreach (['usercreated', 'usermodified'] as $column) {
                $DB->set_field_select($table, $column, 0, "$column $insql", $inparams);
            }
        }
        if ($dbman->table_exists(self::TABLE_USERS)) {
            $select = "enrolledby $insql" . ($keepself ? ' AND enrolledby <> userid' : '');
            $DB->set_field_select(self::TABLE_USERS, 'enrolledby', 0, $select, $inparams);
        }
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) {
                return true;
            }
        }
        return false;
    }
}
