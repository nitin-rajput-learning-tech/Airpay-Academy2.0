<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — GDPR / DPDP metadata + export for the DSR plugin itself.
 *
 * Tables that carry user data:
 *   - local_privacy_requests    : DSR requests lodged by (or for) a user,
 *                                 plus the admin who processed them
 *   - local_privacy_consent_log : per-user consent grant/withdraw events
 *                                 with IP address and user agent
 *
 * DELETION IS INTENTIONALLY A NO-OP. Both tables are the organisation's
 * record of processing (GDPR Art. 30) and proof of consent (DPDP Act 2023
 * s.6) — erasing them on a user's erasure request would destroy the very
 * evidence that the request was honoured. This mirrors Moodle core's
 * tool_dataprivacy provider, whose delete methods are also empty.
 *
 * @package local_sentientia_privacy
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_privacy_requests',
            [
                'userid'        => 'privacy:metadata:privacy_requests:userid',
                'request_type'  => 'privacy:metadata:privacy_requests:request_type',
                'status'        => 'privacy:metadata:privacy_requests:status',
                'reason'        => 'privacy:metadata:privacy_requests:reason',
                'admin_notes'   => 'privacy:metadata:privacy_requests:admin_notes',
                'processed_by'  => 'privacy:metadata:privacy_requests:processed_by',
                'download_url'  => 'privacy:metadata:privacy_requests:download_url',
                'timecreated'   => 'privacy:metadata:privacy_requests:timecreated',
                'timeprocessed' => 'privacy:metadata:privacy_requests:timeprocessed',
            ],
            'privacy:metadata:privacy_requests'
        );

        $collection->add_database_table(
            'local_privacy_consent_log',
            [
                'userid'       => 'privacy:metadata:consent_log:userid',
                'consent_type' => 'privacy:metadata:consent_log:consent_type',
                'consented'    => 'privacy:metadata:consent_log:consented',
                'ip_address'   => 'privacy:metadata:consent_log:ip_address',
                'user_agent'   => 'privacy:metadata:consent_log:user_agent',
                'timecreated'  => 'privacy:metadata:consent_log:timecreated',
            ],
            'privacy:metadata:consent_log'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Both tables are system-context — DSR requests and consent events
        // aren't bound to a course context.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_privacy_requests}", []);
        $userlist->add_from_sql('processed_by',
            "SELECT processed_by FROM {local_privacy_requests}
              WHERE processed_by IS NOT NULL", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_privacy_consent_log}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // DSR requests the user is the subject of.
            $requests = $DB->get_records('local_privacy_requests',
                ['userid' => $userid], 'timecreated ASC');
            $request_data = [];
            foreach ($requests as $r) {
                $request_data[] = [
                    'request_type'  => $r->request_type,
                    'status'        => $r->status,
                    'reason'        => $r->reason,
                    'admin_notes'   => $r->admin_notes,
                    'processed_by'  => (int) $r->processed_by,
                    'download_url'  => $r->download_url,
                    'timecreated'   => userdate((int) $r->timecreated),
                    'timeprocessed' => $r->timeprocessed
                        ? userdate((int) $r->timeprocessed) : null,
                ];
            }
            if (!empty($request_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_privacy'),
                     'requests'],
                    (object) ['requests' => $request_data]
                );
            }

            // Requests this user processed as an admin. The subject's
            // userid is deliberately omitted — it is the subject's
            // personal data, not the processing admin's.
            $processed = $DB->get_records('local_privacy_requests',
                ['processed_by' => $userid], 'timeprocessed ASC');
            $processed_data = [];
            foreach ($processed as $p) {
                $processed_data[] = [
                    'request_type'  => $p->request_type,
                    'status'        => $p->status,
                    'timeprocessed' => $p->timeprocessed
                        ? userdate((int) $p->timeprocessed) : null,
                ];
            }
            if (!empty($processed_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_privacy'),
                     'requests_processed_as_admin'],
                    (object) ['requests_processed' => $processed_data]
                );
            }

            // Consent events.
            $consents = $DB->get_records('local_privacy_consent_log',
                ['userid' => $userid], 'timecreated ASC');
            $consent_data = [];
            foreach ($consents as $c) {
                $consent_data[] = [
                    'consent_type' => $c->consent_type,
                    'consented'    => (bool) $c->consented,
                    'ip_address'   => $c->ip_address,
                    'user_agent'   => $c->user_agent,
                    'timecreated'  => userdate((int) $c->timecreated),
                ];
            }
            if (!empty($consent_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_privacy'),
                     'consent_log'],
                    (object) ['consents' => $consent_data]
                );
            }
        }
    }

    // Deletion no-ops — see class doc block. DSR request rows and consent
    // events are records of processing / proof of consent and are retained
    // when a user's other data is erased (same stance as tool_dataprivacy).

    public static function delete_data_for_all_users_in_context(\context $context): void {
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
    }
}
