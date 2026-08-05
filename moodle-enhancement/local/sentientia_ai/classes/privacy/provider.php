<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * REAL privacy provider — the ledger is user-attributed (every gateway
 * call records the acting userid), so this plugin exports and deletes on
 * request. Prompt/response TEXT is never stored (only token counts,
 * cost estimates and failure reasons), which keeps the ledger useful for
 * spend audit without becoming a shadow store of learner content.
 *
 * @package local_sentientia_ai
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_ai_ledger', [
            'userid'           => 'privacy:metadata:ledger:userid',
            'component'        => 'privacy:metadata:ledger:component',
            'purpose'          => 'privacy:metadata:ledger:purpose',
            'model'            => 'privacy:metadata:ledger:model',
            'prompttokens'     => 'privacy:metadata:ledger:prompttokens',
            'completiontokens' => 'privacy:metadata:ledger:completiontokens',
            'estcost'          => 'privacy:metadata:ledger:estcost',
            'mode'             => 'privacy:metadata:ledger:mode',
            'timecreated'      => 'privacy:metadata:ledger:timecreated',
        ], 'privacy:metadata:ledger');

        $collection->add_external_location_link('anthropic_api',
            ['prompttext' => 'privacy:metadata:anthropic:prompttext'],
            'privacy:metadata:anthropic');

        return $collection;
    }

    /**
     * Ledger rows live at system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_sentientia_ai_ledger', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT userid FROM {local_sentientia_ai_ledger} WHERE userid > 0", []);
    }

    /**
     * Export a user's ledger rows.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $rows = $DB->get_records('local_sentientia_ai_ledger',
                ['userid' => $userid], 'timecreated ASC');
            $out = [];
            foreach ($rows as $row) {
                $out[] = (object) [
                    'component'         => $row->component,
                    'purpose'           => $row->purpose,
                    'model'             => $row->model,
                    'prompt_tokens'     => $row->prompttokens,
                    'completion_tokens' => $row->completiontokens,
                    'estimated_cost'    => $row->estcost,
                    'mode'              => $row->mode,
                    'timecreated'       => transform::datetime($row->timecreated),
                ];
            }
            if ($out) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:exportpath', 'local_sentientia_ai')],
                    (object) ['calls' => $out]);
            }
        }
    }

    /**
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->delete_records('local_sentientia_ai_ledger');
        }
    }

    /**
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->delete_records('local_sentientia_ai_ledger', ['userid' => $userid]);
            }
        }
    }

    /**
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_sentientia_ai_ledger', "userid {$insql}", $params);
    }
}
