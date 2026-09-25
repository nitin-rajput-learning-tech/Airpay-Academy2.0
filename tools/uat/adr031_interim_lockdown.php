<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031 interim lockdown for UAT (approved by Nitin, 2026-09-25).
 *
 * Until the ADR-031 code fix ships, prohibit the P0 cross-tenant capabilities
 * (role escalation, account takeover, destructive resets, cross-tenant writes
 * and the pure cross-tenant "all" switches) for the tenant-admin role on UAT.
 * UAT holds an import of real production data, so a ZEEA or Public tester with
 * a tenant-admin account could otherwise reach Airpay employees' data.
 *
 * The prior permission of every capability is saved to a JSON file in
 * $CFG->dataroot, and --revert restores exactly that. Site admins are never
 * affected (they bypass capability checks).
 *
 * USAGE (on the UAT box):
 *   sudo -u www-data php adr031_interim_lockdown.php --i-am-uat --dry-run
 *   sudo -u www-data php adr031_interim_lockdown.php --i-am-uat --apply
 *   sudo -u www-data php adr031_interim_lockdown.php --i-am-uat --revert
 *
 * Options: --role=<shortname> (default: administrator, UAT's id-9 tenant-admin role).
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle5.2/public/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');

[$options] = cli_get_params(
    ['i-am-uat' => false, 'dry-run' => false, 'apply' => false, 'revert' => false, 'role' => 'administrator'], []);
if (empty($options['i-am-uat'])) {
    cli_error('Refusing to run without --i-am-uat.');
}
if (strpos($CFG->wwwroot, 'academy2.airpay.ninja') === false) {
    cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the UAT instance.");
}
$modes = array_filter([$options['dry-run'] ? 'dry-run' : null, $options['apply'] ? 'apply' : null,
    $options['revert'] ? 'revert' : null]);
if (count($modes) !== 1) {
    cli_error('Pass exactly one of --dry-run, --apply, --revert.');
}
$mode = reset($modes);

// P0 from docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md: escalation,
// takeover, destructive, cross-tenant writes, and the pure cross-tenant switches.
$caps = [
    'local/sentientia_roles:manage', 'local/sentientia_roles:assign',
    'local/sentientia_users:create', 'local/sentientia_users:edit',
    'local/sentientia_recompletion:manage', 'local/sentientia_recompletion:reset',
    'local/sentientia_courses:update', 'local/sentientia_courses:create',
    'local/sentientia_courses:visibility', 'local/sentientia_courses:enrol',
    'local/sentientia_learningpath:enrol', 'local/sentientia_learningpath:update',
    'local/sentientia_programs:enrol', 'local/sentientia_programs:update', 'local/sentientia_programs:create',
    'local/sentientia_classroom:update', 'local/sentientia_classroom:create',
    'local/sentientia_classroom:manage', 'local/sentientia_classroom:attendance',
    'local/sentientia_cart:manageprices', 'local/sentientia_cart:viewallorders',
    'local/sentientia_challenge:manage',
    'local/sentientia_emails:manage_templates', 'local/sentientia_notifications:manage',
    'local/sentientia_exams:manage', 'local/sentientia_recommendations:generate',
    'local/sentientia_skills:manage',
    'local/sentientia_aiquiz:manage_all', 'local/sentientia_authoring:manage_all',
    'local/sentientia_translate:manage_all', 'local/sentientia_live:manage_all',
    'local/sentientia_skillsai:manage_all', 'local/sentientia_request:viewall',
    'local/sentientia_leaderboard:viewall', 'local/sentientia_ai:viewledger',
];

global $DB;
$role = $DB->get_record('role', ['shortname' => $options['role']], '*', MUST_EXIST);
$sys = context_system::instance();
$statefile = $CFG->dataroot . '/adr031_interim_lockdown_' . $role->shortname . '.json';

if ($mode === 'revert') {
    if (!is_readable($statefile)) {
        cli_error("No saved state at {$statefile}; nothing to revert.");
    }
    $saved = json_decode(file_get_contents($statefile), true);
    foreach ($saved as $cap => $previous) {
        if ($previous === null) {
            unassign_capability($cap, $role->id, $sys->id);
            cli_writeln("revert {$cap}: removed (was inherit)");
        } else {
            assign_capability($cap, (int) $previous, $role->id, $sys->id, true);
            cli_writeln("revert {$cap}: restored {$previous}");
        }
    }
    $sys->mark_dirty();
    rename($statefile, $statefile . '.reverted-' . date('Ymd-His'));
    cli_writeln('Reverted ' . count($saved) . ' capabilities for role ' . $role->shortname . '.');
    exit(0);
}

if ($mode === 'apply' && is_readable($statefile)) {
    cli_error("A lockdown is already applied (state at {$statefile}); revert it first.");
}

$saved = [];
$skipped = [];
foreach ($caps as $cap) {
    if (!get_capability_info($cap)) {
        $skipped[] = $cap;
        continue;
    }
    $current = $DB->get_field('role_capabilities', 'permission',
        ['roleid' => $role->id, 'contextid' => $sys->id, 'capability' => $cap]);
    $saved[$cap] = ($current === false) ? null : (int) $current;
    cli_writeln(sprintf('%-48s current=%s -> PROHIBIT', $cap, $current === false ? 'inherit' : $current));
    if ($mode === 'apply') {
        assign_capability($cap, CAP_PROHIBIT, $role->id, $sys->id, true);
    }
}
if ($skipped) {
    cli_writeln('Not declared on this site (skipped): ' . implode(', ', $skipped));
}
if ($mode === 'apply') {
    file_put_contents($statefile, json_encode($saved, JSON_PRETTY_PRINT));
    $sys->mark_dirty();
    cli_writeln('Applied PROHIBIT to ' . count($saved) . " capabilities for role {$role->shortname}; state saved to {$statefile}.");
} else {
    cli_writeln('DRY RUN: ' . count($saved) . " capabilities would be prohibited for role {$role->shortname}.");
}
