<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Secure assessment mode (B2, ADR-038 D3 and D6, post-demo plan B2, session 7, 2026-11-20).
 *
 * Behind sentientia.assessment.secure_mode.enabled (default OFF). A quiz gets a security level (Standard, Secure or
 * Proctored) and a purpose in its access-rule settings (quizaccess_sentientia_proctoring). With the flag ON, the
 * learner accepts the rules in the quiz's pre-start check; that opens the attempt's session (the B1 session table,
 * with the level and the auto-submit limit fixed on it). The attempt pages then run the browser module
 * local_sentientia_proctoring/secure_mode, which blocks what a page can block and reports the rest here.
 *
 * What the server decides (the page only shows the result):
 *  - Violations: leaving fullscreen, and the page or window losing the focus (another tab, window or application).
 *    Events within DEDUP_SECONDS of the last counted one count once (one switch fires several browser events), and a
 *    lost focus in the first GRACE_SECONDS after the server sent a page is not counted (ADR-038 D6, proposed values,
 *    measured from the server's page_served record so the page cannot extend it). Every
 *    event is stored with the server's time, counted or not, with the reason.
 *  - B2 review (2026-11-20): the grace excuses only a lost focus that ENDS within it (a permission prompt), not an
 *    absence of any length that merely began in it (a second monitor). Such a lost focus stays open on the server
 *    until the page reports the focus back (window_focus, now sent at once) or reports, when the grace is over, that
 *    it is still lost (window_still_away). Either report at or after GRACE_SECONDS from the page counts it once, as a
 *    window_blur stored with the reason grace_exceeded.
 *  - Policy (plan decision D11): employees are warned and their attempt is flagged for review; it is never submitted
 *    for them. On a hiring assessment the server submits the attempt itself when the count reaches the quiz's limit
 *    (default 3, 0 = never), with process_submit(), and records integrity_autosubmitted. Auto-submit ends an attempt;
 *    it never fails anybody: a reviewer decides.
 *  - Heartbeat: the page reports every HEARTBEAT_SECONDS. When it has been silent for more than GAP_SECONDS while the
 *    attempt was open (seen when it reports again, when the server sends the next page, or when the attempt ends), a
 *    heartbeat_gap is recorded. A page the server sent that never reported is visible as page_served without
 *    page_shown; an attempt whose pages never reported gets monitor_missing. Gaps are flagged, never counted.
 *  - Rate limit: at most RATE_MAX_EVENTS stored events per session in any RATE_WINDOW seconds, and MAX_BATCH events
 *    per call; beyond that the events are dropped (violations are still counted) and one rate_limited is recorded.
 *  - Listed for the reviewer, never counted or flagged on their own (ADR-038 D3): blocked copy, paste, print and
 *    shortcut attempts, a second display where the browser says so (Chromium only), and a change of the request's
 *    network address between two pages (ip_changed).
 *
 * What it cannot do (and the strings say so): see a second device such as a phone, another person, or a screenshot
 * taken with the computer's own tools; and a modified browser can switch the page's script off, which leaves only the
 * heartbeat gap as a trace.
 *
 * @package local_sentientia_proctoring
 */
class secure_mode {

    /** The B2 flag (ADR-038 D9), registered in db/feature_flags.php, default OFF. */
    public const FLAG = 'sentientia.assessment.secure_mode.enabled';

    /** Security levels. */
    public const LEVEL_STANDARD = 'standard';
    /** Security levels. */
    public const LEVEL_SECURE = 'secure';
    /** Security levels. */
    public const LEVEL_PROCTORED = 'proctored';

    /** The levels, lowest first, with their rank. */
    public const LEVELS = [self::LEVEL_STANDARD => 1, self::LEVEL_SECURE => 2, self::LEVEL_PROCTORED => 3];

    /** Assessment purposes and the lowest level each needs (plan decision D8). */
    public const PURPOSES = [
        'compliance'    => self::LEVEL_STANDARD,
        'skill'         => self::LEVEL_SECURE,
        'certification' => self::LEVEL_PROCTORED,
        'hiring'        => self::LEVEL_PROCTORED,
    ];

    /** The purpose whose attempts are submitted automatically at the violation limit (plan decision D11). */
    public const PURPOSE_HIRING = 'hiring';

    /** Default and highest per-quiz violation limit (ADR-038 D6). */
    public const DEFAULT_VIOLATION_LIMIT = 3;
    /** Default and highest per-quiz violation limit (ADR-038 D6). */
    public const MAX_VIOLATION_LIMIT = 20;

    /**
     * Version of the rules notice the learner accepted, kept in the secure_rules_accepted event. Change it whenever
     * the secure_notice_* strings of quizaccess_sentientia_proctoring change meaning.
     */
    public const NOTICE_VERSION = '2026-11-20.b2';

    /** Seconds between two reports of the attempt page. */
    public const HEARTBEAT_SECONDS = 15;
    /** Silence longer than this, while the attempt is open, is a heartbeat gap (three missed reports). */
    public const GAP_SECONDS = 45;
    /** Violations closer than this to the last counted one count once. */
    public const DEDUP_SECONDS = 2;
    /** A lost focus this soon after a page opened is not counted (permission prompts and slow loads blur). */
    public const GRACE_SECONDS = 10;
    /** Rate limit window, seconds. */
    public const RATE_WINDOW = 60;
    /** Stored events per session per window. */
    public const RATE_MAX_EVENTS = 40;
    /** Events read per call. */
    public const MAX_BATCH = 20;
    /** Lowest and highest page number kept: -1 is the summary page. */
    public const SUMMARY_PAGE = -1;

    /** Browser events that count as violations. */
    public const VIOLATIONS = ['tab_switch', 'window_blur', 'fullscreen_exit'];

    /**
     * B2 review: the page's report, once the grace is over, that a lost focus excused by it has not ended. It is not
     * stored itself: it can only make that lost focus count (as a window_blur with the reason grace_exceeded).
     */
    public const STILL_AWAY = 'window_still_away';

    /** Events the attempt page may report, with the severity the server gives them. Anything else is ignored. */
    public const CLIENT_EVENTS = [
        'page_shown'             => 'info',
        'tab_switch'             => 'warn',
        'window_blur'            => 'warn',
        'window_focus'           => 'info',
        'fullscreen_exit'        => 'warn',
        'fullscreen_enter'       => 'info',
        'fullscreen_unavailable' => 'warn',
        'fullscreen_denied'      => 'info',
        'file_dialog'            => 'info',
        'copy_blocked'           => 'info',
        'cut_blocked'            => 'info',
        'paste_blocked'          => 'warn',
        'contextmenu_blocked'    => 'info',
        'print_blocked'          => 'info',
        'shortcut_blocked'       => 'info',
        'drop_blocked'           => 'warn',
        'screenshot_key'         => 'info',
        'extended_display'       => 'warn',
    ];

    /** Server events that send a secure session to review. */
    public const REVIEW_EVENTS = ['heartbeat_gap', 'monitor_missing', 'integrity_autosubmitted'];

    /**
     * Is secure mode switched on for the current user's customer and tenant?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * Is this a security level?
     *
     * @param string|null $level
     * @return bool
     */
    public static function is_level(?string $level): bool {
        return $level !== null && isset(self::LEVELS[$level]);
    }

    /**
     * The rank of a level (0 for none).
     *
     * @param string|null $level
     * @return int
     */
    public static function rank(?string $level): int {
        return ($level !== null && isset(self::LEVELS[$level])) ? self::LEVELS[$level] : 0;
    }

    /**
     * The lowest level a purpose needs ('' when the purpose is not set).
     *
     * @param string|null $purpose
     * @return string
     */
    public static function min_level(?string $purpose): string {
        return ($purpose !== null && isset(self::PURPOSES[$purpose])) ? self::PURPOSES[$purpose] : '';
    }

    /**
     * The level a quiz runs at: its own level; else Proctored for a quiz saved with "Require proctoring" = Yes before
     * levels existed (ADR-038 D1: the old setting maps to Proctored); else '' (no secure mode).
     *
     * @param string|null $level the stored level
     * @param bool $proctored the stored B1 setting
     * @return string
     */
    public static function effective_level(?string $level, bool $proctored): string {
        if (self::is_level($level)) {
            return $level;
        }
        return $proctored ? self::LEVEL_PROCTORED : '';
    }

    /**
     * The number of violations at which an attempt is submitted automatically: the quiz's limit on a hiring
     * assessment, 0 (never: warn and flag) on every other purpose (plan decision D11).
     *
     * @param string|null $purpose
     * @param int|string|null $limit the stored limit (null: the default)
     * @return int
     */
    public static function autosubmit_limit(?string $purpose, $limit): int {
        if ($purpose !== self::PURPOSE_HIRING) {
            return 0;
        }
        if ($limit === null || $limit === '') {
            return self::DEFAULT_VIOLATION_LIMIT;
        }
        return max(0, min(self::MAX_VIOLATION_LIMIT, (int) $limit));
    }

    /**
     * The server sent a page of the open attempt (attempt.php or summary.php): record it, and check the heartbeat.
     * Called by the access rule on every such page, before the page reaches the browser.
     *
     * The request's network address is kept on the page record, and when it differs from the previous page's an
     * ip_changed event is recorded (ADR-038 D3: an IP change mid-attempt is listed for the reviewer; it is neither
     * counted nor flagged on its own, because office proxies and mobile networks change addresses legitimately).
     *
     * @param \stdClass $session the attempt's active secure session
     * @param int $page quiz page number, SUMMARY_PAGE for the summary page
     * @param int|null $now
     * @param string|null $ip the request's address (default: getremoteaddr())
     * @return void
     */
    public static function page_served(\stdClass $session, int $page, ?int $now = null, ?string $ip = null): void {
        global $DB;
        $now = $now ?? time();
        $ip = $ip ?? (string) getremoteaddr();
        self::check_gap($session, $now);
        if (empty($session->lastheartbeat)) {
            // The first page: the monitor is expected from now on.
            self::update($session, ['lastheartbeat' => $now], $now);
        }
        $previous = $DB->get_records('local_sentientia_proctor_events',
            ['sessionid' => (int) $session->id, 'event_type' => 'page_served'], 'id DESC', 'id, payload_json', 0, 1);
        if ($previous) {
            $payload = json_decode((string) reset($previous)->payload_json, true);
            $before = is_array($payload) ? (string) ($payload['ip'] ?? '') : '';
            if ($before !== '' && $ip !== '' && $before !== $ip) {
                self::log((int) $session->id, 'ip_changed', 'warn', ['from' => $before, 'to' => $ip], $now);
            }
        }
        self::log((int) $session->id, 'page_served', 'info', ['page' => self::page($page), 'ip' => $ip], $now);
    }

    /**
     * Take a report of the attempt page: its events since the last report, and the heartbeat the call itself is.
     *
     * @param \stdClass $session the attempt's active secure session (the caller checked ownership)
     * @param \stdClass $attempt the open quiz_attempts row (id, quiz, userid)
     * @param array $events [['type' => string, 'page' => int], ...], oldest first
     * @param int|null $now
     * @return array violations, limit, counted (violations counted by this call), autosubmitted
     */
    public static function report(\stdClass $session, \stdClass $attempt, array $events, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $sessionid = (int) $session->id;

        // The call is the heartbeat.
        self::check_gap($session, $now);
        if ((int) ($session->lastheartbeat ?? 0) !== $now) {
            self::update($session, ['lastheartbeat' => $now], $now);
        }

        $recent = $DB->count_records_select('local_sentientia_proctor_events',
            'sessionid = :s AND timecreated > :since', ['s' => $sessionid, 'since' => $now - self::RATE_WINDOW]);
        $total = $DB->count_records('local_sentientia_proctor_events', ['sessionid' => $sessionid]);
        $limited = false;
        $counted = 0;

        // Store one event, unless the session is over its rate limit: then it is dropped (a violation is still
        // counted) and one rate_limited is recorded per window.
        $store = function(string $type, array $payload) use ($sessionid, $now, &$recent, &$total, &$limited): void {
            if ($recent >= self::RATE_MAX_EVENTS || $total >= external\report_event::MAX_EVENTS) {
                if (!$limited && $total < external\report_event::MAX_EVENTS && !self::rate_limited_since($sessionid,
                        $now - self::RATE_WINDOW)) {
                    self::log($sessionid, 'rate_limited', 'warn', [], $now);
                    $total++;
                }
                $limited = true;
                return;
            }
            self::log($sessionid, $type, self::CLIENT_EVENTS[$type], $payload, $now);
            $recent++;
            $total++;
        };

        // B2 review: is a lost focus the grace excused still open (the learner has not come back to the page)?
        // null until this call needs to know; then kept up to date as the call's own events open or close it.
        $graceopen = null;
        foreach (array_slice($events, 0, self::MAX_BATCH) as $event) {
            $type = (string) ($event['type'] ?? '');
            $page = self::page((int) ($event['page'] ?? 0));
            if ($type === 'window_focus' || $type === self::STILL_AWAY) {
                // The focus is back, or the page says it is still lost now that the grace is over. Either way a lost
                // focus the grace excused counts after all when it lasted to the end of the grace (ADR-038 D6: the
                // grace is for short blurs such as a permission prompt, not for an absence of any length).
                $graceopen = $graceopen ?? self::grace_blur_open($session);
                if ($graceopen && self::grace_exceeded($session, $now)) {
                    self::count_violation($session, $now);
                    $counted++;
                    $graceopen = false;
                    $store('window_blur', ['page' => $page, 'counted' => 1, 'reason' => 'grace_exceeded']);
                }
                if ($type === self::STILL_AWAY) {
                    continue;  // Never stored itself.
                }
                $graceopen = false;  // The focus is back: whatever was open is over.
            }
            if (!isset(self::CLIENT_EVENTS[$type])) {
                continue;
            }
            $payload = ['page' => $page];
            if (in_array($type, self::VIOLATIONS, true)) {
                $reason = self::not_counted_reason($session, $type, $now);
                if ($reason === '') {
                    self::count_violation($session, $now);
                    $counted++;
                    $payload['counted'] = 1;
                    $graceopen = false;  // A violation counted now covers a lost focus that is still open.
                } else {
                    $payload['counted'] = 0;
                    $payload['reason'] = $reason;
                    if ($reason === 'grace') {
                        $graceopen = true;
                    }
                }
            }
            $store($type, $payload);
        }

        $autosubmitted = false;
        $limit = (int) ($session->violationlimit ?? 0);
        if ($limit > 0 && (int) $session->violations >= $limit && empty($session->timeautosubmitted)) {
            $autosubmitted = self::autosubmit($session, $attempt, $now);
        }

        return [
            'violations'    => (int) $session->violations,
            'limit'         => $limit,
            'counted'       => $counted,
            'autosubmitted' => $autosubmitted,
        ];
    }

    /**
     * Record a heartbeat gap when the page has been silent for more than GAP_SECONDS. The gap is measured up to $end
     * (the attempt's end, at close) or $now, and the heartbeat baseline moves to that point so a gap is never
     * recorded twice.
     *
     * @param \stdClass $session
     * @param int $now server time, stored on the event
     * @param int|null $end measure up to this time instead of $now
     * @return bool whether a gap was recorded
     */
    public static function check_gap(\stdClass $session, int $now, ?int $end = null): bool {
        $last = (int) ($session->lastheartbeat ?? 0);
        $upto = $end ?? $now;
        if ($last <= 0 || $upto - $last <= self::GAP_SECONDS) {
            return false;
        }
        self::log((int) $session->id, 'heartbeat_gap', 'warn',
            ['seconds' => $upto - $last, 'since' => $last, 'atend' => $end === null ? 0 : 1], $now);
        self::update($session, ['lastheartbeat' => $upto], $now);
        return true;
    }

    /**
     * Submit the attempt for the learner, on the server, because it reached the violation limit (hiring only).
     *
     * The session is stamped first: the attempt_submitted observer closes and scores the session inside
     * process_submit(), and it must see the auto-submit to send the session to review.
     *
     * @param \stdClass $session
     * @param \stdClass $attempt quiz_attempts row (id)
     * @param int $now
     * @return bool true when this call submitted the attempt
     */
    public static function autosubmit(\stdClass $session, \stdClass $attempt, int $now): bool {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        if (quiz_integration::open_attempt((int) $attempt->id) === null) {
            return false;  // Already submitted, abandoned or a preview.
        }
        self::update($session, ['timeautosubmitted' => $now], $now);
        self::log((int) $session->id, 'integrity_autosubmitted', 'critical',
            ['violations' => (int) $session->violations, 'limit' => (int) $session->violationlimit], $now);

        $attemptobj = \mod_quiz\quiz_attempt::create((int) $attempt->id);
        if (method_exists($attemptobj, 'process_submit')) {
            // Moodle 5.0+: the same two steps processattempt.php takes when the learner finishes.
            $attemptobj->process_submit($now, false, null, true);
            $attemptobj->process_grade_submission($now);
        } else {
            $attemptobj->process_finish($now, false, null, true);
        }
        return true;
    }

    /**
     * Called by session_manager::close_session() before the session is scored: for a secure session, record a
     * heartbeat gap up to the attempt's end and monitor_missing when the attempt's pages did not report.
     *
     * @param \stdClass $session
     * @param int $now
     * @return void
     */
    public static function before_close(\stdClass $session, int $now): void {
        global $DB;
        if (!self::is_level($session->securitylevel ?? null)) {
            return;
        }
        $end = $now;
        if (!empty($session->attemptid)) {
            $finish = (int) $DB->get_field('quiz_attempts', 'timefinish', ['id' => (int) $session->attemptid]);
            if ($finish > 0 && $finish <= $now) {
                $end = $finish;
            }
        }
        self::check_gap($session, $now, $end);

        $served = $DB->count_records('local_sentientia_proctor_events',
            ['sessionid' => (int) $session->id, 'event_type' => 'page_served']);
        $shown = $DB->count_records('local_sentientia_proctor_events',
            ['sessionid' => (int) $session->id, 'event_type' => 'page_shown']);
        // One page may legitimately be left before its script runs (a double click on Next); two may not.
        // B2 review (2026-11-20): an attempt the server never sent a single page of was never protected or monitored
        // (started and answered through the quiz web services, or the browser closed before the first page), so it
        // goes to review too. A session closed before any attempt claimed it (no attemptid) has nothing to review.
        $neverserved = $served === 0 && !empty($session->attemptid);
        if ($neverserved || ($served > 0 && ($shown === 0 || $served - $shown >= 2))) {
            self::log((int) $session->id, 'monitor_missing', 'warn', ['served' => $served, 'shown' => $shown], $now);
        }
    }

    /**
     * Must a closed secure session go to the review queue (plan decision D11: employees warned and flagged)? Yes when
     * it counted a violation, was submitted automatically, or has a heartbeat gap or pages without the monitor.
     *
     * @param \stdClass $session
     * @return bool
     */
    public static function needs_review(\stdClass $session): bool {
        global $DB;
        if (!self::is_level($session->securitylevel ?? null)) {
            return false;
        }
        if ((int) ($session->violations ?? 0) > 0 || !empty($session->timeautosubmitted)) {
            return true;
        }
        [$insql, $params] = $DB->get_in_or_equal(self::REVIEW_EVENTS, SQL_PARAMS_NAMED, 'ev');
        return $DB->record_exists_select('local_sentientia_proctor_events',
            "sessionid = :sid AND event_type {$insql}", ['sid' => (int) $session->id] + $params);
    }

    /**
     * Why a violation event is not counted: 'duplicate' within DEDUP_SECONDS of the last counted violation, 'grace'
     * for a lost focus within GRACE_SECONDS of the server sending the latest page; '' when it counts. The grace runs
     * from the server's own page_served record, not from the page's page_shown report, so a page cannot extend it.
     * A lost focus excused by the grace stays open until it ends (see report() and grace_blur_open()).
     *
     * @param \stdClass $session
     * @param string $type
     * @param int $now
     * @return string
     */
    private static function not_counted_reason(\stdClass $session, string $type, int $now): string {
        $last = (int) ($session->lastviolation ?? 0);
        if ($last > 0 && $now - $last < self::DEDUP_SECONDS) {
            return 'duplicate';
        }
        if ($type === 'window_blur') {
            $served = self::last_served((int) $session->id);
            if ($served > 0 && $now - $served < self::GRACE_SECONDS) {
                return 'grace';
            }
        }
        return '';
    }

    /**
     * The server time of the latest page the server sent of this session's attempt (0 for none).
     *
     * @param int $sessionid
     * @return int
     */
    private static function last_served(int $sessionid): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT MAX(timecreated) FROM {local_sentientia_proctor_events} WHERE sessionid = :s AND event_type = :t',
            ['s' => $sessionid, 't' => 'page_served']);
    }

    /**
     * B2 review: is a lost focus the grace excused still open? It is when, of the session's lost-focus, focus-back
     * and page records, the latest is that excused lost focus (the focus has not come back and no new page was
     * sent), and no violation has been counted since it (a violation counted then already covers the absence).
     *
     * @param \stdClass $session
     * @return bool
     */
    private static function grace_blur_open(\stdClass $session): bool {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(['window_blur', 'window_focus', 'page_served'], SQL_PARAMS_NAMED, 'gt');
        $rows = $DB->get_records_select('local_sentientia_proctor_events', "sessionid = :s AND event_type {$insql}",
            ['s' => (int) $session->id] + $params, 'id DESC', 'id, event_type, payload_json, timecreated', 0, 1);
        $latest = $rows ? reset($rows) : null;
        if (!$latest || $latest->event_type !== 'window_blur') {
            return false;
        }
        $payload = json_decode((string) ($latest->payload_json ?? ''), true);
        if (!is_array($payload) || ($payload['reason'] ?? '') !== 'grace') {
            return false;
        }
        return (int) ($session->lastviolation ?? 0) < (int) $latest->timecreated;
    }

    /**
     * B2 review: has the grace of the latest page run out, so that a lost focus still open then counts? A violation
     * counted within DEDUP_SECONDS covers it as well.
     *
     * @param \stdClass $session
     * @param int $now
     * @return bool
     */
    private static function grace_exceeded(\stdClass $session, int $now): bool {
        $last = (int) ($session->lastviolation ?? 0);
        if ($last > 0 && $now - $last < self::DEDUP_SECONDS) {
            return false;
        }
        $served = self::last_served((int) $session->id);
        return $served > 0 && $now - $served >= self::GRACE_SECONDS;
    }

    /**
     * Count one violation. The increment is done in SQL, so two reports arriving together are both counted.
     *
     * @param \stdClass $session updated in place
     * @param int $now
     * @return void
     */
    private static function count_violation(\stdClass $session, int $now): void {
        global $DB;
        $DB->execute('UPDATE {local_sentientia_proctor_sessions}
                         SET violations = violations + 1, lastviolation = :now, timemodified = :modified
                       WHERE id = :id', ['now' => $now, 'modified' => $now, 'id' => (int) $session->id]);
        $session->violations = (int) $DB->get_field('local_sentientia_proctor_sessions', 'violations',
            ['id' => (int) $session->id]);
        $session->lastviolation = $now;
        $session->timemodified = $now;
    }

    /**
     * Has a rate_limited event been recorded since $since?
     *
     * @param int $sessionid
     * @param int $since
     * @return bool
     */
    private static function rate_limited_since(int $sessionid, int $since): bool {
        global $DB;
        return $DB->record_exists_select('local_sentientia_proctor_events',
            'sessionid = :s AND event_type = :t AND timecreated > :since',
            ['s' => $sessionid, 't' => 'rate_limited', 'since' => $since]);
    }

    /**
     * A page number as stored: SUMMARY_PAGE for the summary page, else 0..10000.
     *
     * @param int $page
     * @return int
     */
    private static function page(int $page): int {
        return max(self::SUMMARY_PAGE, min(10000, $page));
    }

    /**
     * Update some fields of a session, in the database and on the object. Only the given fields are written, so a
     * report never overwrites a status the attempt observer has just set.
     *
     * @param \stdClass $session
     * @param array $fields
     * @param int $now
     * @return void
     */
    private static function update(\stdClass $session, array $fields, int $now): void {
        global $DB;
        $fields['timemodified'] = $now;
        $DB->update_record('local_sentientia_proctor_sessions', (object) (['id' => (int) $session->id] + $fields));
        foreach ($fields as $name => $value) {
            $session->{$name} = $value;
        }
    }

    /**
     * Store an event with the given server time.
     *
     * @param int $sessionid
     * @param string $type
     * @param string $severity info|warn|critical
     * @param array $payload
     * @param int $now
     * @return int the event id
     */
    public static function log(int $sessionid, string $type, string $severity, array $payload, int $now): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_proctor_events', (object) [
            'sessionid'    => $sessionid,
            'event_type'   => $type,
            'severity'     => $severity,
            'payload_json' => $payload ? json_encode($payload) : null,
            'timecreated'  => $now,
        ]);
    }
}
