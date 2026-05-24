<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Outbound ICS feed endpoint — Tier 2.6 Phase 1.
 *
 * Authentication is via the `token` query parameter, NOT via the
 * Moodle session — Outlook / Google / Apple Calendar fetch this URL
 * from their own background workers and have no Moodle session cookie.
 *
 * Security guarantees:
 *   - Token is 64-char URL-safe random (~381 bits of entropy)
 *   - Token lookup short-circuits on malformed input (length / charset)
 *     before any DB work
 *   - Master feature flag must be ON
 *   - Returns 404 (not 401/403) for invalid / revoked / unknown tokens
 *     — denies an attacker the ability to enumerate user existence
 *   - Cache-Control: no-store — calendar clients should re-fetch each
 *     time and the response varies per-user
 *   - No cookie set — this endpoint MUST NOT establish a session for
 *     the token bearer (the token authenticates THIS request only)
 *
 * Response: text/calendar (UTF-8) with a Sentientia LMS feed body.
 *
 * @package local_sentientia_calendar
 */

// CRITICAL: we must NOT call require_login() here. Calendar clients
// fetch this URL without browser cookies; the token is the auth.

// NO_MOODLE_COOKIES tells Moodle's bootstrap to skip session setup —
// faster, and prevents the calendar client's HTTP fetch from being
// counted as a "user login" for the bearer.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

global $CFG, $DB;

// Suppress PHP errors leaking into the body — clients parsing
// text/calendar will choke on injected HTML.
@ini_set('display_errors', '0');

$token = optional_param('token', '', PARAM_ALPHANUM);

// Master feature flag — refuse to serve even with a valid token when off.
if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('sentientia.calendar_sync.enabled')) {
        send_404();
    }
}

// Empty token → 404 immediately (no DB hit, no time-leak).
if ($token === '') {
    send_404();
}

$row = \local_sentientia_calendar\token_manager::find_active_token($token);
if ($row === null) {
    send_404();
}

// Load the user. Defensive — token might point at a deleted user.
$user = $DB->get_record('user', ['id' => $row->userid]);
if ($user === false || $user->deleted || $user->suspended) {
    send_404();
}

// Build the feed. Wrap in try/catch so a query exception still produces
// a clean 500 (without leaking the SQL in the body).
try {
    $body = \local_sentientia_calendar\ics_builder::build_for_user((int) $user->id);
} catch (\Throwable $e) {
    debugging('local_sentientia_calendar ics.php: build failed: ' . $e->getMessage(),
        DEBUG_DEVELOPER);
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Failed to generate calendar feed. Please try again later.\n";
    exit;
}

// Audit-trail update — best effort, never blocks the response.
$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
// Sanitise — only pass IPv4/IPv6 shapes through to the audit field.
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = '';
}
\local_sentientia_calendar\token_manager::mark_used((int) $row->id, $ip);

// ─── Serve the response ─────────────────────────────────────────────
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="sentientia-calendar.ics"');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
echo $body;
exit;

// ────────────────────────────────────────────────────────────────────

/**
 * Return a 404 and exit. Used for every authentication failure mode so
 * the response is indistinguishable between "token doesn't exist" and
 * "feature flag off" and "user suspended".
 */
function send_404(): void {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "Calendar feed not found.\n";
    exit;
}
