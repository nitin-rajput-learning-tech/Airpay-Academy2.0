<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_platform\schema\user_type_tables;

/**
 * Privacy provider — GDPR / DPDP metadata + export + delete.
 *
 * Tables that carry user data:
 *   - local_sentientia_feature_flags      : modified_by — the admin who
 *                                           last changed a flag
 *   - local_sentientia_feature_flag_audit : changed_by — the admin who
 *                                           made each audited flag change
 *   - the five ADR-017 user-type tables in user_type_tables::TABLES — the
 *     account classification and the employee / consumer / partner-employee /
 *     operator profile of each person (2026-09-24, see below)
 *
 * The flag rows and audit rows themselves are platform configuration and
 * its change history — deleting them would destroy the flag audit trail.
 * Deletion therefore ANONYMISES the author columns (sets them to 0)
 * instead of removing rows.
 *
 * The user-type rows ARE the person's personal data (employee number, job
 * title, manager, date of joining, a consumer's interests and marketing
 * consent, an operator's phone number), so erasure deletes the person's own
 * rows. Where ANOTHER person's profile names the subject as their manager
 * (manager_userid / partner_manager_userid), only that link is set to NULL:
 * the row is the other person's and stays.
 *
 * 2026-09-24: this provider used to cover the two flag tables only. The
 * user-type tables are created by user_type_tables::ensure() (called from
 * db/install.php and upgrade step 2026052801), not by install.xml in every
 * tree, so privacy_coverage_test could not see them and nothing here erased
 * them: a public-signup learner's consumer profile and classification row
 * survived a DPDP erasure that local_sentientia_privacy reported as
 * 'completed'. Because they are created at runtime, any of them may be
 * absent on a given site; every access is guarded with table_exists(), since
 * a provider that throws marks the whole erasure 'partial'.
 *
 * local_sentientia_customer_brand carries no user data.
 *
 * @package local_sentientia_platform
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * User-type table => the column on it that names ANOTHER person as the
     * row owner's manager. Nullable in every definition of the table
     * (install.xml, user_type_tables::ensure(), upgrade step 2026052801), so
     * erasure sets it to NULL rather than touching the owner's row.
     *
     * @var array<string,string>
     */
    private const MANAGER_COLUMNS = [
        'local_sentientia_employee_profile'         => 'manager_userid',
        'local_sentientia_partner_employee_profile' => 'partner_manager_userid',
    ];

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

        // ADR-017 user-type tables. Declared unconditionally: metadata
        // describes what the plugin CAN hold, whether or not the table has
        // been created on this site yet.
        $collection->add_database_table(
            'local_sentientia_user_type',
            [
                'userid'              => 'privacy:metadata:user_type:userid',
                'user_type'           => 'privacy:metadata:user_type:user_type',
                'provisioning_source' => 'privacy:metadata:user_type:provisioning_source',
                'provisioned_at'      => 'privacy:metadata:user_type:provisioned_at',
            ],
            'privacy:metadata:user_type'
        );

        $collection->add_database_table(
            'local_sentientia_employee_profile',
            [
                'userid'           => 'privacy:metadata:employee_profile:userid',
                'employee_id'      => 'privacy:metadata:employee_profile:employee_id',
                'department'       => 'privacy:metadata:employee_profile:department',
                'job_title'        => 'privacy:metadata:employee_profile:job_title',
                'manager_userid'   => 'privacy:metadata:employee_profile:manager_userid',
                'hire_date'        => 'privacy:metadata:employee_profile:hire_date',
                'cost_center_path' => 'privacy:metadata:employee_profile:cost_center_path',
            ],
            'privacy:metadata:employee_profile'
        );

        $collection->add_database_table(
            'local_sentientia_consumer_profile',
            [
                'userid'              => 'privacy:metadata:consumer_profile:userid',
                'interests_json'      => 'privacy:metadata:consumer_profile:interests_json',
                'weekly_goal'         => 'privacy:metadata:consumer_profile:weekly_goal',
                'referral_source'     => 'privacy:metadata:consumer_profile:referral_source',
                'consent_marketing'   => 'privacy:metadata:consumer_profile:consent_marketing',
                'consent_leaderboard' => 'privacy:metadata:consumer_profile:consent_leaderboard',
                'payment_history_url' => 'privacy:metadata:consumer_profile:payment_history_url',
            ],
            'privacy:metadata:consumer_profile'
        );

        $collection->add_database_table(
            'local_sentientia_partner_employee_profile',
            [
                'userid'                 => 'privacy:metadata:partner_employee_profile:userid',
                'customer_id'            => 'privacy:metadata:partner_employee_profile:customer_id',
                'partner_employee_id'    => 'privacy:metadata:partner_employee_profile:partner_employee_id',
                'partner_department'     => 'privacy:metadata:partner_employee_profile:partner_department',
                'partner_job_title'      => 'privacy:metadata:partner_employee_profile:partner_job_title',
                'partner_manager_userid' => 'privacy:metadata:partner_employee_profile:partner_manager_userid',
                'partner_hire_date'      => 'privacy:metadata:partner_employee_profile:partner_hire_date',
                'cost_center_path'       => 'privacy:metadata:partner_employee_profile:cost_center_path',
            ],
            'privacy:metadata:partner_employee_profile'
        );

        $collection->add_database_table(
            'local_sentientia_operator_profile',
            [
                'userid'                 => 'privacy:metadata:operator_profile:userid',
                'operator_role'          => 'privacy:metadata:operator_profile:operator_role',
                'contact_phone'          => 'privacy:metadata:operator_profile:contact_phone',
                'oncall_for_customer_id' => 'privacy:metadata:operator_profile:oncall_for_customer_id',
            ],
            'privacy:metadata:operator_profile'
        );

        return $collection;
    }

    /**
     * The ADR-017 user-type tables that exist on this site.
     *
     * They are created at runtime by user_type_tables::ensure(), so a site
     * can lack any of them. A missing table holds nothing to find, export or
     * erase; querying it would throw, and local_sentientia_privacy marks the
     * whole erasure 'partial' when a provider throws.
     *
     * @return string[]
     */
    private static function present_user_type_tables(): array {
        global $DB;
        $dbman = $DB->get_manager();
        $present = [];
        foreach (user_type_tables::TABLES as $table) {
            if ($dbman->table_exists($table)) {
                $present[] = $table;
            }
        }
        return $present;
    }

    /**
     * Whether this plugin holds anything about the user: a flag or audit row
     * they authored, a user-type row of their own, or another person's
     * profile naming them as manager.
     *
     * @param int $userid
     * @return bool
     */
    private static function user_has_data(int $userid): bool {
        global $DB;
        if ($DB->record_exists('local_sentientia_feature_flags', ['modified_by' => $userid])
                || $DB->record_exists('local_sentientia_feature_flag_audit', ['changed_by' => $userid])) {
            return true;
        }
        foreach (self::present_user_type_tables() as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return true;
            }
            if (isset(self::MANAGER_COLUMNS[$table])
                    && $DB->record_exists($table, [self::MANAGER_COLUMNS[$table] => $userid])) {
                return true;
            }
        }
        return false;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Everything this plugin holds lives at the system context. Reported
        // only when there is something for this user (previously it was
        // reported for everyone, whether or not they had data here).
        if (self::user_has_data($userid)) {
            $contextlist->add_system_context();
        }
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

        // Table and column names below come from class constants, never
        // from input.
        foreach (self::present_user_type_tables() as $table) {
            $userlist->add_from_sql('userid',
                'SELECT userid FROM {' . $table . '}', []);
            if (isset(self::MANAGER_COLUMNS[$table])) {
                $column = self::MANAGER_COLUMNS[$table];
                $userlist->add_from_sql($column,
                    "SELECT {$column} FROM {" . $table . "}
                      WHERE {$column} IS NOT NULL AND {$column} > 0", []);
            }
        }
    }

    /**
     * A stored timestamp for export, or null when it was never set.
     *
     * @param mixed $timestamp
     * @return string|null
     */
    private static function when($timestamp): ?string {
        return empty($timestamp) ? null : transform::datetime((int) $timestamp);
    }

    /**
     * A stored user id for export, or null when it is not set.
     *
     * @param mixed $userid
     * @return mixed
     */
    private static function userref($userid) {
        return empty($userid) ? null : transform::user((int) $userid);
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

            self::export_user_type_data($context, (int) $userid);
        }
    }

    /**
     * Export the user's classification and profile rows, and how many other
     * people's profiles name them as manager.
     *
     * Other people's profile fields are NOT exported: they are the other
     * person's data. The subject is told only that the link exists.
     *
     * @param \context $context the system context
     * @param int $userid
     */
    private static function export_user_type_data(\context $context, int $userid): void {
        global $DB;
        $present = array_flip(self::present_user_type_tables());
        $writer = writer::with_context($context);
        $base = get_string('pluginname', 'local_sentientia_platform');

        if (isset($present['local_sentientia_user_type'])
                && ($r = $DB->get_record('local_sentientia_user_type', ['userid' => $userid]))) {
            $writer->export_data([$base, 'user_type'], (object) [
                'user_type'           => $r->user_type,
                'provisioning_source' => $r->provisioning_source,
                'provisioned_at'      => self::when($r->provisioned_at),
                'timecreated'         => self::when($r->timecreated),
            ]);
        }

        if (isset($present['local_sentientia_employee_profile'])
                && ($r = $DB->get_record('local_sentientia_employee_profile', ['userid' => $userid]))) {
            $writer->export_data([$base, 'employee_profile'], (object) [
                'employee_id'      => $r->employee_id,
                'department'       => $r->department,
                'job_title'        => $r->job_title,
                'manager_userid'   => self::userref($r->manager_userid),
                'hire_date'        => self::when($r->hire_date),
                'cost_center_path' => $r->cost_center_path,
                'timecreated'      => self::when($r->timecreated),
                'timemodified'     => self::when($r->timemodified),
            ]);
        }

        if (isset($present['local_sentientia_consumer_profile'])
                && ($r = $DB->get_record('local_sentientia_consumer_profile', ['userid' => $userid]))) {
            $writer->export_data([$base, 'consumer_profile'], (object) [
                'interests_json'      => $r->interests_json,
                'weekly_goal'         => $r->weekly_goal === null ? null : (int) $r->weekly_goal,
                'referral_source'     => $r->referral_source,
                'consent_marketing'   => transform::yesno((int) $r->consent_marketing),
                'consent_leaderboard' => transform::yesno((int) $r->consent_leaderboard),
                'payment_history_url' => $r->payment_history_url,
                'timecreated'         => self::when($r->timecreated),
                'timemodified'        => self::when($r->timemodified),
            ]);
        }

        if (isset($present['local_sentientia_partner_employee_profile'])
                && ($r = $DB->get_record('local_sentientia_partner_employee_profile', ['userid' => $userid]))) {
            $writer->export_data([$base, 'partner_employee_profile'], (object) [
                'customer_id'            => (int) $r->customer_id,
                'partner_employee_id'    => $r->partner_employee_id,
                'partner_department'     => $r->partner_department,
                'partner_job_title'      => $r->partner_job_title,
                'partner_manager_userid' => self::userref($r->partner_manager_userid),
                'partner_hire_date'      => self::when($r->partner_hire_date),
                'cost_center_path'       => $r->cost_center_path,
                'timecreated'            => self::when($r->timecreated),
                'timemodified'           => self::when($r->timemodified),
            ]);
        }

        if (isset($present['local_sentientia_operator_profile'])
                && ($r = $DB->get_record('local_sentientia_operator_profile', ['userid' => $userid]))) {
            $writer->export_data([$base, 'operator_profile'], (object) [
                'operator_role'          => $r->operator_role,
                'contact_phone'          => $r->contact_phone,
                'oncall_for_customer_id' => $r->oncall_for_customer_id === null
                    ? null : (int) $r->oncall_for_customer_id,
                'timecreated'            => self::when($r->timecreated),
                'timemodified'           => self::when($r->timemodified),
            ]);
        }

        // Profiles of other people that name this user as their manager.
        $managed = [];
        foreach (self::MANAGER_COLUMNS as $table => $column) {
            if (!isset($present[$table])) {
                continue;
            }
            $count = $DB->count_records($table, [$column => $userid]);
            if ($count > 0) {
                // local_sentientia_employee_profile => employee_profiles.
                $managed[substr($table, strlen('local_sentientia_')) . 's'] = $count;
            }
        }
        if (!empty($managed)) {
            $writer->export_data([$base, 'recorded_as_manager'], (object) $managed);
        }
    }

    /**
     * Erase the user-type data of the given users.
     *
     * Their own classification and profile rows are deleted. Where another
     * person's profile names one of them as manager, that column is set to
     * NULL and the other person's row is otherwise left alone. Tables absent
     * on this site are skipped.
     *
     * @param int[] $userids
     */
    private static function erase_user_type_rows(array $userids): void {
        global $DB;
        if (empty($userids)) {
            return;
        }
        foreach (self::present_user_type_tables() as $table) {
            if (isset(self::MANAGER_COLUMNS[$table])) {
                $column = self::MANAGER_COLUMNS[$table];
                [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mgr');
                $DB->set_field_select($table, $column, null, "{$column} {$insql}", $params);
            }
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'own');
            $DB->delete_records_select($table, "userid {$insql}", $params);
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

        // Every user-type row is one person's personal data, held at the
        // system context: erasing everyone in the context removes them all.
        foreach (self::present_user_type_tables() as $table) {
            $DB->delete_records($table);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->set_field('local_sentientia_feature_flags',
                'modified_by', 0, ['modified_by' => $userid]);
            $DB->set_field('local_sentientia_feature_flag_audit',
                'changed_by', 0, ['changed_by' => $userid]);
            self::erase_user_type_rows([$userid]);
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
        self::erase_user_type_rows(array_map('intval', $userids));
    }
}
