<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Session manager — orchestrates the proctored attempt lifecycle.
 *
 * State machine:
 *   new → consenting → verifying → recording → finished
 *                                            → flagged → reviewed
 *
 * One session per (user, quiz) combination at a time. A new attempt
 * starts a new session.
 *
 * @package local_airpay_proctoring
 */
class session_manager {

    /** Open a session — called when user clicks Start on a proctored quiz. */
    public static function start_session(int $userid, int $quizid): \stdClass {
        global $DB, $USER;

        $user = $userid === $USER->id ? $USER
            : $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Tenant snapshot.
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        $costcenterid = isset($parts[0]) && ctype_digit($parts[0])
            ? (int) $parts[0] : 0;

        $now = time();
        $session = (object) [
            'userid'        => $userid,
            'quizid'        => $quizid,
            'costcenterid'  => $costcenterid,
            'status'        => 'new',
            'timecreated'   => $now,
            'timemodified'  => $now,
        ];
        $session->id = $DB->insert_record('local_airpay_proctor_sessions', $session);

        // B3 fix: skip_ownership=true — we're inside start_session, the
        // session row was JUST created by this user, the ownership
        // invariant holds by construction.
        self::record_event($session->id, 'session_start', 'info', [], true);
        return $session;
    }

    /**
     * Verify the current $USER owns $sessionid, otherwise throw.
     * Returns the loaded session row (so callers can re-use it).
     *
     * Phase 8.1 B3 fix helper.
     */
    private static function assert_session_owner(int $sessionid): \stdClass {
        global $DB, $USER;
        $session = $DB->get_record('local_airpay_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);
        if ((int) $session->userid !== (int) $USER->id) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
        }
        return $session;
    }

    /** Candidate clicks "I consent". */
    public static function record_consent(int $sessionid, int $userid): \stdClass {
        global $DB;
        $session = $DB->get_record('local_airpay_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);
        if ((int) $session->userid !== $userid) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
        }
        $session->status           = 'consenting';
        $session->consent_given_at = time();
        $session->timemodified     = time();
        $DB->update_record('local_airpay_proctor_sessions', $session);
        return $session;
    }

    /**
     * Submit identity photos. Returns the identity row.
     * The photo bytes are NEVER stored — only the score.
     */
    public static function submit_identity(int $sessionid, int $userid,
                                            string $id_photo_bytes,
                                            string $selfie_bytes): \stdClass {
        global $DB;
        $session = $DB->get_record('local_airpay_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);
        if ((int) $session->userid !== $userid) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
        }

        $session->status = 'verifying';
        $session->timemodified = time();
        $DB->update_record('local_airpay_proctor_sessions', $session);

        // Verify (photos are passed by value, not retained).
        $verifier = \local_airpay_proctoring\identity\verifier_factory::get_current();
        $result = $verifier->verify($id_photo_bytes, $selfie_bytes);

        // Persist the result row (NO photo bytes).
        $id_row = (object) [
            'userid'      => $userid,
            'sessionid'   => $sessionid,
            'provider'    => $verifier->get_name(),
            'match_score' => (float) ($result['score'] ?? 0),
            'passed'      => $result['passed'] ? 1 : 0,
            'error_code'  => $result['error_code'] ?? null,
            'error_msg'   => $result['error_msg']  ?? null,
            'timecreated' => time(),
        ];
        $id_row->id = $DB->insert_record('local_airpay_proctor_identity', $id_row);

        // Link back to session.
        $session->identity_id = $id_row->id;
        $session->status = $result['passed'] ? 'recording' : 'new';
        $session->timemodified = time();
        if ($result['passed']) {
            $session->timestarted = time();
        }
        $DB->update_record('local_airpay_proctor_sessions', $session);

        // Event log. submit_identity already validated user ownership
        // of the session at line 75 — skip the second check.
        self::record_event($sessionid,
            $result['passed'] ? 'identity_passed' : 'identity_failed',
            $result['passed'] ? 'info' : 'critical',
            ['score' => $result['score']], true);

        if (!$result['passed']) {
            notifier::identity_failed($session, $result);
        }

        // Explicitly free photo memory.
        unset($id_photo_bytes, $selfie_bytes);

        return $id_row;
    }

    /**
     * Append an event to the per-session log.
     *
     * Phase 8.1 B3 fix: every caller must own the session (or be the
     * cron/admin code path). Without this, an attacker can inject
     * false events into another candidate's session to trip the
     * AI-flagging analyzer.
     *
     * Pass `$skip_ownership = true` ONLY from internal callers (e.g.
     * `submit_identity` already validated the owner; the AI analyzer
     * during `finalize()` runs as the candidate's own session).
     */
    public static function record_event(int $sessionid, string $type,
                                         string $severity = 'info',
                                         array $payload = [],
                                         bool $skip_ownership = false): int {
        global $DB, $USER;
        if (!$skip_ownership) {
            self::assert_session_owner($sessionid);
        }
        return $DB->insert_record('local_airpay_proctor_events', (object) [
            'sessionid'    => $sessionid,
            'event_type'   => $type,
            'severity'     => $severity,
            'payload_json' => $payload ? json_encode($payload) : null,
            'timecreated'  => time(),
        ]);
    }

    /**
     * Register a recording chunk that was uploaded directly to S3 by the
     * client. We don't transit the bytes through our server — saves
     * bandwidth and storage.
     *
     * Phase 8.1 B3 fix: caller must own the session AND the session
     * must be in `recording` or `verifying` status. Without this,
     * an attacker could pollute another candidate's recording log
     * with bogus chunk references, and `s3_key` (which is a string
     * we trust later when serving recordings) could point anywhere.
     */
    public static function register_chunk(int $sessionid, string $kind,
                                           int $chunk_idx, string $s3_key,
                                           int $size_bytes, int $duration_ms): int {
        global $DB;
        $session = self::assert_session_owner($sessionid);
        if (!in_array($session->status, ['recording', 'verifying'], true)) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
        }
        // s3_key whitelist — opaque key MUST match a safe-path regex.
        // PARAM_TEXT (the external_value type) is too loose: it lets
        // attackers register a `s3_key` like `../../etc/admin.json` and
        // when a reviewer later opens the chunk, our proxy would resolve
        // outside the intended bucket prefix.
        if (!preg_match('#^[a-zA-Z0-9/_.-]{1,512}$#', $s3_key)) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring',
                '', 'Invalid s3 key');
        }
        // Bound sizes — defense against a client uploading absurd values
        // that bloat the recording table and break analytics.
        if ($size_bytes < 0 || $size_bytes > 64 * 1024 * 1024) {  // 64 MB / chunk max
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring',
                '', 'Invalid size');
        }
        if ($duration_ms < 0 || $duration_ms > 120000) {  // 2 min / chunk max
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring',
                '', 'Invalid duration');
        }

        $retention_days = (int) (get_config('local_airpay_proctoring', 'retention_days') ?: 90);
        return $DB->insert_record('local_airpay_proctor_recordings', (object) [
            'sessionid'    => $sessionid,
            'chunk_idx'    => $chunk_idx,
            'kind'         => $kind,
            's3_key'       => $s3_key,
            'size_bytes'   => $size_bytes,
            'duration_ms'  => $duration_ms,
            'retain_until' => time() + ($retention_days * 86400),
            'timecreated'  => time(),
        ]);
    }

    /**
     * Finalize a session — invoked when the candidate ends the attempt
     * (or session naturally times out). Runs AI analysis, sets the
     * auto_decision, flags for review if score warrants.
     *
     * Phase 8.1 B3 fix: caller must own the session. Without this, an
     * attacker could prematurely finalize another candidate's session
     * mid-attempt, forcing the AI verdict + flagged status before they
     * can complete the quiz.
     */
    public static function finalize(int $sessionid): \stdClass {
        global $DB;
        $session = self::assert_session_owner($sessionid);

        if ($session->status === 'reviewed' || $session->status === 'flagged') {
            return $session;  // idempotent
        }

        $session->timefinished = time();
        $session->timemodified = time();

        // finalize() already validated ownership; pass skip_ownership=true.
        self::record_event($sessionid, 'session_end', 'info', [], true);

        // Run analyzer.
        $result = \local_airpay_proctoring\analyzer\risk_analyzer::analyze($sessionid);
        $session->risk_score    = $result['risk_score'];
        $session->auto_decision = $result['decision'];

        if ($result['decision'] === 'clean') {
            $session->status = 'finished';
        } else {
            $session->status = 'flagged';
            // Notify default reviewer.
            $reviewerid = (int) (get_config('local_airpay_proctoring', 'default_reviewer') ?: 2);
            notifier::session_flagged($session, $reviewerid);
        }

        $DB->update_record('local_airpay_proctor_sessions', $session);
        return $session;
    }

    /** Reviewer submits a decision on a flagged session. */
    public static function submit_review(int $sessionid, int $reviewerid,
                                          string $decision, string $note): \stdClass {
        global $DB;
        if (!in_array($decision, ['clean', 'warn', 'fail'], true)) {
            throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
        }
        $session = $DB->get_record('local_airpay_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);

        $review = (object) [
            'sessionid'       => $sessionid,
            'reviewer_userid' => $reviewerid,
            'decision'        => $decision,
            'note'            => $note,
            'timecreated'     => time(),
        ];
        $DB->insert_record('local_airpay_proctor_reviews', $review);

        $session->human_decision = $decision;
        $session->status         = 'reviewed';
        $session->timemodified   = time();
        $DB->update_record('local_airpay_proctor_sessions', $session);

        notifier::session_reviewed($session);
        return $session;
    }
}
