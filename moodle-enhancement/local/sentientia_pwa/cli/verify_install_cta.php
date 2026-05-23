<?php
// One-shot verify that the D.1.b install CTA injects correctly under
// the right page-layout + flag conditions. Run via:
//   php local/sentientia_pwa/cli/verify_install_cta.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

$pass = 0;
$fail = 0;

function expect_true(bool $got, string $desc) {
    global $pass, $fail;
    if ($got) { echo "  PASS — $desc\n"; $pass++; }
    else      { echo "  FAIL — $desc\n"; $fail++; }
}

// 1. Flag OFF — callback must return empty string.
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled',
    0, false, 2, 'verify_install_cta — start OFF', 0);
// 2026-05-23 — call the canonical helper directly. The legacy
// `local_sentientia_pwa_before_standard_top_of_body_html()` function
// short-circuits to '' on Moodle 5.2 (the new hook fires natively in
// production, the helper is the single source of truth).
$html_off = \local_sentientia_pwa\hook_callbacks::build_install_cta_html();
expect_true(strlen($html_off ?? '') === 0,
    'Flag OFF → callback returns empty string');

// 2. Flip ON + simulate a dashboard page-layout.
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled',
    0, true, 2, 'verify_install_cta — verify', 0);

global $PAGE, $OUTPUT;
$PAGE->set_url('/my/dashboard.php');
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_context(\context_system::instance());

$html_on = \local_sentientia_pwa\hook_callbacks::build_install_cta_html();

expect_true(strlen($html_on) > 0,
    'Flag ON on mydashboard → callback returns CTA HTML');
expect_true(str_contains($html_on, 'sentientia-install-cta-wrap'),
    'HTML contains sentientia-install-cta-wrap wrapper');
expect_true(str_contains($html_on, 'data-action="install"'),
    'HTML contains data-action="install" button');
expect_true(str_contains($html_on, 'hidden'),
    'HTML contains hidden attribute (so banner is invisible until JS fires)');

// 3. Layout filter — popup/embedded layouts should NOT inject the CTA.
$PAGE->set_pagelayout('popup');
$html_popup = \local_sentientia_pwa\hook_callbacks::build_install_cta_html();
expect_true(strlen($html_popup ?? '') === 0,
    'Flag ON on popup layout → no CTA (layout filter respected)');

// 4. Cleanup — restore default OFF as per ADR-005.
\local_airpay_core\feature_flags::set('sentientia.pwa.install.enabled',
    0, false, 2, 'verify_install_cta — restore OFF', 0);
expect_true(!\local_airpay_core\feature_flags::is_enabled('sentientia.pwa.install.enabled'),
    'Flag restored to OFF after verification');

echo "\nSummary: $pass passed, $fail failed.\n";
exit($fail > 0 ? 1 : 0);
