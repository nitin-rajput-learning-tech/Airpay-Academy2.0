<?php
// Airpay Course Management — admin course listing with CRUD.
//
// @package    local_airpay_courses
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_courses:view', $context);

// ── Page setup ────────────────────────────────────────────────────────
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_courses/index.php'));
$PAGE->set_title('Course Management');
$PAGE->set_heading('Course Management');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// ── Filter params ─────────────────────────────────────────────────────
$orgid    = optional_param('orgid', 0, PARAM_INT);
$catid    = optional_param('catid', 0, PARAM_INT);
$status   = optional_param('status', 'visible', PARAM_ALPHA); // visible, hidden, all
$search   = optional_param('search', '', PARAM_TEXT);
$page     = optional_param('page', 0, PARAM_INT);
$perpage  = optional_param('perpage', 25, PARAM_INT);
$sortfield = optional_param('sort', 'fullname', PARAM_ALPHAEXT);
$sortdir  = optional_param('dir', 'ASC', PARAM_ALPHA);

$allowed_sort = ['fullname', 'shortname', 'enrolled', 'completed', 'timecreated'];
if (!in_array($sortfield, $allowed_sort, true)) {
    $sortfield = 'fullname';
}
$sortdir = strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';

// ── Permissions ───────────────────────────────────────────────────────
$can_manage = has_capability('local/airpay_courses:manage', $context);
$can_create = has_capability('local/airpay_courses:create', $context);
$can_delete = is_siteadmin() || has_capability('local/airpay_courses:delete', $context);

// ── Build query ───────────────────────────────────────────────────────
$whereclauses = ["c.id > 1"]; // Exclude site course.
$params = [];

// Org filter — courses use open_path too.
if ($orgid > 0) {
    $org = $DB->get_record('local_airpay_org', ['id' => $orgid]);
    if ($org) {
        $whereclauses[] = "c.open_path LIKE :orgpath";
        $params['orgpath'] = $org->path . '%';
    }
} else if (!is_siteadmin()) {
    global $USER;
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $toporg = $parts[0] ?? '';
    if (!empty($toporg)) {
        $whereclauses[] = "c.open_path LIKE :userorg";
        $params['userorg'] = '/' . $toporg . '%';
    }
}

// Category filter.
if ($catid > 0) {
    $whereclauses[] = "c.category = :catid";
    $params['catid'] = $catid;
}

// Status filter.
if ($status === 'visible') {
    $whereclauses[] = "c.visible = 1";
} else if ($status === 'hidden') {
    $whereclauses[] = "c.visible = 0";
}

// Search.
if (!empty($search)) {
    $searchterm = '%' . $DB->sql_like_escape($search) . '%';
    $whereclauses[] = "(" .
        $DB->sql_like('c.fullname', ':s1', false) . " OR " .
        $DB->sql_like('c.shortname', ':s2', false) .
    ")";
    $params['s1'] = $searchterm;
    $params['s2'] = $searchterm;
}

$where = implode(' AND ', $whereclauses);

// ── Counts ────────────────────────────────────────────────────────────
$basewhere_parts = array_filter($whereclauses, fn($c) => strpos($c, 'visible') === false);
$basewhere = implode(' AND ', $basewhere_parts);
$baseparams = $params;
unset($baseparams['status_param']);

$total_count   = $DB->count_records_sql("SELECT COUNT(*) FROM {course} c WHERE $basewhere", $baseparams);
$visible_count = $DB->count_records_sql("SELECT COUNT(*) FROM {course} c WHERE $basewhere AND c.visible = 1", $baseparams);
$hidden_count  = $total_count - $visible_count;

// Total enrolments + completions.
$total_enrolments = $DB->count_records_sql(
    "SELECT COUNT(ue.id) FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
       JOIN {course} c ON c.id = e.courseid
      WHERE $basewhere", $baseparams);
$total_completions = $DB->count_records_sql(
    "SELECT COUNT(cc.id) FROM {course_completions} cc
       JOIN {course} c ON c.id = cc.course
      WHERE cc.timecompleted IS NOT NULL AND $basewhere", $baseparams);

// ── Fetch courses with enrolment/completion counts ────────────────────
// Sort by aggregated columns needs special handling.
$ordersql = in_array($sortfield, ['enrolled', 'completed'])
    ? "{$sortfield} {$sortdir}"
    : "c.{$sortfield} {$sortdir}";

$sql = "SELECT c.id, c.fullname, c.shortname, c.visible, c.timecreated, c.open_path,
               cc2.name AS categoryname,
               (SELECT COUNT(ue.id)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = c.id) AS enrolled,
               (SELECT COUNT(ccc.id)
                  FROM {course_completions} ccc
                 WHERE ccc.course = c.id AND ccc.timecompleted IS NOT NULL) AS completed
          FROM {course} c
     LEFT JOIN {course_categories} cc2 ON cc2.id = c.category
         WHERE {$where}
      ORDER BY {$ordersql}";

$courses = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
$filtered_count = $DB->count_records_sql("SELECT COUNT(*) FROM {course} c WHERE {$where}", $params);

// ── Resolve org names ─────────────────────────────────────────────────
$orgs = $DB->get_records('local_airpay_org', ['depth' => 1, 'visible' => 1], 'fullname ASC');
$org_lookup = [];
foreach ($orgs as $o) {
    $org_lookup[$o->path] = format_string($o->fullname);
}

// Build course display rows.
$courserows = [];
foreach ($courses as $c) {
    $parts = explode('/', trim($c->open_path ?? '', '/'));
    $toporgid = $parts[0] ?? '';
    $orgname = $org_lookup['/' . $toporgid] ?? '—';

    $completion_rate = ($c->enrolled > 0) ? round(($c->completed / $c->enrolled) * 100) : 0;

    $courserows[] = [
        'id'              => $c->id,
        'fullname'        => format_string($c->fullname),
        'shortname'       => s($c->shortname),
        'categoryname'    => format_string($c->categoryname ?? '—'),
        'orgname'         => $orgname,
        'enrolled'        => (int) $c->enrolled,
        'completed'       => (int) $c->completed,
        'completion_rate' => $completion_rate,
        'rate_color'      => $completion_rate >= 80 ? 'text-success' : ($completion_rate >= 50 ? 'text-warning' : 'text-danger'),
        'isvisible'       => (bool) $c->visible,
        'statuscss'       => $c->visible ? 'badge-success' : 'badge-secondary',
        'statuslabel'     => $c->visible ? 'Active' : 'Hidden',
        'created'         => userdate($c->timecreated, '%d %b %Y'),
        'courseurl'       => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
        'editurl'         => (new moodle_url('/course/edit.php', ['id' => $c->id]))->out(false),
        'enrollurl'       => (new moodle_url('/enrol/instances.php', ['id' => $c->id]))->out(false),
        'can_manage'      => $can_manage,
        'can_delete'      => $can_delete,
    ];
}

// ── Filter options ────────────────────────────────────────────────────
$org_options = [];
foreach ($orgs as $o) {
    $org_options[] = [
        'id' => $o->id,
        'name' => format_string($o->fullname),
        'selected' => ($o->id == $orgid),
    ];
}

$categories = $DB->get_records('course_categories', ['visible' => 1, 'parent' => 0], 'name ASC', 'id, name');
$cat_options = [];
foreach ($categories as $cat) {
    $cat_options[] = [
        'id' => $cat->id,
        'name' => format_string($cat->name),
        'selected' => ($cat->id == $catid),
    ];
}

// ── Pagination ────────────────────────────────────────────────────────
$baseurl = new moodle_url('/local/airpay_courses/index.php', [
    'orgid' => $orgid, 'catid' => $catid, 'status' => $status, 'search' => $search, 'perpage' => $perpage,
]);

$total_pages = ceil($filtered_count / $perpage);
$pagination_pages = [];
for ($i = 0; $i < min($total_pages, 20); $i++) {
    $purl = clone $baseurl;
    $purl->params(['page' => $i, 'sort' => $sortfield, 'dir' => $sortdir]);
    $pagination_pages[] = [
        'pagenum'  => $i + 1,
        'url'      => $purl->out(false),
        'isactive' => ($i == $page),
    ];
}

$prevurl = clone $baseurl;
$prevurl->params(['page' => max(0, $page - 1), 'sort' => $sortfield, 'dir' => $sortdir]);
$nexturl = clone $baseurl;
$nexturl->params(['page' => $page + 1, 'sort' => $sortfield, 'dir' => $sortdir]);

// Sort URL builder.
function course_sort_url($field, $current_sort, $current_dir, $baseurl) {
    $dir = ($field === $current_sort && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $url = clone $baseurl;
    $url->params(['sort' => $field, 'dir' => $dir, 'page' => 0]);
    return [
        'url' => $url->out(false),
        'icon' => ($field === $current_sort) ? ($current_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort',
    ];
}

// ── Template context ──────────────────────────────────────────────────
$data = [
    'total_count'       => $total_count,
    'visible_count'     => $visible_count,
    'hidden_count'      => $hidden_count,
    'total_enrolments'  => number_format($total_enrolments),
    'total_completions' => number_format($total_completions),
    'filtered_count'    => $filtered_count,

    'search'            => $search,
    'org_options'       => $org_options,
    'has_org_options'   => !empty($org_options),
    'cat_options'       => $cat_options,
    'has_cat_options'   => !empty($cat_options),
    'status_visible'    => ($status === 'visible'),
    'status_hidden'     => ($status === 'hidden'),
    'status_all'        => ($status === 'all'),
    'filter_url'        => (new moodle_url('/local/airpay_courses/index.php'))->out(false),

    'sort_name'         => course_sort_url('fullname', $sortfield, $sortdir, $baseurl),
    'sort_shortname'    => course_sort_url('shortname', $sortfield, $sortdir, $baseurl),
    'sort_enrolled'     => course_sort_url('enrolled', $sortfield, $sortdir, $baseurl),
    'sort_completed'    => course_sort_url('completed', $sortfield, $sortdir, $baseurl),
    'sort_created'      => course_sort_url('timecreated', $sortfield, $sortdir, $baseurl),

    'courses'           => $courserows,
    'has_courses'       => !empty($courserows),
    'showing_from'      => ($page * $perpage) + 1,
    'showing_to'        => min(($page + 1) * $perpage, $filtered_count),

    'has_pagination'    => ($total_pages > 1),
    'pagination_pages'  => $pagination_pages,
    'has_prev'          => ($page > 0),
    'prev_url'          => $prevurl->out(false),
    'has_next'          => ($page < $total_pages - 1),
    'next_url'          => $nexturl->out(false),

    'can_manage'        => $can_manage,
    'can_create'        => $can_create,
    'create_url'        => (new moodle_url('/course/edit.php', ['category' => 1]))->out(false),
];

// ── Render ────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_courses/manage', $data);
echo $OUTPUT->footer();
