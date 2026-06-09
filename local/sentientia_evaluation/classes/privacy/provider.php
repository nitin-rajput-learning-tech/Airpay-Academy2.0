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
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_sentientia_evaluation_responses',
                ['userid' => $userid])) {
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
        if (empty($rows)) {
            return;
        }
        $data = [];
        foreach ($rows as $r) {
            $data[] = (object) [
                'evaluation' => format_string($r->evaluation_name ?? '(deleted)'),
                'submitted'  => userdate($r->timesubmitted),
                'answers'    => $r->response_data,
            ];
        }
        \core_privacy\local\request\writer::with_context(
            \context_system::instance())
            ->export_data(['sentientia_evaluation_responses'],
                (object) ['responses' => $data]);
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        // Only delete the response rows; keep evaluation + question metadata.
        $DB->delete_records('local_sentientia_evaluation_responses');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $DB->delete_records('local_sentientia_evaluation_responses',
            ['userid' => $contextlist->get_user()->id]);
    }

    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $DB->get_fieldset_select(
            'local_sentientia_evaluation_responses', 'DISTINCT userid',
            'userid > 0');
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
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel === CONTEXT_SYSTEM) return true;
        }
        return false;
    }
}
