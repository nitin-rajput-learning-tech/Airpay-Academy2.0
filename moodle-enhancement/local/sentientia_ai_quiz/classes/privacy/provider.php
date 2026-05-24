<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai_quiz\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Phase G.1 scaffold.
 *
 * Declares one local table — {local_sentientia_ai_quiz_log} — plus the
 * external Anthropic subsystem. The scaffold never persists a row (the
 * client always throws confirm_required), but the metadata is declared
 * up front so the Privacy API treats this plugin as fully accounted for
 * from day 1. The live-wiring chip will start inserting rows without
 * needing to revisit privacy metadata.
 *
 * Phase G.1 scaffold contract:
 *   - Log table has no rows yet. export, delete_user, delete_users, and
 *     get_users_in_context all return cleanly with empty results.
 *   - Privacy tests assert the empty-state behaviour explicitly.
 *
 * @package local_sentientia_ai_quiz
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe what personal data the plugin stores + transmits.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        // Local table — generation audit log.
        $collection->add_database_table(
            'local_sentientia_ai_quiz_log',
            [
                'userid'      => 'privacy:metadata:log:userid',
                'courseid'    => 'privacy:metadata:log:courseid',
                'prompt_hash' => 'privacy:metadata:log:prompt_hash',
                'model'       => 'privacy:metadata:log:model',
                'tokens_in'   => 'privacy:metadata:log:tokens',
                'tokens_out'  => 'privacy:metadata:log:tokens',
                'success'     => 'privacy:metadata:log:success',
                'error'       => 'privacy:metadata:log:error',
                'timecreated' => 'privacy:metadata:log:timecreated',
            ],
            'privacy:metadata:log'
        );

        // External subsystem — Anthropic Claude. Phase G.1 scaffold does
        // not yet POST; the live-wiring chip will exercise this link.
        $collection->add_external_location_link(
            'anthropic_api',
            [
                'sourcetext' => 'privacy:metadata:anthropic:sourcetext',
                'lang'       => 'privacy:metadata:anthropic:lang',
            ],
            'privacy:metadata:anthropic'
        );

        return $collection;
    }

    /**
     * Return the list of contexts that contain personal data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $exists = $DB->record_exists('local_sentientia_ai_quiz_log', ['userid' => $userid]);
        if ($exists) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Find users in a given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT DISTINCT userid FROM {local_sentientia_ai_quiz_log}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export personal data for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $rows = $DB->get_records('local_sentientia_ai_quiz_log',
            ['userid' => $userid], 'timecreated ASC');

        if (!$rows) {
            return;
        }

        $exportable = [];
        foreach ($rows as $r) {
            $exportable[] = [
                'id'           => (int) $r->id,
                'courseid'     => (int) $r->courseid,
                'customerid'   => (int) $r->customerid,
                'prompt_hash'  => $r->prompt_hash,
                'model'        => $r->model,
                'tokens_in'    => (int) $r->tokens_in,
                'tokens_out'   => (int) $r->tokens_out,
                'success'      => (int) $r->success,
                'error'        => $r->error,
                'timecreated'  => (int) $r->timecreated,
                'timemodified' => (int) $r->timemodified,
            ];
        }

        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_sentientia_ai_quiz')],
            (object) ['log' => $exportable]
        );
    }

    /**
     * Delete all personal data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_ai_quiz_log');
    }

    /**
     * Delete personal data for one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_ai_quiz_log', ['userid' => $userid]);
    }

    /**
     * Delete personal data for a set of users.
     *
     * @param approved_userlist $userlist
     */
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
        $DB->delete_records_select('local_sentientia_ai_quiz_log',
            "userid $insql", $params);
    }
}
