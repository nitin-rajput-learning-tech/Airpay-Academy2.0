<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

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
 * @package local_sentientia_proctoring
 */
class session_manager {

    /**
     * ADR-031: refuse unless the viewer may see / act on a session of this
     * tenant.
     *
     * tenant::require_access() compares tenant roots, so a reviewer with no
     * tenant (root 0) matched every session stamped costcenterid 0 - i.e.
     * every other no-tenant candidate's recordings, identity checks and
     * verdicts. A viewer who is not cross-tenant must now have a tenant of
     * their own; cross-tenant viewers (site admin, :crosstenant) always pass.
     *
     * @param int $costcenterid the session's tenant root
     * @param int|null $viewerid defaults to the current user
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_session_access(int $costcenterid, ?int $viewerid = null): void {
        global $DB, $USER;
        $viewerid = $viewerid ?? (int) ($USER->id ?? 0);
        if (!\local_sentientia_platform\tenant::is_cross_tenant($viewerid)) {
            $viewer = ($viewerid === (int) ($USER->id ?? 0)) ? $USER
                : $DB->get_record('user', ['id' => $viewerid], 'id, open_path');
            if (!$viewer || \local_sentientia_platform\tenant::root_for_user($viewer) <= 0) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
        }
        \local_sentientia_platform\tenant::require_access($costcenterid, $viewerid);
    }

    /**
     * ADR-031: who is told that this session was flagged.
     *
     * There is one site-wide default_reviewer setting. The flag notification
     * names the candidate's user id, the quiz and the risk score, and it went
     * to that reviewer whatever the session's tenant - so a reviewer in one
     * tenant was told about another tenant's candidates, whose session they
     * cannot then open (every reviewer page and web service checks
     * require_session_access()). The notification now goes to the default
     * reviewer only if they pass that same check; otherwise to nobody (0).
     * The default (userid 2, the site admin, cross-tenant) is unaffected.
     *
     * @param \stdClass $session carrying costcenterid
     * @return int reviewer user id, or 0
     */
    public static function flag_recipient(\stdClass $session): int {
        $reviewerid = (int) (get_config('local_sentientia_proctoring', 'default_reviewer') ?: 2);
        if ($reviewerid <= 0) {
            return 0;
        }
        try {
            self::require_session_access((int) ($session->costcenterid ?? 0), $reviewerid);
        } catch (\moodle_exception $e) {
            return 0;
        }
        return $reviewerid;
    }

    /**
     * The context the audit events of a session are logged in (decision A5, 2026-11-12): the quiz's module context
     * when the quiz still exists, else the system context (a quiz deleted since the attempt, or a session with no quiz).
     *
     * @param int $quizid local_sentientia_proctor_sessions.quizid, a quiz instance id
     * @return \context
     */
    public static function event_context(int $quizid): \context {
        if ($quizid > 0) {
            $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $context = \context_module::instance((int) $cm->id, IGNORE_MISSING);
                if ($context) {
                    return $context;
                }
            }
        }
        return \context_system::instance();
    }

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
        $session->id = $DB->insert_record('local_sentientia_proctor_sessions', $session);

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
        $session = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);
        if ((int) $session->userid !== (int) $USER->id) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }
        return $session;
    }

    /**
     * Refuse unless the session is one of the pre-B1 browser flow that has not started yet: no quiz attempt bound to
     * it and its status in $states.
     *
     * B1 review (2026-11-20). Consent and identity now belong to the quiz's own pre-start check
     * (quiz_integration::pass_preflight()). The old web services that reach record_consent() and submit_identity()
     * stay registered (ADR-038 D2: the 12 services stay) and are open to every logged-in user, and until this check
     * they changed any session of the caller, whatever its state:
     *  - during an attempt, give_consent set the active session back to `consenting`; report_event then refused every
     *    later event and the monitor stopped, so the learner could answer unrecorded, and passing the pre-start check
     *    again on the summary page made the session active once more, to be scored clean;
     *  - after an attempt, give_consent or submit_identity reopened a flagged or reviewed session, which left the review
     *    queue (it lists status `flagged`) and was never closed again.
     * A session bound to an attempt, or past these states, is refused with error_session_state and left unchanged.
     *
     * @param \stdClass $session a local_sentientia_proctor_sessions row
     * @param string[] $states the statuses the caller may act on
     * @throws \moodle_exception error_session_state
     */
    private static function require_unstarted(\stdClass $session, array $states): void {
        if (!empty($session->attemptid) || !in_array((string) $session->status, $states, true)) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }
    }

    /**
     * Candidate clicks "I consent" (pre-B1 browser flow, give_consent web service).
     *
     * B1 review (2026-11-20): only on the caller's own session that has no attempt and is `new` or `consenting`
     * (require_unstarted()).
     */
    public static function record_consent(int $sessionid, int $userid): \stdClass {
        global $DB;
        $session = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);
        if ((int) $session->userid !== $userid) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }
        self::require_unstarted($session, ['new', 'consenting']);
        $session->status           = 'consenting';
        $session->consent_given_at = time();
        $session->timemodified     = time();
        $DB->update_record('local_sentientia_proctor_sessions', $session);
        return $session;
    }

    /**
     * Submit identity photos. Returns the identity row.
     * The photo bytes are NEVER stored — only the score.
     *
     * B1 review (2026-11-20): only on the caller's own session that has no attempt and is `new`, `consenting` or
     * `verifying` (require_unstarted()); checked before anything is written or verified.
     */
    public static function submit_identity(int $sessionid, int $userid,
                                            string $id_photo_bytes,
                                            string $selfie_bytes): \stdClass {
        global $DB;
        $session = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);
        if ((int) $session->userid !== $userid) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }
        self::require_unstarted($session, ['new', 'consenting', 'verifying']);

        $session->status = 'verifying';
        $session->timemodified = time();
        $DB->update_record('local_sentientia_proctor_sessions', $session);

        // Verify (photos are passed by value, not retained).
        $verifier = \local_sentientia_proctoring\identity\verifier_factory::get_current();
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
        $id_row->id = $DB->insert_record('local_sentientia_proctor_identity', $id_row);

        // Link back to session.
        $session->identity_id = $id_row->id;
        $session->status = $result['passed'] ? 'recording' : 'new';
        $session->timemodified = time();
        if ($result['passed']) {
            $session->timestarted = time();
        }
        $DB->update_record('local_sentientia_proctor_sessions', $session);

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
        return $DB->insert_record('local_sentientia_proctor_events', (object) [
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
     *
     * B1 review (2026-11-20): the upload_chunk web service refuses every call while
     * sentientia.proctoring.recording.enabled is OFF (B4, ADR-038 D5): nothing records yet, and a key registered here
     * names an object the retention purge (task\purge_old_recordings) later deletes. The regex above still lets the
     * caller name any object in the bucket; before B4 switches recording on, the key must be tied to a prefix the
     * server issues for this session.
     */
    public static function register_chunk(int $sessionid, string $kind,
                                           int $chunk_idx, string $s3_key,
                                           int $size_bytes, int $duration_ms): int {
        global $DB;
        $session = self::assert_session_owner($sessionid);
        if (!in_array($session->status, ['recording', 'verifying'], true)) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }
        // s3_key whitelist — opaque key MUST match a safe-path regex.
        // PARAM_TEXT (the external_value type) is too loose: it lets
        // attackers register a `s3_key` like `../../etc/admin.json` and
        // when a reviewer later opens the chunk, our proxy would resolve
        // outside the intended bucket prefix.
        if (!preg_match('#^[a-zA-Z0-9/_.-]{1,512}$#', $s3_key)) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring',
                '', 'Invalid s3 key');
        }
        // Bound sizes — defense against a client uploading absurd values
        // that bloat the recording table and break analytics.
        if ($size_bytes < 0 || $size_bytes > 64 * 1024 * 1024) {  // 64 MB / chunk max
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring',
                '', 'Invalid size');
        }
        if ($duration_ms < 0 || $duration_ms > 120000) {  // 2 min / chunk max
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring',
                '', 'Invalid duration');
        }

        $retention_days = (int) (get_config('local_sentientia_proctoring', 'retention_days') ?: 90);
        return $DB->insert_record('local_sentientia_proctor_recordings', (object) [
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
     *
     * B1 (ADR-038 D2, 2026-11-20): the server closes a session when its
     * quiz attempt is submitted or abandoned (observer -> close_session()).
     * While that attempt is still open this call changes nothing: a
     * learner who could close their own session early would be scored
     * on a clean start and could then cheat unrecorded, because a closed
     * session is never scored again. A session not tied to an attempt
     * (the pre-B1 browser flow) is closed as before.
     */
    public static function finalize(int $sessionid): \stdClass {
        $session = self::assert_session_owner($sessionid);
        if (!empty($session->attemptid)
                && quiz_integration::open_attempt((int) $session->attemptid) !== null) {
            return $session;
        }
        return self::close_session($session);
    }

    /**
     * Close a session and score it: the server-side end of a proctored
     * attempt. No ownership check: the callers are the attempt observers
     * (which may run in cron, as nobody) and finalize() after its own
     * check.
     *
     * Idempotent: a finished, flagged or reviewed session is returned
     * unchanged and never scored twice (B1 adds `finished`: a replay used
     * to log a second session_end and could turn a clean verdict into a
     * flag from events recorded after the attempt ended).
     *
     * B2 (ADR-038 D6, plan decision D11, 2026-11-20): a secure-mode session
     * first records a heartbeat gap up to the attempt's end and any pages
     * that never reported (secure_mode::before_close()); then, if it counted
     * a violation, was submitted automatically or has such a gap, it goes to
     * review (at least `warn`) whatever its score. The score itself is
     * unchanged; a `fail` still needs a reviewer. Only the closing fields are
     * written, so the violation count a report has just stored is never
     * overwritten from an older copy of the row.
     *
     * @param \stdClass $session a local_sentientia_proctor_sessions row
     * @return \stdClass the stored session
     */
    public static function close_session(\stdClass $session): \stdClass {
        global $DB;
        $sessionid = (int) $session->id;

        if (in_array($session->status, ['finished', 'flagged', 'reviewed'], true)) {
            return $session;  // idempotent
        }

        $now = time();
        secure_mode::before_close($session, $now);
        $session->timefinished = $now;
        $session->timemodified = $now;

        // Internal write on the session being closed (skip_ownership).
        self::record_event($sessionid, 'session_end', 'info', [], true);

        // Run analyzer.
        $result = \local_sentientia_proctoring\analyzer\risk_analyzer::analyze($sessionid);
        $decision = $result['decision'];
        if ($decision === 'clean' && secure_mode::needs_review($session)) {
            $decision = 'warn';
        }
        $session->risk_score    = $result['risk_score'];
        $session->auto_decision = $decision;

        if ($decision === 'clean') {
            $session->status = 'finished';
        } else {
            $session->status = 'flagged';
            // Notify the default reviewer - only one who may open this
            // session's tenant (ADR-031, see flag_recipient()).
            notifier::session_flagged($session, self::flag_recipient($session));
        }

        $DB->update_record('local_sentientia_proctor_sessions', (object) [
            'id'            => $sessionid,
            'status'        => $session->status,
            'risk_score'    => $session->risk_score,
            'auto_decision' => $session->auto_decision,
            'timefinished'  => $session->timefinished,
            'timemodified'  => $session->timemodified,
        ]);
        if ($session->status === 'flagged') {
            // Audit trail (decision A5, 2026-11-12), once the flag is stored. A clean session is not flagged, and an
            // already flagged or reviewed one returned above.
            event\session_flagged::create_from_session($session, 'auto')->trigger();
        }
        return $session;
    }

    /** Reviewer submits a decision on a flagged session. */
    public static function submit_review(int $sessionid, int $reviewerid,
                                          string $decision, string $note): \stdClass {
        global $DB;
        if (!in_array($decision, ['clean', 'warn', 'fail'], true)) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }
        $session = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);

        $review = (object) [
            'sessionid'       => $sessionid,
            'reviewer_userid' => $reviewerid,
            'decision'        => $decision,
            'note'            => $note,
            'timecreated'     => time(),
        ];
        $reviewid = (int) $DB->insert_record('local_sentientia_proctor_reviews', $review);

        $session->human_decision = $decision;
        $session->status         = 'reviewed';
        $session->timemodified   = time();
        $DB->update_record('local_sentientia_proctor_sessions', $session);

        // Audit trail (decision A5, 2026-11-12), once the review and the session's new status are stored. An unknown
        // decision threw above, before anything was written.
        event\review_submitted::create_from_review($session, $reviewid, $reviewerid)->trigger();

        notifier::session_reviewed($session);
        return $session;
    }
}
