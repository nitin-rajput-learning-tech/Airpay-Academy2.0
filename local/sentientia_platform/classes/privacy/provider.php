<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\privacy;

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
 *   - local_sentientia_feature_flags      : modified_by — the admin who
 *                                           last changed a flag
 *   - local_sentientia_feature_flag_audit : changed_by — the admin who
 *                                           made each audited flag change
 *
 * The flag rows and audit rows themselves are platform configuration and
 * its change history — deleting them would destroy the flag audit trail.
 * Deletion therefore ANONYMISES the author columns (sets them to 0)
 * instead of removing rows.
 *
 * local_sentientia_customer_brand carries no user data.
 *
 * @package local_sentientia_platform
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_feature_flags',
            [
                'modified_by'  => 'privacy:metadata:feature_flags:modified_by',
                'flag_key'     => 'privacy:metadata:feature_flags:flag_key',
                'timemodified' => 'privacy:metadata:feature_flags:timemodified',
            ],
            'privacy:metadata:feature_flags'
        );

        $collection->add_database_table(
            'local_sentientia_feature_flag_audit',
            [
                'changed_by'  => 'privacy:metadata:flag_audit:changed_by',
                'flag_key'    => 'privacy:metadata:flag_audit:flag_key',
                'old_value'   => 'privacy:metadata:flag_audit:old_value',
                'new_value'   => 'privacy:metadata:flag_audit:new_value',
                'reason'      => 'privacy:metadata:flag_audit:reason',
                'timecreated' => 'privacy:metadata:flag_audit:timecreated',
            ],
            'privacy:metadata:flag_audit'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Feature flags are platform configuration — system context.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('modified_by',
            "SELECT modified_by FROM {local_sentientia_feature_flags}
              WHERE modified_by > 0", []);
        $userlist->add_from_sql('changed_by',
            "SELECT changed_by FROM {local_sentientia_feature_flag_audit}
              WHERE changed_by > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // Flags this user is recorded as last modifier of.
            $flags = $DB->get_records('local_sentientia_feature_flags',
                ['modified_by' => $userid], 'timemodified ASC');
            $flag_data = [];
            foreach ($flags as $f) {
                $flag_data[] = [
                    'flag_key'     => $f->flag_key,
                    'customer_id'  => (int) $f->customer_id,
                    'tenant_id'    => (int) $f->tenant_id,
                    'is_enabled'   => (bool) $f->is_enabled,
                    'timemodified' => userdate((int) $f->timemodified),
                ];
            }
            if (!empty($flag_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_platform'),
                     'feature_flags_modified'],
                    (object) ['flags' => $flag_data]
                );
            }

            // Audited flag changes made by this user.
            $changes = $DB->get_records('local_sentientia_feature_flag_audit',
                ['changed_by' => $userid], 'timecreated ASC');
            $change_data = [];
            foreach ($changes as $c) {
                $change_data[] = [
                    'flag_key'    => $c->flag_key,
                    'old_value'   => $c->old_value === null
                        ? null : (bool) $c->old_value,
                    'new_value'   => $c->new_value === null
                        ? null : (bool) $c->new_value,
                    'reason'      => $c->reason,
                    'timecreated' => userdate((int) $c->timecreated),
                ];
            }
            if (!empty($change_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_platform'),
                     'feature_flag_changes'],
                    (object) ['changes' => $change_data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        // Anonymise, don't delete — the rows are platform configuration
        // and its audit trail; only the author ids are personal.
        $DB->set_field_select('local_sentientia_feature_flags',
            'modified_by', 0, 'modified_by > 0', []);
        $DB->set_field_select('local_sentientia_feature_flag_audit',
            'changed_by', 0, 'changed_by > 0', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->set_field('local_sentientia_feature_flags',
                'modified_by', 0, ['modified_by' => $userid]);
            $DB->set_field('local_sentientia_feature_flag_audit',
                'changed_by', 0, ['changed_by' => $userid]);
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
        $DB->set_field_select('local_sentientia_feature_flags',
            'modified_by', 0, "modified_by $insql", $params);
        [$insql2, $params2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'chg');
        $DB->set_field_select('local_sentientia_feature_flag_audit',
            'changed_by', 0, "changed_by $insql2", $params2);
    }
}
