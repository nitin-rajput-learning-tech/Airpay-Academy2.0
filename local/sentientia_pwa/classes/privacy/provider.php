<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa\privacy;

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
 * Tables that carry user data:
 *   - local_sentientia_push_subs : per-user web-push subscriptions
 *                                  (endpoint + encryption keys + user agent)
 *   - local_sentientia_push_log  : per-user delivery log with notification
 *                                  title/body excerpt
 *
 * The p256dh / auth_secret encryption keys are declared in metadata but
 * not written to the export — they are device credentials, useless outside
 * the browser that generated them.
 *
 * @package local_sentientia_pwa
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_push_subs',
            [
                'userid'      => 'privacy:metadata:push_subs:userid',
                'endpoint'    => 'privacy:metadata:push_subs:endpoint',
                'p256dh'      => 'privacy:metadata:push_subs:p256dh',
                'auth_secret' => 'privacy:metadata:push_subs:auth_secret',
                'user_agent'  => 'privacy:metadata:push_subs:user_agent',
                'last_seen'   => 'privacy:metadata:push_subs:last_seen',
                'timecreated' => 'privacy:metadata:push_subs:timecreated',
            ],
            'privacy:metadata:push_subs'
        );

        $collection->add_database_table(
            'local_sentientia_push_log',
            [
                'userid'         => 'privacy:metadata:push_log:userid',
                'endpoint_host'  => 'privacy:metadata:push_log:endpoint_host',
                'title'          => 'privacy:metadata:push_log:title',
                'body_truncated' => 'privacy:metadata:push_log:body_truncated',
                'url'            => 'privacy:metadata:push_log:url',
                'result'         => 'privacy:metadata:push_log:result',
                'sent_at'        => 'privacy:metadata:push_log:sent_at',
            ],
            'privacy:metadata:push_log'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Both tables are system-context — push subscriptions aren't bound
        // to a course context.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_push_subs}", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_push_log}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // Subscriptions. Encryption keys deliberately not exported.
            $subs = $DB->get_records('local_sentientia_push_subs',
                ['userid' => $userid], 'timecreated ASC');
            $sub_data = [];
            foreach ($subs as $s) {
                $sub_data[] = [
                    'endpoint'    => $s->endpoint,
                    'user_agent'  => $s->user_agent,
                    'last_seen'   => $s->last_seen
                        ? userdate((int) $s->last_seen) : null,
                    'timecreated' => userdate((int) $s->timecreated),
                ];
            }
            if (!empty($sub_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_pwa'),
                     'push_subscriptions'],
                    (object) ['subscriptions' => $sub_data]
                );
            }

            // Delivery log.
            $logs = $DB->get_records('local_sentientia_push_log',
                ['userid' => $userid], 'sent_at ASC');
            $log_data = [];
            foreach ($logs as $l) {
                $log_data[] = [
                    'endpoint_host'  => $l->endpoint_host,
                    'title'          => $l->title,
                    'body_truncated' => $l->body_truncated,
                    'url'            => $l->url,
                    'result'         => $l->result,
                    'sent_at'        => userdate((int) $l->sent_at),
                ];
            }
            if (!empty($log_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_pwa'),
                     'push_log'],
                    (object) ['notifications' => $log_data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_sentientia_push_subs', []);
        $DB->delete_records('local_sentientia_push_log', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('local_sentientia_push_subs',
                ['userid' => $userid]);
            $DB->delete_records('local_sentientia_push_log',
                ['userid' => $userid]);
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
        $DB->delete_records_select('local_sentientia_push_subs',
            "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_push_log',
            "userid $insql", $params);
    }
}
