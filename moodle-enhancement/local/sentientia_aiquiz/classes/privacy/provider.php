<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Phase G.0.
 *
 * Declares personal data:
 *   draft     — owner, source text, model, tokens, timestamps
 *   question  — content created on behalf of owner (no direct PII)
 *
 * Also declares the external Anthropic API as a subsystem we send data to.
 *
 * @package local_sentientia_aiquiz
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_aiquiz_draft',
            [
                'ownerid'      => 'privacy:metadata:draft:ownerid',
                'sourcetext'   => 'privacy:metadata:draft:sourcetext',
                'title'        => 'privacy:metadata:draft:title',
                'tokens_in'    => 'privacy:metadata:draft:tokens',
                'tokens_out'   => 'privacy:metadata:draft:tokens',
                'reviewed_by'  => 'privacy:metadata:draft:reviewed_by',
                'reviewed_at'  => 'privacy:metadata:draft:reviewed_at',
                'timecreated'  => 'privacy:metadata:draft:timecreated',
                'timemodified' => 'privacy:metadata:draft:timemodified',
            ],
            'privacy:metadata:draft'
        );

        $collection->add_database_table(
            'local_sentientia_aiquiz_question',
            [
                'draftid'       => 'privacy:metadata:question:draftid',
                'qtext'         => 'privacy:metadata:question:qtext',
                'qoptions_json' => 'privacy:metadata:question:qoptions',
                'reviewer_note' => 'privacy:metadata:question:reviewer_note',
                'timecreated'   => 'privacy:metadata:question:timecreated',
                'timemodified'  => 'privacy:metadata:question:timemodified',
            ],
            'privacy:metadata:question'
        );

        // External subsystem — Anthropic Claude.
        $collection->add_external_location_link(
            'anthropic_api',
            [
                'sourcetext' => 'privacy:metadata:anthropic:sourcetext',
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
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT DISTINCT ownerid AS userid FROM {local_sentientia_aiquiz_draft}";
        $userlist->add_from_sql('userid', $sql, []);
        $sql2 = "SELECT DISTINCT reviewed_by AS userid FROM {local_sentientia_aiquiz_draft} WHERE reviewed_by IS NOT NULL";
        $userlist->add_from_sql('userid', $sql2, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        $drafts = $DB->get_records('local_sentientia_aiquiz_draft', ['ownerid' => $userid]);
        if (!$drafts) {
            return;
        }

        $context = \context_system::instance();
        $exportdata = [];
        foreach ($drafts as $d) {
            $exportdata[] = [
                'id'           => $d->id,
                'title'        => $d->title,
                'sourcetext'   => $d->sourcetext,
                'status'       => $d->status,
                'num_requested' => $d->num_requested,
                'num_generated' => $d->num_generated,
                'tokens_in'    => $d->tokens_in,
                'tokens_out'   => $d->tokens_out,
                'timecreated'  => $d->timecreated,
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_sentientia_aiquiz')],
            (object)['drafts' => $exportdata]
        );
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_aiquiz_question');
        $DB->delete_records('local_sentientia_aiquiz_draft');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Delete questions belonging to the user's drafts, then the drafts.
        $draftids = $DB->get_fieldset_select('local_sentientia_aiquiz_draft',
            'id', 'ownerid = :uid', ['uid' => $userid]);
        if (!empty($draftids)) {
            [$insql, $params] = $DB->get_in_or_equal($draftids, SQL_PARAMS_NAMED, 'did');
            $DB->delete_records_select('local_sentientia_aiquiz_question', "draftid $insql", $params);
            $DB->delete_records('local_sentientia_aiquiz_draft', ['ownerid' => $userid]);
        }

        // Null out reviewer references — preserve the draft, redact the reviewer ID.
        $DB->set_field('local_sentientia_aiquiz_draft', 'reviewed_by', null,
            ['reviewed_by' => $userid]);
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

        $draftids = $DB->get_fieldset_select('local_sentientia_aiquiz_draft',
            'id', "ownerid $insql", $params);
        if (!empty($draftids)) {
            [$disql, $dparams] = $DB->get_in_or_equal($draftids, SQL_PARAMS_NAMED, 'did');
            $DB->delete_records_select('local_sentientia_aiquiz_question', "draftid $disql", $dparams);
            $DB->delete_records_select('local_sentientia_aiquiz_draft', "ownerid $insql", $params);
        }
        $DB->execute(
            "UPDATE {local_sentientia_aiquiz_draft} SET reviewed_by = NULL WHERE reviewed_by $insql",
            $params
        );
    }
}
