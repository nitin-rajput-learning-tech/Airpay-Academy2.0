<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY switch of the dashboard blocks drawer for the 2026-11-02 theme evidence, tenant 1 only.
 *
 * The block calendar's day names are checked inside the drawer, which shows only where
 * sentientia.dashboard.blocksdrawer.enabled is ON (default OFF).
 *
 *   php blocksdrawer_flag.php save      record the current override rows of the key (scratch file)
 *   php blocksdrawer_flag.php apply     the drawer ON for tenant 1
 *   php blocksdrawer_flag.php restore   put tenant 1's row back as "save" saw it (or remove it)
 *   php blocksdrawer_flag.php status
 *
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set. Run with cwd = moodle5/public.
 *
 * @package    theme_sentientia
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
const KEY = 'sentientia.dashboard.blocksdrawer.enabled';
$saved = 'D:/Claude Local/kit-scratch/shots2/blocksdrawer-flag-saved.json';
$cmd = $argv[1] ?? 'status';
$admin = (int) $DB->get_field('user', 'id', ['username' => 'vp_siteadmin', 'deleted' => 0], MUST_EXIST);

$rows = function () use ($DB): array {
    return array_values(array_map(fn($r) => ['customer' => (int) $r->customer_id, 'tenant' => (int) $r->tenant_id,
        'on' => (int) $r->is_enabled], $DB->get_records('local_sentientia_feature_flags', ['flag_key' => KEY], 'id')));
};

if ($cmd === 'save') {
    if (file_exists($saved)) {
        fwrite(STDERR, "refused: {$saved} exists (a run was not restored); run restore first\n");
        exit(1);
    }
    @mkdir(dirname($saved), 0777, true);
    file_put_contents($saved, json_encode($rows(), JSON_PRETTY_PRINT));
} else if ($cmd === 'apply') {
    if (!file_exists($saved)) {
        fwrite(STDERR, "refused: run save first\n");
        exit(1);
    }
    feature_flags::set(KEY, TENANT, true, $admin, 'visual evidence 2026-11-02 (local)');
} else if ($cmd === 'restore') {
    if (!file_exists($saved)) {
        fwrite(STDERR, "nothing to restore\n");
        exit(1);
    }
    // Tenant 1 is the only scope this script writes: put its row back as it was, or remove it.
    $was = null;
    foreach (json_decode((string) file_get_contents($saved), true) ?: [] as $r) {
        if ((int) $r['tenant'] === TENANT && (int) $r['customer'] === 0) {
            $was = (bool) $r['on'];
        }
    }
    feature_flags::set(KEY, TENANT, $was, $admin, 'visual evidence 2026-11-02 (local): restored');
    rename($saved, $saved . '.restored-' . date('His'));
}
echo json_encode(['cmd' => $cmd, 'rows' => $rows()]) . "\n";
