<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_sentientia_org.
 *
 * The organisation tables hold no personal data. The one table that does is
 * local_sentientia_cohort_scope (ADR-032, the BizLMS cohort tenant scope): its usermodified column
 * names the administrator who last changed a cohort's scope. It is an actor reference, not the
 * person's own record, so the row is never deleted on a request: it scopes a cohort to a tenant
 * and belongs to the organisation. What goes is the reference (usermodified becomes 0), both when
 * core's privacy API erases a user and when the Sentientia DPDP flow anonymises one in place.
 *
 * Until 2026-09-30 this provider was a null_provider. The scope table made that claim false, and
 * a null_provider on a plugin that holds a user column is the defect class the structural guard in
 * local_sentientia_platform (privacy_coverage_test) blocks.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** The table that names a person. */
    private const TABLE = 'local_sentientia_cohort_scope';

    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'cohortid' => 'privacy:metadata:cohort_scope:cohortid',
            'usermodified' => 'privacy:metadata:cohort_scope:usermodified',
            'timemodified' => 'privacy:metadata:cohort_scope:timemodified',
        ], 'privacy:metadata:cohort_scope');
        return $collection;
    }

    /**
     * The contexts that hold data the user is named in: the system context, when the user last changed a scope.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->get_manager()->table_exists(self::TABLE) && $DB->record_exists(self::TABLE, ['usermodified' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users named in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM || !$DB->get_manager()->table_exists(self::TABLE)) {
            return;
        }
        $userids = $DB->get_fieldset_select(self::TABLE, 'DISTINCT usermodified', 'usermodified > 0');
        if ($userids) {
            $userlist->add_users($userids);
        }
    }

    /**
     * Export the scope rows a user last changed.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist) || !$DB->get_manager()->table_exists(self::TABLE)) {
            return;
        }
        $rows = $DB->get_records(self::TABLE, ['usermodified' => $contextlist->get_user()->id], 'id ASC',
            'id, cohortid, open_path, departmentids, timemodified');
        if (!$rows) {
            return;
        }
        $data = [];
        foreach ($rows as $row) {
            $data[] = (object) [
                'cohortid' => (int) $row->cohortid,
                'open_path' => $row->open_path,
                'departmentids' => $row->departmentids,
                'timemodified' => transform::datetime((int) $row->timemodified),
            ];
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('privacy:subcontext:cohort_scope', 'local_sentientia_org')],
            (object) ['cohort_scope' => $data]);
    }

    /**
     * Erase every reference to users in a context. The scope rows stay.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM || !$DB->get_manager()->table_exists(self::TABLE)) {
            return;
        }
        $DB->set_field_select(self::TABLE, 'usermodified', 0, 'usermodified > 0');
    }

    /**
     * Erase one user's reference. The scope rows stay.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!self::has_system_context($contextlist) || !$DB->get_manager()->table_exists(self::TABLE)) {
            return;
        }
        $DB->set_field(self::TABLE, 'usermodified', 0, ['usermodified' => $contextlist->get_user()->id]);
    }

    /**
     * Erase the references to several users. The scope rows stay.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM || !$DB->get_manager()->table_exists(self::TABLE)) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->set_field_select(self::TABLE, 'usermodified', 0, "usermodified $insql", $params);
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): take the person out of the record and
     * keep the record. Called instead of delete_data_for_user() when present; core's privacy API never calls it.
     *
     * The scope row is organisation data: it says which tenant a cohort belongs to. Only the actor column goes,
     * which is exactly what delete_data_for_user() does here, so the two paths agree.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist) {
        self::delete_data_for_user($contextlist);
    }

    /**
     * Does the approved list hold the system context?
     *
     * @param approved_contextlist $contextlist
     * @return bool
     */
    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                return true;
            }
        }
        return false;
    }
}
