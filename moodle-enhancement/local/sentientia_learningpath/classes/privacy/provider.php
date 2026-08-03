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

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** Per-path enrolment / completion table (userid-keyed). */
    private const TABLE_USERS = 'local_sentientia_learningpath_users';
    /** Adaptive-journey decision log (userid-keyed), added 2026061600. */
    private const TABLE_ADAPTIVE = 'local_sentientia_lp_adaptive_log';

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE_USERS,
            [
                'pathid'        => 'privacy:metadata:lp:pathid',
                'userid'        => 'privacy:metadata:lp:userid',
                'status'        => 'privacy:metadata:lp:status',
                'timecreated'   => 'privacy:metadata:lp:timecreated',
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
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $dbman = $DB->get_manager();
        $hasdata = ($dbman->table_exists(self::TABLE_USERS)
                && $DB->record_exists(self::TABLE_USERS, ['userid' => $userid]))
            || ($dbman->table_exists(self::TABLE_ADAPTIVE)
                && $DB->record_exists(self::TABLE_ADAPTIVE, ['userid' => $userid]));
        if ($hasdata) {
            $contextlist->add_system_context();
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
        }
        if ($dbman->table_exists(self::TABLE_ADAPTIVE)) {
            $logrows = $DB->get_records(self::TABLE_ADAPTIVE, ['userid' => $userid]);
            if (!empty($logrows)) {
                writer::with_context($context)->export_data(
                    ['sentientia_learningpath', 'adaptive_log'],
                    (object) ['decisions' => array_values((array) $logrows)]);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::TABLE_USERS)) {
            $DB->delete_records(self::TABLE_USERS);
        }
        if ($dbman->table_exists(self::TABLE_ADAPTIVE)) {
            $DB->delete_records(self::TABLE_ADAPTIVE);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::TABLE_USERS)) {
            $DB->delete_records(self::TABLE_USERS, ['userid' => $userid]);
        }
        if ($dbman->table_exists(self::TABLE_ADAPTIVE)) {
            $DB->delete_records(self::TABLE_ADAPTIVE, ['userid' => $userid]);
        }
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $dbman = $DB->get_manager();
        foreach ([self::TABLE_USERS, self::TABLE_ADAPTIVE] as $table) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            $userids = $DB->get_fieldset_select($table, 'DISTINCT userid', 'userid > 0');
            if (!empty($userids)) {
                $userlist->add_users($userids);
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
        foreach ([self::TABLE_USERS, self::TABLE_ADAPTIVE] as $table) {
            if ($dbman->table_exists($table)) {
                $DB->delete_records_select($table, "userid $insql", $inparams);
            }
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
