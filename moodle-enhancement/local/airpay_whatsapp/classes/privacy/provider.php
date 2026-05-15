<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Privacy provider for local_airpay_whatsapp.
 *
 * Phase A1 iter 1. Declares storage of personal data (mobile numbers,
 * opt-in state, consent timestamps) and supports DPDP / GDPR export
 * and deletion requests.
 *
 * @package    local_airpay_whatsapp
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_whatsapp\privacy;

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
            'local_airpay_user_channel_prefs',
            [
                'userid'           => 'privacy:metadata:local_airpay_user_channel_prefs:userid',
                'mobile_number'    => 'privacy:metadata:local_airpay_user_channel_prefs:mobile_number',
                'whatsapp_optin'   => 'privacy:metadata:local_airpay_user_channel_prefs:whatsapp_optin',
                'sms_optin'        => 'privacy:metadata:local_airpay_user_channel_prefs:sms_optin',
                'dlt_consent_at'   => 'privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_at',
                'dlt_consent_text' => 'privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_text',
            ],
            'privacy:metadata:local_airpay_user_channel_prefs'
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
        if ($DB->record_exists('local_airpay_user_channel_prefs', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!($context instanceof \context_system)) {
            return;
        }
        $sql = "SELECT userid FROM {local_airpay_user_channel_prefs}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export the user's preferences as a JSON-ish blob under the system
     * context. Includes the audit history so users can see "you opted in
     * on date X".
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $row = $DB->get_record('local_airpay_user_channel_prefs', ['userid' => $userid]);
        if (!$row) {
            return;
        }

        $context = \context_system::instance();
        $subcontext = [get_string('pluginname', 'local_airpay_whatsapp')];

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
        $audit = $DB->get_records('local_airpay_user_channel_audit',
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
        $userid = $contextlist->get_user()->id;
        \local_airpay_whatsapp\preference_manager::delete_user_data($userid);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            \local_airpay_whatsapp\preference_manager::delete_user_data($userid);
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
        $DB->delete_records('local_airpay_user_channel_prefs');
        $DB->delete_records('local_airpay_user_channel_audit');
    }
}
