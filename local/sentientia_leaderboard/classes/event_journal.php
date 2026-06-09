<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Append-only event journal — Phase L.0 (2026-05-24).
 *
 * Per ADR-014 we reuse the PATTERN from local_sentientia_live\event_journal
 * (the canonical Mentimeter SSE substrate) with a per-plugin events table,
 * `local_sentientia_lb_events`.
 *
 * Stream contract: the SSE endpoint (`stream.php`) polls
 *
 *     SELECT * FROM {local_sentientia_lb_events}
 *      WHERE boardid = ? AND id > $last_event_id
 *   ORDER BY id ASC
 *
 * and emits each as `id: N\nevent: type\ndata: {payload}\n\n`.
 *
 * Event types:
 *
 *   leaderboard.recomputed         — payload: {boardid, recomputed_at}
 *   leaderboard.score_changed      — payload: {userid, points_now}
 *   leaderboard.position_changed   — payload: {userid, old_rank, new_rank}
 *
 * Adding a new type? Append to EVENT_TYPES + document its payload shape
 * above. No DB schema change needed — payload is just a JSON text column.
 *
 * Retention: purge_old() runs daily (cron at 03:00). Default keep window
 * = 7 days. Leaderboard events are far less frequent than live-engagement
 * events (one per recompute interval, not one per audience response), so
 * the 7-day window is comfortably affordable.
 *
 * @package local_sentientia_leaderboard
 */
class event_journal {

    public const EVENT_TYPES = [
        'leaderboard.recomputed',
        'leaderboard.score_changed',
        'leaderboard.position_changed',
    ];

    /** Default retention window — purge events this many seconds old. */
    public const DEFAULT_RETENTION_SECONDS = 7 * 86400;

    /** Maximum events to return in one SSE poll cycle. */
    public const POLL_BATCH_MAX = 100;

    /**
     * Append one event to the journal.
     *
     * @param int    $boardid
     * @param string $type    One of EVENT_TYPES.
     * @param array  $payload Any JSON-encodable data.
     * @return int The new event row ID.
     * @throws \moodle_exception on invalid type.
     */
    public static function write(int $boardid, string $type,
                                  array $payload = []): int {
        global $DB;
        if (!in_array($type, self::EVENT_TYPES, true)) {
            throw new \moodle_exception('invalid_event_type',
                'local_sentientia_leaderboard', '', $type);
        }
        $row = new \stdClass();
        $row->boardid      = $boardid;
        $row->type         = $type;
        $row->payload_json = json_encode($payload);
        $row->timecreated  = time();

        return (int) $DB->insert_record('local_sentientia_lb_events', $row);
    }

    /**
     * Read events for a board that are newer than $last_event_id.
     * Returns an array of stdClass rows ordered by id ASC. Capped at
     * POLL_BATCH_MAX rows per call.
     *
     * @param int $boardid
     * @param int $last_event_id Last id the caller already processed.
     * @return array Each row: ['id', 'type', 'payload' (decoded),
     *                          'timecreated']
     */
    public static function read_since(int $boardid,
                                       int $last_event_id = 0): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT id, type, payload_json, timecreated
               FROM {local_sentientia_lb_events}
              WHERE boardid = :bid
                AND id > :last
           ORDER BY id ASC",
            ['bid' => $boardid, 'last' => $last_event_id],
            0, self::POLL_BATCH_MAX
        );
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
     * Get the most-recent event ID for a board.
     */
    public static function latest_event_id(int $boardid): int {
        global $DB;
        $id = $DB->get_field_sql(
            "SELECT MAX(id) FROM {local_sentientia_lb_events}
              WHERE boardid = :bid",
            ['bid' => $boardid]
        );
        return (int) ($id ?? 0);
    }

    /**
     * Count events for a board by type.
     */
    public static function count_by_type(int $boardid, string $type): int {
        global $DB;
        return (int) $DB->count_records('local_sentientia_lb_events', [
            'boardid' => $boardid,
            'type'    => $type,
        ]);
    }

    /**
     * Purge events older than $retention_seconds. Returns the number of
     * rows deleted. Called by the daily cron task.
     *
     * @param int $retention_seconds
     * @return int
     */
    public static function purge_old(int $retention_seconds =
            self::DEFAULT_RETENTION_SECONDS): int {
        global $DB;
        if ($retention_seconds < 0) {
            return 0;
        }
        $cutoff = time() - $retention_seconds;

        $count = (int) $DB->count_records_select(
            'local_sentientia_lb_events',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        if ($count > 0) {
            $DB->delete_records_select(
                'local_sentientia_lb_events',
                'timecreated < :cutoff',
                ['cutoff' => $cutoff]
            );
        }
        return $count;
    }
}
