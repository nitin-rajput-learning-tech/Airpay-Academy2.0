<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_request\privacy;

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
        $collection->add_database_table('local_airpay_request', [
            'userid'        => 'privacy:metadata:local_airpay_request:userid',
            'courseid'      => 'privacy:metadata:local_airpay_request:courseid',
            'reason'        => 'privacy:metadata:local_airpay_request:reason',
            'decision_note' => 'privacy:metadata:local_airpay_request:decision_note',
            'approver_userid' => 'privacy:metadata:local_airpay_request:approver_userid',
            'status'        => 'privacy:metadata:local_airpay_request:status',
            'timecreated'   => 'privacy:metadata:local_airpay_request:timecreated',
        ], 'privacy:metadata:local_airpay_request');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        global $DB;
        // User appears either as requester or as approver/decider.
        $exists = $DB->record_exists_select('local_airpay_request',
            "userid = :u1 OR approver_userid = :u2 OR decided_by_userid = :u3",
            ['u1' => $userid, 'u2' => $userid, 'u3' => $userid]);
        if ($exists) $list->add_system_context();
        return $list;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) return;
        global $DB;
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {local_airpay_request}
              UNION SELECT DISTINCT approver_userid FROM {local_airpay_request}
                  WHERE approver_userid IS NOT NULL
              UNION SELECT DISTINCT decided_by_userid FROM {local_airpay_request}
                  WHERE decided_by_userid IS NOT NULL");
        $userlist->add_users($ids);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $rows = $DB->get_records_select('local_airpay_request',
            "userid = :u1 OR approver_userid = :u2 OR decided_by_userid = :u3",
            ['u1' => $userid, 'u2' => $userid, 'u3' => $userid],
            'timecreated DESC');
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'role'       => $r->userid == $userid ? 'requester' : 'approver',
                'course_id'  => $r->courseid,
                'status'     => $r->status,
                'reason'     => $r->userid == $userid ? $r->reason : '(other user\'s reason)',
                'decision_note' => $r->decision_note,
                'placed_on'  => userdate($r->timecreated),
                'decided_on' => $r->timedecided ? userdate($r->timedecided) : null,
            ];
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_airpay_request')],
            (object) ['requests' => $out]);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) return;
        // Redact reason + decision_note to "(redacted)" — preserve workflow record.
        global $DB;
        $DB->execute("UPDATE {local_airpay_request}
                         SET reason = '(redacted)', decision_note = '(redacted)'");
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
        $DB->execute("UPDATE {local_airpay_request}
                         SET reason = '(redacted)' WHERE userid = :u1",
            ['u1' => $userid]);
        $DB->execute("UPDATE {local_airpay_request}
                         SET decision_note = '(redacted)'
                       WHERE decided_by_userid = :u2",
            ['u2' => $userid]);
    }
}
