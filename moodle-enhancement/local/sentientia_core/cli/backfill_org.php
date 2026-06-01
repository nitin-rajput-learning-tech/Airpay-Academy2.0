<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Backfill the Sentientia org model from the legacy BizLMS graph (ADR-020 Wave 3.3).
 *
 * Runs {@see \local_sentientia_core\org_reconciler} once over all users (or one
 * tenant), mirroring the legacy `open_path` cost-center tree + `open_supervisorid`
 * manager links into `local_sentientia_org_*`. Idempotent + re-runnable.
 *
 * DRY-RUN BY DEFAULT — pass `--execute` to write. Backfilling does NOT change
 * runtime behaviour: the model is only read once `org_legacy` is flipped OFF, and
 * only after `parity_check_org.php` reports 100% parity (rehearse on a clone DB).
 *
 *   php local/sentientia_core/cli/backfill_org.php                      # dry run, all roots
 *   php local/sentientia_core/cli/backfill_org.php --execute            # write, all roots
 *   php local/sentientia_core/cli/backfill_org.php --tenant=177 --execute   # ZEEA only
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['execute' => false, 'tenant' => '', 'help' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Backfill the Sentientia org model from the legacy BizLMS graph.\n");
    cli_writeln('Options:');
    cli_writeln('  --execute    Write changes. Without this flag the script is a DRY RUN.');
    cli_writeln('  --tenant=ID  Restrict to one tenant root id (e.g. 1, 77, 177). Default: all registered roots.');
    cli_writeln("  -h, --help   This help.\n");
    cli_writeln('Example: php local/sentientia_core/cli/backfill_org.php --execute');
    exit(0);
}

$dryrun = empty($options['execute']);

// Tenant scope: one root if given, else the registry's recognised roots.
if ((string) $options['tenant'] !== '') {
    $tenant = (int) $options['tenant'];
    if ($tenant <= 0) {
        cli_error('--tenant must be a positive tenant root id.');
    }
    $roots = [$tenant];
} else {
    $roots = \local_sentientia_core\tenant_registry::valid_roots();
}

$hascostcenter = $DB->get_manager()->table_exists('local_costcenter');

cli_heading('Sentientia org backfill' . ($dryrun ? ' (DRY RUN)' : ''));
cli_writeln('Tenant root scope: [' . implode(', ', $roots) . ']');
cli_writeln('Source: legacy BizLMS (user.open_path + open_supervisorid'
    . ($hascostcenter ? ' + local_costcenter names).' : '; local_costcenter absent — unit names fall back to "Unit <id>").'));
cli_writeln('');

$reconciler = new \local_sentientia_core\org_reconciler(new \local_sentientia_core\org_legacy_source());

// Dry-run executes the real reconciler inside a transaction we then discard, so
// the reported counts are exactly what --execute would write.
$transaction = $DB->start_delegated_transaction();
$counts = $reconciler->reconcile($roots);
if ($dryrun) {
    $DB->force_transaction_rollback();
} else {
    $transaction->allow_commit();
}

cli_writeln(sprintf('Users processed: %d   skipped (no/foreign path): %d',
    $counts->usersprocessed, $counts->usersskipped));
cli_writeln(sprintf('Org units:   created %d, updated %d', $counts->unitscreated, $counts->unitsupdated));
cli_writeln(sprintf('Org members: created %d, updated %d', $counts->memberscreated, $counts->membersupdated));
cli_writeln('');
if ($dryrun) {
    cli_writeln('DRY RUN complete — no changes written. Re-run with --execute to apply.');
} else {
    cli_writeln('Backfill applied. Validate before any flip:');
    cli_writeln('  php local/sentientia_core/cli/parity_check_org.php');
}
exit(0);
