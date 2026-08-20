<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_gamification\privacy;

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
 *   - local_sentientia_points_log  : per-action points awarded to a user
 *   - local_sentientia_user_badges : badges a user has earned
 *   - local_sentientia_streaks     : the user's login-streak counters
 *
 * local_sentientia_badges is badge configuration (name, icon, criteria)
 * and carries no user data.
 *
 * @package local_sentientia_gamification
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_points_log',
            [
                'userid'      => 'privacy:metadata:points_log:userid',
                'action'      => 'privacy:metadata:points_log:action',
                'points'      => 'privacy:metadata:points_log:points',
                'courseid'    => 'privacy:metadata:points_log:courseid',
                'description' => 'privacy:metadata:points_log:description',
                'timecreated' => 'privacy:metadata:points_log:timecreated',
            ],
            'privacy:metadata:points_log'
        );

        $collection->add_database_table(
            'local_sentientia_user_badges',
            [
                'userid'     => 'privacy:metadata:user_badges:userid',
                'badgeid'    => 'privacy:metadata:user_badges:badgeid',
                'timeearned' => 'privacy:metadata:user_badges:timeearned',
            ],
            'privacy:metadata:user_badges'
        );

        $collection->add_database_table(
            'local_sentientia_streaks',
            [
                'userid'          => 'privacy:metadata:streaks:userid',
                'current_streak'  => 'privacy:metadata:streaks:current_streak',
                'longest_streak'  => 'privacy:metadata:streaks:longest_streak',
                'last_login_date' => 'privacy:metadata:streaks:last_login_date',
                'total_points'    => 'privacy:metadata:streaks:total_points',
            ],
            'privacy:metadata:streaks'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // All three tables are system-context — gamification state isn't
        // bound to a course context (points reference courses by id only).
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_points_log}", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_user_badges}", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_streaks}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // Points history.
            $points = $DB->get_records('local_sentientia_points_log',
                ['userid' => $userid], 'timecreated ASC');
            $point_data = [];
            foreach ($points as $p) {
                $point_data[] = [
                    'action'      => $p->action,
                    'points'      => (int) $p->points,
                    'courseid'    => (int) $p->courseid,
                    'description' => $p->description,
                    'timecreated' => userdate((int) $p->timecreated),
                ];
            }
            if (!empty($point_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_gamification'),
                     'points'],
                    (object) ['points' => $point_data]
                );
            }

            // Earned badges (badge name resolved for readability).
            $badges = $DB->get_records_sql(
                "SELECT ub.id, ub.badgeid, ub.timeearned, b.name
                   FROM {local_sentientia_user_badges} ub
              LEFT JOIN {local_sentientia_badges} b ON b.id = ub.badgeid
                  WHERE ub.userid = :userid
               ORDER BY ub.timeearned ASC",
                ['userid' => $userid]);
            $badge_data = [];
            foreach ($badges as $b) {
                $badge_data[] = [
                    'badge'      => $b->name ?? ('badge #' . $b->badgeid),
                    'timeearned' => userdate((int) $b->timeearned),
                ];
            }
            if (!empty($badge_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_gamification'),
                     'badges'],
                    (object) ['badges' => $badge_data]
                );
            }

            // Streaks.
            $streaks = $DB->get_records('local_sentientia_streaks',
                ['userid' => $userid]);
            $streak_data = [];
            foreach ($streaks as $s) {
                $streak_data[] = [
                    'current_streak'  => (int) $s->current_streak,
                    'longest_streak'  => (int) $s->longest_streak,
                    'last_login_date' => $s->last_login_date,
                    'total_points'    => (int) $s->total_points,
                ];
            }
            if (!empty($streak_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_gamification'),
                     'streaks'],
                    (object) ['streaks' => $streak_data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_sentientia_points_log', []);
        $DB->delete_records('local_sentientia_user_badges', []);
        $DB->delete_records('local_sentientia_streaks', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('local_sentientia_points_log',
                ['userid' => $userid]);
            $DB->delete_records('local_sentientia_user_badges',
                ['userid' => $userid]);
            $DB->delete_records('local_sentientia_streaks',
                ['userid' => $userid]);
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
        $DB->delete_records_select('local_sentientia_points_log',
            "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_user_badges',
            "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_streaks',
            "userid $insql", $params);
    }
}
