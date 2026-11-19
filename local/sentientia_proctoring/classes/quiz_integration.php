<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * The link between a quiz attempt and its proctoring session (B1, ADR-038 D2, 2026-11-20).
 *
 * Until B1 the learner opened a session from the browser (consent.js, loaded only on the attempt page) and the quiz
 * access rule refused every attempt that had no session in status `recording`. The learner never reached that page,
 * so a proctored quiz locked every learner out. Now:
 *
 *  1. Consent and identity are fields of the quiz's own pre-start check (the access rule's pre-flight form).
 *     pass_preflight() stores them on a session. For a new attempt there is no attempt id yet, so the session waits
 *     "pending" (attemptid NULL, status `recording`, consent stamped).
 *  2. When the attempt starts, session_for_attempt() claims the pending session for it: from the attempt_started
 *     observer, and again from the access rule on the attempt page if that observer did not run. A pending session
 *     older than PENDING_TTL is never claimed; the learner then sees the pre-start check again.
 *  3. The attempt pages load the monitor with the session the server found for the attempt. The browser never chooses
 *     the session.
 *  4. When the attempt is submitted or abandoned, the observer closes the session on the server (finalize_attempt()).
 *     The browser cannot close a session whose attempt is still open (session_manager::finalize()).
 *
 * The flag is read here, in one place, for the current user (the attempting learner on every quiz page).
 *
 * B2 "Secure assessment mode" (2026-11-20) uses the same life cycle for every secure-mode attempt, at all three levels:
 * the pre-start check stores the accepted policy on the session (pass_preflight() with a $policy), the attempt claims
 * it, and the observers close it. The B2 rules themselves live in secure_mode.
 *
 * @package local_sentientia_proctoring
 */
class quiz_integration {

    /** The B1 flag (ADR-038 D9), registered in db/feature_flags.php, default OFF. */
    public const FLAG = 'sentientia.proctoring.enabled';

    /**
     * The B4 webcam flag (ADR-038 D5), registered in db/feature_flags.php, default OFF. B1 review (2026-11-20): read
     * only by the upload_chunk web service, which refuses every chunk while it is OFF.
     */
    public const RECORDING_FLAG = 'sentientia.proctoring.recording.enabled';

    /** Seconds a consented session may wait for its attempt to start before it no longer counts. */
    public const PENDING_TTL = 3600;

    /**
     * Version of the pre-start notice the learner agreed to, kept in the consent event's payload. Change it whenever
     * the notice text (preflight_notice in quizaccess_sentientia_proctoring) changes meaning.
     */
    public const NOTICE_VERSION = '2026-11-20.b1';

    /** The status of a session whose attempt is being monitored. Kept from the original state machine. */
    public const ACTIVE = 'recording';

    /** Statuses a session can have before the server closes it. */
    private const OPEN_STATES = ['new', 'consenting', 'verifying', 'recording'];

    /** Quiz attempt states in which the attempt pages are open (mod_quiz\quiz_attempt::IN_PROGRESS, OVERDUE). */
    private const OPEN_ATTEMPT_STATES = ['inprogress', 'overdue'];

    /**
     * Is proctoring switched on for the current user's customer and tenant?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * The quiz attempt, if it is open (in progress or overdue) and not a preview.
     *
     * @param int $attemptid quiz_attempts.id
     * @return \stdClass|null id, quiz, userid, state, preview
     */
    public static function open_attempt(int $attemptid): ?\stdClass {
        global $DB;
        if ($attemptid <= 0) {
            return null;
        }
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, quiz, userid, state, preview');
        if (!$attempt || !empty($attempt->preview) || !in_array($attempt->state, self::OPEN_ATTEMPT_STATES, true)) {
            return null;
        }
        return $attempt;
    }

    /**
     * The learner's open (in progress or overdue) attempt at the quiz, not a preview; null when there is none.
     *
     * B2 review (2026-11-20): the access rule uses it so that an attempt keeps the policy its learner accepted when a
     * flag is switched off mid-attempt, and so that a fail-closed quiz blocks only new attempts.
     *
     * @param int $userid
     * @param int $quizid
     * @return \stdClass|null id, quiz, userid, state, preview
     */
    public static function open_attempt_of(int $userid, int $quizid): ?\stdClass {
        global $DB;
        if ($userid <= 0 || $quizid <= 0) {
            return null;
        }
        [$insql, $params] = $DB->get_in_or_equal(self::OPEN_ATTEMPT_STATES, SQL_PARAMS_NAMED, 'st');
        $rows = $DB->get_records_select('quiz_attempts', "quiz = :q AND userid = :u AND preview = 0 AND state {$insql}",
            ['q' => $quizid, 'u' => $userid] + $params, 'attempt DESC', 'id, quiz, userid, state, preview', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /**
     * Has the learner passed the pre-start check for this attempt (or, with no attempt id, for the attempt about to
     * start)?
     *
     * B2 (2026-11-20): with $secure (the quiz runs in secure mode) the session must also carry the secure-mode policy
     * the learner accepted (securitylevel). A session opened by the B1 check, or before secure mode was switched on,
     * has none, so the learner is shown the secure-mode rules once and pass_preflight() adds them to the same session.
     *
     * @param int $userid
     * @param int $quizid
     * @param int|null $attemptid null when no attempt exists yet
     * @param bool $secure the quiz runs in secure mode (B2)
     * @return bool
     */
    public static function preflight_done(int $userid, int $quizid, ?int $attemptid, bool $secure = false): bool {
        if (!empty($attemptid)) {
            $session = self::session_for_attempt($userid, $quizid, (int) $attemptid);
            if ($session === null) {
                return false;
            }
            if (!in_array($session->status, self::OPEN_STATES, true)) {
                // Closed while the attempt is still open (a reviewer flagged it from the queue): there is nothing left
                // to agree to, and asking again would send the learner round the pre-start check for ever, because
                // pass_preflight() never reopens a closed session. The monitor simply no longer loads.
                return true;
            }
            return $session->status === self::ACTIVE && !empty($session->consent_given_at)
                && (!$secure || secure_mode::is_level($session->securitylevel ?? null));
        }
        $pending = self::pending_session($userid, $quizid, true);
        return $pending !== null && (!$secure || secure_mode::is_level($pending->securitylevel ?? null));
    }

    /**
     * Store the learner's consent and identity confirmation from the pre-start check.
     *
     * With an attempt id (continuing an attempt whose session is missing or was not active) the session is bound to
     * that attempt; without one it waits pending for the attempt about to start. A session left from an earlier,
     * unfinished pre-start check of the same quiz is reused, so a learner has at most one pending session per quiz.
     *
     * B2 (2026-11-20): with a $policy (secure mode) the session also keeps the level and the auto-submit limit the
     * learner accepted; they stay fixed for the attempt. The consent and identity events are recorded only for the
     * B1 check and at the Proctored level (whose check carries those two boxes); every secure-mode session records
     * secure_rules_accepted with the notice version, the level and the limit.
     *
     * @param int $userid the learner (the current user)
     * @param int $quizid
     * @param int|null $attemptid
     * @param array|null $policy B2: ['level' => standard|secure|proctored, 'limit' => int], null for the B1 check
     * @return \stdClass the session, status ACTIVE
     */
    public static function pass_preflight(int $userid, int $quizid, ?int $attemptid, ?array $policy = null): \stdClass {
        global $DB;
        $attemptid = empty($attemptid) ? null : (int) $attemptid;
        $session = null;
        if ($attemptid !== null) {
            $session = self::latest_session(['attemptid' => $attemptid, 'userid' => $userid]);
            if ($session && !in_array($session->status, self::OPEN_STATES, true)) {
                // Already closed by the server: the attempt cannot be open. Never reopen a verdict.
                return $session;
            }
        }
        if (!$session) {
            $session = self::pending_session($userid, $quizid, false);
        }
        if (!$session) {
            $session = session_manager::start_session($userid, $quizid);
        }

        $now = time();
        $session->attemptid        = $attemptid ?? ($session->attemptid ?? null);
        $session->status           = self::ACTIVE;
        $session->consent_given_at = $now;
        $session->timestarted      = !empty($session->timestarted) ? $session->timestarted : $now;
        $session->timemodified     = $now;
        if ($policy !== null) {
            $session->securitylevel  = (string) $policy['level'];
            $session->violationlimit = max(0, (int) ($policy['limit'] ?? 0));
        } else if (!empty($session->securitylevel)) {
            // A pending row left by a secure-mode check, reused by the B1 check (secure mode was switched off in
            // between): it must not keep a policy the learner was not shown this time.
            $session->securitylevel  = null;
            $session->violationlimit = 0;
        }
        $DB->update_record('local_sentientia_proctor_sessions', $session);

        if ($policy === null || $policy['level'] === secure_mode::LEVEL_PROCTORED) {
            session_manager::record_event((int) $session->id, 'consent_given', 'info',
                ['notice' => self::NOTICE_VERSION], true);
            session_manager::record_event((int) $session->id, 'identity_confirmed', 'info',
                ['method' => 'attestation'], true);
        }
        if ($policy !== null) {
            session_manager::record_event((int) $session->id, 'secure_rules_accepted', 'info', [
                'notice' => secure_mode::NOTICE_VERSION,
                'level'  => (string) $policy['level'],
                'limit'  => (int) $session->violationlimit,
            ], true);
        }
        if ($attemptid !== null) {
            session_manager::record_event((int) $session->id, 'attempt_linked', 'info',
                ['attemptid' => $attemptid], true);
        }
        return $session;
    }

    /**
     * The session of an attempt, claiming the learner's fresh pending session for it when the attempt has none yet.
     *
     * @param int $userid the attempt's owner
     * @param int $quizid the attempt's quiz
     * @param int $attemptid
     * @return \stdClass|null
     */
    public static function session_for_attempt(int $userid, int $quizid, int $attemptid): ?\stdClass {
        global $DB;
        if ($attemptid <= 0) {
            return null;
        }
        $session = self::latest_session(['attemptid' => $attemptid, 'userid' => $userid]);
        if ($session) {
            return $session;
        }

        $pending = self::pending_session($userid, $quizid, true);
        if (!$pending) {
            return null;
        }
        // Claim only for a real (not preview) attempt of this learner at this quiz.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, quiz, userid, preview');
        if (!$attempt || (int) $attempt->quiz !== $quizid || (int) $attempt->userid !== $userid
                || !empty($attempt->preview)) {
            return null;
        }
        $pending->attemptid    = $attemptid;
        $pending->timemodified = time();
        $DB->update_record('local_sentientia_proctor_sessions', $pending);
        session_manager::record_event((int) $pending->id, 'attempt_linked', 'info', ['attemptid' => $attemptid], true);
        return $pending;
    }

    /**
     * Close the session of an attempt that ended (submitted or abandoned), on the server.
     *
     * Idempotent: a session the server already closed is returned unchanged.
     *
     * @param int $attemptid
     * @param int $userid the attempt's owner
     * @param int $quizid
     * @param string $reason submitted|abandoned
     * @return \stdClass|null the session, or null when the attempt was not proctored
     */
    public static function finalize_attempt(int $attemptid, int $userid, int $quizid, string $reason): ?\stdClass {
        $session = self::session_for_attempt($userid, $quizid, $attemptid);
        if (!$session) {
            return null;
        }
        if (!in_array($session->status, self::OPEN_STATES, true)) {
            return $session;
        }
        session_manager::record_event((int) $session->id, 'attempt_' . $reason, 'info',
            ['attemptid' => $attemptid], true);
        return session_manager::close_session($session);
    }

    /**
     * Note on the session that its attempt went overdue. The attempt is not finished: in the grace period the
     * learner can still submit, so the session stays open.
     *
     * @param int $attemptid
     * @param int $userid
     * @param int $quizid
     * @return void
     */
    public static function note_overdue(int $attemptid, int $userid, int $quizid): void {
        $session = self::session_for_attempt($userid, $quizid, $attemptid);
        if ($session && in_array($session->status, self::OPEN_STATES, true)) {
            session_manager::record_event((int) $session->id, 'attempt_overdue', 'info',
                ['attemptid' => $attemptid], true);
        }
    }

    /**
     * May the browser still report events on this session? It must be active and, once bound, its attempt open.
     *
     * @param \stdClass $session
     * @return bool
     */
    public static function accepts_client_events(\stdClass $session): bool {
        if ($session->status !== self::ACTIVE) {
            return false;
        }
        if (!empty($session->attemptid)) {
            return self::open_attempt((int) $session->attemptid) !== null;
        }
        return true;
    }

    /**
     * Does this learner have a session waiting (not yet bound to an attempt) on any quiz? A cheap first test for the
     * attempt observers, which run for every quiz attempt on the site.
     *
     * @param int $userid
     * @return bool
     */
    public static function has_pending(int $userid): bool {
        global $DB;
        return $DB->record_exists_select('local_sentientia_proctor_sessions',
            'userid = :u AND attemptid IS NULL AND status = :st', ['u' => $userid, 'st' => self::ACTIVE]);
    }

    /**
     * The learner's latest session on the quiz that waits for its attempt.
     *
     * @param int $userid
     * @param int $quizid
     * @param bool $consentedonly true: only an ACTIVE session whose consent is younger than PENDING_TTL;
     *                            false: any open one (to reuse its row)
     * @return \stdClass|null
     */
    private static function pending_session(int $userid, int $quizid, bool $consentedonly): ?\stdClass {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($consentedonly ? [self::ACTIVE] : self::OPEN_STATES,
            SQL_PARAMS_NAMED, 'st');
        $rows = $DB->get_records_select('local_sentientia_proctor_sessions',
            "userid = :u AND quizid = :q AND attemptid IS NULL AND status {$insql}",
            ['u' => $userid, 'q' => $quizid] + $inparams, 'id DESC', '*', 0, 1);
        $session = $rows ? reset($rows) : null;
        if (!$session) {
            return null;
        }
        if ($consentedonly && ((int) ($session->consent_given_at ?? 0)) < time() - self::PENDING_TTL) {
            return null;
        }
        return $session;
    }

    /**
     * The newest session matching the conditions.
     *
     * @param array $conditions
     * @return \stdClass|null
     */
    private static function latest_session(array $conditions): ?\stdClass {
        global $DB;
        $rows = $DB->get_records('local_sentientia_proctor_sessions', $conditions, 'id DESC', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }
}
