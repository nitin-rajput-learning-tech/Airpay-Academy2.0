<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Append-only event journal — Phase E.1.b (2026-05-21).
 *
 * The journal is the substrate for ALL realtime communication in
 * sentientia_live. Every state change that audiences or the trainer
 * should see writes ONE row. The Phase E.3 SSE stream loop just polls:
 *
 *     SELECT * FROM {local_sentientia_live_events}
 *      WHERE sessionid = ? AND id > $last_event_id
 *   ORDER BY id ASC
 *
 * and emits each as `id: N\ndata: {payload}\n\n`.
 *
 * Event types (registered + documented here — clients dispatch by name):
 *
 *   session_started     — payload: {started_at: int}
 *   session_ended       — payload: {ended_at: int, final_results?: {...}}
 *   slide_changed       — payload: {slide_id: int, changed_at: int}
 *   response_added      — payload: {slide_id: int, count_now: int}
 *   participant_joined  — payload: {participant_id: int, count_now: int}
 *   participant_left    — payload: {participant_id: int, count_now: int}
 *
 * Adding a new type? Add a case to the EVENT_TYPES constant + document
 * its payload shape above. No DB schema change needed — payload is just
 * a JSON text column.
 *
 * Retention: purge_old() runs daily (cron at 02:00). Default keep
 * window = 24h after the session ended. While the session is still
 * in 'live' state we never purge — caps event loss during a long run.
 *
 * @package local_sentientia_live
 */
class event_journal {

    public const EVENT_TYPES = [
        'session_started',
        'session_ended',
        'slide_changed',
        'response_added',
        'participant_joined',
        'participant_left',
    ];

    /** Default retention window — purge events this many seconds after
     *  the parent session ended. */
    public const DEFAULT_RETENTION_SECONDS = 86400;

    /** Maximum events to return in one SSE poll cycle (prevents one
     *  stream tying up the worker on a massive backlog). */
    public const POLL_BATCH_MAX = 100;

    /**
     * Append one event to the journal.
     *
     * @param int    $sessionid
     * @param string $type    One of EVENT_TYPES.
     * @param array  $payload Any JSON-encodable data.
     * @return int The new event row ID (also the SSE event ID).
     * @throws \moodle_exception on invalid type.
     */
    public static function write(int $sessionid, string $type,
                                  array $payload = []): int {
        global $DB;
        if (!in_array($type, self::EVENT_TYPES, true)) {
            throw new \moodle_exception('invalid_event_type',
                'local_sentientia_live', '', $type);
        }
        $row = new \stdClass();
        $row->sessionid    = $sessionid;
        $row->type         = $type;
        $row->payload_json = json_encode($payload);
        $row->timecreated  = time();

        return (int) $DB->insert_record('local_sentientia_live_events', $row);
    }

    /**
     * Read events for a session that are newer than $last_event_id.
     * Returns an array of stdClass rows ordered by id ASC. Capped at
     * POLL_BATCH_MAX rows per call.
     *
     * @param int $sessionid
     * @param int $last_event_id Last id the caller already processed.
     * @return array Each row: ['id', 'type', 'payload_json' (decoded),
     *                          'timecreated']
     */
    public static function read_since(int $sessionid,
                                       int $last_event_id = 0): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT id, type, payload_json, timecreated
               FROM {local_sentientia_live_events}
              WHERE sessionid = :sid
                AND id > :last
           ORDER BY id ASC",
            ['sid' => $sessionid, 'last' => $last_event_id],
            0, self::POLL_BATCH_MAX
        );
        // Decode payloads before returning.
        $out = [];
        foreach ($rows as $r) {
            $out[] = (object) [
                'id'          => (int) $r->id,
                'type'        => $r->type,
                'payload'     => $r->payload_json !== null
                    ? json_decode($r->payload_json, true)
                    : [],
                'timecreated' => (int) $r->timecreated,
            ];
        }
        return $out;
    }

    /**
     * Get the most-recent event ID for a session — useful for the SSE
     * endpoint to send as the initial `id:` so reconnections pick up
     * from where the client left off.
     */
    public static function latest_event_id(int $sessionid): int {
        global $DB;
        $id = $DB->get_field_sql(
            "SELECT MAX(id) FROM {local_sentientia_live_events}
              WHERE sessionid = :sid",
            ['sid' => $sessionid]
        );
        return (int) ($id ?? 0);
    }

    /**
     * Count events for a session by type. Cheap helper for trainer
     * dashboards ("47 responses received").
     */
    public static function count_by_type(int $sessionid, string $type): int {
        global $DB;
        return (int) $DB->count_records('local_sentientia_live_events', [
            'sessionid' => $sessionid,
            'type'      => $type,
        ]);
    }

    /**
     * Purge events for sessions that ended more than $retention_seconds
     * ago. Called by a daily cron. Returns the number of rows deleted.
     *
     * Sessions still in 'live' state are NEVER purged — caps event loss
     * during long-running sessions.
     */
    public static function purge_old(int $retention_seconds =
            self::DEFAULT_RETENTION_SECONDS): int {
        global $DB;
        if ($retention_seconds < 0) {
            return 0;
        }
        $cutoff = time() - $retention_seconds;

        // Find ended sessions whose end-time crossed the retention cutoff.
        $stale_session_ids = $DB->get_fieldset_select(
            'local_sentientia_live_sessions', 'id',
            "state = :ended AND timeended IS NOT NULL AND timeended < :cutoff",
            ['ended' => session_manager::STATE_ENDED, 'cutoff' => $cutoff]
        );

        if (empty($stale_session_ids)) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($stale_session_ids,
            SQL_PARAMS_NAMED);
        $count = $DB->count_records_select('local_sentientia_live_events',
            "sessionid $insql", $params);
        if ($count > 0) {
            $DB->delete_records_select('local_sentientia_live_events',
                "sessionid $insql", $params);
        }
        return $count;
    }
}
