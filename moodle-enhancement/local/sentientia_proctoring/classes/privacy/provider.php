<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        // 2026-11-13: every table names its personal-data fields. With an empty
        // field list core calls debugging() under DEBUG_DEVELOPER, which failed
        // local_sentientia_platform\privacy_coverage_test and made core's
        // test_table_coverage risky for every component.
        $collection->add_database_table('local_sentientia_proctor_sessions', [
            'userid'           => 'privacy:metadata:userid',
            'quizid'           => 'privacy:metadata:local_sentientia_proctor_sessions:quizid',
            'attemptid'        => 'privacy:metadata:local_sentientia_proctor_sessions:attemptid',
            'status'           => 'privacy:metadata:local_sentientia_proctor_sessions:status',
            'consent_given_at' => 'privacy:metadata:local_sentientia_proctor_sessions:consent_given_at',
            'risk_score'       => 'privacy:metadata:local_sentientia_proctor_sessions:risk_score',
            'auto_decision'    => 'privacy:metadata:local_sentientia_proctor_sessions:auto_decision',
            'human_decision'   => 'privacy:metadata:local_sentientia_proctor_sessions:human_decision',
            'timestarted'      => 'privacy:metadata:local_sentientia_proctor_sessions:timestarted',
            'timefinished'     => 'privacy:metadata:local_sentientia_proctor_sessions:timefinished',
            'timecreated'      => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_sentientia_proctor_sessions');
        $collection->add_database_table('local_sentientia_proctor_identity', [
            'userid'      => 'privacy:metadata:userid',
            'sessionid'   => 'privacy:metadata:sessionid',
            'provider'    => 'privacy:metadata:local_sentientia_proctor_identity:provider',
            'match_score' => 'privacy:metadata:local_sentientia_proctor_identity:match_score',
            'passed'      => 'privacy:metadata:local_sentientia_proctor_identity:passed',
            'error_code'  => 'privacy:metadata:local_sentientia_proctor_identity:error_code',
            'error_msg'   => 'privacy:metadata:local_sentientia_proctor_identity:error_msg',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_sentientia_proctor_identity');
        $collection->add_database_table('local_sentientia_proctor_events', [
            'sessionid'    => 'privacy:metadata:sessionid',
            'event_type'   => 'privacy:metadata:local_sentientia_proctor_events:event_type',
            'severity'     => 'privacy:metadata:local_sentientia_proctor_events:severity',
            'payload_json' => 'privacy:metadata:local_sentientia_proctor_events:payload_json',
            'timecreated'  => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_sentientia_proctor_events');
        $collection->add_database_table('local_sentientia_proctor_recordings', [
            'sessionid'    => 'privacy:metadata:sessionid',
            'kind'         => 'privacy:metadata:local_sentientia_proctor_recordings:kind',
            's3_key'       => 'privacy:metadata:local_sentientia_proctor_recordings:s3_key',
            'size_bytes'   => 'privacy:metadata:local_sentientia_proctor_recordings:size_bytes',
            'duration_ms'  => 'privacy:metadata:local_sentientia_proctor_recordings:duration_ms',
            'retain_until' => 'privacy:metadata:local_sentientia_proctor_recordings:retain_until',
            'timecreated'  => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_sentientia_proctor_recordings');
        $collection->add_database_table('local_sentientia_proctor_reviews', [
            'sessionid'       => 'privacy:metadata:sessionid',
            'reviewer_userid' => 'privacy:metadata:local_sentientia_proctor_reviews:reviewer_userid',
            'decision'        => 'privacy:metadata:local_sentientia_proctor_reviews:decision',
            'note'            => 'privacy:metadata:local_sentientia_proctor_reviews:note',
            'timecreated'     => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_sentientia_proctor_reviews');
        $collection->add_external_location_link('aws_rekognition',
            ['photo' => 'privacy:metadata:aws_rekognition:photo'],
            'privacy:metadata:aws_rekognition');
        $collection->add_external_location_link('aws_s3',
            ['video' => 'privacy:metadata:aws_s3:video'],
            'privacy:metadata:aws_s3');
        // Decision A5 (2026-11-12): the session_flagged and review_submitted events reach the logstore plugin type
        // (logstore_standard and any other enabled store), which exports and deletes its own rows; the plugin only
        // names the link. logstore is a plugin type (a tool_log subplugin), not a core subsystem.
        $collection->add_plugintype_link('logstore', [], 'privacy:metadata:logstore');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        global $DB;
        // A reviewer holds data here too: reviews.reviewer_userid (2026-09-24).
        if ($DB->record_exists('local_sentientia_proctor_sessions', ['userid' => $userid])
                || $DB->record_exists('local_sentientia_proctor_reviews', ['reviewer_userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) return;
        global $DB;
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {local_sentientia_proctor_sessions} WHERE userid > 0");
        $ids = array_merge($ids, $DB->get_fieldset_sql(
            "SELECT DISTINCT reviewer_userid FROM {local_sentientia_proctor_reviews} WHERE reviewer_userid > 0"));
        $userlist->add_users(array_values(array_unique($ids)));
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $sessions = $DB->get_records('local_sentientia_proctor_sessions',
            ['userid' => $userid], 'timecreated DESC');
        $out = [];
        foreach ($sessions as $s) {
            $events = $DB->get_records('local_sentientia_proctor_events',
                ['sessionid' => $s->id], 'timecreated');
            $out[] = [
                'session_id'     => $s->id,
                'quiz_id'        => $s->quizid,
                'status'         => $s->status,
                'risk_score'     => $s->risk_score,
                'auto_decision'  => $s->auto_decision,
                'human_decision' => $s->human_decision,
                'started_at'     => $s->timestarted ? userdate($s->timestarted) : null,
                'finished_at'    => $s->timefinished ? userdate($s->timefinished) : null,
                'event_count'    => count($events),
            ];
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_sentientia_proctoring')],
            (object) ['proctored_sessions' => $out]);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) return;
        self::delete_all();
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::delete_for_user($contextlist->get_user()->id);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $u) {
            self::delete_for_user((int) $u);
        }
    }

    /**
     * Delete proctoring data for one user. Sessions + events + recordings
     * + identity rows all go. The audit log is preserved by recording
     * "DSR deletion event" in a separate Moodle log table (handled by
     * core privacy framework).
     */
    private static function delete_for_user(int $userid): void {
        global $DB;
        self::anonymise_reviewer($userid);
        $session_ids = $DB->get_fieldset_select('local_sentientia_proctor_sessions',
            'id', 'userid = :u', ['u' => $userid]);
        if (empty($session_ids)) return;
        [$insql, $inparams] = $DB->get_in_or_equal($session_ids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_sentientia_proctor_events',     "sessionid $insql", $inparams);
        self::expire_recordings($insql, $inparams);
        $DB->delete_records_select('local_sentientia_proctor_identity',   "sessionid $insql", $inparams);
        $DB->delete_records_select('local_sentientia_proctor_reviews',    "sessionid $insql", $inparams);
        $DB->delete_records_select('local_sentientia_proctor_sessions',   "id $insql",        $inparams);
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        self::anonymise_reviewer($userid);
        $session_ids = $DB->get_fieldset_select('local_sentientia_proctor_sessions',
            'id', 'userid = :u', ['u' => $userid]);
        if (empty($session_ids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($session_ids, SQL_PARAMS_NAMED);
        // Biometric and behavioural data goes: face-match identity, the event
        // stream, and the webcam/screen/audio recordings.
        $DB->delete_records_select('local_sentientia_proctor_events',   "sessionid $insql", $inparams);
        $DB->delete_records_select('local_sentientia_proctor_identity', "sessionid $insql", $inparams);
        self::expire_recordings($insql, $inparams);
        // The session and its review stay: they are the exam-integrity verdict
        // on a quiz attempt this flow keeps. Only the identity link is cut; the
        // consent timestamp stays as the lawful-basis record for that verdict.
        $DB->execute("UPDATE {local_sentientia_proctor_sessions}
                         SET identity_id = NULL
                       WHERE id $insql", $inparams);
    }

    /**
     * A reviewer acted on SOMEONE ELSE's attempt: keep the review (it is the
     * candidate's record), drop the reviewer's id. reviewer_userid is NOT NULL,
     * hence 0.
     */
    private static function anonymise_reviewer(int $userid): void {
        global $DB;
        if ($userid > 0) {
            $DB->set_field('local_sentientia_proctor_reviews', 'reviewer_userid', 0,
                ['reviewer_userid' => $userid]);
        }
    }

    /**
     * Hand the recordings to the purge task instead of deleting their rows.
     *
     * A recordings row is the only pointer to its chunk in S3, and
     * purge_old_recordings is the only code that deletes S3 objects - it
     * selects rows by retain_until/deleted_at. Deleting the rows (as this
     * provider did until 2026-09-24) left the video in S3 for ever with
     * nothing pointing at it. Expiring them makes the next daily purge
     * delete the objects and stamp deleted_at.
     */
    private static function expire_recordings(string $insql, array $inparams): void {
        global $DB;
        $DB->execute("UPDATE {local_sentientia_proctor_recordings}
                         SET retain_until = :expired
                       WHERE sessionid $insql AND deleted_at IS NULL",
            ['expired' => time() - 1] + $inparams);
    }

    private static function delete_all(): void {
        global $DB;
        $DB->delete_records('local_sentientia_proctor_events');
        $DB->delete_records('local_sentientia_proctor_recordings');
        $DB->delete_records('local_sentientia_proctor_identity');
        $DB->delete_records('local_sentientia_proctor_reviews');
        $DB->delete_records('local_sentientia_proctor_sessions');
    }
}
