<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Phase E.0.
 *
 * Declares all stored personal data:
 *   sessions     — trainer's ownership of sessions (ownerid)
 *   slides       — no personal data (trainer-authored content)
 *   participants — audience member identity (userid + display_name)
 *   responses    — audience member responses (via participant linkage)
 *   events       — no direct personal data (sessionid only)
 *
 * Implements both core_userlist_provider and core_user_data_provider so
 * the DPDP / GDPR self-service export + delete flows work correctly.
 *
 * Phase E.0 ships the metadata + export + delete stubs. Full flows
 * (with realistic data formats + cascade rules) finish in Phase E.10
 * once the data shape is locked in.
 *
 * @package local_sentientia_live
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Declare metadata for every table this plugin uses.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_live_sessions',
            [
                'ownerid'       => 'privacy:metadata:sessions:ownerid',
                'code'          => 'privacy:metadata:sessions:code',
                'tenantid'      => 'privacy:metadata:sessions:tenantid',
                'customerid'    => 'privacy:metadata:sessions:customerid',
                'timecreated'   => 'privacy:metadata:sessions:timecreated',
                'timestarted'   => 'privacy:metadata:sessions:timestarted',
                'timeended'     => 'privacy:metadata:sessions:timeended',
            ],
            'privacy:metadata:sessions'
        );

        $collection->add_database_table(
            'local_sentientia_live_slides',
            [
                'title' => 'privacy:metadata:slides:title',
                'type'  => 'privacy:metadata:slides:type',
            ],
            'privacy:metadata:slides'
        );

        $collection->add_database_table(
            'local_sentientia_live_participants',
            [
                'userid'        => 'privacy:metadata:participants:userid',
                'display_name'  => 'privacy:metadata:participants:display_name',
                'timejoined'    => 'privacy:metadata:participants:timejoined',
                'timelastseen'  => 'privacy:metadata:participants:timelastseen',
            ],
            'privacy:metadata:participants'
        );

        $collection->add_database_table(
            'local_sentientia_live_responses',
            [
                'value_text'  => 'privacy:metadata:responses:value_text',
                'value_int'   => 'privacy:metadata:responses:value_int',
                'timecreated' => 'privacy:metadata:responses:timecreated',
            ],
            'privacy:metadata:responses'
        );

        $collection->add_database_table(
            'local_sentientia_live_events',
            [
                'payload_json' => 'privacy:metadata:events:payload',
                'timecreated'  => 'privacy:metadata:events:timecreated',
            ],
            'privacy:metadata:events'
        );

        return $collection;
    }

    /**
     * The list of contexts that contain personal data for the given user.
     * Phase E.0 stores everything at system context.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        global $DB;

        $has_data = $DB->record_exists_select(
            'local_sentientia_live_participants',
            'userid = :userid', ['userid' => $userid]
        ) || $DB->record_exists_select(
            'local_sentientia_live_sessions',
            'ownerid = :ownerid', ['ownerid' => $userid]
        );

        if ($has_data) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Return the user IDs that have personal data in this context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Trainers who own sessions.
        $owners = $DB->get_fieldset_select(
            'local_sentientia_live_sessions', 'ownerid', '1=1');
        // Audience members who joined.
        $audience = $DB->get_fieldset_select(
            'local_sentientia_live_participants', 'userid',
            'userid IS NOT NULL');

        $all_ids = array_unique(array_filter(array_merge($owners, $audience)));
        if (!empty($all_ids)) {
            $userlist->add_users($all_ids);
        }
    }

    /**
     * Export all of a user's data — Phase E.0 stub. Phase E.10 lands
     * the full structured export with per-session breakdowns.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // For each session the user owns OR participated in, write a
        // single JSON file capturing everything.
        $context = \context_system::instance();
        $sessions = $DB->get_records('local_sentientia_live_sessions',
            ['ownerid' => $userid]);
        foreach ($sessions as $sess) {
            writer::with_context($context)->export_data(
                ['Sentientia Live', 'Sessions you owned', $sess->id],
                (object) [
                    'title'       => $sess->title,
                    'code'        => $sess->code,
                    'state'       => $sess->state,
                    'timecreated' => $sess->timecreated,
                    'timestarted' => $sess->timestarted,
                    'timeended'   => $sess->timeended,
                ]
            );
        }

        $participations = $DB->get_records('local_sentientia_live_participants',
            ['userid' => $userid]);
        foreach ($participations as $p) {
            writer::with_context($context)->export_data(
                ['Sentientia Live', 'Sessions you joined', $p->sessionid],
                (object) [
                    'display_name'  => $p->display_name,
                    'timejoined'    => $p->timejoined,
                    'timelastseen'  => $p->timelastseen,
                ]
            );

            // Each response on that session.
            $responses = $DB->get_records('local_sentientia_live_responses',
                ['participantid' => $p->id]);
            foreach ($responses as $r) {
                writer::with_context($context)->export_data(
                    ['Sentientia Live', 'Your responses', $p->sessionid . '-' . $r->slideid],
                    (object) [
                        'value_int'   => $r->value_int,
                        'value_text'  => $r->value_text,
                        'timecreated' => $r->timecreated,
                    ]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the given context — used when
     * the entire context is being purged (e.g. site shutdown).
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        // Cascade delete — respect FK order.
        $DB->delete_records('local_sentientia_live_events');
        $DB->delete_records('local_sentientia_live_responses');
        $DB->delete_records('local_sentientia_live_participants');
        $DB->delete_records('local_sentientia_live_slides');
        $DB->delete_records('local_sentientia_live_sessions');
    }

    /**
     * Delete just one user's data (DPDP / GDPR right-to-erasure).
     *
     * Strategy:
     *   - sessions you OWNED: anonymise (ownerid → 0) but keep the row
     *     because other participants' responses depend on it. The
     *     code+title etc are not personal data on their own.
     *   - participations: delete the participant row AND all attached
     *     responses (responses are linked by participantid, so cascade).
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Anonymise owned sessions.
        $DB->set_field('local_sentientia_live_sessions',
            'ownerid', 0, ['ownerid' => $userid]);

        // Find this user's participation rows + delete their responses + rows.
        $part_ids = $DB->get_fieldset_select(
            'local_sentientia_live_participants', 'id',
            'userid = :userid', ['userid' => $userid]);
        if (!empty($part_ids)) {
            [$insql, $params] = $DB->get_in_or_equal($part_ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_sentientia_live_responses',
                "participantid $insql", $params);
            $DB->delete_records_select('local_sentientia_live_participants',
                "id $insql", $params);
        }
    }

    /**
     * Bulk delete for a list of users in a context — admin-driven purge.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Anonymise sessions owned by any of them.
        $DB->execute(
            "UPDATE {local_sentientia_live_sessions}
                SET ownerid = 0
              WHERE ownerid $insql", $params);

        // Find + delete participants + their responses.
        $part_ids = $DB->get_fieldset_select(
            'local_sentientia_live_participants', 'id',
            "userid $insql", $params);
        if (!empty($part_ids)) {
            [$pinsql, $pparams] = $DB->get_in_or_equal($part_ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_sentientia_live_responses',
                "participantid $pinsql", $pparams);
            $DB->delete_records_select('local_sentientia_live_participants',
                "id $pinsql", $pparams);
        }
    }
}
