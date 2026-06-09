<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Server-Sent Events stream endpoint — Phase L.0 (2026-05-24).
 *
 * Long-lived HTTP connection that holds open and emits events as they
 * land in local_sentientia_lb_events for the given board.
 *
 * Adapted from local/sentientia_live/stream.php (ADR-014: reuse the
 * pattern, not the table). The lifecycle, headers, and polling loop are
 * identical — only the events table + access-control logic differs.
 *
 * Access control: require_login() + capability check + tenant check.
 * Unlike sentientia_live (which supports anonymous join tokens for
 * audience joins), leaderboards require a logged-in user — no
 * anonymous viewers, no token bearer auth.
 *
 * Per ADR-014: SSE is the default. sentientia.leaderboards.realtime.enabled
 * OFF falls back to polling (block widget re-fetches every 30 s).
 *
 * @package local_sentientia_leaderboard
 */

define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');

// Take Moodle's session lock off NOW so other requests in the same
// browser don't block on this SSE worker.
\core\session\manager::write_close();

$boardid       = required_param('boardid', PARAM_INT);
$last_event_id = optional_param('lastid', 0, PARAM_INT);

// Master flag — fall through to polling-only if the realtime layer is off.
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.leaderboards.enabled')) {
        http_response_code(403);
        exit('sentientia.leaderboards.enabled flag is off');
    }
    if (!\local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.leaderboards.realtime.enabled')) {
        http_response_code(503);
        header('X-Realtime-Disabled: 1');
        exit('realtime SSE disabled by admin; clients should poll instead');
    }
}

// Auth gates.
require_login(null, false);
if (isguestuser()) {
    http_response_code(401);
    exit('guest cannot subscribe to leaderboard events');
}

$context = context_system::instance();
require_capability('local/sentientia_leaderboard:view', $context);

// Load + validate the board.
$board = \local_sentientia_leaderboard\board_manager::get($boardid);
if (!$board) {
    http_response_code(404);
    exit('board not found');
}
if ($board->status !== \local_sentientia_leaderboard\board_manager::STATUS_ACTIVE) {
    http_response_code(403);
    exit('board not active');
}

// Tenant access — site admin + :viewall pass; everyone else must be in
// the board's tenant OR the board must be customer-wide (tenantid=0).
$can_view_all = has_capability('local/sentientia_leaderboard:viewall', $context);
if (!$can_view_all && (int) $board->tenantid > 0) {
    $viewer_root = \local_sentientia_platform\tenant::root_for_current_user();
    if ($viewer_root !== (int) $board->tenantid) {
        http_response_code(403);
        exit('out of tenant');
    }
}

// ── SSE headers ──
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

@ob_implicit_flush(true);
while (ob_get_level() > 0) {
    @ob_end_flush();
}

echo "retry: 3000\n\n";
@flush();

// Honour Last-Event-ID from the auto-reconnect header.
$header_last = $_SERVER['HTTP_LAST_EVENT_ID'] ?? null;
if ($header_last !== null && ctype_digit($header_last)) {
    $last_event_id = (int) $header_last;
}

// Emit an initial sync event with the board's current last_recomputed
// timestamp so the client knows whether its cached data is stale.
$payload = [
    'boardid'         => (int) $boardid,
    'last_recomputed' => (int) $board->last_recomputed,
    'now'             => time(),
];
echo "event: sync\n";
echo "data: " . json_encode($payload) . "\n\n";
@flush();

// Wall-clock budget: 5 minutes. Browsers auto-reconnect.
$max_duration_seconds = 300;
$started = time();
$ping_interval_seconds = 15;
$last_ping = time();

// ── Main loop ──
while (true) {
    if (time() - $started > $max_duration_seconds) {
        echo "event: reconnect\n";
        echo "data: " . json_encode(['reason' => 'rotate']) . "\n\n";
        @flush();
        break;
    }

    if (connection_aborted()) {
        break;
    }

    $events = \local_sentientia_leaderboard\event_journal::read_since(
        $boardid, $last_event_id);

    foreach ($events as $e) {
        echo "id: " . $e->id . "\n";
        echo "event: " . $e->type . "\n";
        echo "data: " . json_encode($e->payload) . "\n\n";
        $last_event_id = $e->id;
    }
    @flush();

    if (time() - $last_ping >= $ping_interval_seconds) {
        echo ": ping " . time() . "\n\n";
        @flush();
        $last_ping = time();
    }

    sleep(1);
}

exit;
