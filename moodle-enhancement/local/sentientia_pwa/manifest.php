<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Web App Manifest endpoint — Phase D.1.a (2026-05-21, per ADR-005).
 *
 * Serves a `application/manifest+json` document per the W3C Web App
 * Manifest spec, so Chrome/Edge/Firefox/Safari can treat Sentientia
 * LMS as an installable Progressive Web App.
 *
 * Per-customer branding flows through `local_airpay_core\customer::branding()`
 * — Phase 0/1 returns the single Airpay bundle; Phase 2+ ADR-008 resolves
 * via the `local_airpay_customer_brand` table.
 *
 * Auth: deliberately public (NO_MOODLE_COOKIES). The browser fetches
 * `/local/sentientia_pwa/manifest.php` from a `<link rel="manifest">`
 * tag during page load — auth would block install on the login page.
 * The manifest itself contains no PII; only branding metadata.
 *
 * Feature flag: `sentientia.pwa.install.enabled` (default OFF). When
 * the flag is off, the manifest still renders (so existing PWA-installed
 * users keep working) but the install CTA on the dashboard is hidden.
 *
 * @package local_sentientia_pwa
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY',  true);

require(__DIR__ . '/../../config.php');

global $CFG, $OUTPUT, $PAGE;

// Phase 0/1 — every user is Airpay customer. Phase 2+ will resolve via
// host header or session.
$customer_id = 1;
if (class_exists('\\local_airpay_core\\customer')) {
    try {
        // For unauthenticated requests this still returns AIRPAY (the
        // class is designed for that case).
        $customer_id = \local_airpay_core\customer::current();
    } catch (\Throwable $e) {
        $customer_id = 1;
    }
}

$brand = class_exists('\\local_airpay_core\\customer')
    ? \local_airpay_core\customer::branding($customer_id)
    : [
        'name'         => 'Sentientia LMS',
        'short_name'   => 'Sentientia',
        'theme_color'  => '#0066A7',
        'bg_color'     => '#F2F4FB',
        'icon_192_url' => $CFG->wwwroot . '/pix/i/grade_correct.svg',
        'icon_512_url' => $CFG->wwwroot . '/pix/i/grade_correct.svg',
        'start_url'    => '/my/dashboard.php',
        'lang'         => 'en',
    ];

$ctx = [
    'name'         => $brand['name'],
    'short_name'   => $brand['short_name'],
    'theme_color'  => $brand['theme_color'],
    'bg_color'     => $brand['bg_color'],
    'icon_192_url' => $brand['icon_192_url'],
    'icon_512_url' => $brand['icon_512_url'],
    // start_url must be absolute (W3C manifest spec recommends absolute
    // URLs to avoid cross-origin install-page confusion).
    'start_url'    => $CFG->wwwroot . $brand['start_url'],
    'scope'        => $CFG->wwwroot . '/',
    'lang'         => $brand['lang'],
];

// Clean any output buffer Moodle layered on — manifest must be raw JSON.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=300');  // 5-min cache; brand changes propagate within this window

$PAGE->set_url('/local/sentientia_pwa/manifest.php');
$PAGE->set_context(\context_system::instance());

echo $OUTPUT->render_from_template('local_sentientia_pwa/manifest', $ctx);
exit;
