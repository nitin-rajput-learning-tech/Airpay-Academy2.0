<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_compliance_report\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider - GDPR / DPDP metadata, export and erasure.
 *
 * REPLACES A null_provider (2026-09-22).
 * -------------------------------------
 * This plugin previously declared `\core_privacy\local\metadata\
 * null_provider`, which is a positive assertion to Moodle's privacy registry
 * that it stores no personal data. That was not true: it owns the tables
 * listed below, each keyed on a user id. A subject access request returned
 * nothing from this plugin and an erasure request deleted nothing, in both
 * cases without any error - the registry simply reported the plugin as
 * holding no data.
 *
 * Tables this plugin owns:
 *   - local_compliance_snapshot
 *       subject rows deleted on erasure
 *   - local_compliance_exemptions
 *       subject rows deleted on erasure
 *       actor column(s) anonymised: approved_by
 *   - local_compliance_email_log
 *       subject rows deleted on erasure
 *   - local_compliance_courses
 *       configuration; author reference anonymised only
 *       actor column(s) anonymised: createdby
 *
 * OWNER versus ACTOR columns
 * --------------------------
 * A column that identifies the DATA SUBJECT has its rows deleted on erasure.
 * A column where the subject merely ACTED on someone else's record - an
 * approver, a creator, a decider - is ANONYMISED to 0 instead, because
 * deleting the row would destroy a third party's record or a shared
 * configuration row. Both are exported, so the subject sees everything held
 * about them either way.
 *
 * @package    local_sentientia_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_compliance_snapshot',
            [
                'userid' => 'privacy:metadata:compliance_snapshot:userid',
                'courseid' => 'privacy:metadata:compliance_snapshot:courseid',
                'costcenterid' => 'privacy:metadata:compliance_snapshot:costcenterid',
                'department_path' => 'privacy:metadata:compliance_snapshot:department_path',
                'status' => 'privacy:metadata:compliance_snapshot:status',
                'completion_date' => 'privacy:metadata:compliance_snapshot:completion_date',
                'progress_percent' => 'privacy:metadata:compliance_snapshot:progress_percent',
                'enrol_date' => 'privacy:metadata:compliance_snapshot:enrol_date',
                'deadline_date' => 'privacy:metadata:compliance_snapshot:deadline_date',
                'days_overdue' => 'privacy:metadata:compliance_snapshot:days_overdue',
                'matched_by' => 'privacy:metadata:compliance_snapshot:matched_by',
                'snapshot_date' => 'privacy:metadata:compliance_snapshot:snapshot_date',
            ],
            'privacy:metadata:compliance_snapshot'
        );

        $collection->add_database_table(
            'local_compliance_exemptions',
            [
                'userid' => 'privacy:metadata:compliance_exemptions:userid',
                'courseid' => 'privacy:metadata:compliance_exemptions:courseid',
                'reason' => 'privacy:metadata:compliance_exemptions:reason',
                'approved_by' => 'privacy:metadata:compliance_exemptions:approved_by',
                'expiry_date' => 'privacy:metadata:compliance_exemptions:expiry_date',
                'is_active' => 'privacy:metadata:compliance_exemptions:is_active',
                'timecreated' => 'privacy:metadata:compliance_exemptions:timecreated',
            ],
            'privacy:metadata:compliance_exemptions'
        );

        $collection->add_database_table(
            'local_compliance_email_log',
            [
                'userid' => 'privacy:metadata:compliance_email_log:userid',
                'courseid' => 'privacy:metadata:compliance_email_log:courseid',
                'email_type' => 'privacy:metadata:compliance_email_log:email_type',
                'sent_to' => 'privacy:metadata:compliance_email_log:sent_to',
                'timecreated' => 'privacy:metadata:compliance_email_log:timecreated',
            ],
            'privacy:metadata:compliance_email_log'
        );

        $collection->add_database_table(
            'local_compliance_courses',
            [
                'createdby' => 'privacy:metadata:compliance_courses:createdby',
            ],
            'privacy:metadata:compliance_courses'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        // All of this plugin's tables are site-level: they reference courses,
        // exams and orgs by id rather than living in a course context. The
        // system context is added only when the user actually appears, so a
        // user with no rows here is not offered an empty export section.
        $found = false;
        $found = $found || $DB->record_exists('local_compliance_snapshot', ['userid' => $userid]);
        $found = $found || $DB->record_exists('local_compliance_exemptions', ['userid' => $userid]);
        $found = $found || $DB->record_exists('local_compliance_exemptions', ['approved_by' => $userid]);
        $found = $found || $DB->record_exists('local_compliance_email_log', ['userid' => $userid]);
        $found = $found || $DB->record_exists('local_compliance_courses', ['createdby' => $userid]);

        if ($found) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_compliance_snapshot} WHERE userid > 0", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_compliance_exemptions} WHERE userid > 0", []);
        $userlist->add_from_sql('approved_by',
            "SELECT approved_by FROM {local_compliance_exemptions} WHERE approved_by > 0", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_compliance_email_log} WHERE userid > 0", []);
        $userlist->add_from_sql('createdby',
            "SELECT createdby FROM {local_compliance_courses} WHERE createdby > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        $root = get_string('pluginname', 'local_sentientia_compliance_report');

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // local_compliance_snapshot
            $compliancesnapshots = $DB->get_records_sql(
                "SELECT id, userid, courseid, costcenterid, department_path, status, completion_date, progress_percent, enrol_date, deadline_date, days_overdue, matched_by, snapshot_date
                   FROM {local_compliance_snapshot}
                  WHERE userid = :u0
               ORDER BY snapshot_date ASC",
                ['u0' => $userid]);

            if (!empty($compliancesnapshots)) {
                $rows = [];
                foreach ($compliancesnapshots as $r) {
                    $rows[] = [
                        'userid' => $r->userid,
                        'courseid' => $r->courseid,
                        'costcenterid' => $r->costcenterid,
                        'department_path' => $r->department_path,
                        'status' => $r->status,
                        'completion_date' => empty($r->completion_date) ? null : userdate((int) $r->completion_date),
                        'progress_percent' => $r->progress_percent,
                        'enrol_date' => empty($r->enrol_date) ? null : userdate((int) $r->enrol_date),
                        'deadline_date' => empty($r->deadline_date) ? null : userdate((int) $r->deadline_date),
                        'days_overdue' => $r->days_overdue,
                        'matched_by' => $r->matched_by,
                        'snapshot_date' => empty($r->snapshot_date) ? null : userdate((int) $r->snapshot_date),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:compliance_snapshot', 'local_sentientia_compliance_report')],
                    (object) ['compliancesnapshots' => $rows]
                );
            }

            // local_compliance_exemptions
            $complianceexemptions = $DB->get_records_sql(
                "SELECT id, userid, courseid, reason, approved_by, expiry_date, is_active, timecreated
                   FROM {local_compliance_exemptions}
                  WHERE userid = :u0 OR approved_by = :u1
               ORDER BY timecreated ASC",
                ['u0' => $userid, 'u1' => $userid]);

            if (!empty($complianceexemptions)) {
                $rows = [];
                foreach ($complianceexemptions as $r) {
                    $rows[] = [
                        'userid' => $r->userid,
                        'courseid' => $r->courseid,
                        'reason' => $r->reason,
                        'approved_by' => $r->approved_by,
                        'expiry_date' => empty($r->expiry_date) ? null : userdate((int) $r->expiry_date),
                        'is_active' => $r->is_active,
                        'timecreated' => empty($r->timecreated) ? null : userdate((int) $r->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:compliance_exemptions', 'local_sentientia_compliance_report')],
                    (object) ['complianceexemptions' => $rows]
                );
            }

            // local_compliance_email_log
            $complianceemaillog = $DB->get_records_sql(
                "SELECT id, userid, courseid, email_type, sent_to, timecreated
                   FROM {local_compliance_email_log}
                  WHERE userid = :u0
               ORDER BY timecreated ASC",
                ['u0' => $userid]);

            if (!empty($complianceemaillog)) {
                $rows = [];
                foreach ($complianceemaillog as $r) {
                    $rows[] = [
                        'userid' => $r->userid,
                        'courseid' => $r->courseid,
                        'email_type' => $r->email_type,
                        'sent_to' => $r->sent_to,
                        'timecreated' => empty($r->timecreated) ? null : userdate((int) $r->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:compliance_email_log', 'local_sentientia_compliance_report')],
                    (object) ['complianceemaillog' => $rows]
                );
            }

            // local_compliance_courses
            $compliancecoursesauthored = $DB->get_records_sql(
                "SELECT id, createdby
                   FROM {local_compliance_courses}
                  WHERE createdby = :u0
               ORDER BY timecreated ASC",
                ['u0' => $userid]);

            if (!empty($compliancecoursesauthored)) {
                $rows = [];
                foreach ($compliancecoursesauthored as $r) {
                    $rows[] = [
                        'createdby' => $r->createdby,
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:compliance_courses', 'local_sentientia_compliance_report')],
                    (object) ['compliancecoursesauthored' => $rows]
                );
            }

        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('local_compliance_snapshot', []);
        $DB->delete_records('local_compliance_exemptions', []);
        $DB->delete_records('local_compliance_email_log', []);
        // Configuration table: keep the rows, drop the author link.
        $DB->set_field('local_compliance_courses', 'createdby', 0, []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            $DB->delete_records('local_compliance_snapshot', ['userid' => $userid]);
            $DB->delete_records('local_compliance_exemptions', ['userid' => $userid]);
            // Anonymise rather than delete: the row is another
            // person's record or shared configuration.
            $DB->set_field('local_compliance_exemptions', 'approved_by', 0, ['approved_by' => $userid]);
            $DB->delete_records('local_compliance_email_log', ['userid' => $userid]);
            // Anonymise rather than delete: the row is another
            // person's record or shared configuration.
            $DB->set_field('local_compliance_courses', 'createdby', 0, ['createdby' => $userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->delete_records_select('local_compliance_snapshot', "userid $insql", $params);
        $DB->delete_records_select('local_compliance_exemptions', "userid $insql", $params);
        $DB->set_field_select('local_compliance_exemptions', 'approved_by', 0, "approved_by $insql", $params);
        $DB->delete_records_select('local_compliance_email_log', "userid $insql", $params);
        $DB->set_field_select('local_compliance_courses', 'createdby', 0, "createdby $insql", $params);
    }
}
