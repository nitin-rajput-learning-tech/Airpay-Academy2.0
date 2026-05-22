<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Phase D.1 endpoint smoke test — HTTP-level verification of the
 * three new endpoints (manifest, sw, offline) plus the per-customer
 * icon assets. Exercises Apache's header passthrough — critical
 * because `Service-Worker-Allowed: /` only takes effect if Apache
 * forwards it (some hosts strip).
 *
 * Usage:
 *   php local/sentientia_pwa/cli/verify_d1_endpoints.php
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

global $CFG;

// CRITICAL: release Moodle's session lock BEFORE any HTTP fetch back
// to this same install. Otherwise the curl request (which Apache
// also serves via Moodle) blocks waiting for the lock this CLI
// process is holding — classic file-session deadlock.
\core\session\manager::write_close();

$pass = 0;
$fail = 0;

function expect_true(bool $got, string $desc) {
    global $pass, $fail;
    if ($got) { echo "  PASS — $desc\n"; $pass++; }
    else      { echo "  FAIL — $desc\n"; $fail++; }
}

function http_fetch(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Sentientia-D1-Smoke/1.0',
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers_raw = substr($resp, 0, $header_size);
    $body        = substr($resp, $header_size);

    $headers = [];
    foreach (explode("\r\n", $headers_raw) as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

$base = rtrim($CFG->wwwroot, '/');

// ── 1. manifest.php ──
echo "1. /local/sentientia_pwa/manifest.php\n";
$r = http_fetch("$base/local/sentientia_pwa/manifest.php");
expect_true($r['status'] === 200,
    'HTTP 200');
expect_true(($r['headers']['content-type'] ?? '')
    === 'application/manifest+json; charset=UTF-8',
    'Content-Type is application/manifest+json');
$json = json_decode($r['body'], true);
expect_true(is_array($json),
    'Body parses as JSON');
expect_true(!empty($json['name']),
    'JSON has `name` field');
expect_true(!empty($json['icons']) && count($json['icons']) >= 2,
    'JSON has >=2 icons');
expect_true(!empty($json['start_url']),
    'JSON has `start_url`');
expect_true(!empty($json['theme_color']),
    'JSON has `theme_color`');

// ── 2. sw.php ──
echo "\n2. /local/sentientia_pwa/sw.php\n";
$r = http_fetch("$base/local/sentientia_pwa/sw.php");
expect_true($r['status'] === 200,
    'HTTP 200');
expect_true(str_starts_with(($r['headers']['content-type'] ?? ''),
    'application/javascript'),
    'Content-Type is application/javascript');
expect_true(($r['headers']['service-worker-allowed'] ?? '') === '/',
    'Service-Worker-Allowed: / header present (root-scope registration)');
expect_true(str_contains($r['body'], 'CACHE_NAME'),
    'Body contains CACHE_NAME constant');
expect_true(str_contains($r['body'], 'OFFLINE_URL'),
    'Body contains OFFLINE_URL constant');
expect_true(str_contains($r['body'], 'sentientia-pwa-v2'),
    'Body has v2 cache key (D.1.d bump)');
expect_true(str_contains($r['body'], "offline.html"),
    'Body references offline.html (not inline-HTML fallback)');
expect_true(str_contains($r['body'], 'CACHE_FIRST_EXT'),
    'Body has CACHE_FIRST_EXT static-asset cache-first list');
expect_true(str_contains($r['body'], '/local/sentientia_live/stream.php'),
    'SSE stream.php in bypass list');
expect_true(str_contains($r['body'], '/lib/ajax/'),
    'Moodle AJAX bypass present');

// ── 3. offline.html ──
echo "\n3. /local/sentientia_pwa/offline.html\n";
$r = http_fetch("$base/local/sentientia_pwa/offline.html");
expect_true($r['status'] === 200,
    'HTTP 200');
expect_true(str_starts_with(($r['headers']['content-type'] ?? ''), 'text/html'),
    'Content-Type is text/html');
expect_true(str_contains($r['body'], 'theme-color'),
    'HTML has theme-color meta');
expect_true(str_contains($r['body'], '#0066A7'),
    'HTML has Airpay-blue brand color');
expect_true(stripos($r['body'], 'offline') !== false,
    'HTML contains "offline" copy');
expect_true(str_contains($r['body'], 'window.location.reload'),
    'HTML has auto-retry JS on online event');
expect_true(str_contains($r['body'], '@media (max-width: 590px)'),
    'HTML has @media (max-width: 590px) mobile responsive rule');

// ── 4. icon-192.png + icon-512.png ──
echo "\n4. Per-customer icons\n";
$r = http_fetch("$base/local/airpay_core/pix/customer/1/icon-192.png");
expect_true($r['status'] === 200,
    '192x192 icon HTTP 200');
expect_true(str_starts_with($r['body'], "\x89PNG"),
    '192x192 starts with PNG magic bytes');
expect_true(strlen($r['body']) > 500,
    '192x192 has reasonable file size (>500 bytes)');

$r = http_fetch("$base/local/airpay_core/pix/customer/1/icon-512.png");
expect_true($r['status'] === 200,
    '512x512 icon HTTP 200');
expect_true(str_starts_with($r['body'], "\x89PNG"),
    '512x512 starts with PNG magic bytes');
expect_true(strlen($r['body']) > 2000,
    '512x512 has reasonable file size (>2KB)');

// ── 5. install_cta render (server-side) ──
echo "\n5. Install CTA server-side render (flag-gated)\n";
require_once(__DIR__ . '/../lib.php');

// Flag OFF → empty.
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled',
    0, false, 2, 'D.1 endpoint smoke', 0);
expect_true(strlen(local_sentientia_pwa_before_standard_top_of_body_html() ?? '') === 0,
    'Flag OFF → callback returns empty string');

// Flag ON + dashboard layout → non-empty + has marker.
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled',
    0, true, 2, 'D.1 endpoint smoke', 0);
global $PAGE;
$PAGE->set_url('/my/dashboard.php');
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_context(\context_system::instance());
$html = local_sentientia_pwa_before_standard_top_of_body_html();
expect_true(str_contains($html, 'sentientia-install-cta'),
    'Flag ON on dashboard → CTA HTML rendered');

// Restore default OFF.
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled',
    0, false, 2, 'D.1 endpoint smoke — restore', 0);

echo "\nSummary: $pass passed, $fail failed.\n";
exit($fail > 0 ? 1 : 0);
