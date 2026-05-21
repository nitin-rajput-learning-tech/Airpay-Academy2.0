<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Participant CRUD + presence tracking — Phase E.1.e (2026-05-21).
 *
 * Manages the (session × audience-member) relationship. Two modes:
 *
 *   Logged-in: user has a Moodle session. join_or_resume looks up
 *              the existing participants row by (sessionid, userid)
 *              and refreshes timelastseen, OR creates a new row if
 *              this is their first time joining this session. The
 *              join_token is still generated (used as bearer for
 *              websocket-style auth from a mobile app or the SSE
 *              EventSource where you can't easily forward cookies).
 *
 *   Anonymous: user has no Moodle session. They submit a display
 *              name; we mint a new participants row with userid=NULL
 *              and a fresh join_token. They keep that token in
 *              localStorage so reconnects find the same row.
 *
 * Presence:
 *   - heartbeat(participantid) is called every ~10s from the SSE
 *     stream loop AND every time a response is POSTed.
 *   - active_count_for_session returns the count of participants
 *     with timelastseen > now - PRESENCE_WINDOW (60s default).
 *
 * @package local_sentientia_live
 */
class participant_manager {

    /** Considered "online" if heard from in the last N seconds. */
    public const PRESENCE_WINDOW = 60;

    /** Join token length (40 hex chars = 160 bits — collision-safe). */
    public const TOKEN_LENGTH = 40;

    /**
     * Join (or resume an existing) participation. Idempotent for
     * logged-in users — calling repeatedly with the same userid
     * returns the same participant row.
     *
     * @param int       $sessionid
     * @param int|null  $userid       Null for anonymous participants.
     * @param string    $display_name Required. Trimmed + capped at 80 chars.
     * @return \stdClass Row from local_sentientia_live_participants.
     * @throws \moodle_exception If session doesn't exist OR display name empty.
     */
    public static function join_or_resume(int $sessionid, ?int $userid,
                                           string $display_name): \stdClass {
        global $DB;

        $sess = session_manager::get($sessionid);
        if (!$sess) {
            throw new \moodle_exception('invalidsession', 'local_sentientia_live');
        }

        $display_name = trim($display_name);
        if ($display_name === '') {
            throw new \moodle_exception('displayname_required',
                'local_sentientia_live');
        }
        $display_name = mb_substr($display_name, 0, 80);

        $now = time();

        // For logged-in users — look up existing row by (sessionid, userid).
        if ($userid !== null && $userid > 0) {
            $existing = $DB->get_record('local_sentientia_live_participants', [
                'sessionid' => $sessionid,
                'userid'    => $userid,
            ]);
            if ($existing) {
                // Refresh display name + presence. Keep the existing token.
                $DB->update_record('local_sentientia_live_participants',
                    (object) [
                        'id'           => $existing->id,
                        'display_name' => $display_name,
                        'timelastseen' => $now,
                    ]);
                // Cast id to int — DB drivers return strings for integer
                // columns; the insert-record branch below uses int via
                // (int)insert_record(). Keep both return-shapes identical.
                $existing->id = (int) $existing->id;
                $existing->display_name = $display_name;
                $existing->timelastseen = $now;
                return $existing;
            }
        }

        // First-time join (or anonymous).
        $token = self::mint_token();
        $row = new \stdClass();
        $row->sessionid    = $sessionid;
        $row->userid       = $userid > 0 ? $userid : null;
        $row->display_name = $display_name;
        $row->join_token   = $token;
        $row->timejoined   = $now;
        $row->timelastseen = $now;

        $row->id = (int) $DB->insert_record(
            'local_sentientia_live_participants', $row);

        // Emit participant_joined event + updated audience count for SSE
        // listeners (trainer's audience counter, primarily).
        $count = self::active_count_for_session($sessionid);
        event_journal::write($sessionid, 'participant_joined', [
            'participant_id' => $row->id,
            'count_now'      => $count,
        ]);

        return $row;
    }

    /**
     * Look up a participant by their join_token. Used by the response-
     * submission endpoint when anonymous clients POST.
     */
    public static function lookup_by_join_token(string $token): ?\stdClass {
        global $DB;
        if (strlen($token) !== self::TOKEN_LENGTH) {
            return null;
        }
        if (!ctype_xdigit($token)) {
            return null;
        }
        $row = $DB->get_record('local_sentientia_live_participants',
            ['join_token' => $token]);
        if (!$row) {
            return null;
        }
        $row->id = (int) $row->id;
        return $row;
    }

    /**
     * Bump timelastseen for one participant. Called by the SSE loop and
     * after every response POST.
     */
    public static function heartbeat(int $participantid): void {
        global $DB;
        if ($participantid <= 0) {
            return;
        }
        $DB->set_field('local_sentientia_live_participants',
            'timelastseen', time(), ['id' => $participantid]);
    }

    /**
     * Count of participants seen within PRESENCE_WINDOW seconds.
     */
    public static function active_count_for_session(int $sessionid): int {
        global $DB;
        $cutoff = time() - self::PRESENCE_WINDOW;
        return (int) $DB->count_records_select(
            'local_sentientia_live_participants',
            'sessionid = :sid AND timelastseen > :cutoff',
            ['sid' => $sessionid, 'cutoff' => $cutoff]
        );
    }

    /**
     * Count of ALL participants ever joined (online + offline).
     */
    public static function total_count_for_session(int $sessionid): int {
        global $DB;
        return (int) $DB->count_records(
            'local_sentientia_live_participants',
            ['sessionid' => $sessionid]
        );
    }

    /**
     * List all participants for a session — used by the trainer's
     * audience panel.
     *
     * @return array stdClass rows ordered by join time.
     */
    public static function list_for_session(int $sessionid): array {
        global $DB;
        return $DB->get_records('local_sentientia_live_participants',
            ['sessionid' => $sessionid],
            'timejoined ASC');
    }

    /**
     * Mark a participant as left — used when SSE detects connection
     * abort. Emits participant_left event for trainer-side audience UI.
     *
     * We don't delete the row — historical data (their responses) stays
     * linked. We just stamp them as gone.
     */
    public static function mark_left(int $participantid): void {
        global $DB;
        $row = $DB->get_record('local_sentientia_live_participants',
            ['id' => $participantid]);
        if (!$row) {
            return;
        }
        // Backdate timelastseen so active_count_for_session excludes them.
        $DB->set_field('local_sentientia_live_participants',
            'timelastseen', 0, ['id' => $participantid]);
        $count = self::active_count_for_session((int) $row->sessionid);
        event_journal::write((int) $row->sessionid, 'participant_left', [
            'participant_id' => $participantid,
            'count_now'      => $count,
        ]);
    }

    /**
     * Generate a fresh join_token. 40 hex chars from
     * cryptographically-safe random bytes.
     */
    private static function mint_token(): string {
        // 20 random bytes = 40 hex chars.
        return bin2hex(random_bytes(20));
    }
}
