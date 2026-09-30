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
        if ($oldt === $newt) { echo "  table {$oldt}: same name (brand-neutral), skip\n"; continue; }
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

// ---- 1b. Message-provider preference keys (2026-09-29) ----------------------
// Step 2 relabels {message_providers}.component, but the provider's site defaults
// ({config_plugins} plugin 'message') and each user's choice ({user_preferences})
// are keyed by NAMES that embed the component:
//   <processor>_provider_<component>_<name>_locked   (message_send() throws if missing)
//   message_provider_<component>_<name>_enabled      (site default and user choice)
//   <component>_<name>_disable                       (admin's site-wide off switch)
// Rename the exact keys of this component's providers (never by prefix:
// local_airpay_X_ is also the start of local_airpay_X_Y_...). An existing key
// under the new name is kept and the old one left. Only the legacy processors
// whose _locked key this run moves are carried into the new _enabled (their locked
// value and enabled membership travel together): merged into it when it exists;
// when it does not, the old key is renamed if that is every member, else just
// those processors are written under the new name and the old key is left. A user who
// already has the new name keeps it; their legacy row is left and reported.
// This step can only rename what exists. Afterwards,
// local/sentientia_platform/cli/repair_task_registrations.php --apply is REQUIRED:
// its step 2e (message_pref_repair) copies what is still stranded and writes
// db/messages.php defaults for any provider still missing a _locked key, and its
// step 4 exits 1 if any provider remains broken. It copies; it never deletes
// the legacy keys this step leaves behind.
if ($dbman->table_exists(new xmldb_table('message_providers'))) {
    [$insql, $inparams] = $DB->get_in_or_equal([$from, $to]);
    $provnames = array_unique($DB->get_fieldset_select('message_providers', 'name', "component {$insql}", $inparams));
    $procnames = $DB->get_fieldset_select('message_processors', 'name', '1 = 1');
    $configmoved = 0;
    $haskey = function (string $name) use ($DB): bool {
        return $DB->record_exists('config_plugins', ['plugin' => 'message', 'name' => $name]);
    };
    $split = function ($v): array {
        return array_values(array_filter(array_map('trim', explode(',', (string) $v)), 'strlen'));
    };
    foreach ($provnames as $pname) {
        $movedlocks = [];
        $pairs = [];
        foreach ($procnames as $proc) {
            $pairs[$proc] = ["{$proc}_provider_{$from}_{$pname}_locked", "{$proc}_provider_{$to}_{$pname}_locked"];
        }
        $pairs['_disable'] = ["{$from}_{$pname}_disable", "{$to}_{$pname}_disable"];
        foreach ($pairs as $proc => [$oldkey, $newkey]) {
            if (!$haskey($oldkey)) {
                continue;
            }
            if ($haskey($newkey)) {
                echo "  message default {$newkey}: already set (kept; {$oldkey} left)\n";
                continue;
            }
            echo "  message default {$oldkey} -> {$newkey}\n";
            if ($run) {
                $DB->set_field('config_plugins', 'name', $newkey, ['plugin' => 'message', 'name' => $oldkey]);
            }
            $configmoved++;
            if ($proc !== '_disable') {
                $movedlocks[] = (string) $proc;
            }
        }
        // _enabled after the _locked keys: it carries only the processors whose
        // _locked key this run moved, so a lock and its enabled membership always
        // travel together (a processor whose new _locked key was already there, or
        // has no legacy one, is not put into the enabled list by this step).
        $oldkey = "message_provider_{$from}_{$pname}_enabled";
        $newkey = "message_provider_{$to}_{$pname}_enabled";
        $oldval = $DB->get_field('config_plugins', 'value', ['plugin' => 'message', 'name' => $oldkey]);
        if ($oldval !== false) {
            $newval = $DB->get_field('config_plugins', 'value', ['plugin' => 'message', 'name' => $newkey]);
            $oldlist = $split($oldval);
            $add = array_values(array_intersect($oldlist, $movedlocks));
            if ($newval === false) {
                if ($add === $oldlist) {
                    // Every legacy member's lock moved: the whole key goes across.
                    echo "  message default {$oldkey} -> {$newkey}\n";
                    if ($run) {
                        $DB->set_field('config_plugins', 'name', $newkey, ['plugin' => 'message', 'name' => $oldkey]);
                    }
                    $configmoved++;
                } else if ($add) {
                    // Only some did: write just those and leave the old key alone.
                    $value = implode(',', $add);
                    echo "  message default {$newkey}: new '{$value}' (legacy " . implode(',', $add)
                        . " only, the processors whose lock moved; {$oldkey} left)\n";
                    if ($run) {
                        $DB->insert_record('config_plugins',
                            (object) ['plugin' => 'message', 'name' => $newkey, 'value' => $value]);
                    }
                    $configmoved++;
                } else {
                    echo "  message default {$oldkey}: none of its processors had a lock moved (left)\n";
                }
            } else {
                $current = $split($newval);
                $merged = array_values(array_unique(array_merge($current, $add)));
                if ($merged !== $current) {
                    $value = implode(',', $merged);
                    echo "  message default {$newkey}: '{$newval}' -> '{$value}'"
                        . ' (merged legacy ' . implode(',', $add) . "; {$oldkey} left)\n";
                    if ($run) {
                        $DB->set_field('config_plugins', 'value', $value, ['plugin' => 'message', 'name' => $newkey]);
                    }
                    $configmoved++;
                } else {
                    echo "  message default {$newkey}: already set (kept; {$oldkey} left)\n";
                }
            }
        }
        // Per-user choices use the message_provider_<component>_<name>_enabled name.
        $olduser = "message_provider_{$from}_{$pname}_enabled";
        $newuser = "message_provider_{$to}_{$pname}_enabled";
        $total = $DB->count_records('user_preferences', ['name' => $olduser]);
        if ($total > 0) {
            // Only rows whose user has no new-name row can move; the rest are leftovers.
            $movable = (int) $DB->count_records_sql(
                "SELECT COUNT(1) FROM {user_preferences} o
                  WHERE o.name = :old
                    AND NOT EXISTS (SELECT 1 FROM {user_preferences} n WHERE n.userid = o.userid AND n.name = :new)",
                ['old' => $olduser, 'new' => $newuser]);
            if ($movable > 0) {
                echo "  user_preferences {$olduser}: {$movable} row(s) -> {$newuser}\n";
            }
            if ($total > $movable) {
                echo "  user_preferences {$olduser}: " . ($total - $movable) . ' row(s) left'
                    . " (those users already have {$newuser}; kept)\n";
            }
            if ($run && $movable > 0) {
                // Row by row: an UPDATE ... WHERE userid NOT IN (SELECT ... FROM the same
                // table) is refused by MySQL (error 1093).
                foreach ($DB->get_records('user_preferences', ['name' => $olduser], 'id', 'id, userid') as $pref) {
                    if (!$DB->record_exists('user_preferences', ['userid' => $pref->userid, 'name' => $newuser])) {
                        $DB->set_field('user_preferences', 'name', $newuser, ['id' => $pref->id]);
                        mark_user_preferences_changed($pref->userid);  // live sessions reload preferences
                    }
                }
            }
        }
    }
    if ($run && $configmoved > 0) {
        // set_field bypasses set_config(), so invalidate the cached 'message' config
        // (what core's message_update_providers() does), not every cache on the site.
        cache_helper::invalidate_by_definition('core', 'config', [], 'message');
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

// ---- 2b. Reconcile external WS descriptions (ADR-025 follow-up) ------------
// The component-column UPDATE above repoints external_functions/services rows to the
// new component, but their NAME column keeps the old airpay_* WS function name (e.g.
// name='local_airpay_X_do' under component='local_sentientia_X'). Rebuilt AMD bundles
// call the NEW name, so the stale registration causes "invalid function" at runtime.
// external_update_descriptions() reconciles a component's WS rows against its on-disk
// db/services.php: insert the new local_sentientia_* names, delete the orphaned
// local_airpay_* ones (+ their external_services_functions joins). Idempotent.
// (Standalone equivalent for already-relabeled instances: cli/rebuild_ws_descriptions.php.)
$svcfile = core_component::get_component_directory($to) . '/db/services.php';
if ($svcfile && file_exists($svcfile)) {
    require_once($CFG->libdir . '/upgradelib.php');
    echo "  external WS descriptions: reconciling {$to} from db/services.php\n";
    if ($run) { external_update_descriptions($to); }
}

// ---- 3. Capabilities (optional; preserves role assignments) ---------------
// Capability NAMES use Moodle's 'type/plugin:cap' form (e.g. local/airpay_X:view)
// - a SLASH after the type, NOT the underscore frankenstyle component. So we must
// NOT match/replace on $from (the underscore component). Match by the exact
// {capabilities}.component column instead, then rename via the plugin-name substring
// (airpay_X -> sentientia_X) so 'local/airpay_X:cap' -> 'local/sentientia_X:cap'.
// (Placeholders use the literal letter X so this file's own driver sed never rewrites them.)
// role_capabilities (role definitions AND context-level overrides, matched by the old
// capability string) are repointed FIRST, before the {capabilities} row is renamed.
if (!empty($options['migrate-caps'])) {
    $needle = preg_replace('/^[a-z]+_/', '', $from);  // local_airpay_X -> airpay_X
    $repl   = preg_replace('/^[a-z]+_/', '', $to);    // local_sentientia_X -> sentientia_X
    $caps = $DB->get_records('capabilities', ['component' => $from]);
    foreach ($caps as $cap) {
        $newname = str_replace($needle, $repl, $cap->name);
        $rc = $DB->count_records('role_capabilities', ['capability' => $cap->name]);
        echo "  capability {$cap->name} -> {$newname} ({$rc} role_capabilities rows)\n";
        if ($run) {
            $DB->set_field('role_capabilities', 'capability', $newname, ['capability' => $cap->name]);
            $DB->set_field('capabilities', 'name', $newname, ['id' => $cap->id]);
            $DB->set_field('capabilities', 'component', $to, ['id' => $cap->id]);
        }
    }
    if (!$caps) { echo "  (no {capabilities} rows with component={$from} - check access.php loaded)\n"; }
}

echo $run ? "DONE. Next: admin/cli/upgrade.php + purge_caches.php, then (REQUIRED)\n"
          . "  php local/sentientia_platform/cli/repair_task_registrations.php --apply\n"
          . "  - it backfills message-provider defaults this step could not move, and exits 1\n"
          . "    if any provider is still missing one (message_send() would throw for it).\n"
          : "DRY RUN complete. Re-run with --run to apply.\n";
exit(0);
