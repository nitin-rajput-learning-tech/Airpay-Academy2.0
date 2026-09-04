<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Server-Sent Events stream endpoint — Phase E.3 (2026-05-21).
 *
 * Long-lived HTTP connection that holds open and emits events as they
 * land in local_sentientia_live_events for the given session.
 *
 * Two access modes:
 *   1. TRAINER: requires login + can_user_run(session). No token needed.
 *      Identified by ?role=trainer.
 *   2. AUDIENCE: requires either a logged-in user with a participants
 *      row for this session, OR a valid join_token (bearer auth for
 *      anonymous joins). Identified by ?token=X.
 *
 * Wire format (text/event-stream):
 *   id: <event-id>
 *   event: <event-type>
 *   data: <JSON payload>
 *   \n
 *
 * Plus a comment ping every 15 s ("\n: ping <timestamp>\n\n") so that
 * idle connections aren't killed by proxies / firewalls / Cloudflare's
 * 100 s timeout.
 *
 * Per ADR-004: SSE is the default. live.realtime.enabled OFF falls back
 * to short polling (audience play page just re-renders normally).
 *
 * IMPORTANT: this endpoint disables Apache's PHP session lock by calling
 * session_write_close() immediately. Without that, every other PHP
 * request from the same browser session would block until the SSE
 * connection times out (60 s default).
 *
 * H4 remediation (UAT-SECURITY-POSTURE-2026-09-03, 2026-09-04): a stream
 * held open for up to 300 s per connection with no concurrency cap was a
 * trivial volumetric DoS once anonymous audience join is enabled (Apache
 * prefork: ~15 workers on UAT). Two admin settings now bound the blast
 * radius — sse_max_seconds (default 60) caps how long any one connection
 * is held before the client is told to reconnect, and sse_max_connections
 * (default 8) caps total concurrently-open streams; a fixed cap of 2
 * concurrent streams per trainer/participant also applies. Both caps are
 * enforced by \local_sentientia_live\sse_connection_registry BEFORE any
 * SSE bytes are sent — a capacity-exceeded request gets a plain 503 with
 * Retry-After instead of a stream. See that class for the full design.
 *
 * @package local_sentientia_live
 */

// SSE = idle long-lived connection. Take the rope off the Moodle session
// lock NOW before doing anything else, otherwise other requests from
// the same browser block.
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');

\core\session\manager::write_close();

$sessionid     = required_param('sessionid', PARAM_INT);
$role          = optional_param('role', 'audience', PARAM_ALPHA);
$token         = optional_param('token', '', PARAM_ALPHANUMEXT);
$last_event_id = optional_param('lastid', 0, PARAM_INT);

// Master flag — fall through to polling-only if SSE disabled.
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('live.enabled')) {
        http_response_code(403);
        exit('live.enabled flag is off');
    }
    if (!\local_sentientia_platform\feature_flags::is_enabled('live.realtime.enabled')) {
        http_response_code(503);
        header('X-Realtime-Disabled: 1');
        exit('realtime SSE disabled by admin; clients should poll instead');
    }
}

$sess = \local_sentientia_live\session_manager::get($sessionid);
if (!$sess) {
    http_response_code(404);
    exit('session not found');
}

// ── Access control ──
$participantid = null;
if ($role === 'trainer') {
    // Require Moodle login + can_user_run.
    if (!isloggedin() || isguestuser()) {
        http_response_code(401);
        exit('trainer role requires login');
    }
    if (!\local_sentientia_live\session_manager::can_user_run(
            (int) $USER->id, $sessionid)) {
        http_response_code(403);
        exit('not permitted to run this session');
    }
} else {
    // Audience: identify the participant by either token (anon) or
    // logged-in user's participants row.
    if ($token !== '') {
        $participant = \local_sentientia_live\participant_manager::lookup_by_join_token($token);
    } elseif (isloggedin() && !isguestuser()) {
        global $DB;
        $participant = $DB->get_record(
            'local_sentientia_live_participants',
            ['sessionid' => $sessionid, 'userid' => $USER->id]
        );
        if ($participant) {
            $participant->id = (int) $participant->id;
        } else {
            $participant = null;
        }
    } else {
        $participant = null;
    }

    if (!$participant) {
        http_response_code(401);
        exit('not joined to this session');
    }
    if ((int) $participant->sessionid !== $sessionid) {
        http_response_code(403);
        exit('participant does not belong to this session');
    }
    $participantid = (int) $participant->id;
}

// ── H4 remediation: SSE concurrency caps ──
// Enforced BEFORE any SSE header/byte is sent so a capacity-exceeded
// client gets a plain 503 (EventSource treats a non-2xx response as a
// fatal error and stops auto-reconnecting; audience_sse.js and
// trainer_sse.js both fall back to a delayed page reload in that case —
// see those modules) instead of a stream that opens then closes at once.
$ping_interval_seconds = 15;
$sse_max_connections = (int) (get_config('local_sentientia_live', 'sse_max_connections') ?: 0);

// Per-actor identity for the concurrency cap. A trainer stream is always
// authenticated (checked above); an audience stream always resolves to a
// participants row (also checked above, via token OR logged-in userid) —
// which already scopes an anonymous audience member by their per-browser
// join_token. The ip: fallback is defensive/documented only — the access
// control above means it should be unreachable in practice.
if ($role === 'trainer') {
    $sse_actor_key = 'u:' . (int) $USER->id;
    $sse_registry_userid = (int) $USER->id;
} else if ($participantid !== null) {
    $sse_actor_key = 'p:' . $participantid;
    $sse_registry_userid = (isloggedin() && !isguestuser()) ? (int) $USER->id : null;
} else {
    $sse_actor_key = 'ip:' . (getremoteaddr() ?: 'unknown');
    $sse_registry_userid = null;
}

$sse_connection = \local_sentientia_live\sse_connection_registry::acquire(
    $sessionid, $sse_registry_userid, $sse_actor_key,
    $sse_max_connections, $ping_interval_seconds);

if (!$sse_connection->ok) {
    http_response_code(503);
    header('Retry-After: 5');
    exit($sse_connection->reason === 'peractor'
        ? 'too many concurrent streams for this participant/trainer; retry shortly'
        : 'sse capacity reached; client should retry shortly or fall back to polling');
}
$sse_connection_id = $sse_connection->id;

// Release the slot on the way out, whatever the exit path — clean
// disconnect, wall-clock rotation, session end, or an uncaught error.
// The loop's connection_aborted() check breaks immediately on abort, so
// this fires (and frees the slot) within the same ~1 s poll tick.
// sse_connection_registry::prune() is the backstop for the abnormal case
// where a shutdown function never runs (an Apache worker killed outright).
register_shutdown_function(static function () use ($sse_connection_id): void {
    \local_sentientia_live\sse_connection_registry::release($sse_connection_id);
});

// ── SSE headers ──
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Connection: keep-alive');
// nginx + some other proxies buffer response bodies by default —
// explicitly disable so events flush to the browser immediately.
header('X-Accel-Buffering: no');

// Disable PHP-level output buffering so each echo flushes.
@ob_implicit_flush(true);
while (ob_get_level() > 0) {
    @ob_end_flush();
}

// Hint to the browser about reconnect delay.
echo "retry: 3000\n\n";
@flush();

// If client provided Last-Event-ID via header (browser auto-reconnect),
// honour it. Otherwise use ?lastid param.
$header_last = $_SERVER['HTTP_LAST_EVENT_ID'] ?? null;
if ($header_last !== null && ctype_digit($header_last)) {
    $last_event_id = (int) $header_last;
}

// Emit an initial sync event so clients know they're connected. This
// also acts as a "ready for the rest" signal.
$payload = [
    'sessionid'      => $sessionid,
    'state'          => $sess->state,
    'current_slide'  => (int) ($sess->current_slide_id ?? 0),
    'audience_count' => \local_sentientia_live\participant_manager::active_count_for_session($sessionid),
    'now'            => time(),
];
echo "event: sync\n";
echo "data: " . json_encode($payload) . "\n\n";
@flush();

// Max wall-clock duration we'll hold this connection open before
// asking the client to reconnect. H4 remediation: this used to be a
// hardcoded 300 s (5 min); it's now the sse_max_seconds admin setting
// (default 60 s) so an operator can tighten it further without a code
// change. Browsers auto-reconnect via EventSource (retry: line above).
$max_duration_seconds = (int) (get_config('local_sentientia_live', 'sse_max_seconds') ?: 0);
if ($max_duration_seconds <= 0) {
    $max_duration_seconds = 60;
}
$started = time();

// Ping interval — line we emit so intermediaries don't kill idle.
// $ping_interval_seconds is declared earlier (H4 concurrency-cap block)
// so it can also size the registry's staleness window.
$last_ping = time();

// Track if we've already emitted a session_ended event so we don't
// keep streaming after the trainer ends.
$session_ended_sent = false;

// ── Main polling loop ──
while (true) {
    // Have we exceeded our wall-clock budget? Tell the client to
    // reconnect (it will, automatically).
    if (time() - $started > $max_duration_seconds) {
        echo "event: reconnect\n";
        echo "data: " . json_encode(['reason' => 'rotate']) . "\n\n";
        @flush();
        break;
    }

    // Client gone?
    if (connection_aborted()) {
        // For audience: stamp them as left so trainer's count drops.
        if ($participantid !== null) {
            \local_sentientia_live\participant_manager::mark_left($participantid);
        }
        break;
    }

    // Read any new events since last_event_id.
    $events = \local_sentientia_live\event_journal::read_since(
        $sessionid, $last_event_id);

    foreach ($events as $e) {
        echo "id: " . $e->id . "\n";
        echo "event: " . $e->type . "\n";
        echo "data: " . json_encode($e->payload) . "\n\n";
        $last_event_id = $e->id;

        if ($e->type === 'session_ended') {
            $session_ended_sent = true;
        }
    }
    @flush();

    if ($session_ended_sent) {
        // Hold for one more second so the client receives + processes
        // the session_ended event, then close.
        sleep(1);
        break;
    }

    // Audience: bump presence so trainer's audience-count stays fresh.
    if ($participantid !== null) {
        \local_sentientia_live\participant_manager::heartbeat($participantid);
    }

    // Comment-line ping so idle connections aren't killed by middle boxes.
    // Also refresh this connection's registry heartbeat on the same
    // cadence — cheap enough at ~15s and keeps prune()'s staleness
    // window accurate without a write on every 1s loop tick.
    if (time() - $last_ping >= $ping_interval_seconds) {
        echo ": ping " . time() . "\n\n";
        @flush();
        $last_ping = time();
        \local_sentientia_live\sse_connection_registry::heartbeat($sse_connection_id);
    }

    // Sleep 1 s between polls. Sub-second responsiveness isn't worth
    // the DB load — Mentimeter's perceived latency is similar.
    sleep(1);
}

exit;
