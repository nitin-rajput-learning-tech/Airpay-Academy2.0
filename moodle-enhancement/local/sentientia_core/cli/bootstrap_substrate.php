<?php
// This file is part of Sentientia LMS.
//
// Sentientia LMS is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. Distributed WITHOUT ANY WARRANTY. See the GNU GPL for
// more details. <http://www.gnu.org/licenses/>.

/**
 * Sentientia LMS - tenant-substrate bootstrap (explicit / manual).
 *
 * Ensures the BizLMS-compatible `open_*` columns exist on {user} and {course}
 * so a from-scratch Sentientia install has a working multi-tenant substrate
 * WITHOUT the external (eAbyas/OPEN-LMS) BizLMS plugins.
 *
 * As of ADR-024 Wave 2 the same logic also runs AUTOMATICALLY on plugin
 * install/upgrade (db/upgrade.php -> \local_sentientia_core\substrate). This
 * CLI remains for explicit re-runs and the --dry-run preview. Both share the
 * single source of truth in classes/substrate.php (additive + idempotent).
 *
 * Usage:
 *   php local/sentientia_core/cli/bootstrap_substrate.php            # apply
 *   php local/sentientia_core/cli/bootstrap_substrate.php --dry-run  # preview
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_core\substrate;

global $CFG;

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'dry-run' => false],
    ['h' => 'help', 'n' => 'dry-run']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if (!empty($options['help'])) {
    echo "Sentientia tenant-substrate bootstrap.\n\n";
    echo "Adds the BizLMS-compatible open_* columns to mdl_user and mdl_course\n";
    echo "if they are missing (idempotent). Also runs automatically on plugin\n";
    echo "install/upgrade; this CLI is for explicit re-runs + dry-run preview.\n\n";
    echo "Options:\n";
    echo "  -n, --dry-run   Show what would change; make no changes.\n";
    echo "  -h, --help      Print this help.\n";
    exit(0);
}

$dryrun = !empty($options['dry-run']);

echo "=== Sentientia tenant-substrate bootstrap" . ($dryrun ? ' (DRY RUN)' : '') . " ===\n\n";

$ucount = count(substrate::user_fields());
$ccount = count(substrate::course_fields());
$result = substrate::ensure_all($dryrun);

foreach (['user' => $ucount, 'course' => $ccount] as $table => $total) {
    $added = $result[$table];
    if ($added) {
        foreach ($added as $name) {
            echo ($dryrun ? '  [dry-run] would add ' : '  + added   ') . "{$table}.{$name}\n";
        }
    }
    echo "  {$table}: " . count($added) . ' ' . ($dryrun ? 'missing' : 'added')
        . ', ' . ($total - count($added)) . " already present (of {$total}).\n";
}

$totaladded = count($result['user']) + count($result['course']);
echo "\n=== Summary ===\n";
if ($dryrun) {
    echo "DRY RUN: {$totaladded} column(s) would be added. Re-run without --dry-run to apply.\n";
} else if ($totaladded > 0) {
    purge_all_caches();
    echo "Done. {$totaladded} column(s) added. Caches purged.\n";
} else {
    echo "Substrate already complete - nothing to do.\n";
}

exit(0);
