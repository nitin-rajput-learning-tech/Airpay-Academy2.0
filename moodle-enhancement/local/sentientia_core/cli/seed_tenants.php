<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Seed the Sentientia tenant registry from the legacy allow-list (ADR-021 Wave 4).
 *
 * Creates the customer-zero customer (Airpay) and one tenant row per recognised
 * root (1=Airpay, 77=Public, 177=ZEEA today). Idempotent + re-runnable. Run with
 * --dry-run first. Seeding does NOT change runtime behaviour: the registry is only
 * consulted once `tenant_registry_legacy` is flipped OFF.
 *
 *   php local/sentientia_core/cli/seed_tenants.php --dry-run
 *   php local/sentientia_core/cli/seed_tenants.php
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['dry-run' => false, 'help' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Seed the Sentientia tenant registry from the legacy allow-list.\n");
    cli_writeln("Options:");
    cli_writeln("  --dry-run   Show what would be created without writing.");
    cli_writeln("  -h, --help  This help.\n");
    cli_writeln("Example: php local/sentientia_core/cli/seed_tenants.php --dry-run");
    exit(0);
}

$dryrun = (bool) $options['dry-run'];
$now = time();

// Known display names for customer-zero's roots; unknown roots get a generic name.
$rootnames = [1 => 'Airpay', 77 => 'Public', 177 => 'ZEEA'];

// Seed source = the legacy allow-list (ADR-021 decision Q2).
$roots = \local_sentientia_core\tenant_registry::valid_roots();

cli_heading('Sentientia tenant registry seed' . ($dryrun ? ' (DRY RUN)' : ''));
cli_writeln('Seed source (legacy allow-list): ' . implode(', ', $roots));
cli_writeln('');

// ── 1. Customer-zero (Airpay) ─────────────────────────────────────────────
$shortname = 'airpay';
$customer = $DB->get_record('local_sentientia_customer', ['shortname' => $shortname]);
if ($customer) {
    $customerid = (int) $customer->id;
    cli_writeln("customer '{$shortname}' already exists (id {$customerid}) — skip");
} else if ($dryrun) {
    $customerid = 0;
    cli_writeln("WOULD create customer '{$shortname}' (Airpay Payment Services)");
} else {
    $customerid = (int) $DB->insert_record('local_sentientia_customer', (object) [
        'name'         => 'Airpay Payment Services',
        'shortname'    => $shortname,
        'status'       => 'active',
        'timecreated'  => $now,
        'timemodified' => $now,
    ]);
    cli_writeln("created customer '{$shortname}' (id {$customerid})");
}

// ── 2. One tenant row per root ─────────────────────────────────────────────
$created = 0;
$skipped = 0;
foreach ($roots as $rootid) {
    $rootid = (int) $rootid;
    $name = $rootnames[$rootid] ?? ('Tenant ' . $rootid);

    if ($DB->record_exists('local_sentientia_tenant', ['rootid' => $rootid])) {
        cli_writeln("  tenant root {$rootid} ({$name}) already registered — skip");
        $skipped++;
        continue;
    }
    if ($dryrun) {
        cli_writeln("  WOULD register tenant root {$rootid} ({$name}) → customer {$shortname}");
        continue;
    }
    $DB->insert_record('local_sentientia_tenant', (object) [
        'rootid'       => $rootid,
        'customerid'   => $customerid,
        'name'         => $name,
        'idnumber'     => null,
        'status'       => 'active',
        'timecreated'  => $now,
        'timemodified' => $now,
    ]);
    cli_writeln("  registered tenant root {$rootid} ({$name}) → customer {$shortname}");
    $created++;
}

cli_writeln('');
if ($dryrun) {
    cli_writeln('DRY RUN complete — no changes written. Re-run without --dry-run to apply.');
} else {
    cli_writeln("Done. {$created} tenant(s) created, {$skipped} already present.");
    cli_writeln('Registry is seeded but DORMANT — flip tenant_registry_legacy OFF to activate.');
    cli_writeln('Validate first: php local/sentientia_core/cli/parity_check_tenants.php');
}
exit(0);
