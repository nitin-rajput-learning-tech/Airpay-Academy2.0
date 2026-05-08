<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_airpay_lp_users',
            [
                'pathid'       => 'privacy:metadata:lp:pathid',
                'userid'       => 'privacy:metadata:lp:userid',
                'status'       => 'privacy:metadata:lp:status',
                'timecreated'  => 'privacy:metadata:lp:timecreated',
                'timemodified' => 'privacy:metadata:lp:timemodified',
            ],
            'privacy:metadata:lp');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->get_manager()->table_exists('local_airpay_lp_users')
            && $DB->record_exists('local_airpay_lp_users', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        if (!$DB->get_manager()->table_exists('local_airpay_lp_users')) return;
        $rows = $DB->get_records('local_airpay_lp_users',
            ['userid' => $contextlist->get_user()->id]);
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['airpay_learningpath'],
                (object) ['assignments' => array_values((array) $rows)]);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        if ($DB->get_manager()->table_exists('local_airpay_lp_users')) {
            $DB->delete_records('local_airpay_lp_users');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        if ($DB->get_manager()->table_exists('local_airpay_lp_users')) {
            $DB->delete_records('local_airpay_lp_users',
                ['userid' => $contextlist->get_user()->id]);
        }
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        if (!$DB->get_manager()->table_exists('local_airpay_lp_users')) return;
        $userids = $DB->get_fieldset_select('local_airpay_lp_users',
            'DISTINCT userid', 'userid > 0');
        if (!empty($userids)) $userlist->add_users($userids);
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        if (!$DB->get_manager()->table_exists('local_airpay_lp_users')) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_airpay_lp_users',
            "userid $insql", $inparams);
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}
