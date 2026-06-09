<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase Z.1 (2026-05-08) — privacy provider for sentientia_notifications.

namespace local_sentientia_notifications\privacy;

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
        $collection->add_database_table('local_sentientia_notif_log',
            [
                'ruleid'      => 'privacy:metadata:log:ruleid',
                'userid'      => 'privacy:metadata:log:userid',
                'courseid'    => 'privacy:metadata:log:courseid',
                'channel'     => 'privacy:metadata:log:channel',
                'subject'     => 'privacy:metadata:log:subject',
                'message'     => 'privacy:metadata:log:message',
                'status'      => 'privacy:metadata:log:status',
                'timecreated' => 'privacy:metadata:log:timecreated',
                'timeread'    => 'privacy:metadata:log:timeread',
            ],
            'privacy:metadata:log');
        $collection->add_database_table('local_sentientia_notif_prefs',
            [
                'userid'              => 'privacy:metadata:prefs:userid',
                'channel_inapp'       => 'privacy:metadata:prefs:channel_inapp',
                'channel_email'       => 'privacy:metadata:prefs:channel_email',
                'channel_push'        => 'privacy:metadata:prefs:channel_push',
                'digest_frequency'    => 'privacy:metadata:prefs:digest_frequency',
                'disabled_rule_types' => 'privacy:metadata:prefs:disabled_rule_types',
                'quiet_hours_start'   => 'privacy:metadata:prefs:quiet_hours_start',
                'quiet_hours_end'     => 'privacy:metadata:prefs:quiet_hours_end',
                'timemodified'        => 'privacy:metadata:prefs:timemodified',
            ],
            'privacy:metadata:prefs');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_sentientia_notif_log', ['userid' => $userid])
            || $DB->record_exists('local_sentientia_notif_prefs', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $userid = $contextlist->get_user()->id;

        $log = $DB->get_records('local_sentientia_notif_log',
            ['userid' => $userid], 'timecreated DESC',
            'id, ruleid, channel, subject, status, timecreated, timeread');
        $prefs = $DB->get_record('local_sentientia_notif_prefs', ['userid' => $userid]);

        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['sentientia_notifications'],
                (object) [
                    'log_count' => count($log),
                    'log'       => array_values((array) $log),
                    'prefs'     => $prefs,
                ]);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_sentientia_notif_log');
        $DB->delete_records('local_sentientia_notif_prefs');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_notif_log', ['userid' => $uid]);
        $DB->delete_records('local_sentientia_notif_prefs', ['userid' => $uid]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $log_users = $DB->get_fieldset_select('local_sentientia_notif_log',
            'DISTINCT userid', 'userid > 0');
        $pref_users = $DB->get_fieldset_select('local_sentientia_notif_prefs',
            'DISTINCT userid', 'userid > 0');
        $userids = array_unique(array_merge((array) $log_users, (array) $pref_users));
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
        $DB->delete_records_select('local_sentientia_notif_log', "userid $insql", $inparams);
        $DB->delete_records_select('local_sentientia_notif_prefs', "userid $insql", $inparams);
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}
