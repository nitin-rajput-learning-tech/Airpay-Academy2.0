<?php
// This file is part of Sentientia LMS. GNU GPL v3 or later.

/**
 * Sentientia LMS - reconcile external_functions/external_services WS descriptions
 * after a Class-B relabel (ADR-025 follow-up).
 *
 * relabel_plugin.php updates external_functions/external_services by the *component*
 * column only, leaving the WS function *name* column stale - e.g.
 * name='local_airpay_courses_toggle_visibility' under component='local_sentientia_courses'.
 * The rebuilt AMD bundles call the NEW local_sentientia_* WS names, so the stale
 * registrations produce "invalid function" at runtime on the affected AJAX actions.
 *
 * This CLI calls Moodle's own external_update_descriptions($component) for every
 * component that still carries a stale airpay-named WS function. That reconciles a
 * component's WS rows against its on-disk db/services.php: it INSERTs the new
 * local_sentientia_* functions and DELETEs the orphaned local_airpay_* ones (plus
 * their external_services_functions join rows). Idempotent + safe to re-run.
 *
 * This is also the documented live-cutover remediation step: run it on the live
 * instance immediately after the rename batch + admin/cli/upgrade.php, before
 * serving the renamed plugins, so 2,888 live users' AJAX actions keep working.
 *
 * Usage:
 *   php local/sentientia_core/cli/rebuild_ws_descriptions.php            # dry-run
 *   php local/sentientia_core/cli/rebuild_ws_descriptions.php --run      # apply
 *   php local/sentientia_core/cli/rebuild_ws_descriptions.php --component=local_sentientia_courses --run
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/upgradelib.php');

global $DB, $CFG;

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'component' => '', 'run' => false],
    ['h' => 'help']
);

if (!empty($options['help'])) {
    echo "Reconcile external_functions/external_services after a Class-B relabel (ADR-025).\n\n";
    echo "Calls external_update_descriptions() per component to delete stale airpay-named\n";
    echo "WS functions and (re)insert the on-disk local_sentientia_* ones.\n\n";
    echo "  --component=X   only this component (default: auto-discover all stale)\n";
    echo "  --run           apply the change (default: dry-run)\n";
    echo "  -h, --help      show this help\n\n";
    echo "Examples:\n";
    echo "  php local/sentientia_core/cli/rebuild_ws_descriptions.php\n";
    echo "  php local/sentientia_core/cli/rebuild_ws_descriptions.php --run\n";
    exit(0);
}

$run = !empty($options['run']);

// A component needs reconciling when any of its registered external_functions still
// carries a pre-rename airpay name. paygw_airpay (external payment gateway) is kept
// by decision and has no WS functions of this shape; it is guarded explicitly anyway.
$stalewhere = "name LIKE 'local_airpay_%' OR name LIKE 'block_airpay_%' OR name LIKE 'quizaccess_airpay_%'";

if ($options['component'] !== '') {
    $components = [$options['component']];
} else {
    $components = $DB->get_fieldset_sql(
        "SELECT DISTINCT component FROM {external_functions} WHERE {$stalewhere} ORDER BY component"
    );
}

// Never touch the intentionally-kept external payment gateway.
$components = array_values(array_filter($components, function($c) {
    return $c !== '' && strpos($c, 'paygw_airpay') === false;
}));

if (!$components) {
    echo "No components with stale airpay-named WS functions. Nothing to do.\n";
    exit(0);
}

$before = (int)$DB->count_records_select('external_functions', $stalewhere);
echo ($run ? "APPLY" : "DRY RUN") . " - " . count($components) . " component(s), {$before} stale WS function row(s):\n";

foreach ($components as $component) {
    $stale = (int)$DB->count_records_select('external_functions',
        "component = ? AND ({$stalewhere})", [$component]);
    // Guard: the component MUST have db/services.php on disk. external_update_descriptions
    // deletes every WS row for a component whose services.php is absent - that would wipe,
    // not reconcile. Skip (and warn) rather than destroy.
    $dir = core_component::get_component_directory($component);
    $hassvc = $dir && file_exists($dir . '/db/services.php');
    printf("  %-36s stale=%-3d services.php=%s%s\n",
        $component, $stale, $hassvc ? 'yes' : 'NO',
        $hassvc ? '' : '  (SKIP - no db/services.php on disk)');
    if ($run && $hassvc) {
        external_update_descriptions($component);
    }
}

if ($run) {
    // Refresh the WS catalogue + JS revision so callers see the new function names.
    purge_all_caches();
    $after = (int)$DB->count_records_select('external_functions', $stalewhere);
    echo "\nstale airpay WS functions: {$before} -> {$after} (expect 0)\n";
    echo ($after === 0 ? "DONE - all reconciled.\n" : "WARNING - {$after} still stale (check the SKIP lines above).\n");
} else {
    echo "\nWould reconcile {$before} stale airpay WS function row(s). Re-run with --run to apply.\n";
}
exit(0);
