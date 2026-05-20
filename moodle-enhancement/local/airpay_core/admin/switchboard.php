<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The Switchboard — Phase A0 (2026-05-14) + Session 2 (2026-05-20).
 *
 * Super-admin page for toggling every feature flag in the platform.
 * One screen, every capability, audit-logged per change.
 *
 * Session 2 / ADR-002 (2026-05-20) added the customer-scope tab strip
 * above the existing tenant-scope tab strip, gated on the
 * sentientia.customer_level_flags.enabled meta-flag. When the gate is
 * OFF, the UI renders identically to Phase A0.
 *
 * URL: /local/airpay_core/admin/switchboard.php?customer=C&tenant=T
 *
 * Access: site admin only (moodle/site:config).
 *
 * @package local_airpay_core
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// ── Resolve customer scope (Session 2 addition).
$customer_id = optional_param('customer', 0, PARAM_INT);
$customer_layer_on = \local_airpay_core\feature_flags::is_enabled_for(
    \local_airpay_core\feature_flags::CUSTOMER_LEVEL_FLAG, 0, 0);

// Validate customer_id. When the layer is OFF, the only legal value is 0
// (all customers). When ON, accept any known customer.
if (!$customer_layer_on) {
    $customer_id = 0;
} else {
    $known_customers = \local_airpay_core\customer::known_customers();
    $valid_customer_ids = array_column($known_customers, 'id');
    if (!in_array($customer_id, $valid_customer_ids, true)) {
        $customer_id = 0;
    }
}

// ── Resolve tenant scope.
$tenant_id = optional_param('tenant', 0, PARAM_INT);
$valid_tenants = [0, 1, 77, 177];
if (!in_array($tenant_id, $valid_tenants, true)) {
    $tenant_id = 0;
}

global $DB, $OUTPUT, $USER;

// ── Handle POST (toggle one or more flags).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $reason = optional_param('reason', '', PARAM_TEXT);
    $changes_param = optional_param('changes', '{}', PARAM_RAW);
    $changes = json_decode($changes_param, true);

    if (!is_array($changes) || empty($changes)) {
        redirect(
            new moodle_url('/local/airpay_core/admin/switchboard.php',
                ['customer' => $customer_id, 'tenant' => $tenant_id]),
            get_string('switchboard_no_changes', 'local_airpay_core'),
            2,
            \core\output\notification::NOTIFY_INFO
        );
    }

    $applied = 0;
    foreach ($changes as $key => $new_value) {
        // 2026-05-20 bugfix: the AMD module sends 'on'/'off'/'default' as the
        // tri-state value (matching the data-action="toggle-on/off/default"
        // attribute names) but this handler previously only recognised
        // 'true'/'false'/'default'. Mismatch meant every UI toggle resolved
        // to $val=null (revert-to-default) — a silent no-op when no row
        // existed. Now both vocabularies are accepted.
        $val = null;
        if ($new_value === 'true' || $new_value === true
                || $new_value === 'on') {
            $val = true;
        } else if ($new_value === 'false' || $new_value === false
                || $new_value === 'off') {
            $val = false;
        } else if ($new_value === 'default' || $new_value === null) {
            $val = null;
        }
        try {
            \local_airpay_core\feature_flags::set(
                (string) $key,
                $tenant_id,
                $val,
                (int) $USER->id,
                $reason,
                $customer_id  // Session 2 — new last positional arg
            );
            $applied++;
        } catch (\moodle_exception $e) {
            debugging("Switchboard: skipping flag '$key': "
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    redirect(
        new moodle_url('/local/airpay_core/admin/switchboard.php',
            ['customer' => $customer_id, 'tenant' => $tenant_id]),
        get_string('switchboard_applied', 'local_airpay_core', $applied),
        2,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Render the page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_core/admin/switchboard.php',
    ['customer' => $customer_id, 'tenant' => $tenant_id]));
$PAGE->set_title(get_string('switchboard_pagetitle', 'local_airpay_core'));
$PAGE->set_heading(get_string('switchboard_pagetitle', 'local_airpay_core'));
$PAGE->set_pagelayout('admin');

// Load all flags + their resolved state for this (customer, tenant) pair.
$all_flags = \local_airpay_core\feature_flags::all($tenant_id, $customer_id);

// Group by category.
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
    //
    // The "tri_state" is what the toggle group's active button reflects
    // — it represents what this (customer, tenant) view's own override
    // says, NOT what the resolved value is. Inherited values render as
    // "default" with an "inheriting from ..." badge.
    $tri_state = 'default';
    if ($customer_layer_on && $customer_id > 0 && $tenant_id > 0 && $flag['has_tenant_override']) {
        $tri_state = $flag['resolved'] ? 'on' : 'off';
    } else if ($customer_layer_on && $customer_id > 0 && $tenant_id === 0 && $flag['has_customer_override']) {
        $tri_state = $flag['resolved'] ? 'on' : 'off';
    } else if (!$customer_layer_on && $tenant_id > 0 && $flag['has_legacy_tenant_override']) {
        // Legacy mode (gate OFF) — show legacy tenant override.
        $tri_state = $flag['resolved'] ? 'on' : 'off';
    } else if ($customer_id === 0 && $tenant_id === 0 && $flag['has_global_override']) {
        $tri_state = $flag['resolved'] ? 'on' : 'off';
    }

    // Which override layer is currently being inherited from? Shown as a
    // small "inheriting global" / "inheriting customer" badge so admins
    // know why a flag has its current resolved value.
    $inherits_from = '';
    if ($customer_layer_on && $customer_id > 0 && $tenant_id > 0
            && !$flag['has_tenant_override']) {
        if ($flag['has_customer_override']) {
            $inherits_from = 'customer';
        } else if ($flag['has_legacy_tenant_override']) {
            $inherits_from = 'tenant';
        } else if ($flag['has_global_override']) {
            $inherits_from = 'global';
        }
    } else if ($customer_layer_on && $customer_id > 0 && $tenant_id === 0
            && !$flag['has_customer_override']) {
        if ($flag['has_global_override']) {
            $inherits_from = 'global';
        }
    } else if (!$customer_layer_on && $tenant_id > 0
            && !$flag['has_legacy_tenant_override']
            && $flag['has_global_override']) {
        $inherits_from = 'global';
    }

    $by_category[$cat]['flags'][] = [
        'key'                  => $key,
        'description'          => $flag['description'],
        'default'              => $flag['default'],
        'default_label'        => $flag['default'] ? 'ON' : 'OFF',
        'resolved'             => $flag['resolved'],
        'resolved_label'       => $flag['resolved'] ? 'ON' : 'OFF',
        'is_default'           => $tri_state === 'default',
        'is_on'                => $tri_state === 'on',
        'is_off'               => $tri_state === 'off',
        'tri_state'            => $tri_state,
        'has_global_override'  => $flag['has_global_override'],
        'has_customer_override' => $flag['has_customer_override'],
        'has_tenant_override'  => $flag['has_tenant_override'],
        'has_legacy_tenant_override' => $flag['has_legacy_tenant_override'],
        'inherits_from'        => $inherits_from,
        'inherits_from_global'   => $inherits_from === 'global',
        'inherits_from_customer' => $inherits_from === 'customer',
        'inherits_from_tenant'   => $inherits_from === 'tenant',
    ];
}

// ── Customer tabs (Session 2).
$customer_tabs = [];
if ($customer_layer_on) {
    foreach (\local_airpay_core\customer::known_customers() as $c) {
        $customer_tabs[] = [
            'id'         => $c['id'],
            'name'       => $c['name'],
            'is_active'  => $customer_id === $c['id'],
            'is_default' => $c['is_default'],
            'url'        => (new moodle_url('/local/airpay_core/admin/switchboard.php',
                ['customer' => $c['id'], 'tenant' => 0]))->out(false),
        ];
    }
}

// ── Tenant tabs (Phase A0, preserved).
$tenants = [
    ['id' => 0,   'name' => 'All tenants',  'is_active' => $tenant_id === 0],
    ['id' => 1,   'name' => 'Airpay',       'is_active' => $tenant_id === 1],
    ['id' => 77,  'name' => 'Public',       'is_active' => $tenant_id === 77],
    ['id' => 177, 'name' => 'ZEEA',         'is_active' => $tenant_id === 177],
];
foreach ($tenants as &$t) {
    $t['url'] = (new moodle_url('/local/airpay_core/admin/switchboard.php',
        ['customer' => $customer_id, 'tenant' => $t['id']]))->out(false);
}
unset($t);

// ── Determine the banner copy. Five distinct states:
//   1. (cust=0, ten=0)  → "Global default"
//   2. (cust=0, ten>0)  → "Legacy tenant override"
//   3. (cust>0, ten=0)  → "Customer-wide override" (new)
//   4. (cust>0, ten>0)  → "Tenant-within-customer override" (new)
//   5. customer_layer_on=false → suppress all customer-related copy
$scope_banner = '';
$scope_label = '';
if (!$customer_layer_on || $customer_id === 0) {
    if ($tenant_id === 0) {
        $scope_label = get_string('scope_global', 'local_airpay_core');
        $scope_banner = get_string('scope_banner_global', 'local_airpay_core');
    } else {
        $scope_label = $tenants[array_search($tenant_id,
            array_column($tenants, 'id'), true)]['name'] ?? 'Unknown';
        $scope_banner = get_string('scope_banner_legacy_tenant',
            'local_airpay_core', format_string($scope_label));
    }
} else {
    $customer_label = \local_airpay_core\customer::label_for($customer_id);
    $tenant_label = $tenants[array_search($tenant_id,
        array_column($tenants, 'id'), true)]['name'] ?? 'Unknown';
    if ($tenant_id === 0) {
        $scope_label = $customer_label;
        $scope_banner = get_string('scope_banner_customer',
            'local_airpay_core', format_string($customer_label));
    } else {
        $scope_label = $customer_label . ' / ' . $tenant_label;
        $scope_banner = get_string('scope_banner_customer_tenant',
            'local_airpay_core',
            (object) ['customer' => format_string($customer_label),
                      'tenant' => format_string($tenant_label)]);
    }
}

$data = [
    'customer_layer_on'    => $customer_layer_on,
    'customer_id'          => $customer_id,
    'tenant_id'            => $tenant_id,
    'scope_label'          => $scope_label,
    'scope_banner'         => $scope_banner,
    'is_global'            => ($customer_id === 0 && $tenant_id === 0),
    'is_customer_only'     => ($customer_id > 0 && $tenant_id === 0),
    'is_customer_tenant'   => ($customer_id > 0 && $tenant_id > 0),
    'is_legacy_tenant'     => ($customer_id === 0 && $tenant_id > 0),
    'customers'            => $customer_tabs,
    'tenants'              => $tenants,
    'categories'           => array_values($by_category),
    'sesskey'              => sesskey(),
    'post_url'             => (new moodle_url('/local/airpay_core/admin/switchboard.php',
        ['customer' => $customer_id, 'tenant' => $tenant_id]))->out(false),
    'history_url'          => (new moodle_url('/local/airpay_core/admin/flags_history.php'))->out(false),
    'flag_count'           => count($all_flags),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_core/switchboard', $data);
echo $OUTPUT->footer();
