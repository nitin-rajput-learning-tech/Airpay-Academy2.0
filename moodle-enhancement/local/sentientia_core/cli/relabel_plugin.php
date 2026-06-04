<?php
// This file is part of Sentientia LMS. GNU GPL v3 or later.

/**
 * Sentientia LMS - relabel a renamed plugin's DB footprint IN PLACE (ADR-025, Class B).
 *
 * For component renames where the plugin owns tables and/or capabilities, a naive
 * dir rename makes Moodle install a FRESH (empty) new component and DROP the old
 * one's tables. This CLI instead relabels the existing footprint so Moodle sees
 * the new component as already-installed (no install, no drop):
 *   - renames tables per the explicit --tables map (data preserved),
 *   - UPDATEs component-keyed rows (config_plugins, task_scheduled, task_adhoc,
 *     message_providers, files, external_functions/services),
 *   - optionally migrates capabilities + role_capabilities (--migrate-caps).
 *
 * Table names are NOT assumed from the component (e.g. local_sentientia_integrations
 * owns local_sentientia_integration_log - singular); pass them explicitly.
 *
 * Run the dir rename + code sed FIRST, then this CLI, then admin/cli/upgrade.php.
 * Dry-run by default. Rehearse on a clone before any live deploy.
 *
 * Usage (example):
 *   php local/sentientia_core/cli/relabel_plugin.php \
 *       --from=local_airpay_PLUGIN --to=local_sentientia_PLUGIN \
 *       --tables=OLDTABLE:NEWTABLE [--migrate-caps] [--run]
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
    ['help' => false, 'from' => '', 'to' => '', 'tables' => '', 'migrate-caps' => false, 'run' => false],
    ['h' => 'help']
);

if (!empty($options['help']) || $options['from'] === '' || $options['to'] === '') {
    echo "Relabel a renamed plugin's DB footprint in place (ADR-025 Class B).\n\n";
    echo "  --from=local_airpay_X      old component (required)\n";
    echo "  --to=local_sentientia_X    new component (required)\n";
    echo "  --tables=old1:new1,old2:new2   explicit table renames (no prefix)\n";
    echo "  --migrate-caps             also migrate capabilities + role_capabilities\n";
    echo "  --run                      apply (default: dry-run)\n";
    exit(0);
}

$from = $options['from'];
$to   = $options['to'];
$run  = !empty($options['run']);
$dbman = $DB->get_manager();

echo "=== Relabel {$from} -> {$to}" . ($run ? '' : ' (DRY RUN)') . " ===\n";

// ---- 1. Tables (explicit map; data preserved) -----------------------------
if ($options['tables'] !== '') {
    foreach (explode(',', $options['tables']) as $pair) {
        $parts = explode(':', trim($pair));
        if (count($parts) !== 2) { cli_error("bad --tables entry: {$pair}"); }
        list($oldt, $newt) = $parts;
        $oldx = new xmldb_table($oldt);
        if ($dbman->table_exists($oldx)) {
            $rows = $DB->count_records($oldt);
            echo "  table {$oldt} ({$rows} rows) -> {$newt}\n";
            if ($run) { $dbman->rename_table($oldx, $newt); }
        } else if ($dbman->table_exists(new xmldb_table($newt))) {
            echo "  table {$newt}: already renamed (skip)\n";
        } else {
            echo "  table {$oldt}: NOT FOUND (skip)\n";
        }
    }
}

// ---- 2. Component-keyed rows ----------------------------------------------
$targets = [
    ['config_plugins', 'plugin'], ['task_scheduled', 'component'], ['task_adhoc', 'component'],
    ['message_providers', 'component'], ['files', 'component'],
    ['external_functions', 'component'], ['external_services', 'component'],
];
foreach ($targets as [$table, $col]) {
    if (!$dbman->table_exists(new xmldb_table($table))) { continue; }
    $n = $DB->count_records_select($table, "{$col} = ?", [$from]);
    if ($n > 0) {
        echo "  {$table}.{$col}: {$n} row(s) {$from} -> {$to}\n";
        if ($run) { $DB->set_field_select($table, $col, $to, "{$col} = ?", [$from]); }
    }
}

// ---- 3. Capabilities (optional; preserves role assignments) ---------------
if (!empty($options['migrate-caps'])) {
    $caps = $DB->get_records_select('capabilities', "name LIKE ?", [$from . ':%']);
    foreach ($caps as $cap) {
        $newname = str_replace($from . ':', $to . ':', $cap->name);
        $rc = $DB->count_records('role_capabilities', ['capability' => $cap->name]);
        echo "  capability {$cap->name} -> {$newname} ({$rc} role_capabilities rows)\n";
        if ($run) {
            $DB->set_field('capabilities', 'name', $newname, ['id' => $cap->id]);
            $DB->set_field('capabilities', 'component', $to, ['id' => $cap->id]);
            $DB->set_field('role_capabilities', 'capability', $newname, ['capability' => $cap->name]);
        }
    }
}

echo $run ? "DONE. Run admin/cli/upgrade.php + purge_caches.php next.\n"
          : "DRY RUN complete. Re-run with --run to apply.\n";
exit(0);
