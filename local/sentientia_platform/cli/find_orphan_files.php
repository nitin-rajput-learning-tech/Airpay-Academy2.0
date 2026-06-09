<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Find orphan `mdl_files` rows whose underlying `filedir/` blob no
 * longer exists on disk.
 *
 * Born from F-081 (Stabilization Audit Phase 1, 2026-05-28): Apache
 * log showed `getimagesize(.../filedir/bd/54/bd5484...): Failed to
 * open stream` during catalog renders. Some `mdl_files` rows point
 * at blobs that production cleanup deleted physically without
 * clearing the DB rows. Catalog renders a broken background-image
 * silently.
 *
 * Usage:
 *   # Report-only (safe, read-only):
 *   php local/sentientia_platform/cli/find_orphan_files.php
 *
 *   # Report a specific area:
 *   php local/sentientia_platform/cli/find_orphan_files.php --area=coursebannerimage
 *
 *   # Limit sample size (default: 500 most-recent file hashes):
 *   php local/sentientia_platform/cli/find_orphan_files.php --limit=2000
 *
 *   # Output as CSV for further triage:
 *   php local/sentientia_platform/cli/find_orphan_files.php --csv > orphans.csv
 *
 *   # Delete the orphan rows ([CONFIRM] gate per CLAUDE.md §3):
 *   php local/sentientia_platform/cli/find_orphan_files.php --delete --confirm
 *
 * Notes:
 *   - Scans by contenthash (not by mdl_files.id) so we deduplicate the
 *     N-rows-per-blob situation (one blob can back many file
 *     references across components).
 *   - --delete is gated by --confirm to avoid accidental destructive
 *     runs. Always run --report first and review the CSV.
 *   - Designed to run safely in production cron (read-only mode) for
 *     ongoing health monitoring. Wire as scheduled task in v2.
 *
 * @package    local_sentientia_platform
 * @subpackage cli
 * @copyright  2026 Airpay Payment Services
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'area'    => '',
    'limit'   => 500,
    'csv'     => false,
    'delete'  => false,
    'confirm' => false,
    'help'    => false,
], [
    'h' => 'help',
]);

if ($options['help']) {
    cli_writeln(file_get_contents(__FILE__, false, null, 0, 1800));
    exit(0);
}

global $DB, $CFG;

$area  = (string) $options['area'];
$limit = (int) $options['limit'];

// Build the query. Group by contenthash to deduplicate.
$where  = "f.filesize > 0 AND f.contenthash <> ''";
$params = [];
if ($area !== '') {
    $where .= " AND f.filearea = :area";
    $params['area'] = $area;
}

$candidates = $DB->get_records_sql(
    "SELECT f.contenthash,
            MIN(f.id) AS sample_id,
            MAX(f.filename) AS sample_filename,
            MAX(f.filearea) AS sample_area,
            MAX(f.component) AS sample_component,
            MAX(f.mimetype) AS sample_mimetype,
            MAX(f.filesize) AS filesize,
            COUNT(*) AS ref_count
       FROM {files} f
      WHERE $where
   GROUP BY f.contenthash
   ORDER BY MAX(f.id) DESC",
    $params, 0, $limit);

$orphans = [];
foreach ($candidates as $f) {
    $ch   = $f->contenthash;
    $path = $CFG->dataroot . '/filedir/' . substr($ch, 0, 2)
          . '/' . substr($ch, 2, 2) . '/' . $ch;
    if (!file_exists($path)) {
        $orphans[] = $f;
    }
}

// Report.
if ($options['csv']) {
    cli_writeln('contenthash,sample_id,sample_filename,sample_area,sample_component,sample_mimetype,filesize,ref_count');
    foreach ($orphans as $o) {
        cli_writeln(implode(',', [
            $o->contenthash,
            $o->sample_id,
            '"' . str_replace('"', '""', (string) $o->sample_filename) . '"',
            (string) $o->sample_area,
            (string) $o->sample_component,
            (string) $o->sample_mimetype,
            (int) $o->filesize,
            (int) $o->ref_count,
        ]));
    }
} else {
    cli_writeln("=== Orphan-file scan ===");
    cli_writeln("Scope: " . ($area !== '' ? "filearea=$area" : "all areas"));
    cli_writeln("Sample: " . count($candidates) . " most-recent unique content hashes");
    cli_writeln("Orphans found: " . count($orphans));
    cli_writeln('');
    if (count($orphans) === 0) {
        cli_writeln("✓ All sampled blobs present on disk.");
        exit(0);
    }
    foreach (array_slice($orphans, 0, 20) as $o) {
        cli_writeln(sprintf(
            "  contenthash=%s area=%s component=%s file=%s refs=%d size=%d",
            substr($o->contenthash, 0, 12),
            $o->sample_area,
            $o->sample_component,
            $o->sample_filename,
            $o->ref_count,
            $o->filesize));
    }
    if (count($orphans) > 20) {
        cli_writeln('  ... (' . (count($orphans) - 20) . ' more — use --csv for full list)');
    }
}

// Delete (gated).
if ($options['delete']) {
    if (!$options['confirm']) {
        cli_writeln('');
        cli_writeln('⚠  --delete passed but --confirm missing. Aborting.');
        cli_writeln('   Re-run with `--delete --confirm` to actually delete the orphan rows.');
        exit(2);
    }
    $deleted = 0;
    foreach ($orphans as $o) {
        // Delete every mdl_files row pointing at this missing blob.
        $deleted += $DB->delete_records('files', ['contenthash' => $o->contenthash]);
    }
    cli_writeln('');
    cli_writeln("✓ Deleted $deleted orphan mdl_files rows across "
        . count($orphans) . " missing content hashes.");
    cli_writeln('  (filedir/ blobs were already missing — only DB rows removed.)');
}
