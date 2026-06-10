<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SW-1 (2026-06-10, Nitin's rollout decision) — enable the one-click free
 * self-enrolment flag for INTERNAL tenants.
 *
 * Flips `sentientia.catalog.free_oneclick_enrol.enabled` ON per tenant via
 * \local_sentientia_platform\feature_flags::set() (audited, idempotent).
 * The Public/B2C tenant keeps the cart flow and is deliberately NOT touched.
 *
 * Run on production during the deploy window (after file deploy + upgrade):
 *   php local/sentientia_catalog/cli/enable_oneclick_enrol.php
 *
 * Options:
 *   --tenants=1,177   Tenant roots to enable (default: 1,177 — Airpay, ZEEA)
 *   --disable         Revert: set the override to OFF for those tenants
 *   --dry-run         Show what would change, write nothing
 *
 * @package local_sentientia_catalog
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['tenants' => '1,177', 'disable' => false, 'dry-run' => false, 'help' => false],
    ['h' => 'help']
);
if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)));
}
if ($options['help']) {
    cli_writeln('Enable one-click free enrolment per internal tenant.');
    cli_writeln('  --tenants=1,177  --disable  --dry-run');
    exit(0);
}

$flag = \local_sentientia_catalog\enrolment::FLAG;
$value = !$options['disable'];
$tenants = array_filter(array_map('intval',
    preg_split('/[,\s]+/', (string) $options['tenants'])));
if (empty($tenants)) {
    cli_error('No valid tenant ids in --tenants.');
}

cli_writeln("Flag:    $flag");
cli_writeln('Target:  ' . ($value ? 'ENABLE' : 'DISABLE')
    . ' for tenant root(s) ' . implode(', ', $tenants)
    . ($options['dry-run'] ? '  [DRY RUN]' : ''));

$changed = 0;
foreach ($tenants as $tid) {
    $before = \local_sentientia_platform\feature_flags::is_enabled_for_tenant($flag, $tid);
    if ($before === $value) {
        cli_writeln("  tenant $tid: already " . ($value ? 'ON' : 'OFF') . ' — skip');
        continue;
    }
    if (!$options['dry-run']) {
        \local_sentientia_platform\feature_flags::set($flag, $tid, $value, null,
            'SW-1 rollout decision (Nitin, 2026-06-10) — one-click free enrol for internal tenants');
    }
    $after = $options['dry-run'] ? $value
        : \local_sentientia_platform\feature_flags::is_enabled_for_tenant($flag, $tid);
    cli_writeln("  tenant $tid: " . ($before ? 'ON' : 'OFF') . ' -> ' . ($after ? 'ON' : 'OFF'));
    $changed++;
}

\local_sentientia_platform\feature_flags::invalidate_caches();
cli_writeln("Done. Rows changed: $changed (caches invalidated).");
exit(0);
