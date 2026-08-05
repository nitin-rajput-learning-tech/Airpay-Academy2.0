<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Real privacy provider for local_sentientia_assistant.
//
// Replaces the former null_provider: this plugin DOES store personal data in
// airpay-owned tables —
//   {local_sentientia_chat_log}     — the learner's conversation with the assistant
//   {local_sentientia_agent_audit}  — P1.3 agentic copilot: every tool the LLM
//                                      proposed on the user's behalf + the
//                                      authorisation outcome and arguments
// and (when the live-API flag is ON) sends chat messages to Anthropic Claude.
// {local_sentientia_chat_cache} holds query→response pairs keyed by a hash, with
// no userid, so it carries no personal data and is not exported per-user.

namespace local_sentientia_assistant\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — P1.3 agentic copilot + chat assistant.
 *
 * @package local_sentientia_assistant
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_chat_log',
            [
                'userid'      => 'privacy:metadata:chat_log:userid',
                'role'        => 'privacy:metadata:chat_log:role',
                'message'     => 'privacy:metadata:chat_log:message',
                'model'       => 'privacy:metadata:chat_log:model',
                'tokens_in'   => 'privacy:metadata:chat_log:tokens_in',
                'tokens_out'  => 'privacy:metadata:chat_log:tokens_out',
                'timecreated' => 'privacy:metadata:chat_log:timecreated',
            ],
            'privacy:metadata:chat_log'
        );

        $collection->add_database_table(
            'local_sentientia_agent_audit',
            [
                'userid'          => 'privacy:metadata:agent_audit:userid',
                'costcenterid'    => 'privacy:metadata:agent_audit:costcenterid',
                'tool'            => 'privacy:metadata:agent_audit:tool',
                'args_json'       => 'privacy:metadata:agent_audit:args_json',
                'proposed_by'     => 'privacy:metadata:agent_audit:proposed_by',
                'outcome'         => 'privacy:metadata:agent_audit:outcome',
                'detail'          => 'privacy:metadata:agent_audit:detail',
                'idempotency_key' => 'privacy:metadata:agent_audit:idempotency_key',
                'timecreated'     => 'privacy:metadata:agent_audit:timecreated',
            ],
            'privacy:metadata:agent_audit'
        );

        // External subsystem — Anthropic Claude. Only sent when the live-API
        // flag is ON; the chat message text + model id leave the platform.
        $collection->add_external_location_link(
            'anthropic_api',
            [
                'message' => 'privacy:metadata:anthropic:message',
                'model'   => 'privacy:metadata:anthropic:model',
            ],
            'privacy:metadata:anthropic'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT 1
                  FROM {local_sentientia_chat_log} cl
                 WHERE cl.userid = :uid1
                 UNION
                SELECT 1
                  FROM {local_sentientia_agent_audit} aa
                 WHERE aa.userid = :uid2";
        global $DB;
        if ($DB->record_exists_sql($sql, ['uid1' => $userid, 'uid2' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_chat_log}", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_agent_audit}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(CONTEXT_SYSTEM, array_map(static function($c) {
            return $c->contextlevel;
        }, $contextlist->get_contexts()), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $chats = $DB->get_records('local_sentientia_chat_log', ['userid' => $userid],
            'timecreated ASC');
        if ($chats) {
            $rows = [];
            foreach ($chats as $c) {
                $rows[] = [
                    'role'        => $c->role,
                    'message'     => $c->message,
                    'model'       => $c->model,
                    'tokens_in'   => $c->tokens_in,
                    'tokens_out'  => $c->tokens_out,
                    'timecreated' => \core_privacy\local\request\transform::datetime($c->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_assistant'),
                 get_string('privacy:export:chat', 'local_sentientia_assistant')],
                (object) ['messages' => $rows]
            );
        }

        $audit = $DB->get_records('local_sentientia_agent_audit', ['userid' => $userid],
            'timecreated ASC');
        if ($audit) {
            $rows = [];
            foreach ($audit as $a) {
                $rows[] = [
                    'tool'        => $a->tool,
                    'args_json'   => $a->args_json,
                    'proposed_by' => $a->proposed_by,
                    'outcome'     => $a->outcome,
                    'detail'      => $a->detail,
                    'timecreated' => \core_privacy\local\request\transform::datetime($a->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_assistant'),
                 get_string('privacy:export:audit', 'local_sentientia_assistant')],
                (object) ['actions' => $rows]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_chat_log');
        $DB->delete_records('local_sentientia_agent_audit');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(CONTEXT_SYSTEM, array_map(static function($c) {
            return $c->contextlevel;
        }, $contextlist->get_contexts()), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_chat_log', ['userid' => $userid]);
        $DB->delete_records('local_sentientia_agent_audit', ['userid' => $userid]);
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
        $DB->delete_records_select('local_sentientia_chat_log', "userid $insql", $params);
        $DB->delete_records_select('local_sentientia_agent_audit', "userid $insql", $params);
    }
}
