<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for local_airpay_challenge.
 *
 * Stores PII across three tables:
 * - challenges:  `createdby` (the admin who created the challenge)
 * - attempts:    `userid` (the participant)
 * - leaderboard: `userid` (the participant; also `costcenterid` indirectly tracks the user's tenant)
 *
 * **Retention policy:**
 * - Per-user delete: nukes the user's attempts + leaderboard rows
 *   (gamification data is not compliance-critical).
 * - For challenges they CREATED: anonymise `createdby = 0` rather
 *   than delete (other users may have joined).
 *
 * @package local_airpay_challenge
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_airpay_challenge_challenges', [
            'createdby'    => 'privacy:metadata:challenges:createdby',
            'name'         => 'privacy:metadata:challenges:name',
            'open_path'    => 'privacy:metadata:challenges:open_path',
            'timecreated'  => 'privacy:metadata:challenges:timecreated',
        ], 'privacy:metadata:challenges');

        $collection->add_database_table('local_airpay_challenge_attempts', [
            'challengeid'  => 'privacy:metadata:attempts:challengeid',
            'userid'       => 'privacy:metadata:attempts:userid',
            'status'       => 'privacy:metadata:attempts:status',
            'progress'     => 'privacy:metadata:attempts:progress',
            'pointsawarded' => 'privacy:metadata:attempts:pointsawarded',
            'timecreated'  => 'privacy:metadata:attempts:timecreated',
        ], 'privacy:metadata:attempts');

        $collection->add_database_table('local_airpay_challenge_leaderboard', [
            'challengeid'  => 'privacy:metadata:leaderboard:challengeid',
            'userid'       => 'privacy:metadata:leaderboard:userid',
            'points'       => 'privacy:metadata:leaderboard:points',
            'userrank'     => 'privacy:metadata:leaderboard:userrank',
        ], 'privacy:metadata:leaderboard');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // All challenge data lives at system context.
        $sql = "SELECT id FROM {context} WHERE contextlevel = :sys";
        $contextlist->add_from_sql($sql, ['sys' => CONTEXT_SYSTEM]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        // userids from attempts + leaderboard + createdby.
        $sql = "SELECT userid FROM {local_airpay_challenge_attempts}
                 UNION
                SELECT userid FROM {local_airpay_challenge_leaderboard}
                 UNION
                SELECT createdby AS userid FROM {local_airpay_challenge_challenges}
                  WHERE createdby > 0";
        $userlist->add_from_sql('userid', $sql, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        // Challenges they created.
        $created = $DB->get_records('local_airpay_challenge_challenges',
            ['createdby' => $userid]);
        if (!empty($created)) {
            $entries = array_map(fn($c) => (object) [
                'name'         => format_string($c->name),
                'shortname'    => $c->shortname,
                'type'         => $c->type,
                'pointsreward' => (int) $c->pointsreward,
                'time_created' => (int) $c->timecreated,
            ], $created);
            writer::with_context($context)
                ->export_data(['Airpay Challenges — challenges I created'],
                    (object) ['challenges' => array_values($entries)]);
        }

        // Attempts.
        $attempts = $DB->get_records_sql("
            SELECT a.*, c.name AS challenge_name
              FROM {local_airpay_challenge_attempts} a
         LEFT JOIN {local_airpay_challenge_challenges} c ON c.id = a.challengeid
             WHERE a.userid = :u
          ORDER BY a.timecreated DESC", ['u' => $userid]);
        if (!empty($attempts)) {
            $entries = array_map(fn($a) => (object) [
                'challenge'       => format_string((string) ($a->challenge_name ?? '')),
                'status'          => $a->status,
                'progress'        => (int) $a->progress,
                'target'          => (int) $a->targetcount,
                'points_awarded'  => (int) $a->pointsawarded,
                'completion_date' => $a->completiondate ? (int) $a->completiondate : null,
            ], $attempts);
            writer::with_context($context)
                ->export_data(['Airpay Challenges — my participation'],
                    (object) ['attempts' => array_values($entries)]);
        }

        // Leaderboard.
        $leaderboard = $DB->get_records('local_airpay_challenge_leaderboard',
            ['userid' => $userid]);
        if (!empty($leaderboard)) {
            $entries = array_map(fn($l) => (object) [
                'challengeid' => (int) $l->challengeid,
                'points'      => (int) $l->points,
                'rank'        => (int) $l->userrank,
                'attempts_completed' => (int) $l->attemptscompleted,
            ], $leaderboard);
            writer::with_context($context)
                ->export_data(['Airpay Challenges — my leaderboard standings'],
                    (object) ['leaderboard' => array_values($entries)]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) return;
        $DB->delete_records('local_airpay_challenge_attempts');
        $DB->delete_records('local_airpay_challenge_leaderboard');
        $DB->execute("UPDATE {local_airpay_challenge_challenges}
                         SET createdby = 0, open_path = NULL");
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        $DB->delete_records('local_airpay_challenge_attempts',
            ['userid' => $userid]);
        $DB->delete_records('local_airpay_challenge_leaderboard',
            ['userid' => $userid]);
        // Challenges authored by user — anonymise rather than delete.
        $DB->execute("UPDATE {local_airpay_challenge_challenges}
                         SET createdby = 0, open_path = NULL
                       WHERE createdby = :u", ['u' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->execute("DELETE FROM {local_airpay_challenge_attempts}    WHERE userid $insql", $inparams);
        $DB->execute("DELETE FROM {local_airpay_challenge_leaderboard} WHERE userid $insql", $inparams);
        $DB->execute("UPDATE {local_airpay_challenge_challenges}
                         SET createdby = 0, open_path = NULL
                       WHERE createdby $insql", $inparams);
    }
}
