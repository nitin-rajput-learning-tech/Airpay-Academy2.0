<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_airpay_skills.
 *
 * The only PII-bearing table is `local_airpay_user_skills` which stores
 * earned skill levels per user. Other tables (skill_cats, skills,
 * skill_levels, role_skills, course_skills) are reference data with no
 * user-specific rows.
 *
 * @package local_airpay_skills
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_airpay_user_skills', [
            'userid'        => 'privacy:metadata:user_skills:userid',
            'skillid'       => 'privacy:metadata:user_skills:skillid',
            'current_level' => 'privacy:metadata:user_skills:current_level',
            'source'        => 'privacy:metadata:user_skills:source',
            'source_id'     => 'privacy:metadata:user_skills:source_id',
            'timecreated'   => 'privacy:metadata:user_skills:timecreated',
        ], 'privacy:metadata:user_skills');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT id FROM {context} WHERE contextlevel = :sys";
        $contextlist->add_from_sql($sql, ['sys' => CONTEXT_SYSTEM]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT userid FROM {local_airpay_user_skills}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $rows = $DB->get_records_sql("
            SELECT us.*, s.name AS skill_name, c.name AS category_name
              FROM {local_airpay_user_skills} us
              JOIN {local_airpay_skills} s ON s.id = us.skillid
         LEFT JOIN {local_airpay_skill_cats} c ON c.id = s.categoryid
             WHERE us.userid = :u
          ORDER BY c.sort_order ASC, s.name ASC", ['u' => $userid]);

        if (empty($rows)) return;

        $entries = array_map(fn($r) => (object) [
            'category'     => format_string((string) ($r->category_name ?? '')),
            'skill'        => format_string($r->skill_name),
            'current_level' => (int) $r->current_level,
            'source'       => $r->source,
            'time_recorded' => (int) $r->timecreated,
        ], $rows);

        writer::with_context($context)
            ->export_data(['Airpay Skills — my skill levels'],
                (object) ['skills' => array_values($entries)]);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_airpay_user_skills');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $DB->delete_records('local_airpay_user_skills',
            ['userid' => $contextlist->get_user()->id]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->execute("DELETE FROM {local_airpay_user_skills} WHERE userid $insql", $inparams);
    }
}
