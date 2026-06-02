<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace enrol_sentientiasub\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_course;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for enrol_sentientiasub (ADR-023).
 *
 * Stores one subscription row per (user, enrol instance). Data is scoped to the
 * course context of the enrol instance.
 *
 * @package enrol_sentientiasub
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('enrol_sentientiasub_subscription', [
            'userid'        => 'privacy:metadata:enrol_sentientiasub_subscription:userid',
            'status'        => 'privacy:metadata:enrol_sentientiasub_subscription:status',
            'amount'        => 'privacy:metadata:enrol_sentientiasub_subscription:amount',
            'ap_mandate_id' => 'privacy:metadata:enrol_sentientiasub_subscription:ap_mandate_id',
            'timecreated'   => 'privacy:metadata:enrol_sentientiasub_subscription:timecreated',
        ], 'privacy:metadata:enrol_sentientiasub_subscription');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {enrol_sentientiasub_subscription} s
                  JOIN {enrol} e ON e.id = s.enrolid
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :courselevel
                 WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $sql = "SELECT s.userid
                  FROM {enrol_sentientiasub_subscription} s
                  JOIN {enrol} e ON e.id = s.enrolid
                 WHERE e.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $sql = "SELECT s.*
                      FROM {enrol_sentientiasub_subscription} s
                      JOIN {enrol} e ON e.id = s.enrolid
                     WHERE e.courseid = :courseid AND s.userid = :userid";
            $records = $DB->get_records_sql($sql, ['courseid' => $context->instanceid, 'userid' => $userid]);
            foreach ($records as $r) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'enrol_sentientiasub'), (string) $r->id],
                    (object) [
                        'status'        => $r->status,
                        'scope'         => $r->scope,
                        'billingperiod' => $r->billingperiod,
                        'amount'        => $r->amount,
                        'currency'      => $r->currency,
                        'startedts'     => $r->startedts ? userdate($r->startedts) : '',
                        'cancelledts'   => $r->cancelledts ? userdate($r->cancelledts) : '',
                        'timecreated'   => userdate($r->timecreated),
                    ]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof context_course) {
            return;
        }
        $enrolids = $DB->get_fieldset_select('enrol', 'id',
            'courseid = :courseid AND enrol = :enrol',
            ['courseid' => $context->instanceid, 'enrol' => 'sentientiasub']);
        if (empty($enrolids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('enrol_sentientiasub_subscription', "enrolid $insql", $params);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $enrolids = $DB->get_fieldset_select('enrol', 'id',
                'courseid = :courseid AND enrol = :enrol',
                ['courseid' => $context->instanceid, 'enrol' => 'sentientiasub']);
            if (empty($enrolids)) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED);
            $params['userid'] = $userid;
            $DB->delete_records_select('enrol_sentientiasub_subscription',
                "enrolid $insql AND userid = :userid", $params);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $enrolids = $DB->get_fieldset_select('enrol', 'id',
            'courseid = :courseid AND enrol = :enrol',
            ['courseid' => $context->instanceid, 'enrol' => 'sentientiasub']);
        if (empty($enrolids)) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$enrolsql, $enrolparams] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, 'e');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $DB->delete_records_select('enrol_sentientiasub_subscription',
            "enrolid $enrolsql AND userid $usersql", array_merge($enrolparams, $userparams));
    }
}
