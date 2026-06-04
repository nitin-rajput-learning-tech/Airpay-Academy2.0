<?php
// This file is part of Sentientia LMS.
//
// Sentientia LMS is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful, but WITHOUT ANY
// WARRANTY; without even the implied warranty of MERCHANTABILITY or
// FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
// for more details.  <http://www.gnu.org/licenses/>.

/**
 * Sentientia LMS - tenant-substrate bootstrap.
 *
 * Ensures the BizLMS-compatible `open_*` columns exist on {user} and {course}
 * so a FROM-SCRATCH Sentientia install has a working multi-tenant substrate
 * WITHOUT the external (eAbyas/OPEN-LMS) BizLMS plugins.
 *
 * Background (ADR-018 independence program): Sentientia's tenant detection is
 * pure `$USER->open_path` string parsing (VALID_TENANTS=[1,77,177]); it does
 * NOT require the eAbyas local_costcenter/local_userdata tables at runtime.
 * The only hard dependency a vanilla Moodle install is missing is the set of
 * `open_*` columns themselves. This CLI closes that gap, additively and
 * idempotently - it only ADDS columns that are absent and never drops or
 * alters an existing one, so it is a safe no-op on a restored production DB.
 *
 * The column definitions below were captured verbatim from the live
 * production-faithful schema on 2026-06-04 (37 user cols + 18 course cols).
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

global $DB, $CFG;

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
    echo "if they are missing, so a from-scratch Sentientia install has a working\n";
    echo "tenant substrate. Idempotent - existing columns are left untouched.\n\n";
    echo "Options:\n";
    echo "  -n, --dry-run   Show what would change; make no changes.\n";
    echo "  -h, --help      Print this help.\n\n";
    echo "Example:\n";
    echo "  php local/sentientia_core/cli/bootstrap_substrate.php --dry-run\n";
    exit(0);
}

$dryrun = !empty($options['dry-run']);

// ---------------------------------------------------------------------------
// Authoritative open_* schema (captured from production-faithful DB 2026-06-04).
// Format: column name => exact MySQL/MariaDB column definition.
// ---------------------------------------------------------------------------
$userfields = [
    'open_path'            => 'VARCHAR(255) NULL',
    'open_supervisorid'    => 'BIGINT(20) NULL',
    'open_employeeid'      => 'VARCHAR(255) NULL',
    'open_usermodified'    => 'BIGINT(20) NULL',
    'open_designation'     => 'VARCHAR(255) NULL',
    'open_state'           => 'VARCHAR(200) NULL',
    'open_jobfunction'     => 'VARCHAR(200) NULL',
    'open_group'           => 'VARCHAR(200) NULL',
    'open_qualification'   => 'VARCHAR(200) NULL',
    'open_location'        => 'VARCHAR(200) NULL',
    'open_team'            => 'VARCHAR(200) NULL',
    'open_client'          => 'VARCHAR(200) NULL',
    'open_supervisorempid' => 'VARCHAR(200) NULL',
    'open_band'            => 'VARCHAR(200) NULL',
    'open_hrmsrole'        => 'VARCHAR(200) NULL',
    'open_zone'            => 'VARCHAR(200) NULL',
    'open_region'          => 'VARCHAR(200) NULL',
    'open_grade'           => 'VARCHAR(200) NULL',
    'open_positionid'      => 'VARCHAR(255) NULL',
    'open_domainid'        => 'VARCHAR(255) NULL',
    'open_states'          => 'VARCHAR(255) NULL',
    'open_district'        => 'VARCHAR(255) NULL',
    'open_subdistrict'     => 'VARCHAR(255) NULL',
    'open_village'         => 'VARCHAR(255) NULL',
    'open_joindate'        => 'VARCHAR(512) NULL',
    'open_dateofbirth'     => 'VARCHAR(512) NULL',
    'open_employmenttype'  => 'VARCHAR(512) NULL',
    'open_prefix'          => 'VARCHAR(512) NULL',
    'open_orgactive'       => 'TINYINT(1) NOT NULL DEFAULT 0',
    'open_educationlevel'  => "VARCHAR(225) NOT NULL DEFAULT '0'",
    'open_fieldwork'       => "VARCHAR(225) NOT NULL DEFAULT '0'",
    'open_jobtitle'        => "VARCHAR(225) NOT NULL DEFAULT '0'",
    'open_company'         => "VARCHAR(225) NOT NULL DEFAULT '0'",
    'open_paymentinfo'     => "VARCHAR(225) NOT NULL DEFAULT '0'",
    'open_privacypolicy'   => 'TINYINT(1) NULL DEFAULT 0',
    'open_termscondition'  => 'TINYINT(1) NULL DEFAULT 0',
    'open_countryid'       => "VARCHAR(100) NOT NULL DEFAULT '0'",
];

$coursefields = [
    'open_certificateid'        => 'BIGINT(20) NULL',
    'open_path'                 => 'VARCHAR(255) NULL',
    'open_categoryid'           => 'BIGINT(20) NULL DEFAULT 0',
    'open_identifiedas'         => 'VARCHAR(255) NULL',
    'open_points'               => 'BIGINT(20) NULL DEFAULT 0',
    'open_requestcourseid'      => 'BIGINT(20) NULL',
    'open_coursecreator'        => 'BIGINT(20) NULL',
    'open_coursecompletiondays' => 'BIGINT(20) NULL',
    'open_cost'                 => 'BIGINT(20) NULL',
    'open_skill'                => 'BIGINT(20) NULL',
    'open_level'                => 'BIGINT(20) NULL',
    'open_securecourse'         => 'TINYINT(4) NULL DEFAULT 0',
    'open_hrmsrole'             => 'VARCHAR(255) NULL',
    'open_location'             => 'VARCHAR(255) NULL',
    'open_module'               => 'VARCHAR(255) NULL',
    'open_coursetype'           => 'TINYINT(1) NULL DEFAULT 0',
    'open_group'                => 'VARCHAR(225) NULL',
    'open_designation'          => 'VARCHAR(225) NULL',
];

/**
 * Add any missing columns from $fields to the given (unprefixed) table.
 *
 * @param string $table   Unprefixed table name, e.g. 'user'.
 * @param array  $fields  Map of column name => MySQL column definition.
 * @param bool   $dryrun  When true, report only; make no changes.
 * @return int Number of columns added (or that would be added).
 */
function sentientia_ensure_columns(string $table, array $fields, bool $dryrun): int {
    global $DB, $CFG;

    $existing = $DB->get_columns($table, false); // Fresh (no cache).
    $prefixed = $CFG->prefix . $table;
    $added = 0;
    $present = 0;

    foreach ($fields as $name => $definition) {
        if (isset($existing[strtolower($name)])) {
            $present++;
            continue;
        }
        echo ($dryrun ? '  [dry-run] would add ' : '  + adding   ') . "{$table}.{$name}  ({$definition})\n";
        if (!$dryrun) {
            // Raw DDL is intentional: we are reproducing an external (eAbyas)
            // schema verbatim; the xmldb generator would impose Moodle's own
            // type opinions. change_database_structure() resets the DB caches.
            $DB->change_database_structure("ALTER TABLE `{$prefixed}` ADD COLUMN `{$name}` {$definition}");
        }
        $added++;
    }

    echo "  {$table}: {$added} " . ($dryrun ? 'missing' : 'added') . ", {$present} already present (of " . count($fields) . ").\n";
    return $added;
}

echo "=== Sentientia tenant-substrate bootstrap" . ($dryrun ? ' (DRY RUN)' : '') . " ===\n\n";
echo "Ensuring BizLMS-compatible open_* columns exist (additive, idempotent).\n\n";

echo "-- mdl_user --\n";
$ua = sentientia_ensure_columns('user', $userfields, $dryrun);
echo "\n-- mdl_course --\n";
$ca = sentientia_ensure_columns('course', $coursefields, $dryrun);

$total = $ua + $ca;
echo "\n=== Summary ===\n";
if ($dryrun) {
    echo "DRY RUN: {$total} column(s) would be added. Re-run without --dry-run to apply.\n";
} else {
    echo "Done. {$total} column(s) added.\n";
    if ($total > 0) {
        purge_all_caches();
        echo "Caches purged.\n";
    } else {
        echo "Substrate already complete - nothing to do.\n";
    }
}

exit(0);
