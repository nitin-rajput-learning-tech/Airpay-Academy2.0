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
 * Erasure anonymises, it does not delete (2026-09-24). Drafts and templates
 * are the tenant's work, not the author's personal data: a draft is shared
 * with the author's whole tenant (draft_manager::list_for_actor() matches on
 * costcenterid), carries other reviewers' card/question notes and the
 * published_courseid provenance of a live course; a template is a shared
 * tenant asset other authors generate from. Erasing a person therefore sets
 * ownerid to 0 and reviewed_by to NULL and keeps the rows. ownerid 0 is
 * "nobody" - it never makes anyone an owner (see the guards in draft_manager
 * and template_manager).
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
     * The system context, but only for someone this plugin holds data about:
     * the author of a draft or template, or the reviewer of a draft.
     *
     * Until 2026-09-24 this reported the system context for every user, so
     * every DPDP erasure (local_sentientia_privacy Step 0) ran this plugin's
     * erasure for people who had never opened the studio.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($userid <= 0) {
            // 0 is the anonymised author and the owner of the built-in
            // templates - never a person.
            return $contextlist;
        }
        $hasdata = $DB->record_exists_select('local_sentientia_auth_draft',
                'ownerid = :owner OR reviewed_by = :reviewer',
                ['owner' => $userid, 'reviewer' => $userid])
            || $DB->record_exists('local_sentientia_auth_template', ['ownerid' => $userid]);
        if ($hasdata) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Everyone who authored a draft or template, or reviewed a draft.
     * Anonymised rows (ownerid 0, reviewed_by NULL) name nobody.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT ownerid AS userid FROM {local_sentientia_auth_draft} WHERE ownerid > 0", []);
        // reviewed_by > 0 also excludes NULL (not yet reviewed, or anonymised).
        $userlist->add_from_sql('userid',
            "SELECT DISTINCT reviewed_by AS userid FROM {local_sentientia_auth_draft} WHERE reviewed_by > 0", []);
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
     * Erase one person: anonymise their authorship, keep the tenant's work.
     *
     * Until 2026-09-24 this deleted every draft the person owned (with its
     * cards, questions and voiceovers) and every template they wrote. Those
     * rows are shared with the whole tenant, hold other reviewers' notes and
     * record which live course a draft became, so every erasure destroyed
     * other people's work. Now:
     *   - draft.ownerid     -> 0     (ownerid is NOT NULL; 0 = nobody)
     *   - draft.reviewed_by -> NULL  (the reviewer, on anyone's draft)
     *   - template.ownerid  -> 0     (built-ins already carry 0; a built-in
     *                                 the person somehow owns is anonymised
     *                                 too, never deleted)
     * Cards, questions and voiceovers name no user and are kept with their
     * draft.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        if ($userid <= 0
                || !in_array((int) \context_system::instance()->id,
                    array_map('intval', $contextlist->get_contextids()), true)) {
            return;
        }

        $DB->set_field('local_sentientia_auth_draft', 'ownerid', 0, ['ownerid' => $userid]);
        $DB->set_field('local_sentientia_auth_draft', 'reviewed_by', null, ['reviewed_by' => $userid]);
        $DB->set_field('local_sentientia_auth_template', 'ownerid', 0, ['ownerid' => $userid]);
    }

    /**
     * Erase several people in the system context. Same anonymisation as
     * delete_data_for_user().
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!($userlist->get_context() instanceof \context_system)) {
            return;
        }
        $userids = array_values(array_filter(array_map('intval', $userlist->get_userids()),
            static function (int $id): bool {
                return $id > 0;
            }));
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        $DB->set_field_select('local_sentientia_auth_draft', 'ownerid', 0, "ownerid $insql", $params);
        $DB->set_field_select('local_sentientia_auth_draft', 'reviewed_by', null, "reviewed_by $insql", $params);
        $DB->set_field_select('local_sentientia_auth_template', 'ownerid', 0, "ownerid $insql", $params);
    }
}
