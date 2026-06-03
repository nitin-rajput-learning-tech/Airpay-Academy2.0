<?php
// tools/rename/handover.php — ADR-022 DB hand-over (the DB half of a component rename).
//
// Pairs with codemod.php (the SOURCE half). Full batch procedure:
//   1. codemod.php --apply           — rename the source dir + all code references
//   2. deploy renamed source         — with a bumped version.php + a db/upgrade.php
//   3. handover.php ... --apply       — re-point every DB reference (this script)
//   4. admin/notifications (or admin/cli/upgrade.php) — runs the bumped upgrade, which
//      rebuilds the component classmap (get_all_component_hash) + re-registers the web
//      service from the NEW db/services.php
//
// Usage (DRY-RUN by default — prints what it would do, writes nothing):
//   php handover.php <moodle_root> <oldcomponent> <newcomponent>
//   php handover.php <moodle_root> <oldcomponent> <newcomponent> --apply
//
// Example:
//   php handover.php C:/xampp/htdocs/moodle5/public local_airpay_ratings local_sentientia_ratings --apply
//
// LESSONS BAKED IN from batch-1 (local_airpay_ratings -> local_sentientia_ratings):
//   * capabilities.COMPONENT must be re-pointed too — not just capabilities.name. Otherwise
//     update_capabilities() during the upgrade tries to INSERT the cap again and dies with
//     "Duplicate entry '<cap>' for key 'mdl_capa_nam_uix'".
//   * old external_functions / external_services rows must be DELETED (the upgrade recreates
//     them from the new db/services.php). Re-pointing their component would keep the old
//     classname and break the WS, so the broad component sweep EXCLUDES the WS tables.
//   * a broad sweep of every table with a `component` column catches the long tail
//     (task_scheduled, message_providers, etc.).
//   * core_component.php (the bootstrap classmap cache) is NOT cleared by purge_caches; the
//     version-bump upgrade in step 4 rebuilds it. Don't rely on a plain cache purge.

define('CLI_SCRIPT', true);

$root = $argv[1] ?? '';
$old  = $argv[2] ?? '';
$new  = $argv[3] ?? '';
$apply = in_array('--apply', $argv, true);

if ($root === '' || $old === '' || $new === '') {
    fwrite(STDERR, "Usage: php handover.php <moodle_root> <oldcomponent> <newcomponent> [--apply]\n");
    exit(2);
}

require(rtrim(str_replace('\\', '/', $root), '/') . '/config.php');
global $DB, $CFG;
$dbman = $DB->get_manager();

$mode = $apply ? 'APPLY' : 'DRY-RUN';
echo "=== ADR-022 DB hand-over [$mode]: $old -> $new ===\n";

// WS tables are handled by DELETE (old rows) + the upgrade (new rows), never swept.
$wstables = ['external_functions', 'external_services', 'external_services_functions'];

// ── 1. Tables. Derive old table names from the NEW plugin's install.xml (standard naming:
//        table name carries the component string), then rename each that still exists.
$plugintype = explode('_', $new, 2)[0];               // 'local'
$pluginname = substr($new, strlen($plugintype) + 1);  // 'sentientia_ratings'
$installxml = "$CFG->dirroot/$plugintype/$pluginname/db/install.xml";
$tables = [];
if (is_readable($installxml)) {
    $xml = simplexml_load_file($installxml);
    foreach ($xml->xpath('//TABLE') as $t) {
        $newtable = (string)$t['NAME'];
        $oldtable = str_replace($new, $old, $newtable);
        if ($oldtable !== $newtable) {
            $tables[$oldtable] = $newtable;
        }
    }
}
foreach ($tables as $oldtable => $newtable) {
    $oldx = new xmldb_table($oldtable);
    $newx = new xmldb_table($newtable);
    if ($dbman->table_exists($oldx) && !$dbman->table_exists($newx)) {
        echo "  table rename: mdl_$oldtable -> mdl_$newtable\n";
        if ($apply) { $dbman->rename_table($oldx, $newtable); }
    } else {
        echo "  table rename skipped: $oldtable (exists=" . ($dbman->table_exists($oldx) ? 'Y' : 'N')
            . "), $newtable (exists=" . ($dbman->table_exists($newx) ? 'Y' : 'N') . ")\n";
    }
}

// ── 2. config_plugins (settings + the version row that drives the upgrade).
$n = $DB->count_records('config_plugins', ['plugin' => $old]);
echo "  config_plugins: $n row(s)\n";
if ($apply && $n) { $DB->set_field('config_plugins', 'plugin', $new, ['plugin' => $old]); }

// ── 3 + 4. capabilities — BOTH name and component (see lesson above).
$caps = $DB->get_records_select('capabilities', $DB->sql_like('name', '?'), ["$old:%"]);
echo "  capabilities: " . count($caps) . " row(s) (name + component)\n";
if ($apply) {
    foreach ($caps as $cap) {
        $newname = $new . substr($cap->name, strlen($old)); // local_old:rate -> local_new:rate
        $DB->set_field('capabilities', 'name', $newname, ['id' => $cap->id]);
    }
    $DB->set_field('capabilities', 'component', $new, ['component' => $old]);
}

// ── 5. role_capabilities — the CROWN JEWEL: the actual access grants. Re-point by name.
//        $caps was read in step 3 BEFORE any rename, so it still holds the OLD names; and
//        role_capabilities still references the OLD names (step 3 only touched capabilities).
foreach ($caps as $cap) {
    $oldname = $cap->name;
    $newname = $new . substr($oldname, strlen($old));
    $cnt = $DB->count_records('role_capabilities', ['capability' => $oldname]);
    echo "  role_capabilities for $oldname: $cnt grant(s) -> $newname\n";
    if ($apply) {
        $DB->set_field('role_capabilities', 'capability', $newname, ['capability' => $oldname]);
    }
}

// ── 6. files.component.
$n = $DB->count_records('files', ['component' => $old]);
echo "  files.component: $n row(s)\n";
if ($apply && $n) { $DB->set_field('files', 'component', $new, ['component' => $old]); }

// ── 7. DELETE old web-service rows (the upgrade recreates them from the new db/services.php).
$oldfnnames = $DB->get_fieldset_select('external_functions', 'name', 'component = ?', [$old]);
echo "  external_functions to delete: " . count($oldfnnames) . "\n";
if ($apply && $oldfnnames) {
    [$insql, $inparams] = $DB->get_in_or_equal($oldfnnames);
    $DB->delete_records_select('external_services_functions', "functionname $insql", $inparams);
    $DB->delete_records('external_functions', ['component' => $old]);
}
$noldsvc = $DB->count_records('external_services', ['component' => $old]);
echo "  external_services to delete: $noldsvc\n";
if ($apply && $noldsvc) { $DB->delete_records('external_services', ['component' => $old]); }

// ── 8. Broad sweep: any OTHER table with a `component` column still holding the old value.
$cols = $DB->get_records_sql(
    "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'component'",
    [$CFG->dbname]
);
$swept = 0;
foreach ($cols as $c) {
    $full = $c->table_name;
    if (strpos($full, $CFG->prefix) !== 0) { continue; }
    $tbl = substr($full, strlen($CFG->prefix));
    if (in_array($tbl, $wstables, true)) { continue; } // WS handled in step 7
    try {
        $cnt = $DB->count_records_select($tbl, 'component = ?', [$old]);
        if ($cnt > 0) {
            echo "  sweep $tbl.component: $cnt row(s)\n";
            $swept += $cnt;
            if ($apply) { $DB->set_field($tbl, 'component', $new, ['component' => $old]); }
        }
    } catch (\Throwable $e) {
        // Not all tables with a 'component' column are writable plugin tables; skip safely.
    }
}

echo "=== hand-over " . ($apply ? "applied" : "dry-run complete (re-run with --apply)") . " ===\n";
echo "Next: deploy the renamed source (bumped version.php + db/upgrade.php), then run\n";
echo "admin/cli/upgrade.php so the classmap rebuilds and the web service re-registers.\n";
