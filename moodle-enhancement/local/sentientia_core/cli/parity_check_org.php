<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Parity check: does the (backfilled) Sentientia org model match the legacy
 * BizLMS graph for every user? (ADR-020 Wave 3.3 — the objective cutover gate.)
 *
 * Thin wrapper over {@see \local_sentientia_core\org_parity}, which for each
 * in-scope user asserts (dogfooding the exact seam the cutover flips to):
 *   1. org::manager_via_model(u) == legacy open_supervisorid(u)        (manager edge)
 *   2. the user's model unit's idnumber == the open_path leaf segment  (membership)
 *
 * Read-only. Exits 0 on 100% parity, 1 on any mismatch (or empty model) — so it
 * can gate an automated cutover. Run AFTER backfill_org.php --execute and BEFORE
 * flipping org_legacy OFF.
 *
 *   php local/sentientia_core/cli/parity_check_org.php
 *   php local/sentientia_core/cli/parity_check_org.php --tenant=177
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['tenant' => '', 'limit' => 20, 'help' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Parity check: Sentientia org model vs legacy BizLMS graph.\n");
    cli_writeln('Options:');
    cli_writeln('  --tenant=ID  Restrict to one tenant root id. Default: all registered roots.');
    cli_writeln('  --limit=N    Max mismatches to list (default 20).');
    cli_writeln("  -h, --help   This help.\n");
    exit(0);
}

$tenantfilter = (string) $options['tenant'] !== '' ? (int) $options['tenant'] : 0;
$roots = $tenantfilter > 0 ? [$tenantfilter] : \local_sentientia_core\tenant_registry::valid_roots();
$limit = max(0, (int) $options['limit']);

cli_heading('Sentientia org model — parity check' . ($tenantfilter ? " (tenant {$tenantfilter})" : ''));
cli_writeln('Tenant root scope: [' . implode(', ', $roots) . ']');
cli_writeln('');

$parity = new \local_sentientia_core\org_parity(new \local_sentientia_core\org_legacy_source());
$r = $parity->check($roots, $limit);

cli_writeln(sprintf('Checked (in-scope) users: %d   skipped (no/foreign path): %d', $r->checked, $r->skipped));
cli_writeln(sprintf('Manager-edge mismatches:  %d', $r->mgrmismatch));
cli_writeln(sprintf('Membership mismatches:    %d', $r->memmismatch));
if (!empty($r->samples)) {
    cli_writeln('');
    cli_writeln('Sample mismatches (max ' . $limit . '):');
    foreach ($r->samples as $s) {
        cli_writeln($s);
    }
}
cli_writeln('');

if ($r->checked === 0) {
    cli_writeln('No in-scope users found — has the model been backfilled? (backfill_org.php --execute)');
    cli_writeln('RESULT: NOT READY.');
    exit(1);
}
if ($r->mgrmismatch === 0 && $r->memmismatch === 0) {
    cli_writeln("RESULT: 100% PARITY \xE2\x9C\x93  model == legacy for all {$r->checked} in-scope users.");
    cli_writeln('Safe to flip org_legacy OFF (ZEEA-first; rehearse on a clone DB first).');
    exit(0);
}
cli_writeln('RESULT: MISMATCH — do NOT cut over until resolved.');
exit(1);
