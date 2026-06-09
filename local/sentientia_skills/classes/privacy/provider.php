<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_sentientia_skills.
 *
 * The only PII-bearing table is `local_sentientia_user_skills` which stores
 * earned skill levels per user. Other tables (skill_cats, skills,
 * skill_levels, role_skills, course_skills) are reference data with no
 * user-specific rows.
 *
 * @package local_sentientia_skills
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_user_skills', [
            'userid'        => 'privacy:metadata:user_skills:userid',
            'skillid'       => 'privacy:metadata:user_skills:skillid',
            'current_level' => 'privacy:metadata:user_skills:current_level',
            'source'        => 'privacy:metadata:user_skills:source',
            'source_id'     => 'privacy:metadata:user_skills:source_id',
            'timecreated'   => 'privacy:metadata:user_skills:timecreated',
        ], 'privacy:metadata:user_skills');

        // P1 #22 (2026-05-16) — audit log table. Each row records the
        // before/after level the user held at a point in time.
        $collection->add_database_table('local_sentientia_user_skill_hist', [
            'userid'            => 'privacy:metadata:user_skill_hist:userid',
            'skillid'           => 'privacy:metadata:user_skill_hist:skillid',
            'previous_level'    => 'privacy:metadata:user_skill_hist:previous_level',
            'new_level'         => 'privacy:metadata:user_skill_hist:new_level',
            'source'            => 'privacy:metadata:user_skill_hist:source',
            'source_id'         => 'privacy:metadata:user_skill_hist:source_id',
            'changed_by_userid' => 'privacy:metadata:user_skill_hist:changed_by_userid',
            'timecreated'       => 'privacy:metadata:user_skill_hist:timecreated',
        ], 'privacy:metadata:user_skill_hist');

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
            "SELECT DISTINCT userid FROM {local_sentientia_user_skills}", []);
        // P1 #22 — also pick up users who only appear in the audit log
        // (e.g. they were granted a level then later opted-out of all
        // skill tracking — their current row is gone but history remains
        // until erasure runs).
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT userid FROM {local_sentientia_user_skill_hist}", []);
        // Acting users (managers / admins) who recorded changes for
        // others must also be discoverable.
        $userlist->add_from_sql('changed_by_userid',
            "SELECT DISTINCT changed_by_userid
               FROM {local_sentientia_user_skill_hist}
              WHERE changed_by_userid IS NOT NULL", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $rows = $DB->get_records_sql("
            SELECT us.*, s.name AS skill_name, c.name AS category_name
              FROM {local_sentientia_user_skills} us
              JOIN {local_sentientia_skills} s ON s.id = us.skillid
         LEFT JOIN {local_sentientia_skill_cats} c ON c.id = s.categoryid
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

        // P1 #22 — also export the audit-log timeline.
        $history = $DB->get_records_sql("
            SELECT h.*, s.name AS skill_name
              FROM {local_sentientia_user_skill_hist} h
              JOIN {local_sentientia_skills} s ON s.id = h.skillid
             WHERE h.userid = :u
          ORDER BY h.timecreated DESC, h.id DESC", ['u' => $userid]);
        if (!empty($history)) {
            $hist_entries = array_map(fn($r) => (object) [
                'skill'             => format_string($r->skill_name),
                'previous_level'    => (int) $r->previous_level,
                'new_level'         => (int) $r->new_level,
                'source'            => (string) $r->source,
                'source_id'         => $r->source_id !== null ? (int) $r->source_id : null,
                'changed_by_userid' => $r->changed_by_userid !== null
                    ? (int) $r->changed_by_userid : null,
                'time_recorded'     => (int) $r->timecreated,
            ], $history);
            writer::with_context($context)
                ->export_data(['Airpay Skills — my skill-level history'],
                    (object) ['changes' => array_values($hist_entries)]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_sentientia_user_skills');
        // P1 #22 — also purge the audit log on full erasure.
        $DB->delete_records('local_sentientia_user_skill_hist');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $uid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_user_skills', ['userid' => $uid]);
        // P1 #22 — wipe the user's history rows AND any rows where they
        // appear only as the actor (so the data subject's id isn't
        // retained as `changed_by_userid` on someone else's row).
        $DB->delete_records('local_sentientia_user_skill_hist', ['userid' => $uid]);
        $DB->set_field('local_sentientia_user_skill_hist',
            'changed_by_userid', null, ['changed_by_userid' => $uid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->execute("DELETE FROM {local_sentientia_user_skills} WHERE userid $insql", $inparams);
        // P1 #22 — same dual cleanup for the history table.
        $DB->execute("DELETE FROM {local_sentientia_user_skill_hist} WHERE userid $insql", $inparams);
        $DB->execute("UPDATE {local_sentientia_user_skill_hist}
                         SET changed_by_userid = NULL
                       WHERE changed_by_userid $insql", $inparams);
    }
}
