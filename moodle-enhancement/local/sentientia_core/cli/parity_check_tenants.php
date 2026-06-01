<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Parity check: does the seeded Sentientia tenant registry match the legacy
 * hardcoded allow-list? (ADR-021 Wave 4 — the objective cutover gate.)
 *
 * Computes BOTH sets directly (without flipping the flag) and compares them.
 * Exits 0 on 100% parity, 1 on any mismatch — so it can gate an automated
 * cutover. Run AFTER seed_tenants.php and BEFORE flipping tenant_registry_legacy.
 *
 *   php local/sentientia_core/cli/parity_check_tenants.php
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Legacy allow-list — the set production currently validates against.
if (class_exists('\local_airpay_core\tenant')
        && defined('\local_airpay_core\tenant::VALID_TENANTS')) {
    $legacy = \local_airpay_core\tenant::VALID_TENANTS;
    $legacysrc = 'local_airpay_core\\tenant::VALID_TENANTS';
} else {
    $legacy = [1, 77, 177];
    $legacysrc = 'inline fallback [1, 77, 177]';
}
$legacy = array_values(array_unique(array_map('intval', $legacy)));
sort($legacy);

// Registry set — active rootids in the table (read directly, flag-independent).
$registry = [];
if ($DB->get_manager()->table_exists('local_sentientia_tenant')) {
    $registry = $DB->get_fieldset_select(
        'local_sentientia_tenant', 'rootid', 'status = :s', ['s' => 'active']);
}
$registry = array_values(array_unique(array_map('intval', $registry)));
sort($registry);

cli_heading('Sentientia tenant registry — parity check');
cli_writeln("legacy   ({$legacysrc}): [" . implode(', ', $legacy) . ']');
cli_writeln('registry (local_sentientia_tenant active): [' . implode(', ', $registry) . ']');
cli_writeln('');

$missing = array_values(array_diff($legacy, $registry));   // in legacy, NOT in registry
$extra   = array_values(array_diff($registry, $legacy));   // in registry, NOT in legacy

if (empty($registry)) {
    cli_writeln('Registry is EMPTY — run seed_tenants.php first.');
    cli_writeln('RESULT: NOT READY (cutover would fall back to legacy).');
    exit(1);
}

if (empty($missing) && empty($extra)) {
    cli_writeln('RESULT: 100% PARITY ✓  registry == legacy allow-list.');
    cli_writeln('Safe to flip tenant_registry_legacy OFF (rehearse on a clone DB first).');
    exit(0);
}

if (!empty($missing)) {
    cli_writeln('MISSING from registry (legacy has, registry does not): ['
        . implode(', ', $missing) . ']');
}
if (!empty($extra)) {
    cli_writeln('EXTRA in registry (registry has, legacy does not): ['
        . implode(', ', $extra) . ']');
    cli_writeln('  (extra roots are expected once you intentionally onboard new tenants;'
        . ' for a pure customer-zero parity check they should be empty.)');
}
cli_writeln('');
cli_writeln('RESULT: MISMATCH — do NOT cut over until resolved.');
exit(1);
