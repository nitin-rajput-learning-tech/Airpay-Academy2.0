<?php
// Airpay Organisation Management — hierarchical org tree.
//
// @package    local_airpay_org
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/airpay_org:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/airpay_org/admin.php'));
$PAGE->set_title('Organisation Management');
$PAGE->set_heading('Organisation Management');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Get all org nodes.
$all_orgs = $DB->get_records('local_airpay_org', null, 'depth ASC, fullname ASC');

// Build tree structure.
$tenants = [];
$children_map = []; // parentid => [children]

foreach ($all_orgs as $org) {
    if ($org->depth == 1) {
        // Top-level tenant.
        $child_count = $DB->count_records_select('local_airpay_org', "path LIKE :p AND depth > 1", ['p' => $org->path . '/%']);
        $user_count = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {user} WHERE deleted = 0 AND suspended = 0 AND open_path LIKE :p",
            ['p' => $org->path . '%']);

        $tenants[] = [
            'id'         => $org->id,
            'name'       => format_string($org->fullname),
            'shortname'  => s($org->shortname ?? ''),
            'path'       => s($org->path ?? ''),
            'child_count' => $child_count,
            'user_count' => $user_count,
            'visible'    => (bool) $org->visible,
            'visiblelabel' => $org->visible ? 'Active' : 'Hidden',
            'visiblecss' => $org->visible ? 'badge-success' : 'badge-secondary',
        ];
    }
}

// Get children per tenant for expandable tree.
$tenant_children = [];
foreach ($tenants as &$t) {
    $kids = $DB->get_records_sql(
        "SELECT o.id, o.fullname, o.shortname, o.path, o.depth, o.visible,
                (SELECT COUNT(*) FROM {user} WHERE deleted = 0 AND open_path LIKE " . $DB->sql_concat("o.path", "'%'") . ") AS user_count
           FROM {local_airpay_org} o
          WHERE o.path LIKE :p AND o.depth = 2
       ORDER BY o.fullname ASC",
        ['p' => $t['path'] . '/%']);

    $dept_rows = [];
    foreach ($kids as $k) {
        $dept_rows[] = [
            'id'        => $k->id,
            'name'      => format_string($k->fullname),
            'shortname' => s($k->shortname ?? ''),
            'path'      => s($k->path ?? ''),
            'user_count' => (int) $k->user_count,
            'visible'   => (bool) $k->visible,
        ];
    }
    $t['departments'] = $dept_rows;
    $t['has_departments'] = !empty($dept_rows);
}
unset($t);

$total_orgs = count($all_orgs);
$total_users = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 2');

$data = [
    'total_orgs'   => $total_orgs,
    'total_tenants' => count($tenants),
    'total_users'  => number_format($total_users),
    'tenants'      => $tenants,
    'has_tenants'  => !empty($tenants),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_org/manage', $data);
echo $OUTPUT->footer();
