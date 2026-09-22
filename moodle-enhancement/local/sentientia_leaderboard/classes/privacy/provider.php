<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard\privacy;

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
 *   - local_sentientia_lb_entries  : the user's ranking row
 *   - local_sentientia_lb_optouts  : whether they opted-out
 *
 * Boards + events are NOT user-personal: a board is a config row, events
 * carry payloads with userids but the events themselves are a system
 * activity log not a user-personal record.
 *
 * @package local_sentientia_leaderboard
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_lb_entries',
            [
                'userid'           => 'privacy:metadata:lb_entries:userid',
                'boardid'          => 'privacy:metadata:lb_entries:boardid',
                'points'           => 'privacy:metadata:lb_entries:points',
                'userrank'         => 'privacy:metadata:lb_entries:userrank',
                'last_recomputed'  => 'privacy:metadata:lb_entries:last_recomputed',
            ],
            'privacy:metadata:lb_entries'
        );

        $collection->add_database_table(
            'local_sentientia_lb_optouts',
            [
                'userid'        => 'privacy:metadata:lb_optouts:userid',
                'customerid'    => 'privacy:metadata:lb_optouts:customerid',
                'timeoptedout'  => 'privacy:metadata:lb_optouts:timeoptedout',
            ],
            'privacy:metadata:lb_optouts'
        );

        // Added 2026-09-22 -- owned but undeclared.
        $collection->add_database_table(
            'local_sentientia_lb_boards',
            [
                'ownerid' => 'privacy:metadata:lb_boards:ownerid',
                'name'    => 'privacy:metadata:lb_boards:name',
            ],
            'privacy:metadata:lb_boards'
        );

        $collection->add_database_table(
            'local_sentientia_lb_notify_log',
            [
                'userid'        => 'privacy:metadata:lb_notify_log:userid',
                'boardid'       => 'privacy:metadata:lb_notify_log:boardid',
                'last_sent'     => 'privacy:metadata:lb_notify_log:last_sent',
                'last_old_rank' => 'privacy:metadata:lb_notify_log:last_old_rank',
                'last_new_rank' => 'privacy:metadata:lb_notify_log:last_new_rank',
                'last_reason'   => 'privacy:metadata:lb_notify_log:last_reason',
            ],
            'privacy:metadata:lb_notify_log'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Both tables are system-context — leaderboards aren't bound to
        // a course context.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_lb_entries}", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_lb_optouts}", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_lb_notify_log} WHERE userid > 0", []);
        $userlist->add_from_sql('ownerid',
            "SELECT ownerid FROM {local_sentientia_lb_boards} WHERE ownerid > 0", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // Entries.
            $entries = $DB->get_records('local_sentientia_lb_entries',
                ['userid' => $userid]);
            $entry_data = [];
            foreach ($entries as $e) {
                $entry_data[] = [
                    'boardid'         => (int) $e->boardid,
                    'points'          => (int) $e->points,
                    'userrank'        => (int) $e->userrank,
                    'last_recomputed' => userdate((int) $e->last_recomputed),
                ];
            }
            if (!empty($entry_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_leaderboard'),
                     'entries'],
                    (object) ['entries' => $entry_data]
                );
            }

            // Opt-outs.
            $optouts = $DB->get_records('local_sentientia_lb_optouts',
                ['userid' => $userid]);
            $optout_data = [];
            foreach ($optouts as $o) {
                $optout_data[] = [
                    'customerid'   => (int) $o->customerid,
                    'timeoptedout' => userdate((int) $o->timeoptedout),
                ];
            }
            if (!empty($optout_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_leaderboard'),
                     'optouts'],
                    (object) ['optouts' => $optout_data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_sentientia_lb_entries', []);
        $DB->delete_records('local_sentientia_lb_optouts', []);
        $DB->delete_records('local_sentientia_lb_notify_log', []);
        // A board is shared configuration -- other people's leaderboards --
        // so the owner reference is anonymised rather than the board deleted.
        $DB->set_field('local_sentientia_lb_boards', 'ownerid', 0, []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('local_sentientia_lb_entries',
                ['userid' => $userid]);
            $DB->delete_records('local_sentientia_lb_optouts',
                ['userid' => $userid]);
            $DB->delete_records('local_sentientia_lb_notify_log',
                ['userid' => $userid]);
            // Anonymise, do not delete: the board belongs to everyone on it.
            $DB->set_field('local_sentientia_lb_boards', 'ownerid', 0,
                ['ownerid' => $userid]);
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
        $DB->delete_records_select('local_sentientia_lb_entries',
            "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_lb_optouts',
            "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_lb_notify_log',
            "userid $insql", $params);
        $DB->set_field_select('local_sentientia_lb_boards', 'ownerid', 0,
            "ownerid $insql", $params);
    }
}
