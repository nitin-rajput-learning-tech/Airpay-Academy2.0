<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Export role-compare diff as YAML — Phase 4 B.11.
 *
 * Used to version-control role definitions in a config repo.
 *
 * @package local_sentientia_roles
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$left  = required_param('left',  PARAM_INT);
$right = required_param('right', PARAM_INT);

$ctx = context_system::instance();
require_capability('local/sentientia_roles:view', $ctx);

$role_l = $DB->get_record('role', ['id' => $left],  '*', MUST_EXIST);
$role_r = $DB->get_record('role', ['id' => $right], '*', MUST_EXIST);

$caps_l = $DB->get_records('role_capabilities',
    ['roleid' => $left], '', 'capability, permission');
$caps_r = $DB->get_records('role_capabilities',
    ['roleid' => $right], '', 'capability, permission');

$cap_l_map = []; foreach ($caps_l as $c) $cap_l_map[$c->capability] = (int) $c->permission;
$cap_r_map = []; foreach ($caps_r as $c) $cap_r_map[$c->capability] = (int) $c->permission;

$perm_label = function(?int $p): string {
    return match ($p) {
        CAP_ALLOW    => 'allow',
        CAP_PREVENT  => 'prevent',
        CAP_PROHIBIT => 'prohibit',
        null         => 'inherit',
        default      => 'unknown',
    };
};

// Build the YAML body.
$yaml = "# Role comparison export\n"
      . "# Generated: " . date('c') . "\n"
      . "# Source: sentientia_roles/compare.php\n\n"
      . "role_a:\n"
      . "  id:        $role_l->id\n"
      . "  shortname: $role_l->shortname\n"
      . "  name:      \"" . str_replace('"', '\\"', $role_l->name ?: $role_l->shortname) . "\"\n"
      . "  archetype: $role_l->archetype\n\n"
      . "role_b:\n"
      . "  id:        $role_r->id\n"
      . "  shortname: $role_r->shortname\n"
      . "  name:      \"" . str_replace('"', '\\"', $role_r->name ?: $role_r->shortname) . "\"\n"
      . "  archetype: $role_r->archetype\n\n"
      . "# Capability diff (only differences shown).\n"
      . "# Format: capability_name: { a: <perm>, b: <perm> }\n"
      . "capability_diff:\n";

$all_caps = array_unique(array_merge(
    array_keys($cap_l_map), array_keys($cap_r_map)));
sort($all_caps);

$diff_count = 0;
foreach ($all_caps as $cap) {
    $lp = $cap_l_map[$cap] ?? null;
    $rp = $cap_r_map[$cap] ?? null;
    if ($lp === $rp) continue;  // same perm — skip from diff
    $yaml .= "  \"$cap\":\n"
           . "    a: " . $perm_label($lp) . "\n"
           . "    b: " . $perm_label($rp) . "\n";
    $diff_count++;
}

if ($diff_count === 0) {
    $yaml .= "  # (no differences — roles are identical)\n";
}

$yaml .= "\n# total_differences: $diff_count\n";

// Send as download.
$filename = sprintf('roles_compare_%s_vs_%s_%s.yaml',
    $role_l->shortname, $role_r->shortname, date('Y-m-d'));
header('Content-Type: application/x-yaml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $yaml;
exit;
