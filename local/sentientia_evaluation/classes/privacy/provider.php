<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase Z.1 (2026-05-08) — privacy provider for sentientia_evaluation.

namespace local_sentientia_evaluation\privacy;

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
        $collection->add_database_table(
            'local_sentientia_evaluation_responses',
            [
                'evaluationid'  => 'privacy:metadata:responses:evaluationid',
                'userid'        => 'privacy:metadata:responses:userid',
                'response_data' => 'privacy:metadata:responses:response_data',
                'timesubmitted' => 'privacy:metadata:responses:timesubmitted',
            ],
            'privacy:metadata:responses');

        // Added 2026-09-22 -- owned but undeclared. Both record that an
        // evaluation was aimed at a specific employee, which is personal data
        // whether or not they ever answered it.
        $collection->add_database_table(
            'local_sentientia_evaluation_triggers',
            [
                'userid'        => 'privacy:metadata:triggers:userid',
                'evaluationid'  => 'privacy:metadata:triggers:evaluationid',
                'trigger_event' => 'privacy:metadata:triggers:trigger_event',
                'status'        => 'privacy:metadata:triggers:status',
                'timefired'     => 'privacy:metadata:triggers:timefired',
            ],
            'privacy:metadata:triggers');

        $collection->add_database_table(
            'local_sentientia_evaluation_assign',
            [
                'userid'             => 'privacy:metadata:assign:userid',
                'evaluationid'       => 'privacy:metadata:assign:evaluationid',
                'trigger_event'      => 'privacy:metadata:assign:trigger_event',
                'status'             => 'privacy:metadata:assign:status',
                'assigned_by_userid' => 'privacy:metadata:assign:assigned_by_userid',
                'due_at'             => 'privacy:metadata:assign:due_at',
                'responded_at'       => 'privacy:metadata:assign:responded_at',
            ],
            'privacy:metadata:assign');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        // Every table delete_data_for_user() acts on, not just responses: an
        // employee assigned an evaluation they never answered holds trigger and
        // assign rows and nothing else. Until 2026-09-24 they got no context
        // back, so core privacy never asked this provider to export or erase
        // those rows while get_metadata() said they were handled.
        if ($DB->record_exists('local_sentientia_evaluation_responses', ['userid' => $userid])
                || $DB->record_exists('local_sentientia_evaluation_triggers', ['userid' => $userid])
                || $DB->record_exists('local_sentientia_evaluation_assign', ['userid' => $userid])
                || $DB->record_exists('local_sentientia_evaluation_assign',
                    ['assigned_by_userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.evaluationid, r.response_data, r.timesubmitted,
                    e.name AS evaluation_name
               FROM {local_sentientia_evaluation_responses} r
          LEFT JOIN {local_sentientia_evaluation} e ON e.id = r.evaluationid
              WHERE r.userid = :uid",
            ['uid' => $userid]);
        $data = [];
        foreach ($rows as $r) {
            $data[] = (object) [
                'evaluation' => format_string($r->evaluation_name ?? '(deleted)'),
                'submitted'  => userdate($r->timesubmitted),
                'answers'    => $r->response_data,
            ];
        }
        $writer = \core_privacy\local\request\writer::with_context(\context_system::instance());
        if ($data) {
            $writer->export_data(['sentientia_evaluation_responses'],
                (object) ['responses' => $data]);
        }

        // Added 2026-09-24: the evaluations aimed at this person, answered or
        // not. Where the subject was the ASSIGNER, only the count is exported:
        // those rows are about other people.
        $assigned = [];
        foreach ($DB->get_records('local_sentientia_evaluation_assign', ['userid' => $userid]) as $a) {
            $assigned[] = (object) [
                'evaluationid'  => (int) $a->evaluationid,
                'trigger_event' => $a->trigger_event,
                'status'        => $a->status,
                'due'           => $a->due_at ? userdate($a->due_at) : '',
                'responded'     => $a->responded_at ? userdate($a->responded_at) : '',
            ];
        }
        $triggers = [];
        foreach ($DB->get_records('local_sentientia_evaluation_triggers', ['userid' => $userid]) as $t) {
            $triggers[] = (object) [
                'evaluationid'  => (int) $t->evaluationid,
                'trigger_event' => $t->trigger_event,
                'status'        => $t->status,
                'fired'         => $t->timefired ? userdate($t->timefired) : '',
            ];
        }
        $assignedby = $DB->count_records('local_sentientia_evaluation_assign',
            ['assigned_by_userid' => $userid]);
        if ($assigned || $triggers || $assignedby) {
            $writer->export_data(['sentientia_evaluation_assignments'], (object) [
                'assigned_to_you'      => $assigned,
                'triggers'             => $triggers,
                'assignments_you_made' => $assignedby,
            ]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        // Only delete the response rows; keep evaluation + question metadata.
        $DB->delete_records('local_sentientia_evaluation_responses');
        $DB->delete_records('local_sentientia_evaluation_triggers');
        $DB->delete_records('local_sentientia_evaluation_assign');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        // Until 2026-09-24 $userid was never assigned here, so every condition
        // below compared against NULL: the subject's trigger and assign rows
        // were never erased, and - assigned_by_userid being nullable - the
        // anonymise step rewrote every OTHER system-assigned row instead.
        $userid = (int) $contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }
        $DB->delete_records('local_sentientia_evaluation_triggers',
            ['userid' => $userid]);
        $DB->delete_records('local_sentientia_evaluation_assign',
            ['userid' => $userid]);
        // The assigner acted on somebody else's row, so anonymise rather than
        // delete -- the assignee's record must survive.
        $DB->set_field('local_sentientia_evaluation_assign',
            'assigned_by_userid', 0, ['assigned_by_userid' => $userid]);
        $DB->delete_records('local_sentientia_evaluation_responses',
            ['userid' => $userid]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $DB->get_fieldset_select(
            'local_sentientia_evaluation_responses', 'DISTINCT userid',
            'userid > 0');
        // Added 2026-09-22: somebody assigned an evaluation but never
        // answered it still has data here.
        $userids = array_merge($userids,
            $DB->get_fieldset_select('local_sentientia_evaluation_triggers',
                'DISTINCT userid', 'userid > 0'),
            $DB->get_fieldset_select('local_sentientia_evaluation_assign',
                'DISTINCT userid', 'userid > 0'),
            $DB->get_fieldset_select('local_sentientia_evaluation_assign',
                'DISTINCT assigned_by_userid', 'assigned_by_userid > 0'));
        $userids = array_values(array_unique($userids));
        if (!empty($userids)) {
            $userlist->add_users($userids);
        }
    }

    public static function delete_data_for_users(
            \core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_sentientia_evaluation_responses',
            "userid $insql", $inparams);
        $DB->delete_records_select('local_sentientia_evaluation_triggers',
            "userid $insql", $inparams);
        $DB->delete_records_select('local_sentientia_evaluation_assign',
            "userid $insql", $inparams);
        $DB->set_field_select('local_sentientia_evaluation_assign',
            'assigned_by_userid', 0, "assigned_by_userid $insql", $inparams);
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}
