<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY flag switch for the ui-fix-request evidence (2026-10-19), tenant 1 only.
 *
 * "Request enrolment" shows only where a request is the learner's way in. A free course in a tenant with one-click
 * enrolment is enrolled at once, so it never shows the button (by design), and locally tenants 1 and 177 have
 * sentientia.catalog.free_oneclick_enrol.enabled ON. The evidence therefore needs, for tenant 1 and for the run only:
 * sentientia.request.catalog_button ON and sentientia.catalog.free_oneclick_enrol.enabled OFF.
 *
 *   php fix_request_flags.php save      record the current override rows of both keys (scratch file)
 *   php fix_request_flags.php apply     catalog_button ON and one-click OFF, for tenant 1
 *   php fix_request_flags.php restore   put back exactly the rows "save" recorded (and remove any it did not see)
 *   php fix_request_flags.php status
 *
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');

use local_sentientia_platform\feature_flags;

global $CFG, $DB;
$host = parse_url($CFG->wwwroot, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1'], true) || empty($CFG->noemailever)) {
    fwrite(STDERR, "refused: run only on the local site with noemailever set\n");
    exit(1);
}

const TENANT = 1;
const KEYS = ['sentientia.request.catalog_button', 'sentientia.catalog.free_oneclick_enrol.enabled'];
$saved = 'D:/Claude Local/kit-scratch/shots2/request-flags-saved.json';
$cmd = $argv[1] ?? 'status';
$admin = (int) $DB->get_field('user', 'id', ['username' => 'vp_siteadmin', 'deleted' => 0], MUST_EXIST);

$rows = function (string $key) use ($DB): array {
    return array_values(array_map(fn($r) => ['customer' => (int) $r->customer_id, 'tenant' => (int) $r->tenant_id,
        'on' => (int) $r->is_enabled], $DB->get_records('local_sentientia_feature_flags', ['flag_key' => $key], 'id')));
};

if ($cmd === 'save') {
    if (file_exists($saved)) {
        fwrite(STDERR, "refused: {$saved} exists (a run was not restored); run restore first\n");
        exit(1);
    }
    $state = [];
    foreach (KEYS as $key) {
        $state[$key] = $rows($key);
    }
    file_put_contents($saved, json_encode($state, JSON_PRETTY_PRINT));
} else if ($cmd === 'apply') {
    if (!file_exists($saved)) {
        fwrite(STDERR, "refused: run save first\n");
        exit(1);
    }
    feature_flags::set(KEYS[0], TENANT, true, $admin, 'visual evidence 2026-10-19 (local)');
    feature_flags::set(KEYS[1], TENANT, false, $admin, 'visual evidence 2026-10-19 (local)');
} else if ($cmd === 'restore') {
    if (!file_exists($saved)) {
        fwrite(STDERR, "nothing to restore\n");
        exit(1);
    }
    $state = json_decode((string) file_get_contents($saved), true);
    foreach (KEYS as $key) {
        // Tenant 1 is the only scope this script writes: put its row back as it was, or remove it.
        $was = null;
        foreach ($state[$key] ?? [] as $r) {
            if ((int) $r['tenant'] === TENANT && (int) $r['customer'] === 0) {
                $was = (bool) $r['on'];
            }
        }
        feature_flags::set($key, TENANT, $was, $admin, 'visual evidence 2026-10-19 (local): restored');
    }
    $after = [];
    foreach (KEYS as $key) {
        $after[$key] = $rows($key);
    }
    if ($after == $state) {
        rename($saved, $saved . '.restored-' . date('Ymd-His'));
        echo "restored: identical to the saved rows\n";
    } else {
        echo "WARNING: rows differ from the saved state\n" . json_encode(['saved' => $state, 'now' => $after]) . "\n";
    }
} else if ($cmd !== 'status') {
    fwrite(STDERR, "usage: fix_request_flags.php save|apply|restore|status\n");
    exit(1);
}

foreach (KEYS as $key) {
    echo $key . ' rows=' . json_encode($rows($key)) . ' t1=' . var_export(feature_flags::is_enabled_for_tenant($key, TENANT), true)
        . ' t77=' . var_export(feature_flags::is_enabled_for_tenant($key, 77), true)
        . ' t177=' . var_export(feature_flags::is_enabled_for_tenant($key, 177), true) . "\n";
}
