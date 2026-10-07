<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_users\legacy_history;

/**
 * Privacy provider for local_sentientia_users.
 *
 * Until 2026-10-01 this plugin declared \core_privacy\local\metadata\null_provider: a positive claim to Moodle's
 * privacy registry that it stores no personal data. That was false. The HRMS sync log
 * (local_sentientia_users_sync_errors) stores the e-mail address, employee code, username and name of every
 * rejected CSV line, and the sync runs store who uploaded them; the BizLMS import (ADR-032) adds earlier
 * training records (learner id, employee id, name) and login days (user id and day). A subject-access request
 * returned nothing from this plugin and an erasure request erased nothing, both reporting success.
 *
 * The plugin also extends the core {user} table with open_* columns; those are exported and erased by
 * core_user and are not repeated here.
 *
 * What an erasure request does (signed decision users.erasure_treatment = anonymise): the imported history is
 * KEPT and the person removed from it. See legacy_history for the details, including the one exception (login
 * days are deleted; the owner's written decision users.logindays_erasure = delete was signed 2026-10-07).
 *
 * Everything is held at system context.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            legacy_history::RUNS,
            [
                'usercreated' => 'privacy:metadata:field:actor',
                'filename' => 'privacy:metadata:field:filename',
                'timecreated' => 'privacy:metadata:field:time',
            ],
            'privacy:metadata:sync_runs'
        );

        $collection->add_database_table(
            legacy_history::ERRORS,
            [
                'email' => 'privacy:metadata:field:email',
                'employee_code' => 'privacy:metadata:field:employee_code',
                'username' => 'privacy:metadata:field:username',
                'firstname' => 'privacy:metadata:field:name',
                'lastname' => 'privacy:metadata:field:name',
                'error_message' => 'privacy:metadata:field:message',
                'mandatory_fields' => 'privacy:metadata:field:message',
                'modified_by' => 'privacy:metadata:field:actor',
                'timecreated' => 'privacy:metadata:field:time',
            ],
            'privacy:metadata:sync_errors'
        );

        $collection->add_database_table(
            legacy_history::TRANSCRIPT,
            [
                'userid' => 'privacy:metadata:field:userid',
                'employee_id' => 'privacy:metadata:field:employee_code',
                'learner_name' => 'privacy:metadata:field:name',
                'title' => 'privacy:metadata:field:training',
                'training_type' => 'privacy:metadata:field:training',
                'objectref' => 'privacy:metadata:field:training',
                'location' => 'privacy:metadata:field:training',
                'courseid' => 'privacy:metadata:field:training',
                'status' => 'privacy:metadata:field:training',
                'status_raw' => 'privacy:metadata:field:training',
                'completion_date_raw' => 'privacy:metadata:field:training',
                'score_raw' => 'privacy:metadata:field:training',
                'hours_raw' => 'privacy:metadata:field:training',
                'timecompleted' => 'privacy:metadata:field:training',
                'score' => 'privacy:metadata:field:training',
                'hours' => 'privacy:metadata:field:training',
                'costcenterid' => 'privacy:metadata:field:tenant',
                'open_path' => 'privacy:metadata:field:tenant',
                'usercreated' => 'privacy:metadata:field:actor',
                'usermodified' => 'privacy:metadata:field:actor',
                'timecreated' => 'privacy:metadata:field:time',
                'timemodified' => 'privacy:metadata:field:time',
            ],
            'privacy:metadata:transcript'
        );

        $collection->add_database_table(
            legacy_history::LOGINDAYS,
            [
                'userid' => 'privacy:metadata:field:userid',
                'logindate' => 'privacy:metadata:field:logindate',
                'source' => 'privacy:metadata:field:source',
                'timecreated' => 'privacy:metadata:field:time',
                'timemodified' => 'privacy:metadata:field:time',
            ],
            'privacy:metadata:logindays'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (legacy_history::holds_data_for_user($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        // The people a row names by id, and the people a rejected line or an unmatched transcript row names by
        // e-mail, username or employee code: the same two routes get_contexts_for_userid() takes (legacy_history
        // builds both, so the two halves of the privacy API agree).
        foreach (legacy_history::user_list_sql() as [$sql, $params]) {
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            foreach (legacy_history::export_data($userid) as $section => $rows) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_users'), $section],
                    (object) ['rows' => $rows]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        // History is kept; only the people are removed from it.
        legacy_history::anonymise_all();
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                legacy_history::anonymise_users([$userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        legacy_history::anonymise_users($userlist->get_userids());
    }
}
