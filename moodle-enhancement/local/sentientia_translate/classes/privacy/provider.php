<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Phase T.0.
 *
 * Declares personal data:
 *   translations_log — ownerid, source text, translated text, tokens, timestamps
 *
 * The brand-overrides table holds no personal data (customer-level
 * configuration only) so it is not declared as containing user data.
 *
 * Also declares the external Anthropic API as a subsystem we send source
 * text to (only when the live-API flag is ON).
 *
 * @package local_sentientia_translate
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_tr_log',
            [
                'ownerid'        => 'privacy:metadata:tr:ownerid',
                'sourcetext'     => 'privacy:metadata:tr:sourcetext',
                'translatedtext' => 'privacy:metadata:tr:translatedtext',
                'targetlang'     => 'privacy:metadata:tr:targetlang',
                'title'          => 'privacy:metadata:tr:title',
                'tokens_in'      => 'privacy:metadata:tr:tokens',
                'tokens_out'     => 'privacy:metadata:tr:tokens',
                'timecreated'    => 'privacy:metadata:tr:timecreated',
                'timemodified'   => 'privacy:metadata:tr:timemodified',
            ],
            'privacy:metadata:tr'
        );

        // External subsystem — Anthropic Claude.
        $collection->add_external_location_link(
            'anthropic_api',
            [
                'sourcetext' => 'privacy:metadata:anthropic:sourcetext',
                'targetlang' => 'privacy:metadata:anthropic:targetlang',
                'model'      => 'privacy:metadata:anthropic:model',
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
        $sql = "SELECT DISTINCT ownerid AS userid FROM {local_sentientia_tr_log}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        $rows = $DB->get_records('local_sentientia_tr_log', ['ownerid' => $userid],
            'timecreated DESC');
        if (!$rows) {
            return;
        }

        $context = \context_system::instance();
        $exportdata = [];
        foreach ($rows as $r) {
            $exportdata[] = [
                'id'             => $r->id,
                'title'          => $r->title,
                'sourcetext'     => $r->sourcetext,
                'translatedtext' => $r->translatedtext,
                'targetlang'     => $r->targetlang,
                'status'         => $r->status,
                'tokens_in'      => $r->tokens_in,
                'tokens_out'     => $r->tokens_out,
                'timecreated'    => $r->timecreated,
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_sentientia_translate')],
            (object)['translations' => $exportdata]
        );
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_tr_log');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_tr_log', ['ownerid' => $userid]);
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
        $DB->delete_records_select('local_sentientia_tr_log', "ownerid $insql", $params);
    }
}
