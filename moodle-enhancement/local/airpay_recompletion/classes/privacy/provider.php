<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_recompletion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_airpay_recompletion_rules', [],
            'privacy:metadata:local_airpay_recompletion_rules');
        $collection->add_database_table('local_airpay_recompletion_history', [
            'userid'   => 'privacy:metadata:local_airpay_recompletion_history:userid',
            'courseid' => 'privacy:metadata:local_airpay_recompletion_history:courseid',
            'reason'   => 'privacy:metadata:local_airpay_recompletion_history:reason',
        ], 'privacy:metadata:local_airpay_recompletion_history');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        global $DB;
        if ($DB->record_exists('local_airpay_recompletion_history', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) return;
        global $DB;
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {local_airpay_recompletion_history}");
        $userlist->add_users($ids);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $rows = $DB->get_records('local_airpay_recompletion_history',
            ['userid' => $userid], 'timecreated DESC');
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'course_id'    => $r->courseid,
                'reason'       => $r->reason,
                'reset_at'     => userdate($r->timecreated),
                'previous_completion' => $r->previous_timecompleted
                    ? userdate($r->previous_timecompleted) : null,
                'dryrun'       => (bool) $r->dryrun,
            ];
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_airpay_recompletion')],
            (object) ['recompletion_history' => $out]);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) return;
        // Compliance: history is required audit. Redact only.
        global $DB;
        $DB->execute("UPDATE {local_airpay_recompletion_history} SET userid = 0");
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::redact_for_user($contextlist->get_user()->id);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $u) {
            self::redact_for_user((int) $u);
        }
    }

    private static function redact_for_user(int $userid): void {
        global $DB;
        // Redact userid (= 0 = "(deleted)"), preserve courseid + reason
        // + reset_at for compliance retention.
        $DB->execute("UPDATE {local_airpay_recompletion_history}
                         SET userid = 0 WHERE userid = :uid",
            ['uid' => $userid]);
    }
}
