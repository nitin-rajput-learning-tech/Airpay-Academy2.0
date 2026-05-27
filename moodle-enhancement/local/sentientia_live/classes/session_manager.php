<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Session CRUD + state transitions — Phase E.1.a (2026-05-21).
 *
 * Pure data layer for local_sentientia_live_sessions. Trainer UI (Phase
 * E.1) calls these; audience join flow (Phase E.2) calls find_by_code()
 * + can_user_join(); SSE stream (Phase E.3) calls get().
 *
 * State machine (enforced by transition methods, NOT trusting callers):
 *
 *     draft  ──start()──>  live  ──end()──>  ended
 *                  ↑
 *                  └─ create() returns sessions in 'draft' state.
 *                     start_session() flips to 'live' and writes a
 *                     session_started event. end_session() flips to
 *                     'ended' and writes a session_ended event. No
 *                     other transitions allowed.
 *
 * Code generation: 6-digit numeric, collision-checked among currently-
 * live sessions only. Once a session enters 'ended' state its code is
 * recyclable — keeps the join keyspace from drifting toward exhaustion
 * over months of usage.
 *
 * Multi-tenant: customerid + tenantid are derived from the creating
 * user's open_path at creation time and PINNED to the row. A later
 * tenant change on the user doesn't move existing sessions.
 *
 * @package local_sentientia_live
 */
class session_manager {

    public const STATE_DRAFT = 'draft';
    public const STATE_LIVE  = 'live';
    public const STATE_ENDED = 'ended';

    /** Code generation: number of digits. */
    public const CODE_LENGTH = 6;

    /** Max attempts to generate a unique code before raising. */
    private const CODE_GEN_MAX_ATTEMPTS = 20;

    /**
     * Create a new session in draft state.
     *
     * @param int    $ownerid    Trainer's user ID. Must be > 0.
     * @param string $title      Session display name (1-200 chars).
     * @param array  $settings   Optional: ['allow_anonymous' => bool,
     *                                       'show_results_to_audience' => bool,
     *                                       'allow_late_join' => bool,
     *                                       'max_concurrent' => int]
     * @return int New session row ID.
     * @throws \moodle_exception on invalid input.
     */
    public static function create(int $ownerid, string $title,
                                   array $settings = []): int {
        global $DB, $USER;
        if ($ownerid <= 0) {
            throw new \moodle_exception('invaliduser', 'core');
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new \moodle_exception('invalidtitle', 'local_sentientia_live');
        }

        // Derive customerid + tenantid from the owner's open_path.
        // open_path is a BizLMS-added column on {user}; it is NOT present
        // on a vanilla Moodle. Sentientia LMS ships to non-BizLMS
        // customers, and the PHPUnit test DB has no such column — so read
        // it defensively. When absent, there is no multi-tenant tree and
        // tenantid stays 0 (the "no tenant / global" scope).
        $usercolumns = $DB->get_columns('user');
        $userfields = isset($usercolumns['open_path']) ? 'id, open_path' : 'id';
        $user = $DB->get_record('user', ['id' => $ownerid],
            $userfields, MUST_EXIST);
        $tenantid = 0;
        $openpath = $user->open_path ?? '';
        if ($openpath !== '') {
            $parts = explode('/', trim($openpath, '/'));
            if (!empty($parts[0]) && ctype_digit($parts[0])) {
                $tenantid = (int) $parts[0];
            }
        }
        $customerid = 1;
        if (class_exists('\\local_airpay_core\\customer')) {
            $customerid = \local_airpay_core\customer::current();
        }

        $now = time();
        $row = new \stdClass();
        $row->code             = self::generate_unique_code();
        $row->ownerid          = $ownerid;
        $row->customerid       = $customerid;
        $row->tenantid         = $tenantid;
        $row->title            = $title;
        $row->state            = self::STATE_DRAFT;
        $row->current_slide_id = null;
        $row->settings_json    = json_encode(self::sanitise_settings($settings));
        $row->timecreated      = $now;
        $row->timestarted      = null;
        $row->timeended        = null;
        $row->timemodified     = $now;

        return (int) $DB->insert_record('local_sentientia_live_sessions', $row);
    }

    /**
     * Get one session by ID. Returns null if not found.
     */
    public static function get(int $sessionid): ?\stdClass {
        global $DB;
        if ($sessionid <= 0) {
            return null;
        }
        $row = $DB->get_record('local_sentientia_live_sessions',
            ['id' => $sessionid]);
        return $row ?: null;
    }

    /**
     * Find a session by its audience-facing join code. Only returns
     * sessions in 'live' state — codes for ended/draft sessions are
     * invisible to audience.
     *
     * @param string $code 6-digit numeric code (will be trimmed +
     *                      digit-stripped to handle "123 456" spacing)
     * @return \stdClass|null
     */
    public static function find_by_code(string $code): ?\stdClass {
        global $DB;
        $clean = preg_replace('/\D/', '', $code);
        if (strlen($clean) !== self::CODE_LENGTH) {
            return null;
        }
        $row = $DB->get_record('local_sentientia_live_sessions', [
            'code'  => $clean,
            'state' => self::STATE_LIVE,
        ]);
        return $row ?: null;
    }

    /**
     * List sessions owned by a user, newest first.
     *
     * @param int $ownerid
     * @param string|null $state Filter by state, or null for all states.
     * @param int $limit
     * @return array Array of stdClass session rows.
     */
    public static function list_owned_by(int $ownerid, ?string $state = null,
                                          int $limit = 100): array {
        global $DB;
        $conds = ['ownerid' => $ownerid];
        if ($state !== null) {
            $conds['state'] = $state;
        }
        return $DB->get_records('local_sentientia_live_sessions',
            $conds, 'timecreated DESC', '*', 0, $limit);
    }

    /**
     * Transition draft → live. Writes a session_started event so
     * the SSE stream + audience screens get notified.
     *
     * @param int $sessionid
     * @return bool True on success, false if state wasn't draft.
     */
    public static function start_session(int $sessionid): bool {
        global $DB;
        $sess = self::get($sessionid);
        if (!$sess || $sess->state !== self::STATE_DRAFT) {
            return false;
        }
        $now = time();
        $DB->update_record('local_sentientia_live_sessions', (object) [
            'id'           => $sessionid,
            'state'        => self::STATE_LIVE,
            'timestarted'  => $now,
            'timemodified' => $now,
        ]);
        event_journal::write($sessionid, 'session_started', [
            'started_at' => $now,
        ]);
        return true;
    }

    /**
     * Transition live → ended. Writes a session_ended event.
     */
    public static function end_session(int $sessionid): bool {
        global $DB;
        $sess = self::get($sessionid);
        if (!$sess || $sess->state !== self::STATE_LIVE) {
            return false;
        }
        $now = time();
        $DB->update_record('local_sentientia_live_sessions', (object) [
            'id'           => $sessionid,
            'state'        => self::STATE_ENDED,
            'timeended'    => $now,
            'timemodified' => $now,
        ]);
        event_journal::write($sessionid, 'session_ended', [
            'ended_at' => $now,
        ]);
        return true;
    }

    /**
     * Advance to a specific slide. Writes a slide_changed event so
     * audience SSE listeners update simultaneously.
     */
    public static function set_current_slide(int $sessionid, int $slideid): bool {
        global $DB;
        $sess = self::get($sessionid);
        if (!$sess || $sess->state !== self::STATE_LIVE) {
            return false;
        }
        // Validate the slide belongs to this session.
        $belongs = $DB->record_exists('local_sentientia_live_slides', [
            'id'        => $slideid,
            'sessionid' => $sessionid,
        ]);
        if (!$belongs) {
            return false;
        }
        $now = time();
        $DB->update_record('local_sentientia_live_sessions', (object) [
            'id'               => $sessionid,
            'current_slide_id' => $slideid,
            'timemodified'     => $now,
        ]);
        event_journal::write($sessionid, 'slide_changed', [
            'slide_id'   => $slideid,
            'changed_at' => $now,
        ]);
        return true;
    }

    /**
     * Hard-delete a session and all its dependent rows. Used by GDPR
     * delete + admin "purge" tooling. Cascades: events, responses
     * (via participants), participants, slides, then the session
     * itself. Wrapped in a transaction.
     */
    public static function delete(int $sessionid): void {
        global $DB;
        $trans = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('local_sentientia_live_events',
                ['sessionid' => $sessionid]);

            // Responses linked through participants.
            $part_ids = $DB->get_fieldset_select(
                'local_sentientia_live_participants', 'id',
                'sessionid = :sid', ['sid' => $sessionid]);
            if (!empty($part_ids)) {
                [$insql, $params] = $DB->get_in_or_equal(
                    $part_ids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('local_sentientia_live_responses',
                    "participantid $insql", $params);
            }
            $DB->delete_records('local_sentientia_live_participants',
                ['sessionid' => $sessionid]);
            $DB->delete_records('local_sentientia_live_slides',
                ['sessionid' => $sessionid]);
            $DB->delete_records('local_sentientia_live_sessions',
                ['id' => $sessionid]);
            $trans->allow_commit();
        } catch (\Throwable $e) {
            $trans->rollback($e);
            throw $e;
        }
    }

    /**
     * Can $userid run (start/advance/end) the given session?
     *
     * Allowed when:
     *   1. The user owns the session, OR
     *   2. The user has local/sentientia_live:manage_all capability.
     */
    public static function can_user_run(int $userid, int $sessionid): bool {
        $sess = self::get($sessionid);
        if (!$sess) {
            return false;
        }
        if ((int) $sess->ownerid === $userid) {
            return true;
        }
        $context = \context_system::instance();
        return has_capability(
            'local/sentientia_live:manage_all', $context, $userid);
    }

    /**
     * Can $userid join the given session?
     *
     * Allowed when:
     *   1. Session is in 'live' state, AND
     *   2. Either the user has local/sentientia_live:join, OR
     *      session->settings.allow_anonymous = true AND
     *      live.allow_anonymous master flag is ON.
     */
    public static function can_user_join(?int $userid, int $sessionid): bool {
        $sess = self::get($sessionid);
        if (!$sess || $sess->state !== self::STATE_LIVE) {
            return false;
        }

        $context = \context_system::instance();
        // Logged-in user with capability.
        if ($userid !== null && $userid > 0) {
            if (has_capability('local/sentientia_live:join', $context, $userid)) {
                return true;
            }
        }

        // Anonymous joins, if both per-session AND global flag agree.
        $settings = self::parse_settings($sess);
        if (!empty($settings['allow_anonymous'])) {
            if (class_exists('\\local_airpay_core\\feature_flags')) {
                try {
                    return \local_airpay_core\feature_flags::is_enabled(
                        'live.allow_anonymous');
                } catch (\Throwable $e) {
                    return false;
                }
            }
            return false;
        }
        return false;
    }

    /**
     * Parse the settings_json blob into a typed array. Returns the
     * defaults if the blob is empty / malformed.
     */
    public static function parse_settings(\stdClass $session): array {
        if (empty($session->settings_json)) {
            return self::default_settings();
        }
        $decoded = json_decode($session->settings_json, true);
        if (!is_array($decoded)) {
            return self::default_settings();
        }
        return array_merge(self::default_settings(), $decoded);
    }

    /**
     * The default values for the settings_json blob.
     */
    public static function default_settings(): array {
        return [
            'allow_anonymous'          => false,
            'show_results_to_audience' => true,
            'allow_late_join'          => true,
            'max_concurrent'           => 500,
        ];
    }

    /**
     * Normalise a settings array — strips unknown keys, coerces types,
     * clamps numeric values to their allowed ranges.
     */
    public static function sanitise_settings(array $input): array {
        $defaults = self::default_settings();
        $out = $defaults;
        if (array_key_exists('allow_anonymous', $input)) {
            $out['allow_anonymous'] = (bool) $input['allow_anonymous'];
        }
        if (array_key_exists('show_results_to_audience', $input)) {
            $out['show_results_to_audience'] =
                (bool) $input['show_results_to_audience'];
        }
        if (array_key_exists('allow_late_join', $input)) {
            $out['allow_late_join'] = (bool) $input['allow_late_join'];
        }
        if (array_key_exists('max_concurrent', $input)) {
            // Clamp to [1, 500] per ADR-004 worker-pool limit.
            $n = (int) $input['max_concurrent'];
            $out['max_concurrent'] = max(1, min(500, $n));
        }
        return $out;
    }

    /**
     * Generate a unique 6-digit code that doesn't collide with any
     * currently-live session.
     *
     * @throws \moodle_exception if all attempts fail (extreme
     *                            saturation of the keyspace).
     */
    public static function generate_unique_code(): string {
        global $DB;

        for ($i = 0; $i < self::CODE_GEN_MAX_ATTEMPTS; $i++) {
            // 6-digit zero-padded random.
            $candidate = str_pad((string) random_int(0, 999999),
                self::CODE_LENGTH, '0', STR_PAD_LEFT);
            // Codes starting with 0 are awkward (UX confusion with O);
            // skip them — 100K codes are still plenty.
            if ($candidate[0] === '0') {
                continue;
            }
            $exists = $DB->record_exists('local_sentientia_live_sessions', [
                'code'  => $candidate,
                'state' => self::STATE_LIVE,
            ]);
            if (!$exists) {
                return $candidate;
            }
        }
        throw new \moodle_exception('code_generation_failed',
            'local_sentientia_live');
    }
}
