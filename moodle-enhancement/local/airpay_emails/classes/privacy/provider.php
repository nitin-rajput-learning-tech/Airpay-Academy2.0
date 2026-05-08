<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_emails\privacy;

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
        $collection->add_database_table('local_airpay_email_log',
            [
                'userid'       => 'privacy:metadata:emaillog:userid',
                'subject'      => 'privacy:metadata:emaillog:subject',
                'recipient'    => 'privacy:metadata:emaillog:recipient',
                'status'       => 'privacy:metadata:emaillog:status',
                'timecreated'  => 'privacy:metadata:emaillog:timecreated',
            ],
            'privacy:metadata:emaillog');
        $collection->add_database_table('local_airpay_email_prefs',
            [
                'userid'       => 'privacy:metadata:emailprefs:userid',
                'timemodified' => 'privacy:metadata:emailprefs:timemodified',
            ],
            'privacy:metadata:emailprefs');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $hit = false;
        if ($DB->get_manager()->table_exists('local_airpay_email_log')
            && $DB->record_exists('local_airpay_email_log', ['userid' => $userid])) {
            $hit = true;
        }
        if (!$hit && $DB->get_manager()->table_exists('local_airpay_email_prefs')
            && $DB->record_exists('local_airpay_email_prefs', ['userid' => $userid])) {
            $hit = true;
        }
        if ($hit) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        $payload = ['userid' => $uid];
        if ($DB->get_manager()->table_exists('local_airpay_email_log')) {
            $payload['log'] = array_values(
                (array) $DB->get_records('local_airpay_email_log',
                    ['userid' => $uid], 'timecreated DESC'));
        }
        if ($DB->get_manager()->table_exists('local_airpay_email_prefs')) {
            $payload['prefs'] = $DB->get_record('local_airpay_email_prefs',
                ['userid' => $uid]);
        }
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['airpay_emails'], (object) $payload);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        if ($DB->get_manager()->table_exists('local_airpay_email_log')) {
            $DB->delete_records('local_airpay_email_log');
        }
        if ($DB->get_manager()->table_exists('local_airpay_email_prefs')) {
            $DB->delete_records('local_airpay_email_prefs');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) return;
        $uid = $contextlist->get_user()->id;
        if ($DB->get_manager()->table_exists('local_airpay_email_log')) {
            $DB->delete_records('local_airpay_email_log', ['userid' => $uid]);
        }
        if ($DB->get_manager()->table_exists('local_airpay_email_prefs')) {
            $DB->delete_records('local_airpay_email_prefs', ['userid' => $uid]);
        }
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = [];
        if ($DB->get_manager()->table_exists('local_airpay_email_log')) {
            $userids = array_merge($userids,
                (array) $DB->get_fieldset_select('local_airpay_email_log',
                    'DISTINCT userid', 'userid > 0'));
        }
        if ($DB->get_manager()->table_exists('local_airpay_email_prefs')) {
            $userids = array_merge($userids,
                (array) $DB->get_fieldset_select('local_airpay_email_prefs',
                    'DISTINCT userid', 'userid > 0'));
        }
        $userids = array_unique($userids);
        if (!empty($userids)) $userlist->add_users($userids);
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        if ($DB->get_manager()->table_exists('local_airpay_email_log')) {
            $DB->delete_records_select('local_airpay_email_log',
                "userid $insql", $inparams);
        }
        if ($DB->get_manager()->table_exists('local_airpay_email_prefs')) {
            $DB->delete_records_select('local_airpay_email_prefs',
                "userid $insql", $inparams);
        }
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}
