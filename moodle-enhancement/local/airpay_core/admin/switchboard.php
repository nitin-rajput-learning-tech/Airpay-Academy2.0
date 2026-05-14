<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The Switchboard — Phase A0 (2026-05-14).
 *
 * Super-admin page for toggling every feature flag in the platform.
 * One screen, every capability, audit-logged per change. The user's
 * Phase A0 directive: "AI and all major capabilities in the platform
 * should be configurable by super admin, should be able to toggle
 * on/off without breaking the platform."
 *
 * URL: /local/airpay_core/admin/switchboard.php
 *
 * Access: site admin only (moodle/site:config). Per-tenant override
 * editing requires super admin too — no tenant-admin shortcut. We
 * may relax this in Phase Δ when tenant admins manage their own
 * branding + tenant-local flags.
 *
 * @package local_airpay_core
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Which tenant are we editing? 0 = global; otherwise a known tenant root.
$tenant_id = optional_param('tenant', 0, PARAM_INT);
$valid_tenants = [0, 1, 77, 177];
if (!in_array($tenant_id, $valid_tenants, true)) {
    $tenant_id = 0;
}

global $DB, $OUTPUT, $USER;

// Handle POST (toggle one or more flags).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $reason = optional_param('reason', '', PARAM_TEXT);
    $changes_param = optional_param('changes', '{}', PARAM_RAW);
    $changes = json_decode($changes_param, true);

    if (!is_array($changes) || empty($changes)) {
        redirect(
            new moodle_url('/local/airpay_core/admin/switchboard.php',
                ['tenant' => $tenant_id]),
            get_string('switchboard_no_changes', 'local_airpay_core'),
            2,
            \core\output\notification::NOTIFY_INFO
        );
    }

    $applied = 0;
    foreach ($changes as $key => $new_value) {
        // new_value is a string from JSON: "true", "false", or "default".
        $val = null;
        if ($new_value === 'true' || $new_value === true) {
            $val = true;
        } else if ($new_value === 'false' || $new_value === false) {
            $val = false;
        } else if ($new_value === 'default' || $new_value === null) {
            $val = null;
        }
        try {
            \local_airpay_core\feature_flags::set(
                (string) $key, $tenant_id, $val, (int) $USER->id, $reason);
            $applied++;
        } catch (\moodle_exception $e) {
            // Unknown key — log and continue.
            debugging("Switchboard: skipping unknown flag '$key': "
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    redirect(
        new moodle_url('/local/airpay_core/admin/switchboard.php',
            ['tenant' => $tenant_id]),
        get_string('switchboard_applied', 'local_airpay_core', $applied),
        2,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Render the page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_core/admin/switchboard.php',
    ['tenant' => $tenant_id]));
$PAGE->set_title(get_string('switchboard_pagetitle', 'local_airpay_core'));
$PAGE->set_heading(get_string('switchboard_pagetitle', 'local_airpay_core'));
$PAGE->set_pagelayout('admin');

// Load all flags + their resolved state for this tenant.
$all_flags = \local_airpay_core\feature_flags::all($tenant_id);

// Group by category for the template.
$by_category = [];
foreach ($all_flags as $key => $flag) {
    $cat = $flag['category'];
    if (!isset($by_category[$cat])) {
        $by_category[$cat] = [
            'category' => $cat,
            'category_label' => get_string('flag_category_' . $cat,
                'local_airpay_core', null, true) ?: ucfirst($cat),
            'flags' => [],
        ];
    }

    // What does the toggle currently render?
    //   - if has_tenant_override: state = override value
    //   - elif has_global_override: state = global value (but for the
    //     tenant view, we render as "inherited global")
    //   - else: state = default
    $tri_state = 'default';
    if ($flag['has_tenant_override'] && $tenant_id > 0) {
        $tri_state = $flag['resolved'] ? 'on' : 'off';
    } else if ($flag['has_global_override'] && $tenant_id === 0) {
        $tri_state = $flag['resolved'] ? 'on' : 'off';
    }

    $by_category[$cat]['flags'][] = [
        'key'         => $key,
        'description' => $flag['description'],
        'default'     => $flag['default'],
        'default_label' => $flag['default'] ? 'ON' : 'OFF',
        'resolved'    => $flag['resolved'],
        'resolved_label' => $flag['resolved'] ? 'ON' : 'OFF',
        'is_default'  => $tri_state === 'default',
        'is_on'       => $tri_state === 'on',
        'is_off'      => $tri_state === 'off',
        'tri_state'   => $tri_state,
        'has_global_override' => $flag['has_global_override'],
        'has_tenant_override' => $flag['has_tenant_override'],
        'inherits_from_global' => ($tenant_id > 0 && !$flag['has_tenant_override']
            && $flag['has_global_override']),
    ];
}

// Tenant tabs.
$tenants = [
    ['id' => 0,   'name' => 'Global default', 'is_active' => $tenant_id === 0],
    ['id' => 1,   'name' => 'Airpay',          'is_active' => $tenant_id === 1],
    ['id' => 77,  'name' => 'Public',          'is_active' => $tenant_id === 77],
    ['id' => 177, 'name' => 'ZEEA',            'is_active' => $tenant_id === 177],
];
foreach ($tenants as &$t) {
    $t['url'] = (new moodle_url('/local/airpay_core/admin/switchboard.php',
        ['tenant' => $t['id']]))->out(false);
}
unset($t);

$data = [
    'tenant_id'      => $tenant_id,
    'tenant_label'   => $tenants[array_search(
        $tenant_id, array_column($tenants, 'id'), true)]['name'] ?? 'Unknown',
    'is_global'      => $tenant_id === 0,
    'tenants'        => $tenants,
    'categories'     => array_values($by_category),
    'sesskey'        => sesskey(),
    'post_url'       => (new moodle_url('/local/airpay_core/admin/switchboard.php',
        ['tenant' => $tenant_id]))->out(false),
    'history_url'    => (new moodle_url('/local/airpay_core/admin/flags_history.php'))->out(false),
    'flag_count'     => count($all_flags),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_core/switchboard', $data);
echo $OUTPUT->footer();
