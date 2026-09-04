<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Open SSE connection registry — H4 remediation (2026-09-04).
 *
 * See moodle-enhancement/docs/security/UAT-SECURITY-POSTURE-2026-09-03.md,
 * finding H4: stream.php held an Apache/PHP worker open for up to 300s per
 * connection with no per-user or global concurrency cap. Once anonymous
 * audience join is enabled (live.allow_anonymous), that endpoint is
 * reachable pre-auth (token-gated, but the practical risk is volumetric
 * flooding, not guessing the token) and a modest number of concurrent
 * connections can exhaust the Apache worker pool (prefork: about 15
 * workers on UAT).
 *
 * This table backs two caps enforced by stream.php BEFORE it sends any
 * SSE bytes:
 *   - a global cap (setting sse_max_connections, default
 *     DEFAULT_MAX_CONNECTIONS) on how many rows may exist at once, and
 *   - a per-actor cap of MAX_PER_ACTOR (2) concurrent streams.
 *
 * Column note — sid is NOT the PHP/Moodle session id. stream.php calls
 * core\session\manager::write_close() before touching this table (and
 * before anything else) precisely so a long-lived stream never holds the
 * session lock. sid is the per-actor identity key used for the
 * concurrency cap:
 *   - trainer stream:          u: + userid (always authenticated)
 *   - audience stream:         p: + participantid (this already scopes
 *     an anonymous audience member by their per-browser join_token,
 *     since that token is how anonymous joins are re-identified — see
 *     participant_manager::lookup_by_join_token())
 *   - documented last resort:  ip: + remote address, for any future
 *     caller that reaches the registry without a resolved trainer or
 *     participant identity. Unreachable via the current stream.php
 *     access control (which always resolves one of the two cases above
 *     or exits with 401/403 first) — kept only as a defensive fallback.
 *
 * Row lifecycle: acquire() inserts a row before the stream starts.
 * heartbeat() refreshes timeheartbeat on the same cadence as the SSE
 * ping (about 15s). release() deletes the row when the stream ends by
 * any path (clean close, wall-clock rotation, session end). prune() is
 * the backstop for the abnormal case where release() never runs (an
 * Apache worker killed outright, a PHP fatal error) — it sweeps rows
 * whose timeheartbeat is older than 2x the heartbeat interval.
 *
 * Concurrency note: acquire() is a best-effort / soft cap. The count-
 * then-insert is not wrapped in row-level locking, so under true
 * concurrent load two requests can both pass the count check before
 * either inserts, allowing a small, bounded overshoot. That is an
 * acceptable trade-off for a DoS-mitigation control (the goal is to
 * bound worker exhaustion, not to enforce an exact limit) and it avoids
 * adding lock contention to a path every SSE connection executes.
 *
 * @package local_sentientia_live
 */
class sse_connection_registry {

    /** Global concurrency cap used when the sse_max_connections admin
     *  setting is unset or invalid. Matches settings.php default. */
    public const DEFAULT_MAX_CONNECTIONS = 8;

    /** Per-actor (per trainer / per audience participant) concurrent
     *  stream cap. Fixed — not exposed as an admin setting. */
    public const MAX_PER_ACTOR = 2;

    /** Rows whose timeheartbeat is older than this multiple of the
     *  caller heartbeat interval are considered abandoned and pruned. */
    public const STALE_HEARTBEAT_MULTIPLIER = 2;

    /**
     * Attempt to reserve one SSE connection slot.
     *
     * Prunes stale rows first, then checks the global cap and the
     * per-actor cap, and if both pass, inserts a row and returns it.
     *
     * @param int         $sessionid                  FK to
     *                    local_sentientia_live_sessions.id.
     * @param int|null    $userid                     FK to user.id, or
     *                    null for an anonymous audience connection.
     * @param string      $sid                        Per-actor identity
     *                    key — see class docblock (u:, p:, ip:).
     * @param int         $max_connections            Global cap. A value
     *                    <= 0 falls back to DEFAULT_MAX_CONNECTIONS.
     * @param int         $heartbeat_interval_seconds Caller heartbeat
     *                    cadence, used to size the staleness window for
     *                    the pre-check prune() sweep.
     * @return \stdClass {ok: bool, id: int|null,
     *                    reason: string|null (global|peractor)}
     */
    public static function acquire(int $sessionid, ?int $userid, string $sid,
            int $max_connections, int $heartbeat_interval_seconds = 15): \stdClass {
        global $DB;

        if ($max_connections <= 0) {
            $max_connections = self::DEFAULT_MAX_CONNECTIONS;
        }

        self::prune($heartbeat_interval_seconds);

        $total = $DB->count_records('local_sentientia_live_sse');
        if ($total >= $max_connections) {
            return (object) ['ok' => false, 'id' => null, 'reason' => 'global'];
        }

        $for_actor = $DB->count_records('local_sentientia_live_sse', ['sid' => $sid]);
        if ($for_actor >= self::MAX_PER_ACTOR) {
            return (object) ['ok' => false, 'id' => null, 'reason' => 'peractor'];
        }

        $row = new \stdClass();
        $row->sessionid     = $sessionid;
        $row->userid        = $userid;
        $row->sid           = $sid;
        $row->timecreated   = time();
        $row->timeheartbeat = time();
        $id = (int) $DB->insert_record('local_sentientia_live_sse', $row);

        return (object) ['ok' => true, 'id' => $id, 'reason' => null];
    }

    /**
     * Refresh the heartbeat timestamp for one open connection. Called on
     * the same ~15s cadence as the SSE ping line so a live stream never
     * looks stale to prune().
     */
    public static function heartbeat(int $id): void {
        global $DB;
        if ($id <= 0) {
            return;
        }
        $DB->set_field('local_sentientia_live_sse', 'timeheartbeat', time(), ['id' => $id]);
    }

    /**
     * Release one connection slot. Safe to call more than once for the
     * same id — a second delete simply matches zero rows.
     */
    public static function release(int $id): void {
        global $DB;
        if ($id <= 0) {
            return;
        }
        $DB->delete_records('local_sentientia_live_sse', ['id' => $id]);
    }

    /**
     * Delete rows whose timeheartbeat is older than
     * STALE_HEARTBEAT_MULTIPLIER x $heartbeat_interval_seconds. Backstop
     * for connections whose release() never ran (worker killed, fatal
     * error). Returns the number of rows deleted.
     */
    public static function prune(int $heartbeat_interval_seconds): int {
        global $DB;
        $interval = max(1, $heartbeat_interval_seconds);
        $cutoff = time() - ($interval * self::STALE_HEARTBEAT_MULTIPLIER);

        $count = $DB->count_records_select('local_sentientia_live_sse',
            'timeheartbeat < :cutoff', ['cutoff' => $cutoff]);
        if ($count > 0) {
            $DB->delete_records_select('local_sentientia_live_sse',
                'timeheartbeat < :cutoff', ['cutoff' => $cutoff]);
        }
        return $count;
    }

    /** Total open connections across all sessions. */
    public static function count_active(): int {
        global $DB;
        return (int) $DB->count_records('local_sentientia_live_sse');
    }

    /** Open connections for one actor key (see class docblock). */
    public static function count_active_for_actor(string $sid): int {
        global $DB;
        return (int) $DB->count_records('local_sentientia_live_sse', ['sid' => $sid]);
    }
}
