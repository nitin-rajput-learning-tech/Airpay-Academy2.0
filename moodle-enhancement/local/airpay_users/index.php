<?php
// Airpay User Engine — admin user management (datatable-driven).
//
// @package    local_airpay_users
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_users:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_users/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_airpay_users'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_users'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_create = has_capability('local/airpay_users:create', $context);
$can_bulkstatus = has_capability('local/airpay_users:bulkstatuschange', $context);

// ── KPI counts (cheap aggregate) ───────────────────────────────────
$dbman = $DB->get_manager();
$base_where = 'deleted = 0 AND id > 2';
$base_params = [];
if (!is_siteadmin()) {
    global $USER;
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $top = $parts[0] ?? '';
    if (!empty($top)) {
        $base_where .= ' AND open_path LIKE :userorg';
        $base_params['userorg'] = '/' . $top . '%';
    }
}
$total_count   = (int) $DB->count_records_select('user', $base_where, $base_params);
$active_count  = (int) $DB->count_records_select('user', "$base_where AND suspended = 0", $base_params);
$suspended_count = $total_count - $active_count;

// ── Org dropdown options ──
$orgs = $DB->get_records('local_airpay_org', ['depth' => 1, 'visible' => 1], 'fullname ASC',
    'id, fullname');
$org_options = [];
foreach ($orgs as $o) {
    $org_options[] = [
        'id'   => $o->id,
        'name' => format_string($o->fullname),
    ];
}

// ── Datatable column config (passed as JSON to client) ──
$columns = [
    ['key' => 'fullname',    'label' => 'Name',          'sortable' => true,  'sortkey' => 'lastname',         'format' => 'html'],
    ['key' => 'employeeid',  'label' => 'Emp ID',        'sortable' => true,  'sortkey' => 'open_employeeid'],
    ['key' => 'email',       'label' => 'Email',         'sortable' => true,  'sortkey' => 'email'],
    ['key' => 'orgname',     'label' => 'Tenant',        'sortable' => false],
    ['key' => 'designation', 'label' => 'Designation',   'sortable' => true,  'sortkey' => 'open_designation'],
    ['key' => 'lastaccess',  'label' => 'Last Access',   'sortable' => true,  'sortkey' => 'lastaccess'],
    ['key' => 'statuslabel', 'label' => 'Status',        'sortable' => true,  'sortkey' => 'suspended', 'format' => 'badge'],
];

// Map sortable columns to underlying DB fields. The AMD module passes
// column.key as the sort param; we let the WS use a whitelist on its end too.
foreach ($columns as &$c) {
    if (!empty($c['sortkey']) && empty($c['sort'])) {
        // Datatable sends 'sort' = column.key; we override with sortkey for DB-side mapping.
        $c['key'] = $c['key'];
    }
}
unset($c);

// Override sort key on client by rewriting column key for sort matching.
// (The WS whitelist accepts both field forms; map common UI keys -> DB columns.)
$column_key_to_db = [
    'fullname'    => 'lastname',
    'employeeid'  => 'open_employeeid',
    'designation' => 'open_designation',
    'statuslabel' => 'suspended',
];

// Phase B0+ — stat_card-compatible tiles. Suspended flips to danger
// when > 0 (a suspended account usually means something escalated).
$kpi_tiles = [
    [
        'label' => 'Total Users',
        'value' => number_format($total_count),
        'icon'  => 'users',
        'color' => 'primary',
    ],
    [
        'label' => 'Active',
        'value' => number_format($active_count),
        'icon'  => 'user-circle',
        'color' => 'success',
    ],
    [
        'label' => 'Suspended',
        'value' => number_format($suspended_count),
        'icon'  => 'user-times',
        'color' => $suspended_count > 0 ? 'danger' : 'primary',
    ],
];

$data = [
    'total_count'     => number_format($total_count),
    'active_count'    => number_format($active_count),
    'suspended_count' => number_format($suspended_count),
    'kpi_tiles'       => $kpi_tiles,
    'has_kpi_tiles'   => !empty($kpi_tiles),
    'can_create'      => $can_create,
    'can_bulkstatus'  => $can_bulkstatus,
    'org_options'     => $org_options,
    'has_org_options' => !empty($org_options),
    'export_url'      => (new moodle_url('/local/airpay_users/exportcsv.php'))->out(false),
    // UAT fix 2026-05-09: hardcoded /local/... links → moodle_url routes.
    'bulk_csv_url'    => (new moodle_url('/local/airpay_users/bulk_csv.php'))->out(false),
    'bulk_import_url' => (new moodle_url('/local/airpay_users/bulk_import.php'))->out(false),
    'columns_json'    => s(json_encode($columns)),
    'sortkey_map_json' => s(json_encode($column_key_to_db)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_users/manage', $data);
echo $OUTPUT->footer();
