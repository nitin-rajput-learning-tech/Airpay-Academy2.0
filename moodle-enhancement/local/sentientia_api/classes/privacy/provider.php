<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_sentientia_api.
 *
 * The plugin stores: an append-only request log (userid + endpoint + status),
 * and rate-limit counters (userid + hit count). Both are keyed by user id and
 * therefore constitute personal data under GDPR. LTI registrations + nonces
 * are configuration/transient and hold no user PII.
 *
 * @package local_sentientia_api
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_api_log', [
            'userid'       => 'privacy:metadata:log:userid',
            'endpoint'     => 'privacy:metadata:log:endpoint',
            'status'       => 'privacy:metadata:log:status',
            'timecreated'  => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');

        $collection->add_database_table('local_sentientia_api_rate', [
            'userid'       => 'privacy:metadata:rate:userid',
            'hits'         => 'privacy:metadata:rate:hits',
            'windowstart'  => 'privacy:metadata:rate:windowstart',
        ], 'privacy:metadata:rate');

        // ADR-030 Wave A — outbound webhook deliveries carry the affected user id
        // and an id-only JSON payload. Subscriptions hold no user data.
        $collection->add_database_table('local_sentientia_api_whdel', [
            'userid'       => 'privacy:metadata:whdel:userid',
            'eventkey'     => 'privacy:metadata:whdel:eventkey',
            'status'       => 'privacy:metadata:whdel:status',
            'timecreated'  => 'privacy:metadata:whdel:timecreated',
        ], 'privacy:metadata:whdel');
        $collection->add_external_location_link('webhook_endpoint', [
            'userid'   => 'privacy:metadata:whdel:userid',
            'eventkey' => 'privacy:metadata:whdel:eventkey',
        ], 'privacy:metadata:webhook_endpoint');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // All data is stored at system context.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_sentientia_api_log}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_sentientia_api_rate}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_sentientia_api_whdel} WHERE userid > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $logs = $DB->get_records('local_sentientia_api_log', ['userid' => $user->id]);
            if ($logs) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_api')],
                    (object) ['requestlog' => array_values((array) $logs)]
                );
            }
            $dels = $DB->get_records('local_sentientia_api_whdel', ['userid' => $user->id], 'timecreated ASC',
                'id, eventkey, status, attempts, httpstatus, timecreated, timeupdated');
            if ($dels) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_api'), get_string('webhooks_title', 'local_sentientia_api')],
                    (object) ['deliveries' => array_values((array) $dels)]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_sentientia_api_log');
        $DB->delete_records('local_sentientia_api_rate');
        $DB->delete_records_select('local_sentientia_api_whdel', 'userid > 0');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->delete_records('local_sentientia_api_log', ['userid' => $userid]);
                $DB->delete_records('local_sentientia_api_rate', ['userid' => $userid]);
                $DB->delete_records('local_sentientia_api_whdel', ['userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_sentientia_api_log', "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_api_rate', "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_api_whdel', "userid $insql", $params);
    }
}
