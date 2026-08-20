<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\privacy;

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
 * Table that carries user data:
 *   - local_sentientia_org_member : a user's org-unit membership
 *                                   (unit, role, direct manager)
 *
 * A user can appear in this table two ways: as the member (userid) and as
 * another member's manager (managerid). Deletion removes their own
 * membership rows and resets managerid to 0 on rows that reference them —
 * the remaining rows are the other members' data, not theirs.
 *
 * customer / tenant / org_unit tables are org configuration (names, ids,
 * status) and carry no user data.
 *
 * @package local_sentientia_core
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_org_member',
            [
                'userid'      => 'privacy:metadata:org_member:userid',
                'unitid'      => 'privacy:metadata:org_member:unitid',
                'role'        => 'privacy:metadata:org_member:role',
                'managerid'   => 'privacy:metadata:org_member:managerid',
                'timecreated' => 'privacy:metadata:org_member:timecreated',
            ],
            'privacy:metadata:org_member'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Org membership is system-context — it isn't bound to a course.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_org_member}", []);
        $userlist->add_from_sql('managerid',
            "SELECT managerid FROM {local_sentientia_org_member}
              WHERE managerid > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // Memberships (unit name resolved for readability).
            $members = $DB->get_records_sql(
                "SELECT m.id, m.unitid, m.role, m.managerid, m.timecreated,
                        u.name AS unitname
                   FROM {local_sentientia_org_member} m
              LEFT JOIN {local_sentientia_org_unit} u ON u.id = m.unitid
                  WHERE m.userid = :userid
               ORDER BY m.timecreated ASC",
                ['userid' => $userid]);
            $member_data = [];
            foreach ($members as $m) {
                $member_data[] = [
                    'unit'        => $m->unitname ?? ('unit #' . $m->unitid),
                    'role'        => $m->role,
                    'managerid'   => (int) $m->managerid,
                    'timecreated' => userdate((int) $m->timecreated),
                ];
            }
            if (!empty($member_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_core'),
                     'org_memberships'],
                    (object) ['memberships' => $member_data]
                );
            }

            // Being someone's manager is also this user's data — export
            // the fact and scope, not the subordinates' identities.
            $managed = (int) $DB->count_records('local_sentientia_org_member',
                ['managerid' => $userid]);
            if ($managed > 0) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_core'),
                     'org_manager_of'],
                    (object) ['members_managed' => $managed]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_sentientia_org_member', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('local_sentientia_org_member',
                ['userid' => $userid]);
            // Unlink, don't delete: the rows themselves belong to the
            // members this user managed.
            $DB->set_field('local_sentientia_org_member', 'managerid', 0,
                ['managerid' => $userid]);
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
        $DB->delete_records_select('local_sentientia_org_member',
            "userid $insql", $params);
        [$insql2, $params2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mgr');
        $DB->set_field_select('local_sentientia_org_member', 'managerid', 0,
            "managerid $insql2", $params2);
    }
}
