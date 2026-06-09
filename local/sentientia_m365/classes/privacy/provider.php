<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_m365\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Phase C.1.
 *
 * Declares personal data:
 *   tokens — encrypted Microsoft 365 OAuth tokens linked to a Moodle
 *            user. The ciphertext is technically PII because it can be
 *            decrypted with the server key to recover a credential
 *            that grants access to the user's Microsoft data. We mark
 *            the encrypted columns themselves as PII so the data
 *            inventory is accurate even though the columns are
 *            opaque to a human reader.
 *
 * Declares the external Microsoft Graph subsystem so the privacy
 * dashboard tells the user where the link gets used.
 *
 * Export contract: token records are exported with their ciphertext
 * masked (replaced with the literal string `[encrypted]`) — exporting
 * the raw ciphertext would leak the encrypted credential to whoever
 * receives the DSAR ZIP. Metadata (expires, scopes, timestamps) is
 * preserved so the data subject can see WHAT they granted, WHEN, and
 * to which customer scope.
 *
 * Delete contract: removes the row entirely. There is no soft-delete
 * because retaining encrypted tokens after a deletion request would
 * violate Article 17 (right to erasure). Phase C.2 will additionally
 * POST to the Microsoft revocation endpoint to invalidate the refresh
 * token server-side.
 *
 * @package local_sentientia_m365
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Declare metadata for every table this plugin uses + the external
     * Microsoft Graph subsystem.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_m365_tokens',
            [
                'userid'            => 'privacy:metadata:tokens:userid',
                'customerid'        => 'privacy:metadata:tokens:customerid',
                'access_token_enc'  => 'privacy:metadata:tokens:access_token_enc',
                'refresh_token_enc' => 'privacy:metadata:tokens:refresh_token_enc',
                'expires'           => 'privacy:metadata:tokens:expires',
                'scopes'            => 'privacy:metadata:tokens:scopes',
                'timecreated'       => 'privacy:metadata:tokens:timecreated',
                'timemodified'      => 'privacy:metadata:tokens:timemodified',
            ],
            'privacy:metadata:tokens'
        );

        // External subsystem — Microsoft Graph receives the access token
        // every time a Phase C.2+ feature fetches user data on the user's
        // behalf. The user data flows OUTWARD; we receive responses but
        // do not push personal Moodle data into Microsoft beyond the
        // OAuth identity claim.
        $collection->add_external_location_link(
            'microsoft_graph',
            [
                'userid' => 'privacy:metadata:microsoft_graph:userid',
                'scopes' => 'privacy:metadata:microsoft_graph:scopes',
            ],
            'privacy:metadata:microsoft_graph'
        );

        return $collection;
    }

    /**
     * The list of contexts that contain personal data for the given user.
     *
     * Phase C.1 stores everything at system context (one row per
     * (userid, customerid)). Phase C.2 may add per-course context when
     * SharePoint folders attach to specific courses.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        global $DB;

        $has_data = $DB->record_exists('local_sentientia_m365_tokens',
            ['userid' => $userid]);
        if ($has_data) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Return the user IDs that have personal data in this context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $DB->get_fieldset_select(
            'local_sentientia_m365_tokens', 'userid', '1=1');
        $userids = array_values(array_unique(array_filter($userids)));
        if (!empty($userids)) {
            $userlist->add_users($userids);
        }
    }

    /**
     * Export the user's M365 link metadata.
     *
     * The encrypted token columns are NEVER exported in plaintext or
     * ciphertext — we substitute the placeholder string `[encrypted]`
     * so the data subject sees that a credential exists without
     * receiving a usable copy of it. Metadata (expires, scopes,
     * timestamps) is exported in full.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $rows = $DB->get_records('local_sentientia_m365_tokens',
                ['userid' => $userid]);
            foreach ($rows as $row) {
                writer::with_context($context)->export_data(
                    ['Sentientia Microsoft 365', 'Tokens', (string)$row->id],
                    (object)[
                        'customerid'        => (int)$row->customerid,
                        'access_token_enc'  => '[encrypted]',
                        'refresh_token_enc' => '[encrypted]',
                        'expires'           => (int)$row->expires,
                        'scopes'            => (string)$row->scopes,
                        'timecreated'       => (int)$row->timecreated,
                        'timemodified'      => (int)$row->timemodified,
                    ]
                );
            }
        }
    }

    /**
     * Delete every user's M365 link data in the given context.
     *
     * Used when the whole context is being purged (e.g. site shutdown).
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_m365_tokens');
    }

    /**
     * Delete one user's M365 link data (right-to-erasure).
     *
     * Removes the row outright. Phase C.2 will additionally POST to
     * Microsoft's revocation endpoint so the token cannot be replayed.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $DB->delete_records('local_sentientia_m365_tokens',
                ['userid' => $userid]);
        }
    }

    /**
     * Bulk delete for an admin-driven list of users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_sentientia_m365_tokens',
            "userid $insql", $params);
    }
}
