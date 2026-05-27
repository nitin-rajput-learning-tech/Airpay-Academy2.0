<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Phase H.0.
 *
 * Declares personal data:
 *   recommendations_log — userid, courseid, reasoning, tokens, timestamps
 *
 * Also declares the external Anthropic API as a subsystem we send data
 * to (only when the live-API flag is ON).
 *
 * @package local_sentientia_recommendations
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_rec_log',
            [
                'userid'       => 'privacy:metadata:rec:userid',
                'courseid'     => 'privacy:metadata:rec:courseid',
                'score'        => 'privacy:metadata:rec:score',
                'reasoning'    => 'privacy:metadata:rec:reasoning',
                'tokens_in'    => 'privacy:metadata:rec:tokens',
                'tokens_out'   => 'privacy:metadata:rec:tokens',
                'status'       => 'privacy:metadata:rec:status',
                'generated_at' => 'privacy:metadata:rec:generated_at',
                'timecreated'  => 'privacy:metadata:rec:timecreated',
                'timemodified' => 'privacy:metadata:rec:timemodified',
            ],
            'privacy:metadata:rec'
        );

        // External subsystem — Anthropic Claude.
        $collection->add_external_location_link(
            'anthropic_api',
            [
                'profile_role'      => 'privacy:metadata:anthropic:profile_role',
                'profile_completed' => 'privacy:metadata:anthropic:profile_completed',
                'profile_skills'    => 'privacy:metadata:anthropic:profile_skills',
                'model'             => 'privacy:metadata:anthropic:model',
            ],
            'privacy:metadata:anthropic'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT DISTINCT userid FROM {local_sentientia_rec_log}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        $rows = $DB->get_records('local_sentientia_rec_log', ['userid' => $userid],
            'generated_at DESC');
        if (!$rows) {
            return;
        }

        $context = \context_system::instance();
        $exportdata = [];
        foreach ($rows as $r) {
            $exportdata[] = [
                'id'           => $r->id,
                'courseid'     => $r->courseid,
                'score'        => $r->score,
                'reasoning'    => $r->reasoning,
                'status'       => $r->status,
                'tokens_in'    => $r->tokens_in,
                'tokens_out'   => $r->tokens_out,
                'generated_at' => $r->generated_at,
                'timecreated'  => $r->timecreated,
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_sentientia_recommendations')],
            (object)['recommendations' => $exportdata]
        );
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_rec_log');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_rec_log', ['userid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!($userlist->get_context() instanceof \context_system)) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_sentientia_rec_log', "userid $insql", $params);
    }
}
