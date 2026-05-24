<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Privacy provider for local_sentientia_calendar.
 *
 * Phase 1: one row per user in {local_sentientia_calendar_token}.
 * Phase 2: additionally one row per (user, provider) in
 *           {local_sentientia_calendar_oauth} when the user has connected
 *           Microsoft 365 and/or Google Calendar via OAuth.
 *
 * Both tables hold material that the privacy framework treats as
 * personal data:
 *   - The Phase 1 token row carries the user's secret subscription
 *     credential + audit metadata (last_used_ip).
 *   - The Phase 2 OAuth row carries encrypted access_token +
 *     refresh_token (functionally PII because a refresh_token persists
 *     the user's identity to the provider for months).
 *
 * Export contract
 * ---------------
 * Both kinds of tokens are EXCLUDED FROM EXPORT BODIES. We surface
 * "you have a row" + metadata (provider, expires, scopes, timestamps);
 * we deliberately do not write the actual secret credential into the
 * user's exported archive. That archive is typically downloaded to the
 * user's PC and a token in there would broaden the attack surface for
 * very little legitimate utility — the user already has access to their
 * own calendar.
 *
 * Delete contract
 * ---------------
 * On a right-to-erasure request we drop BOTH the Phase 1 token row(s)
 * and the Phase 2 OAuth row(s). The user's tokens with the upstream
 * providers (Microsoft / Google) are NOT touched here — the user must
 * revoke those via account.microsoft.com / myaccount.google.com.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_calendar_token',
            [
                'userid'        => 'privacy:metadata:token:userid',
                'token'         => 'privacy:metadata:token:token',
                'last_used_at'  => 'privacy:metadata:token:last_used_at',
                'last_used_ip'  => 'privacy:metadata:token:last_used_ip',
                'use_count'     => 'privacy:metadata:token:use_count',
                'timecreated'   => 'privacy:metadata:token:timecreated',
                'timemodified'  => 'privacy:metadata:token:timemodified',
            ],
            'privacy:metadata:token'
        );

        $collection->add_database_table(
            'local_sentientia_calendar_oauth',
            [
                'userid'            => 'privacy:metadata:oauth:userid',
                'customerid'        => 'privacy:metadata:oauth:customerid',
                'provider'          => 'privacy:metadata:oauth:provider',
                'access_token_enc'  => 'privacy:metadata:oauth:access_token_enc',
                'refresh_token_enc' => 'privacy:metadata:oauth:refresh_token_enc',
                'expires'           => 'privacy:metadata:oauth:expires',
                'scopes'            => 'privacy:metadata:oauth:scopes',
                'timecreated'       => 'privacy:metadata:oauth:timecreated',
                'timemodified'      => 'privacy:metadata:oauth:timemodified',
            ],
            'privacy:metadata:oauth'
        );

        // Disclose that we exchange data with Microsoft + Google when the
        // Phase 2 OAuth feature is enabled. Even though Phase 2 scaffolding
        // does NOT make live calls, the metadata system documents the
        // INTENT — required for a clean GDPR review.
        $collection->add_external_location_link(
            'microsoft_graph',
            ['userid' => 'privacy:metadata:microsoft_graph:userid'],
            'privacy:metadata:microsoft_graph'
        );
        $collection->add_external_location_link(
            'google_calendar',
            ['userid' => 'privacy:metadata:google_calendar:userid'],
            'privacy:metadata:google_calendar'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        $has_token = $DB->record_exists('local_sentientia_calendar_token',
            ['userid' => $userid]);
        $has_oauth = $DB->record_exists('local_sentientia_calendar_oauth',
            ['userid' => $userid]);

        if ($has_token || $has_oauth) {
            // Both tables live under the user's own user context (matches the
            // capability contextlevel in db/access.php).
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;
        $has_token = $DB->record_exists('local_sentientia_calendar_token',
            ['userid' => $userid]);
        $has_oauth = $DB->record_exists('local_sentientia_calendar_oauth',
            ['userid' => $userid]);
        if ($has_token || $has_oauth) {
            $userlist->add_user($userid);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER || (int) $context->instanceid !== $userid) {
                continue;
            }

            $subcontext = [get_string('pluginname', 'local_sentientia_calendar')];

            // ─── Phase 1 token rows ─────────────────────────────────
            $rows = $DB->get_records('local_sentientia_calendar_token',
                ['userid' => $userid]);
            $tokens_export = [];
            foreach ($rows as $row) {
                $tokens_export[] = [
                    // We do NOT export the token itself — it's a secret
                    // that authenticates the user's feed. Exporting it
                    // would create a copy in the user's exported archive
                    // (typically downloaded to their PC), broadening the
                    // attack surface. Knowing IT EXISTS is enough.
                    'token_exists' => true,
                    'token_id'     => (int) $row->id,
                    'revoked'      => (bool) $row->revoked,
                    'last_used_at' => (int) $row->last_used_at,
                    'last_used_ip' => (string) ($row->last_used_ip ?? ''),
                    'use_count'    => (int) $row->use_count,
                    'timecreated'  => (int) $row->timecreated,
                    'timemodified' => (int) $row->timemodified,
                ];
            }

            // ─── Phase 2 OAuth rows ─────────────────────────────────
            // We never write the encrypted columns into the export. The
            // user gets provider name + expiry + scopes — enough to know
            // what's stored without putting a long-lived refresh_token
            // into a downloadable archive.
            $oauth_rows = \local_sentientia_calendar\oauth\token_vault::describe_for_user($userid);
            $oauth_export = [];
            foreach ($oauth_rows as $row) {
                $oauth_export[] = [
                    'oauth_exists'      => true,
                    'provider'          => $row['provider'],
                    'expires'           => $row['expires'],
                    'scopes'            => $row['scopes'],
                    'access_token_enc'  => '[REDACTED — encrypted credential not exported]',
                    'refresh_token_enc' => '[REDACTED — encrypted credential not exported]',
                    'timecreated'       => $row['timecreated'],
                    'timemodified'      => $row['timemodified'],
                ];
            }

            if (!empty($tokens_export) || !empty($oauth_export)) {
                writer::with_context($context)->export_data(
                    $subcontext,
                    (object) [
                        'tokens'       => $tokens_export,
                        'oauth_tokens' => $oauth_export,
                    ]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        global $DB;
        $userid = $context->instanceid;
        $DB->delete_records('local_sentientia_calendar_token',
            ['userid' => $userid]);
        $DB->delete_records('local_sentientia_calendar_oauth',
            ['userid' => $userid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_USER && (int) $context->instanceid === $userid) {
                $DB->delete_records('local_sentientia_calendar_token',
                    ['userid' => $userid]);
                $DB->delete_records('local_sentientia_calendar_oauth',
                    ['userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_sentientia_calendar_token',
            "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_calendar_oauth',
            "userid $insql", $params);
    }
}
