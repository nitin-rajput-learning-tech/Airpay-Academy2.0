<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Authoring Studio P0.3.
 *
 * Declares personal data:
 *   template  — owner, name, body, timestamps
 *   draft     — owner, source text, model, tokens, reviewer, timestamps
 *   card      — content created on behalf of owner (no direct PII)
 *   question  — content created on behalf of owner (no direct PII)
 *   voiceover — owner-attributed via parent draft (no audio PII in mock mode)
 *
 * Also declares the external Anthropic + ElevenLabs APIs as subsystems data
 * MAY be sent to (only when live_api is ON — OFF by default, so no data leaves
 * the server in the shipped configuration).
 *
 * @package local_sentientia_authoring
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_auth_template', [
            'ownerid'      => 'privacy:metadata:template:ownerid',
            'name'         => 'privacy:metadata:template:name',
            'body'         => 'privacy:metadata:template:body',
            'timecreated'  => 'privacy:metadata:template:timecreated',
            'timemodified' => 'privacy:metadata:template:timemodified',
        ], 'privacy:metadata:template');

        $collection->add_database_table('local_sentientia_auth_draft', [
            'ownerid'      => 'privacy:metadata:draft:ownerid',
            'title'        => 'privacy:metadata:draft:title',
            'sourcetext'   => 'privacy:metadata:draft:sourcetext',
            'model'        => 'privacy:metadata:draft:model',
            'tokens_in'    => 'privacy:metadata:draft:tokens',
            'tokens_out'   => 'privacy:metadata:draft:tokens',
            'reviewed_by'  => 'privacy:metadata:draft:reviewed_by',
            'reviewed_at'  => 'privacy:metadata:draft:reviewed_at',
            'timecreated'  => 'privacy:metadata:draft:timecreated',
            'timemodified' => 'privacy:metadata:draft:timemodified',
        ], 'privacy:metadata:draft');

        $collection->add_database_table('local_sentientia_auth_card', [
            'draftid'       => 'privacy:metadata:card:draftid',
            'body'          => 'privacy:metadata:card:body',
            'reviewer_note' => 'privacy:metadata:card:reviewer_note',
            'timecreated'   => 'privacy:metadata:card:timecreated',
            'timemodified'  => 'privacy:metadata:card:timemodified',
        ], 'privacy:metadata:card');

        $collection->add_database_table('local_sentientia_auth_question', [
            'draftid'       => 'privacy:metadata:question:draftid',
            'qtext'         => 'privacy:metadata:question:qtext',
            'qoptions_json' => 'privacy:metadata:question:qoptions',
            'reviewer_note' => 'privacy:metadata:question:reviewer_note',
            'timecreated'   => 'privacy:metadata:question:timecreated',
            'timemodified'  => 'privacy:metadata:question:timemodified',
        ], 'privacy:metadata:question');

        // External subsystems — only reached when live_api is ON (default OFF).
        $collection->add_external_location_link('anthropic_api', [
            'sourcetext' => 'privacy:metadata:anthropic:sourcetext',
            'model'      => 'privacy:metadata:anthropic:model',
        ], 'privacy:metadata:anthropic');

        $collection->add_external_location_link('elevenlabs_api', [
            'narration' => 'privacy:metadata:elevenlabs:narration',
        ], 'privacy:metadata:elevenlabs');

        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT ownerid AS userid FROM {local_sentientia_auth_draft}", []);
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT reviewed_by AS userid FROM {local_sentientia_auth_draft} WHERE reviewed_by IS NOT NULL", []);
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT ownerid AS userid FROM {local_sentientia_auth_template} WHERE ownerid > 0", []);
    }

    /**
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $drafts = $DB->get_records('local_sentientia_auth_draft', ['ownerid' => $userid]);
        if ($drafts) {
            $rows = [];
            foreach ($drafts as $d) {
                $rows[] = [
                    'id'            => $d->id,
                    'title'         => $d->title,
                    'sourcetext'    => $d->sourcetext,
                    'status'        => $d->status,
                    'num_cards'     => $d->num_cards,
                    'num_questions' => $d->num_questions,
                    'tokens_in'     => $d->tokens_in,
                    'tokens_out'    => $d->tokens_out,
                    'timecreated'   => $d->timecreated,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_authoring'),
                 get_string('privacy:path:drafts', 'local_sentientia_authoring')],
                (object) ['drafts' => $rows]);
        }

        $templates = $DB->get_records('local_sentientia_auth_template', ['ownerid' => $userid]);
        if ($templates) {
            $rows = [];
            foreach ($templates as $t) {
                $rows[] = [
                    'id'          => $t->id,
                    'name'        => $t->name,
                    'body'        => $t->body,
                    'timecreated' => $t->timecreated,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_authoring'),
                 get_string('privacy:path:templates', 'local_sentientia_authoring')],
                (object) ['templates' => $rows]);
        }
    }

    /**
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_auth_voiceover');
        $DB->delete_records('local_sentientia_auth_question');
        $DB->delete_records('local_sentientia_auth_card');
        $DB->delete_records('local_sentientia_auth_draft');
        $DB->delete_records('local_sentientia_auth_template');
    }

    /**
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        $draftids = $DB->get_fieldset_select('local_sentientia_auth_draft',
            'id', 'ownerid = :uid', ['uid' => $userid]);
        self::purge_drafts($draftids);
        $DB->delete_records('local_sentientia_auth_draft', ['ownerid' => $userid]);

        // Redact reviewer references while preserving non-owned drafts.
        $DB->set_field('local_sentientia_auth_draft', 'reviewed_by', null, ['reviewed_by' => $userid]);

        // Owned templates (skip built-ins, which carry ownerid 0).
        $DB->delete_records_select('local_sentientia_auth_template',
            'ownerid = :uid AND is_builtin = 0', ['uid' => $userid]);
    }

    /**
     * @param approved_userlist $userlist
     * @return void
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

        $draftids = $DB->get_fieldset_select('local_sentientia_auth_draft',
            'id', "ownerid $insql", $params);
        self::purge_drafts($draftids);
        $DB->delete_records_select('local_sentientia_auth_draft', "ownerid $insql", $params);

        $DB->execute("UPDATE {local_sentientia_auth_draft} SET reviewed_by = NULL WHERE reviewed_by $insql", $params);
        $DB->delete_records_select('local_sentientia_auth_template',
            "ownerid $insql AND is_builtin = 0", $params);
    }

    /**
     * Delete cards/questions/voiceovers for a set of draft ids.
     *
     * @param int[] $draftids
     * @return void
     */
    private static function purge_drafts(array $draftids): void {
        global $DB;
        if (empty($draftids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($draftids, SQL_PARAMS_NAMED, 'did');
        $DB->delete_records_select('local_sentientia_auth_voiceover', "draftid $insql", $params);
        $DB->delete_records_select('local_sentientia_auth_question', "draftid $insql", $params);
        $DB->delete_records_select('local_sentientia_auth_card', "draftid $insql", $params);
    }
}
