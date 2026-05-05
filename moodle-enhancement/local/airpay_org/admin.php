<?php
// Airpay Organisation Management — hierarchical org tree with CRUD.
//
// @package    local_airpay_org
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_org:view', $context);

$can_manage = is_siteadmin() || has_capability('local/airpay_org:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_org/admin.php'));
$PAGE->set_title('Organisation Management');
$PAGE->set_heading('Organisation Management');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Get all org nodes once.
$all_orgs = $DB->get_records('local_airpay_org', null, 'depth ASC, sortorder ASC, fullname ASC');

// Group by parentid for fast tree assembly.
$by_parent = [];
foreach ($all_orgs as $o) {
    $by_parent[(int) $o->parentid][] = $o;
}

/**
 * Recursively build a render-friendly tree node.
 */
$build_node = function (object $org) use (&$build_node, $by_parent, $DB, $can_manage): array {
    $kid_records = $by_parent[(int) $org->id] ?? [];
    $children = [];
    foreach ($kid_records as $k) {
        $children[] = $build_node($k);
    }

    // Count users at this org's path level (descendants included).
    $user_count = 0;
    if (!empty($org->path)) {
        $user_count = $DB->count_records_select('user',
            'deleted = 0 AND open_path LIKE :p',
            ['p' => $org->path . '%']);
    }

    return [
        'id'           => (int) $org->id,
        'fullname'     => format_string($org->fullname),
        'shortname'    => s($org->shortname ?? ''),
        'description'  => format_string($org->description ?? ''),
        'path'         => s($org->path ?? ''),
        'depth'        => (int) $org->depth,
        'is_tenant'    => ((int) $org->depth === 1),
        'visible'      => (bool) $org->visible,
        'visible_int'  => (int) $org->visible,
        'visiblelabel' => $org->visible ? 'Active' : 'Hidden',
        'visiblecss'   => $org->visible ? 'badge-success' : 'badge-secondary',
        'brand_color'  => s($org->brand_color ?? ''),
        'has_brand'    => !empty($org->brand_color),
        'children'     => $children,
        'has_children' => !empty($children),
        'child_count'  => count($children),
        'user_count'   => (int) $user_count,
        'can_manage'   => $can_manage,
    ];
};

// Tenants are nodes with parentid=0.
$tenant_records = $by_parent[0] ?? [];
$tenants = [];
foreach ($tenant_records as $t) {
    $tenants[] = $build_node($t);
}

$total_orgs = count($all_orgs);
$total_users = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 2');

$data = [
    'total_orgs'    => $total_orgs,
    'total_tenants' => count($tenants),
    'total_users'   => number_format($total_users),
    'tenants'       => $tenants,
    'has_tenants'   => !empty($tenants),
    'can_manage'    => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_org/manage', $data);
echo $OUTPUT->footer();
