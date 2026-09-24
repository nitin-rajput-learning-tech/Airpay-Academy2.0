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
        $collection->add_database_table('local_sentientia_proctor_sessions', [], 'privacy:metadata:local_sentientia_proctor_sessions');
        $collection->add_database_table('local_sentientia_proctor_identity', [], 'privacy:metadata:local_sentientia_proctor_identity');
        $collection->add_database_table('local_sentientia_proctor_events',   [], 'privacy:metadata:local_sentientia_proctor_events');
        $collection->add_database_table('local_sentientia_proctor_recordings', [], 'privacy:metadata:local_sentientia_proctor_recordings');
        $collection->add_database_table('local_sentientia_proctor_reviews',  [], 'privacy:metadata:local_sentientia_proctor_reviews');
        $collection->add_external_location_link('aws_rekognition',
            ['photo' => 'privacy:metadata:aws_rekognition:photo'],
            'privacy:metadata:aws_rekognition');
        $collection->add_external_location_link('aws_s3',
            ['video' => 'privacy:metadata:aws_s3:video'],
            'privacy:metadata:aws_s3');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        global $DB;
        if ($DB->record_exists('local_sentientia_proctor_sessions', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) return;
        global $DB;
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {local_sentientia_proctor_sessions}");
        $userlist->add_users($ids);
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
        // on a quiz attempt this flow keeps. Only the identity link is cut.
        $DB->execute("UPDATE {local_sentientia_proctor_sessions}
                         SET identity_id = NULL, consent_given_at = NULL
                       WHERE id $insql", $inparams);
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
