<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * WF-004 (FOOLPROOF campaign 2026-06-10) — repair scheduled-task registrations
 * after the ADR-025 component rename.
 *
 * The rename handover re-pointed capabilities + web services but NOT
 * {task_scheduled}: rows still carried `\local_airpay_*` classnames whose
 * classes no longer exist, so those crons were silently dead; and the renamed
 * plugins' tasks were never (re)registered because plugin versions did not
 * change (Moodle only reconciles tasks on install/upgrade).
 *
 * This CLI:
 *   1. For every installed local_sentientia_* plugin that ships db/tasks.php,
 *      calls \core\task\manager::reset_scheduled_tasks_for_component() —
 *      inserts missing rows, updates schedules, removes rows the component no
 *      longer declares. (Admin schedule customisations on EXISTING rows are
 *      preserved by Moodle's reconciler.)
 *   2. Deletes orphan {task_scheduled} rows whose classname references the
 *      retired \local_airpay_* namespace (class no longer exists on disk).
 *   3. Reports (report-only) other component-bound tables still carrying
 *      local_airpay_* rows ({message_providers}, {event_handlers} if present)
 *      so follow-up repairs are visible.
 *
 * Default is a DRY RUN. Pass --apply to write.
 * Runs on local staging, the ninja sandbox, and live (deploy-packet step).
 *
 * @package local_sentientia_platform
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['apply' => false, 'help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)));
}
if ($options['help']) {
    cli_writeln('Repair scheduled-task registrations post component-rename. --apply to write (default dry-run).');
    exit(0);
}
$apply = (bool) $options['apply'];
cli_writeln('Mode: ' . ($apply ? 'APPLY' : 'DRY RUN (pass --apply to write)'));

global $DB, $CFG;

// ── 1. Re-register tasks for every sentientia component that declares them ──
$reset = 0;
foreach (glob($CFG->dirroot . '/local/sentientia_*/db/tasks.php') as $tasksfile) {
    $component = 'local_' . basename(dirname(dirname($tasksfile)));
    if ($apply) {
        \core\task\manager::reset_scheduled_tasks_for_component($component);
    }
    cli_writeln(($apply ? '  reconciled ' : '  would reconcile ') . $component);
    $reset++;
}
cli_writeln("Components with db/tasks.php: $reset");

// ── 2. Purge orphan \local_airpay_* task rows (classes no longer exist) ──
$like = $DB->sql_like('classname', ':cn');
$orphans = $DB->get_records_select('task_scheduled', $like, ['cn' => '%local_airpay%']);
foreach ($orphans as $o) {
    // Double-check the class really is gone before touching the row.
    $exists = class_exists(ltrim(str_replace('\\\\', '\\', $o->classname), '\\'));
    if ($exists) {
        cli_writeln("  SKIP (class exists): {$o->classname}");
        continue;
    }
    if ($apply) {
        $DB->delete_records('task_scheduled', ['id' => $o->id]);
    }
    cli_writeln(($apply ? '  purged orphan ' : '  would purge orphan ') . $o->classname);
}
cli_writeln('Orphan airpay task rows: ' . count($orphans));

// ── 2b. WF-005: customer_brand rows storing pre-rename URL paths ──
// The rename codemod rewrote code, not DB data: icon/start URLs saved as
// '/local/airpay_core/...' point at a component that no longer exists, so
// PWA manifest icons 404. Rewrite to the renamed plugin path.
$brandfixes = 0;
if ($DB->get_manager()->table_exists('local_sentientia_customer_brand')) {
    foreach ($DB->get_records('local_sentientia_customer_brand') as $row) {
        $dirty = false;
        foreach (['icon_192_url', 'icon_512_url', 'start_url'] as $f) {
            if (isset($row->$f) && str_contains($row->$f, '/local/airpay_core/')) {
                $row->$f = str_replace('/local/airpay_core/', '/local/sentientia_platform/', $row->$f);
                $dirty = true;
            }
        }
        if ($dirty) {
            if ($apply) {
                $row->timemodified = time();
                $DB->update_record('local_sentientia_customer_brand', $row);
            }
            cli_writeln(($apply ? '  fixed brand paths ' : '  would fix brand paths ')
                . "customer {$row->customerid}");
            $brandfixes++;
        }
    }
    if ($apply && $brandfixes > 0 && class_exists('\\local_sentientia_platform\\customer')) {
        // Invalidate the brand cache so the manifest re-renders fresh.
        \cache_helper::purge_by_definition('local_sentientia_platform', 'customer_brand');
    }
}
cli_writeln("Brand rows with stale paths: $brandfixes");

// ── 2c. WF-008a: orphan {message_providers} rows from pre-rename components ──
// Every message_send() walks ALL provider rows; rows whose component no longer
// exists carry stale capability strings (e.g. local/airpay_cart:view) and fire
// a debugging() backtrace PER MESSAGE PER ORPHAN — the compliance snapshot
// cron drowned in thousands of these. Purge providers whose component
// directory is gone (double-checked, same discipline as the §2 task purge).
$providerpurged = 0;
if ($DB->get_manager()->table_exists('message_providers')) {
    $orphanproviders = $DB->get_records_select('message_providers',
        $DB->sql_like('component', ':c'), ['c' => 'local_airpay%']);
    foreach ($orphanproviders as $prov) {
        if (\core_component::get_component_directory($prov->component) !== null) {
            cli_writeln("  SKIP provider {$prov->component}/{$prov->name} — component still installed");
            continue;
        }
        cli_writeln(($apply ? '  purged provider ' : '  would purge provider ')
            . "{$prov->component}/{$prov->name} (id {$prov->id})");
        if ($apply) {
            // Clean dependent preference rows first, then the provider row.
            $DB->delete_records_select('user_preferences',
                $DB->sql_like('name', ':p'), ['p' => 'message_provider_' . $prov->component . '_' . $prov->name . '%']);
            $DB->delete_records('config_plugins', ['plugin' => 'message',
                'name' => 'message_provider_' . $prov->component . '_' . $prov->name . '_enabled']);
            $DB->delete_records('message_providers', ['id' => $prov->id]);
        }
        $providerpurged++;
    }
    if ($apply && $providerpurged > 0) {
        \cache_helper::purge_all();  // provider map is cached aggressively
    }
}
cli_writeln("Orphan message_providers rows: $providerpurged");

// ── 2d. WF-008a: provider rows with stale pre-rename CAPABILITY strings ──
// The component column was renamed by the upgrade, but rows installed before
// the capability-string fix still say e.g. 'local/airpay_cart:view'. Every
// message_send() then calls has_capability() on a capability that no longer
// exists → debugging() backtrace per message per stale row. Rewrite to the
// renamed capability, but ONLY when the target exists in {capabilities}.
$capfixed = 0;
if ($DB->get_manager()->table_exists('message_providers')) {
    $stalecaps = $DB->get_records_select('message_providers',
        $DB->sql_like('capability', ':c'), ['c' => 'local/airpay\_%']);
    foreach ($stalecaps as $prov) {
        $newcap = preg_replace('~^local/airpay_~', 'local/sentientia_', $prov->capability);
        if (!$DB->record_exists('capabilities', ['name' => $newcap])) {
            cli_writeln("  REPORT: {$prov->component}/{$prov->name} capability "
                . "'{$prov->capability}' stale but target '$newcap' not defined — left as-is");
            continue;
        }
        cli_writeln(($apply ? '  rewrote capability ' : '  would rewrite capability ')
            . "{$prov->component}/{$prov->name}: {$prov->capability} -> $newcap");
        if ($apply) {
            $DB->set_field('message_providers', 'capability', $newcap, ['id' => $prov->id]);
        }
        $capfixed++;
    }
    if ($apply && $capfixed > 0) {
        \cache_helper::purge_all();
    }
}
cli_writeln("Provider rows with stale capability strings: $capfixed");

// ── 3. Report-only: other component-bound residue ──
foreach (['event' => 'component'] as $table => $col) {
    try {
        if (!$DB->get_manager()->table_exists($table)) {
            continue;
        }
        $n = $DB->count_records_select($table, $DB->sql_like($col, ':c'), ['c' => 'local_airpay%']);
        if ($n > 0) {
            cli_writeln("REPORT: {$table} still has $n local_airpay_* rows (separate repair needed)");
        }
    } catch (\Throwable $e) {
        // Table shape differs across versions — report-only, never fatal.
    }
}

// ── 4. Verify ──
$stillstale = $DB->count_records_select('task_scheduled',
    $DB->sql_like('classname', ':cn'), ['cn' => '%local_airpay%']);
$sentientia = $DB->count_records_select('task_scheduled',
    $DB->sql_like('classname', ':cn'), ['cn' => '%local_sentientia%']);
cli_writeln("task_scheduled now: sentientia=$sentientia stale-airpay=$stillstale");
exit(0);
