<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_reports\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — GDPR / DPDP metadata + export + delete.
 *
 * Table that carries user data:
 *   - local_sentientia_reports : created_by — the user who authored a
 *                                saved report definition
 *
 * Report definitions are organisational assets shared across admins, so
 * deletion ANONYMISES created_by (sets it to 0) instead of removing the
 * report itself.
 *
 * @package local_sentientia_reports
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_reports',
            [
                'created_by'  => 'privacy:metadata:reports:created_by',
                'name'        => 'privacy:metadata:reports:name',
                'report_type' => 'privacy:metadata:reports:report_type',
                'timecreated' => 'privacy:metadata:reports:timecreated',
            ],
            'privacy:metadata:reports'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Saved reports are system-context admin artefacts.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('created_by',
            "SELECT created_by FROM {local_sentientia_reports}
              WHERE created_by > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $reports = $DB->get_records('local_sentientia_reports',
                ['created_by' => $userid], 'timecreated ASC');
            $report_data = [];
            foreach ($reports as $r) {
                $report_data[] = [
                    'name'        => $r->name,
                    'report_type' => $r->report_type,
                    'timecreated' => userdate((int) $r->timecreated),
                ];
            }
            if (!empty($report_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_reports'),
                     'reports_created'],
                    (object) ['reports' => $report_data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        // Anonymise, don't delete — report definitions are shared
        // organisational assets; only the author id is personal.
        $DB->set_field_select('local_sentientia_reports',
            'created_by', 0, 'created_by > 0', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->set_field('local_sentientia_reports',
                'created_by', 0, ['created_by' => $userid]);
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
        $DB->set_field_select('local_sentientia_reports',
            'created_by', 0, "created_by $insql", $params);
    }
}
