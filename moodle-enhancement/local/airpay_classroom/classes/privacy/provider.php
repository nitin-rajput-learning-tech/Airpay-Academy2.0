<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase Z.1 (2026-05-08) — privacy provider for airpay_classroom.
// Metadata-only provider — declares tables that contain user data.
// Full export/delete pathway is deferred to follow-up work.

namespace local_airpay_classroom\privacy;

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
        $collection->add_database_table('local_airpay_classroom_users',
            [
                'classroomid' => 'privacy:metadata:roster:classroomid',
                'userid'      => 'privacy:metadata:roster:userid',
                'timecreated' => 'privacy:metadata:roster:timecreated',
            ],
            'privacy:metadata:roster');
        $collection->add_database_table('local_airpay_classroom_attendance',
            [
                'sessionid'  => 'privacy:metadata:attendance:sessionid',
                'userid'     => 'privacy:metadata:attendance:userid',
                'status'     => 'privacy:metadata:attendance:status',
                'markedat'   => 'privacy:metadata:attendance:markedat',
                'markedby'   => 'privacy:metadata:attendance:markedby',
            ],
            'privacy:metadata:attendance');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_airpay_classroom_users', ['userid' => $userid])
            || $DB->record_exists('local_airpay_classroom_attendance', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $userid = $contextlist->get_user()->id;
        $roster = $DB->get_records('local_airpay_classroom_users',
            ['userid' => $userid]);
        $attendance = $DB->get_records('local_airpay_classroom_attendance',
            ['userid' => $userid]);
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['airpay_classroom'],
                (object) [
                    'roster_count'     => count($roster),
                    'roster'           => array_values((array) $roster),
                    'attendance_count' => count($attendance),
                    'attendance'       => array_values((array) $attendance),
                ]);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_airpay_classroom_users');
        $DB->delete_records('local_airpay_classroom_attendance');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        $DB->delete_records('local_airpay_classroom_users', ['userid' => $uid]);
        $DB->delete_records('local_airpay_classroom_attendance', ['userid' => $uid]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $u1 = $DB->get_fieldset_select('local_airpay_classroom_users',
            'DISTINCT userid', 'userid > 0');
        $u2 = $DB->get_fieldset_select('local_airpay_classroom_attendance',
            'DISTINCT userid', 'userid > 0');
        $userids = array_unique(array_merge((array) $u1, (array) $u2));
        if (!empty($userids)) {
            $userlist->add_users($userids);
        }
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_airpay_classroom_users',
            "userid $insql", $inparams);
        $DB->delete_records_select('local_airpay_classroom_attendance',
            "userid $insql", $inparams);
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}
