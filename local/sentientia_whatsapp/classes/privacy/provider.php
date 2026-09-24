<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Privacy provider for local_sentientia_whatsapp.
 *
 * Phase A1 iter 1. Declares storage of personal data (mobile numbers,
 * opt-in state, consent timestamps) and supports DPDP / GDPR export
 * and deletion requests.
 *
 * @package    local_sentientia_whatsapp
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_whatsapp\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider
{

    /**
     * Describes what personal data this plugin stores.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_user_channel_prefs',
            [
                'userid'           => 'privacy:metadata:local_sentientia_user_channel_prefs:userid',
                'mobile_number'    => 'privacy:metadata:local_sentientia_user_channel_prefs:mobile_number',
                'whatsapp_optin'   => 'privacy:metadata:local_sentientia_user_channel_prefs:whatsapp_optin',
                'sms_optin'        => 'privacy:metadata:local_sentientia_user_channel_prefs:sms_optin',
                'dlt_consent_at'   => 'privacy:metadata:local_sentientia_user_channel_prefs:dlt_consent_at',
                'dlt_consent_text' => 'privacy:metadata:local_sentientia_user_channel_prefs:dlt_consent_text',
            ],
            'privacy:metadata:local_sentientia_user_channel_prefs'
        );

        // Added 2026-09-22 -- owned but undeclared. The audit rows were
        // already being deleted on erasure; the registry simply never said
        // they existed. send_log was neither declared NOR deleted, and its
        // `recipient` column is the employee's mobile number.
        $collection->add_database_table(
            'local_sentientia_user_channel_audit',
            [
                'userid'     => 'privacy:metadata:channel_audit:userid',
                'changed_by' => 'privacy:metadata:channel_audit:changed_by',
                'field_name' => 'privacy:metadata:channel_audit:field_name',
                'old_value'  => 'privacy:metadata:channel_audit:old_value',
                'new_value'  => 'privacy:metadata:channel_audit:new_value',
                'reason'     => 'privacy:metadata:channel_audit:reason',
                'ip_address' => 'privacy:metadata:channel_audit:ip_address',
            ],
            'privacy:metadata:channel_audit'
        );

        $collection->add_database_table(
            'local_sentientia_send_log',
            [
                'userid'         => 'privacy:metadata:send_log:userid',
                'channel'        => 'privacy:metadata:send_log:channel',
                'template_key'   => 'privacy:metadata:send_log:template_key',
                'status'         => 'privacy:metadata:send_log:status',
                'recipient'      => 'privacy:metadata:send_log:recipient',
                'failure_reason' => 'privacy:metadata:send_log:failure_reason',
            ],
            'privacy:metadata:send_log'
        );

        return $collection;
    }

    /**
     * Returns the contexts where the user has stored personal data.
     * For us that's just the system context — preferences are global.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        global $DB;
        // Every table get_users_in_context() reads, not just prefs. Until
        // 2026-09-24 a user with send-log rows (each carrying their mobile
        // number) or channel-audit rows but no saved preference was reported as
        // holding no data here, so no erasure - core's or Sentientia's - ever
        // asked this provider to delete those rows.
        if ($DB->record_exists('local_sentientia_user_channel_prefs', ['userid' => $userid])
                || $DB->record_exists('local_sentientia_send_log', ['userid' => $userid])
                || $DB->record_exists('local_sentientia_user_channel_audit', ['userid' => $userid])
                || $DB->record_exists('local_sentientia_user_channel_audit', ['changed_by' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!($context instanceof \context_system)) {
            return;
        }
        $sql = "SELECT userid FROM {local_sentientia_user_channel_prefs}";
        $userlist->add_from_sql('userid', $sql, []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_user_channel_audit} WHERE userid > 0", []);
        $userlist->add_from_sql('changed_by',
            "SELECT changed_by FROM {local_sentientia_user_channel_audit} WHERE changed_by > 0", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_send_log} WHERE userid > 0", []);
    }

    /**
     * Export the user's preferences as a JSON-ish blob under the system
     * context. Includes the audit history so users can see "you opted in
     * on date X".
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $row = $DB->get_record('local_sentientia_user_channel_prefs', ['userid' => $userid]);
        if (!$row) {
            return;
        }

        $context = \context_system::instance();
        $subcontext = [get_string('pluginname', 'local_sentientia_whatsapp')];

        // Strip ID + technical fields; export the meaningful prefs.
        $data = (object) [
            'mobile_number'    => $row->mobile_number,
            'whatsapp_optin'   => (bool) $row->whatsapp_optin,
            'sms_optin'        => (bool) $row->sms_optin,
            'email_optin'      => (bool) $row->email_optin,
            'prefer_channel'   => $row->prefer_channel,
            'dlt_consent_at'   => $row->dlt_consent_at
                ? userdate($row->dlt_consent_at) : null,
            'dlt_consent_text' => $row->dlt_consent_text,
            'last_updated'     => userdate($row->timemodified),
        ];
        writer::with_context($context)
            ->export_data($subcontext, $data);

        // Also export the audit trail.
        $audit = $DB->get_records('local_sentientia_user_channel_audit',
            ['userid' => $userid], 'timecreated ASC');
        if ($audit) {
            $audit_export = array_map(fn($a) => (object) [
                'field_name'   => $a->field_name,
                'old_value'    => $a->old_value,
                'new_value'    => $a->new_value,
                'reason'       => $a->reason,
                'ip_address'   => $a->ip_address,
                'changed_at'   => userdate($a->timecreated),
            ], $audit);
            writer::with_context($context)
                ->export_data(
                    array_merge($subcontext, ['audit_history']),
                    (object) ['entries' => array_values($audit_export)]
                );
        }
    }

    /**
     * Delete all stored data for a single user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        \local_sentientia_whatsapp\preference_manager::delete_user_data($userid);
        // preference_manager handles prefs + audit. It does NOT know about
        // send_log, whose `recipient` column holds the mobile number each
        // message went to. Deleted here rather than inside preference_manager
        // so that helper's contract is unchanged for its other callers.
        $DB->delete_records('local_sentientia_send_log', ['userid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        foreach ($userids as $userid) {
            \local_sentientia_whatsapp\preference_manager::delete_user_data($userid);
        }
        if (!empty($userids)) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_sentientia_send_log',
                "userid $insql", $params);
        }
    }

    /**
     * Delete all data when the system context is deleted (rare —
     * essentially "wipe the platform"). Cleans up both tables.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!($context instanceof \context_system)) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_user_channel_prefs');
        $DB->delete_records('local_sentientia_user_channel_audit');
        // Added 2026-09-22. Every row carries the recipient's mobile number.
        $DB->delete_records('local_sentientia_send_log');
    }
}
