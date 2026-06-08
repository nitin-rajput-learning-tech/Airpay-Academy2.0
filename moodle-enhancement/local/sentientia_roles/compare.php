<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Side-by-side role compare — Phase 4 B.11.
 *
 * Pick 2 roles, see capability-by-capability diff:
 *   - both allow
 *   - both deny / inherit
 *   - left allows, right doesn't
 *   - right allows, left doesn't
 *
 * @package local_sentientia_roles
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$left  = optional_param('left',  0, PARAM_INT);
$right = optional_param('right', 0, PARAM_INT);

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_roles/compare.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Compare roles');
$PAGE->set_heading('Compare roles');
require_capability('local/sentientia_roles:view', $ctx);

// Get list of all roles for the picker.
$roles = $DB->get_records('role', null, 'sortorder ASC', 'id, shortname, name, archetype');

$role_options = [['value' => 0, 'label' => '— pick a role —']];
foreach ($roles as $r) {
    $role_options[] = [
        'value' => $r->id,
        'label' => ($r->name ?: $r->shortname) . ' (' . $r->shortname . ')',
    ];
}

$compare_data = null;
if ($left > 0 && $right > 0) {
    $role_l = $roles[$left]  ?? null;
    $role_r = $roles[$right] ?? null;
    if ($role_l && $role_r) {
        // Build union of all capabilities mentioned in either role.
        $caps_l = $DB->get_records('role_capabilities',
            ['roleid' => $left], '', 'capability, permission');
        $caps_r = $DB->get_records('role_capabilities',
            ['roleid' => $right], '', 'capability, permission');

        $cap_l_map = [];
        foreach ($caps_l as $c) $cap_l_map[$c->capability] = (int) $c->permission;
        $cap_r_map = [];
        foreach ($caps_r as $c) $cap_r_map[$c->capability] = (int) $c->permission;

        $all_caps = array_unique(array_merge(
            array_keys($cap_l_map), array_keys($cap_r_map)));
        sort($all_caps);

        $perm_label = function(?int $p): string {
            if ($p === null)            return 'inherit';
            if ($p === CAP_ALLOW)       return 'allow';
            if ($p === CAP_PREVENT)     return 'prevent';
            if ($p === CAP_PROHIBIT)    return 'prohibit';
            return (string) $p;
        };

        $perm_css = function(?int $p): string {
            if ($p === CAP_ALLOW)    return 'bg-success';
            if ($p === CAP_PREVENT)  return 'bg-warning text-dark';
            if ($p === CAP_PROHIBIT) return 'bg-danger';
            return 'bg-secondary';
        };

        $rows = [];
        $stats = ['both_allow' => 0, 'both_block' => 0,
                  'only_left' => 0, 'only_right' => 0, 'diff_other' => 0];
        foreach ($all_caps as $cap) {
            $lp = $cap_l_map[$cap] ?? null;
            $rp = $cap_r_map[$cap] ?? null;

            $diff_kind = 'same';
            if ($lp === CAP_ALLOW && $rp === CAP_ALLOW) {
                $stats['both_allow']++;
            } else if (($lp === null && $rp === null)
                || ($lp !== CAP_ALLOW && $rp !== CAP_ALLOW)) {
                $stats['both_block']++;
            } else if ($lp === CAP_ALLOW && $rp !== CAP_ALLOW) {
                $stats['only_left']++;
                $diff_kind = 'only_left';
            } else if ($lp !== CAP_ALLOW && $rp === CAP_ALLOW) {
                $stats['only_right']++;
                $diff_kind = 'only_right';
            } else {
                $stats['diff_other']++;
                $diff_kind = 'diff_other';
            }

            $rows[] = [
                'capability' => $cap,
                'left_label' => $perm_label($lp),
                'right_label' => $perm_label($rp),
                'left_css'   => $perm_css($lp),
                'right_css'  => $perm_css($rp),
                'is_diff'    => $diff_kind !== 'same',
                'diff_kind'  => $diff_kind,
            ];
        }

        $compare_data = [
            'left_name'  => $role_l->name ?: $role_l->shortname,
            'right_name' => $role_r->name ?: $role_r->shortname,
            'rows'       => $rows,
            'total_caps' => count($rows),
            'stats'      => $stats,
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_roles/compare', [
    'role_options'  => $role_options,
    'left'          => $left,
    'right'         => $right,
    'has_compare'   => $compare_data !== null,
    'compare'       => $compare_data,
    'export_url'    => $compare_data
        ? (new moodle_url('/local/sentientia_roles/compare_export.php',
            ['left' => $left, 'right' => $right]))->out(false)
        : '',
]);
echo $OUTPUT->footer();
