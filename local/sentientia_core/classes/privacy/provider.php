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
 * Tables that carry user data:
 *   - local_sentientia_org_member : a user's org-unit membership
 *                                   (unit, role, direct manager)
 *   - local_sentientia_admin_log  : the imported BizLMS admin log and bulk
 *                                   course upload errors (ADR-032 legacy_logs):
 *                                   the actor (userid, usermodified) and a
 *                                   description that names the actor by first name
 *
 * A user can appear in org_member two ways: as the member (userid) and as
 * another member's manager (managerid). Deletion removes their own
 * membership rows and resets managerid to 0 on rows that reference them —
 * the remaining rows are the other members' data, not theirs.
 *
 * The admin log is history, so an erasure request KEEPS the row and removes
 * the person (signed decision legacy_logs.description_erasure =
 * keep_row_scrub_name): userid and usermodified are set to 0 and the first
 * name in the description is replaced (admin_log::scrub_description()).
 *
 * customer / tenant / org_unit tables are org configuration (names, ids,
 * status) and carry no user data.
 *
 * The substrate adds BizLMS columns to core tables (classes/substrate.php). One of them is a person:
 * course.open_coursecreator, the user who created the course (owner decision, 2026-10-07, "privacy: actor columns", rule R9
 * of the BizLMS import; 0 courses carry one on the April 2026 copy, but the live backup may). It was declared by no
 * provider. A course belongs to its tenant, not to its creator, so an erasure KEEPS the course and sets the creator to 0
 * (the signed users.erasure_treatment = anonymise design for actor columns); the export lists the courses the user created
 * (id, short name, when). The column exists only on a site that has the BizLMS substrate, so every access checks for it.
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

        $collection->add_database_table(
            'local_sentientia_admin_log',
            [
                'source'       => 'privacy:metadata:admin_log:source',
                'event'        => 'privacy:metadata:admin_log:event',
                'module'       => 'privacy:metadata:admin_log:module',
                'description'  => 'privacy:metadata:admin_log:description',
                'itemref'      => 'privacy:metadata:admin_log:itemref',
                'userid'       => 'privacy:metadata:admin_log:userid',
                'usermodified' => 'privacy:metadata:admin_log:usermodified',
                'actor_path'   => 'privacy:metadata:admin_log:actor_path',
                'timecreated'  => 'privacy:metadata:admin_log:timecreated',
                'timemodified' => 'privacy:metadata:admin_log:timemodified',
            ],
            'privacy:metadata:admin_log'
        );

        $collection->add_database_table(
            'course',
            [
                'open_coursecreator' => 'privacy:metadata:course_creator:open_coursecreator',
            ],
            'privacy:metadata:course_creator'
        );

        return $collection;
    }

    /**
     * Does this site have the BizLMS course-creator column? A vanilla Moodle does not.
     *
     * @return bool
     */
    private static function has_creator_column(): bool {
        global $DB;
        return array_key_exists('open_coursecreator', $DB->get_columns('course'));
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
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_admin_log}
              WHERE userid > 0", []);
        $userlist->add_from_sql('usermodified',
            "SELECT usermodified FROM {local_sentientia_admin_log}
              WHERE usermodified > 0", []);
        if (self::has_creator_column()) {
            $userlist->add_from_sql('open_coursecreator',
                "SELECT DISTINCT open_coursecreator FROM {course}
                  WHERE open_coursecreator > 0", []);
        }
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

            // The imported admin log: entries where this user is the actor or
            // the modifier. The description names the user, so it is exported.
            // The user record's id is a string when it comes straight from the database, so compare as integers.
            $entries = [];
            $uid = (int) $userid;
            foreach (\local_sentientia_core\admin_log::rows_for_user($uid) as $row) {
                $entries[] = [
                    'source'       => $row->source,
                    'event'        => $row->event,
                    'module'       => $row->module,
                    'itemref'      => $row->itemref,
                    'description'  => $row->description,
                    'actor'        => (int) $row->userid === $uid,
                    'modifier'     => (int) $row->usermodified === $uid,
                    'timecreated'  => userdate((int) $row->timecreated),
                    'timemodified' => userdate((int) $row->timemodified),
                ];
            }
            if (!empty($entries)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_core'),
                     'admin_log'],
                    (object) ['entries' => $entries]
                );
            }

            // Courses this user created (BizLMS open_coursecreator): which course and when, nothing else.
            if (self::has_creator_column()) {
                $created = [];
                foreach ($DB->get_records_select('course', 'open_coursecreator = :uid', ['uid' => $uid], 'id',
                        'id, shortname, timecreated') as $course) {
                    $created[] = [
                        'courseid'    => (int) $course->id,
                        'shortname'   => $course->shortname,
                        'timecreated' => userdate((int) $course->timecreated),
                    ];
                }
                if (!empty($created)) {
                    writer::with_context($context)->export_data(
                        [get_string('pluginname', 'local_sentientia_core'),
                         'courses_created'],
                        (object) ['courses' => $created]
                    );
                }
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_sentientia_org_member', []);
        // History is kept; only the people are removed from it.
        \local_sentientia_core\admin_log::anonymise_all();
        // Courses stay with their tenant; no creator stays on them.
        if (self::has_creator_column()) {
            $DB->set_field_select('course', 'open_coursecreator', 0, 'open_coursecreator <> 0');
        }
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
            // The admin log keeps the row and loses the person.
            \local_sentientia_core\admin_log::anonymise_users([$userid]);
            // A course keeps its record and loses its creator.
            if (self::has_creator_column()) {
                $DB->set_field('course', 'open_coursecreator', 0, ['open_coursecreator' => $userid]);
            }
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
        \local_sentientia_core\admin_log::anonymise_users($userids);
        if (self::has_creator_column()) {
            [$insql3, $params3] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'cre');
            $DB->set_field_select('course', 'open_coursecreator', 0, "open_coursecreator $insql3", $params3);
        }
    }
}
