<?php
// Airpay User Engine — enterprise user management.
//
// @package    local_airpay_users
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_users:view', $context);

// ── Page setup ────────────────────────────────────────────────────────
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_users/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_airpay_users'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_users'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// ── Filter params ─────────────────────────────────────────────────────
$orgid      = optional_param('orgid', 0, PARAM_INT);
$status     = optional_param('status', 'active', PARAM_ALPHA);  // active, suspended, all
$search     = optional_param('search', '', PARAM_TEXT);
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', 25, PARAM_INT);
$sortfield  = optional_param('sort', 'lastname', PARAM_ALPHAEXT);
$sortdir    = optional_param('dir', 'ASC', PARAM_ALPHA);

// Validate sort params.
$allowed_sort = ['firstname', 'lastname', 'email', 'open_employeeid', 'open_designation', 'lastaccess'];
if (!in_array($sortfield, $allowed_sort, true)) {
    $sortfield = 'lastname';
}
$sortdir = strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';

// ── Tenant scoping ────────────────────────────────────────────────────
// Siteadmin sees all; others scoped by open_path.
$can_manage = has_capability('local/airpay_users:manage', $context);
$can_create = has_capability('local/airpay_users:create', $context);
$can_edit   = has_capability('local/airpay_users:edit', $context);
$can_delete = has_capability('local/airpay_users:delete', $context);

$whereclauses = ["u.deleted = 0", "u.id > 2"]; // Exclude guest + admin.
$params = [];

// Org filter.
if ($orgid > 0) {
    $org = $DB->get_record('local_airpay_org', ['id' => $orgid]);
    if ($org) {
        $whereclauses[] = "u.open_path LIKE :orgpath";
        $params['orgpath'] = $org->path . '%';
    }
} else if (!is_siteadmin()) {
    // Non-admin: scope to own org.
    global $USER;
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $toporg = $parts[0] ?? '';
    if (!empty($toporg)) {
        $whereclauses[] = "u.open_path LIKE :userorg";
        $params['userorg'] = '/' . $toporg . '%';
    }
}

// Status filter.
if ($status === 'active') {
    $whereclauses[] = "u.suspended = 0";
} else if ($status === 'suspended') {
    $whereclauses[] = "u.suspended = 1";
}
// 'all' = no status filter.

// Search filter.
if (!empty($search)) {
    $searchterm = '%' . $DB->sql_like_escape($search) . '%';
    $whereclauses[] = "(" .
        $DB->sql_like('u.firstname', ':s1', false) . " OR " .
        $DB->sql_like('u.lastname', ':s2', false) . " OR " .
        $DB->sql_like('u.email', ':s3', false) . " OR " .
        $DB->sql_like('COALESCE(u.open_employeeid, \'\')', ':s4', false) .
    ")";
    $params['s1'] = $searchterm;
    $params['s2'] = $searchterm;
    $params['s3'] = $searchterm;
    $params['s4'] = $searchterm;
}

$where = implode(' AND ', $whereclauses);

// ── Counts ────────────────────────────────────────────────────────────
// Build base WHERE without status filter for counts.
$basewhere_parts = array_filter($whereclauses, fn($c) => strpos($c, 'suspended') === false);
$basewhere = implode(' AND ', $basewhere_parts);
$baseparams = array_filter($params, fn($k) => $k !== 'status_param', ARRAY_FILTER_USE_KEY);

$total_count   = $DB->count_records_sql("SELECT COUNT(*) FROM {user} u WHERE $basewhere", $baseparams);
$active_count  = $DB->count_records_sql("SELECT COUNT(*) FROM {user} u WHERE $basewhere AND u.suspended = 0", $baseparams);
$suspended_count = $total_count - $active_count;

// ── Fetch users ───────────────────────────────────────────────────────
$sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.suspended, u.lastaccess,
               u.open_employeeid, u.open_designation, u.open_path, u.open_location,
               u.department
          FROM {user} u
         WHERE {$where}
      ORDER BY u.{$sortfield} {$sortdir}";

$users = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
$filtered_count = $DB->count_records_sql("SELECT COUNT(*) FROM {user} u WHERE {$where}", $params);

// ── Resolve org names ─────────────────────────────────────────────────
$orgs = $DB->get_records('local_airpay_org', ['depth' => 1, 'visible' => 1], 'fullname ASC');
$org_lookup = [];
foreach ($orgs as $o) {
    $org_lookup[$o->path] = format_string($o->fullname);
}

// Build user display data.
$userrows = [];
foreach ($users as $u) {
    $parts = explode('/', trim($u->open_path ?? '', '/'));
    $toporgid = $parts[0] ?? '';
    $orgname = $org_lookup['/' . $toporgid] ?? '—';

    $userrows[] = [
        'id'          => $u->id,
        'fullname'    => fullname($u),
        'employeeid'  => s($u->open_employeeid ?? '—'),
        'email'       => s($u->email),
        'orgname'     => $orgname,
        'department'  => s($u->department ?: '—'),
        'designation' => s($u->open_designation ?? '—'),
        'location'    => s($u->open_location ?? ''),
        'issuspended' => (bool) $u->suspended,
        'statuslabel' => $u->suspended ? 'Suspended' : 'Active',
        'statuscss'   => $u->suspended ? 'badge-danger' : 'badge-success',
        'lastaccess'  => $u->lastaccess ? userdate($u->lastaccess, '%d %b %Y, %I:%M %p') : 'Never',
        'profileurl'  => (new moodle_url('/local/airpay_users/profile.php', ['id' => $u->id]))->out(false),
        'can_edit'    => $can_edit,
        'can_delete'  => $can_delete,
    ];
}

// ── Sort URL helper ───────────────────────────────────────────────────
$baseurl = new moodle_url('/local/airpay_users/index.php', [
    'orgid' => $orgid, 'status' => $status, 'search' => $search, 'perpage' => $perpage,
]);

function sort_url($field, $current_sort, $current_dir, $baseurl) {
    $dir = ($field === $current_sort && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $url = clone $baseurl;
    $url->param('sort', $field);
    $url->param('dir', $dir);
    return [
        'url' => $url->out(false),
        'icon' => ($field === $current_sort) ? ($current_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort',
    ];
}

// ── Org filter options ────────────────────────────────────────────────
$org_options = [];
foreach ($orgs as $o) {
    $org_options[] = [
        'id' => $o->id,
        'name' => format_string($o->fullname),
        'selected' => ($o->id == $orgid),
    ];
}

// ── Pagination ────────────────────────────────────────────────────────
$total_pages = ceil($filtered_count / $perpage);
$pagination_pages = [];
for ($i = 0; $i < $total_pages; $i++) {
    $purl = clone $baseurl;
    $purl->param('page', $i);
    $purl->param('sort', $sortfield);
    $purl->param('dir', $sortdir);
    $pagination_pages[] = [
        'pagenum'  => $i + 1,
        'url'      => $purl->out(false),
        'isactive' => ($i == $page),
    ];
}

// ── Template context ──────────────────────────────────────────────────
$data = [
    // KPIs.
    'total_count'     => $total_count,
    'active_count'    => $active_count,
    'suspended_count' => $suspended_count,
    'filtered_count'  => $filtered_count,

    // Filters.
    'search'          => $search,
    'org_options'     => $org_options,
    'has_org_options' => !empty($org_options),
    'status_active'   => ($status === 'active'),
    'status_suspended' => ($status === 'suspended'),
    'status_all'      => ($status === 'all'),
    'filter_url'      => (new moodle_url('/local/airpay_users/index.php'))->out(false),

    // Sort headers.
    'sort_name'       => sort_url('lastname', $sortfield, $sortdir, $baseurl),
    'sort_email'      => sort_url('email', $sortfield, $sortdir, $baseurl),
    'sort_empid'      => sort_url('open_employeeid', $sortfield, $sortdir, $baseurl),
    'sort_designation' => sort_url('open_designation', $sortfield, $sortdir, $baseurl),
    'sort_lastaccess' => sort_url('lastaccess', $sortfield, $sortdir, $baseurl),

    // Table data.
    'users'           => $userrows,
    'has_users'       => !empty($userrows),
    'showing_from'    => ($page * $perpage) + 1,
    'showing_to'      => min(($page + 1) * $perpage, $filtered_count),

    // Pagination.
    'has_pagination'  => ($total_pages > 1),
    'pagination_pages' => $pagination_pages,
    'has_prev'        => ($page > 0),
    'prev_url'        => (clone $baseurl)->param('page', $page - 1) ? (clone $baseurl) : $baseurl,
    'has_next'        => ($page < $total_pages - 1),

    // Permissions.
    'can_manage'      => $can_manage,
    'can_create'      => $can_create,
    'can_edit'        => $can_edit,
    'can_delete'      => $can_delete,

    // URLs.
    'export_url'      => (new moodle_url('/local/airpay_users/exportcsv.php', [
                            'orgid' => $orgid, 'status' => $status, 'search' => $search,
                         ]))->out(false),
    'sesskey'         => sesskey(),
];

// Build prev/next URLs properly.
$prevurl = clone $baseurl;
$prevurl->params(['page' => max(0, $page - 1), 'sort' => $sortfield, 'dir' => $sortdir]);
$data['prev_url'] = $prevurl->out(false);

$nexturl = clone $baseurl;
$nexturl->params(['page' => $page + 1, 'sort' => $sortfield, 'dir' => $sortdir]);
$data['next_url'] = $nexturl->out(false);

// ── Render ────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_users/manage', $data);
echo $OUTPUT->footer();
