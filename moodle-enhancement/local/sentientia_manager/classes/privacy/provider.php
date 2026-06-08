<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_manager\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_sentientia_manager.
 *
 * Stores PII in:
 * - mgr_requests:    userid (requester), managerid (assignee), decided_by
 * - mgr_allocations: managerid (creator), userid (target)
 *
 * **Retention policy:** delete the user's rows where they appear as a
 * requester or target; anonymise managerid/decided_by references on
 * shared rows (other parties' data must be preserved).
 *
 * @package local_sentientia_manager
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_mgr_requests', [
            'userid'         => 'privacy:metadata:requests:userid',
            'courseid'       => 'privacy:metadata:requests:courseid',
            'managerid'      => 'privacy:metadata:requests:managerid',
            'status'         => 'privacy:metadata:requests:status',
            'reason'         => 'privacy:metadata:requests:reason',
            'decision_reason' => 'privacy:metadata:requests:decision_reason',
            'decided_by'     => 'privacy:metadata:requests:decided_by',
            'decided_at'     => 'privacy:metadata:requests:decided_at',
            'timecreated'    => 'privacy:metadata:requests:timecreated',
        ], 'privacy:metadata:requests');

        $collection->add_database_table('local_sentientia_mgr_allocations', [
            'managerid'      => 'privacy:metadata:allocations:managerid',
            'userid'         => 'privacy:metadata:allocations:userid',
            'courseid'       => 'privacy:metadata:allocations:courseid',
            'due_date'       => 'privacy:metadata:allocations:due_date',
            'status'         => 'privacy:metadata:allocations:status',
            'note'           => 'privacy:metadata:allocations:note',
            'timecreated'    => 'privacy:metadata:allocations:timecreated',
        ], 'privacy:metadata:allocations');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // System context only.
        $sql = "SELECT id FROM {context} WHERE contextlevel = :sys";
        $contextlist->add_from_sql($sql, ['sys' => CONTEXT_SYSTEM]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $sql = "SELECT userid FROM {local_sentientia_mgr_requests}
                 UNION
                SELECT managerid FROM {local_sentientia_mgr_requests} WHERE managerid > 0
                 UNION
                SELECT decided_by FROM {local_sentientia_mgr_requests} WHERE decided_by > 0
                 UNION
                SELECT userid FROM {local_sentientia_mgr_allocations}
                 UNION
                SELECT managerid FROM {local_sentientia_mgr_allocations} WHERE managerid > 0";
        $userlist->add_from_sql('userid', $sql, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        // Requests where I'm the requester / manager / decider.
        $requests = $DB->get_records_sql("
            SELECT r.*, c.fullname AS coursename
              FROM {local_sentientia_mgr_requests} r
         LEFT JOIN {course} c ON c.id = r.courseid
             WHERE r.userid = :u1 OR r.managerid = :u2 OR r.decided_by = :u3
          ORDER BY r.timecreated DESC",
            ['u1' => $userid, 'u2' => $userid, 'u3' => $userid]);
        if (!empty($requests)) {
            $entries = array_map(fn($r) => (object) [
                'role_in_event'   => self::request_role($r, $userid),
                'course'          => format_string((string) ($r->coursename ?? '')),
                'status'          => $r->status,
                'reason'          => (string) ($r->reason ?? ''),
                'decision_reason' => (string) ($r->decision_reason ?? ''),
                'time_created'    => (int) $r->timecreated,
                'time_decided'    => $r->decided_at ? (int) $r->decided_at : null,
            ], $requests);
            writer::with_context($context)
                ->export_data(['Airpay Manager — enrolment requests'],
                    (object) ['requests' => array_values($entries)]);
        }

        // Allocations where I'm the manager or the target.
        $allocations = $DB->get_records_sql("
            SELECT a.*, c.fullname AS coursename
              FROM {local_sentientia_mgr_allocations} a
         LEFT JOIN {course} c ON c.id = a.courseid
             WHERE a.managerid = :u1 OR a.userid = :u2
          ORDER BY a.timecreated DESC",
            ['u1' => $userid, 'u2' => $userid]);
        if (!empty($allocations)) {
            $entries = array_map(fn($a) => (object) [
                'role_in_event' => ((int) $a->managerid === $userid) ? 'manager' : 'recipient',
                'course'        => format_string((string) ($a->coursename ?? '')),
                'status'        => $a->status,
                'note'          => (string) ($a->note ?? ''),
                'due_date'      => $a->due_date ? (int) $a->due_date : null,
                'time_created'  => (int) $a->timecreated,
            ], $allocations);
            writer::with_context($context)
                ->export_data(['Airpay Manager — course allocations'],
                    (object) ['allocations' => array_values($entries)]);
        }
    }

    private static function request_role(\stdClass $r, int $userid): string {
        if ((int) $r->userid === $userid)     return 'requester';
        if ((int) $r->managerid === $userid)  return 'manager';
        if ((int) $r->decided_by === $userid) return 'decider';
        return 'unknown';
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_sentientia_mgr_requests');
        $DB->delete_records('local_sentientia_mgr_allocations');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Delete requests where this user is the requester (their data).
        $DB->delete_records('local_sentientia_mgr_requests', ['userid' => $userid]);
        // Anonymise rows where they were manager or decider on others' requests.
        $DB->execute("UPDATE {local_sentientia_mgr_requests}
                         SET managerid = 0, decision_reason = NULL
                       WHERE managerid = :u", ['u' => $userid]);
        $DB->execute("UPDATE {local_sentientia_mgr_requests}
                         SET decided_by = NULL
                       WHERE decided_by = :u", ['u' => $userid]);

        // Delete allocations where this user is the recipient.
        $DB->delete_records('local_sentientia_mgr_allocations', ['userid' => $userid]);
        // Anonymise allocations they CREATED for others.
        $DB->execute("UPDATE {local_sentientia_mgr_allocations}
                         SET managerid = 0, note = NULL
                       WHERE managerid = :u", ['u' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->execute("DELETE FROM {local_sentientia_mgr_requests} WHERE userid $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_mgr_requests}
                         SET managerid = 0, decision_reason = NULL
                       WHERE managerid $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_mgr_requests}
                         SET decided_by = NULL
                       WHERE decided_by $insql", $inparams);
        $DB->execute("DELETE FROM {local_sentientia_mgr_allocations} WHERE userid $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_mgr_allocations}
                         SET managerid = 0, note = NULL
                       WHERE managerid $insql", $inparams);
    }
}
