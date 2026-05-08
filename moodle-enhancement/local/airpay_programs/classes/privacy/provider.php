<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs\privacy;

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
        $collection->add_database_table('local_airpay_programs_users',
            [
                'programid'      => 'privacy:metadata:enrol:programid',
                'userid'         => 'privacy:metadata:enrol:userid',
                'currentlevelid' => 'privacy:metadata:enrol:currentlevelid',
                'status'         => 'privacy:metadata:enrol:status',
                'timecreated'    => 'privacy:metadata:enrol:timecreated',
                'timecompleted'  => 'privacy:metadata:enrol:timecompleted',
            ],
            'privacy:metadata:enrol');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_airpay_programs_users', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $rows = $DB->get_records('local_airpay_programs_users',
            ['userid' => $contextlist->get_user()->id]);
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['airpay_programs'],
                (object) ['enrolments' => array_values((array) $rows)]);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_airpay_programs_users');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $DB->delete_records('local_airpay_programs_users',
            ['userid' => $contextlist->get_user()->id]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $DB->get_fieldset_select('local_airpay_programs_users',
            'DISTINCT userid', 'userid > 0');
        if (!empty($userids)) $userlist->add_users($userids);
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_airpay_programs_users',
            "userid $insql", $inparams);
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}
