<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\privacy;

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
 *   - local_sentientia_exams_remind_sent
 *       subject rows deleted on erasure
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
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_exams_remind_sent',
            [
                'userid' => 'privacy:metadata:exams_remind_sent:userid',
                'examid' => 'privacy:metadata:exams_remind_sent:examid',
                'days_before_deadline' => 'privacy:metadata:exams_remind_sent:days_before_deadline',
                'deadline_ts' => 'privacy:metadata:exams_remind_sent:deadline_ts',
                'timesent' => 'privacy:metadata:exams_remind_sent:timesent',
            ],
            'privacy:metadata:exams_remind_sent'
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
        $found = $found || $DB->record_exists('local_sentientia_exams_remind_sent', ['userid' => $userid]);

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
            "SELECT userid FROM {local_sentientia_exams_remind_sent} WHERE userid > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        $root = get_string('pluginname', 'local_sentientia_exams');

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // local_sentientia_exams_remind_sent
            $examreminders = $DB->get_records_sql(
                "SELECT id, userid, examid, days_before_deadline, deadline_ts, timesent
                   FROM {local_sentientia_exams_remind_sent}
                  WHERE userid = :u0
               ORDER BY timesent ASC",
                ['u0' => $userid]);

            if (!empty($examreminders)) {
                $rows = [];
                foreach ($examreminders as $r) {
                    $rows[] = [
                        'userid' => $r->userid,
                        'examid' => $r->examid,
                        'days_before_deadline' => $r->days_before_deadline,
                        'deadline_ts' => empty($r->deadline_ts) ? null : userdate((int) $r->deadline_ts),
                        'timesent' => empty($r->timesent) ? null : userdate((int) $r->timesent),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:exams_remind_sent', 'local_sentientia_exams')],
                    (object) ['examreminders' => $rows]
                );
            }

        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('local_sentientia_exams_remind_sent', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            $DB->delete_records('local_sentientia_exams_remind_sent', ['userid' => $userid]);
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

        $DB->delete_records_select('local_sentientia_exams_remind_sent', "userid $insql", $params);
    }
}
