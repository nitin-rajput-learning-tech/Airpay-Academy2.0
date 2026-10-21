<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: switch the feature flags of the 2026-10-14 evidence pass on or off for ONE tenant, and read them back.
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set. Only the three flags listed below can be
 * touched, and only for the tenant given (default 77, the local test tenant); an override that did not exist before
 * is REMOVED again by "off" (not written as false), so "off" restores the state the box had before the pass.
 *
 *   php gst_evidence_flags.php status [tenant]
 *   php gst_evidence_flags.php on  cart|evaluation|request|all [tenant]
 *   php gst_evidence_flags.php off cart|evaluation|request|all [tenant]
 *
 *   cart       sentientia.cart.purchase_receipts + sentientia.cart.gst_tax_invoices
 *   evaluation sentientia.evaluation.learner_history
 *   request    sentientia.request.catalog_button (added 2026-10-19 for the ui-fix-request evidence)
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}
if (empty($CFG->noemailever)) {
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set.\n");
    exit(1);
}

use local_sentientia_platform\feature_flags;

$groups = [
    'cart' => ['sentientia.cart.purchase_receipts', 'sentientia.cart.gst_tax_invoices'],
    'evaluation' => ['sentientia.evaluation.learner_history'],
    'request' => ['sentientia.request.catalog_button'],  // 2026-10-19 fix-branch evidence (ui-fix-request)
];
$cmd = $argv[1] ?? 'status';
$which = $argv[2] ?? 'all';
$tenant = (int) ($argv[3] ?? ($cmd === 'status' && ctype_digit($which) ? $which : 77));
if ($cmd === 'status' && ctype_digit($which)) {
    $which = 'all';
}
$keys = $which === 'all' ? array_merge(...array_values($groups)) : ($groups[$which] ?? null);
if (!in_array($cmd, ['status', 'on', 'off'], true) || $keys === null || $tenant <= 0) {
    fwrite(STDERR, "usage: gst_evidence_flags.php status [tenant] | on|off cart|evaluation|request|all [tenant]\n");
    exit(1);
}

global $DB;
$admin = (int) $DB->get_field('user', 'id', ['username' => 'vp_siteadmin', 'deleted' => 0], MUST_EXIST);
foreach ($keys as $key) {
    if ($cmd === 'on') {
        feature_flags::set($key, $tenant, true, $admin, 'visual evidence 2026-10-14 (local)');
    } else if ($cmd === 'off') {
        feature_flags::set($key, $tenant, null, $admin, 'visual evidence 2026-10-14 (local): back to default');
    }
}
foreach ($keys as $key) {
    $rows = $DB->get_records('local_sentientia_feature_flags', ['flag_key' => $key]);
    echo $key . ' override rows=' . json_encode(array_values(array_map(
            fn($r) => ['customer' => (int) $r->customer_id, 'tenant' => (int) $r->tenant_id, 'on' => (int) $r->is_enabled],
            $rows)))
        . ' resolved: t' . $tenant . '=' . var_export(feature_flags::is_enabled_for($key, 1, $tenant), true)
        . ' t1=' . var_export(feature_flags::is_enabled_for($key, 1, 1), true)
        . ' t77=' . var_export(feature_flags::is_enabled_for($key, 1, 77), true)
        . ' t177=' . var_export(feature_flags::is_enabled_for($key, 1, 177), true) . "\n";
}
