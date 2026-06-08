<?php
/**
 * Airpay Compliance Report — main dashboard page.
 *
 * Shows compliance matrix, KPIs, department scorecard, defaulters, manager report.
 * Siteadmin sees global data, tenant admin sees own org.
 *
 * @package    local_sentientia_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$systemcontext = context_system::instance();
$isadmin = is_siteadmin() || has_capability('local/courses:manage', $systemcontext);

// BizLMS admin fallback.
if (!$isadmin) {
    $hasbizlmsadmin = $DB->record_exists_sql(
        "SELECT 1 FROM {role_assignments} ra
         JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE ra.userid = :uid AND ra.roleid = 9 AND ctx.contextlevel = 40",
        ['uid' => $USER->id]);
    $isadmin = $hasbizlmsadmin;
}

// Managers can view compliance for their team (read-only).
// Check capability first, then fall back to supervisor relationship.
$ismanager = false;
if (!$isadmin) {
    // Capability-based check (covers HRBP, trainer roles).
    if (has_capability('moodle/site:viewreports', $systemcontext)) {
        $ismanager = true;
    } else {
        // Supervisor relationship check — guard against missing column.
        try {
            $dbman = $DB->get_manager();
            $usertable = new xmldb_table('user');
            $superfield = new xmldb_field('open_supervisorid');
            if ($dbman->field_exists($usertable, $superfield)) {
                $directreports = $DB->count_records_select('user',
                    "open_supervisorid = :uid AND deleted = 0 AND suspended = 0",
                    ['uid' => $USER->id]);
                $ismanager = ($directreports > 0);
            }
        } catch (\Throwable $e) {
            // Column doesn't exist — not a manager in this context.
            $ismanager = false;
        }
    }
}
if (!$isadmin && !$ismanager) {
    throw new moodle_exception('nopermission');
}

$PAGE->set_url(new moodle_url('/local/sentientia_compliance_report/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('compliancereport', 'local_sentientia_compliance_report'));
$PAGE->set_pagelayout('standard');

$tab    = optional_param('tab', 'matrix', PARAM_ALPHA);
$page   = optional_param('page', 0, PARAM_INT);
$bu     = optional_param('bu', 0, PARAM_INT);
$dept   = optional_param('dept', 0, PARAM_INT);
$subdept = optional_param('subdept', 0, PARAM_INT);

// Handle admin actions: manage courses, exclude users.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action && confirm_sesskey()) {
    $engine_cls = \local_sentientia_compliance_report\compliance_engine::class;
    switch ($action) {
        case 'addcourse':
            $courseid = required_param('courseid', PARAM_INT);
            $entityid = optional_param('entityid', 0, PARAM_INT);
            $days = optional_param('days', 30, PARAM_INT);
            $engine_cls::add_compliance_course($courseid, $entityid, $days);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                'Course added to compliance tracking.', null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'removecourse':
            $id = required_param('id', PARAM_INT);
            $engine_cls::remove_compliance_course($id);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                'Course removed from tracking.', null, \core\output\notification::NOTIFY_WARNING);
            break;
        case 'exclude':
            $excludeid = required_param('userid', PARAM_INT);
            $reason = optional_param('reason', 'Operations exclusion', PARAM_TEXT);
            $engine_cls::exclude_user($excludeid, $reason);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                'User excluded from tracking.', null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'include':
            $includeid = required_param('userid', PARAM_INT);
            $engine_cls::include_user($includeid);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                'User re-included in tracking.', null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

// Tenant scoping.
$orgpath = '';
if (!is_siteadmin()) {
    $orgpath = \local_sentientia_org\tenant_manager::get_tenant_path();
}

// Build filter path from BU/Dept/SubDept dropdowns.
$filterpath = $orgpath;
if ($bu > 0) {
    $filterpath = '/' . $bu;
    if ($dept > 0) {
        $filterpath .= '/' . $dept;
        if ($subdept > 0) {
            $filterpath .= '/' . $subdept;
        }
    }
}

$engine = \local_sentientia_compliance_report\compliance_engine::class;

// Build filter dropdown data.
$filter_bus = $engine::get_org_hierarchy_level(1, $orgpath); // Business Units.
$filter_depts = ($bu > 0) ? $engine::get_org_hierarchy_children($bu) : [];
$filter_subdepts = ($dept > 0) ? $engine::get_org_hierarchy_children($dept) : [];

// Mark selected values.
foreach ($filter_bus as &$item) { $item['selected'] = ($item['id'] == $bu); }
foreach ($filter_depts as &$item) { $item['selected'] = ($item['id'] == $dept); }
foreach ($filter_subdepts as &$item) { $item['selected'] = ($item['id'] == $subdept); }
unset($item);

// Get data based on active tab — use cache for expensive queries.
$cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'local_sentientia_compliance_report', 'dashboard');
$cachekey = 'kpis_' . md5($filterpath);
$kpis = $cache->get($cachekey);
if ($kpis === false) {
    $kpis = $engine::get_summary_kpis($filterpath);
    $cache->set($cachekey, $kpis);  // TTL managed by Moodle cache definition.
}

// Phase B0 iter X — derive a stat_card-compatible array from the flat
// $kpis dict so the dashboard template can iterate the canonical KPI
// partial instead of 5 hand-coded <div> blocks. The legacy {{kpis.X}}
// access pattern is preserved for any other template that still uses it.
$compliance_rate = (int) ($kpis['compliance_rate'] ?? 0);
$is_healthy      = $compliance_rate >= 80;  // matches the legacy is_healthy gate
$overdue_count   = (int) ($kpis['overdue'] ?? 0);
$kpi_tiles = [
    [
        'label' => 'Compliance Rate',
        'value' => $compliance_rate . '%',
        'icon'  => $is_healthy ? 'check-circle' : 'exclamation-circle',
        'color' => $is_healthy ? 'success' : 'warning',
    ],
    [
        'label' => 'Completed',
        'value' => number_format((int) ($kpis['completed'] ?? 0)),
        'icon'  => 'graduation-cap',
        'color' => 'success',
    ],
    [
        'label' => 'Overdue',
        'value' => number_format($overdue_count),
        'icon'  => 'exclamation-triangle',
        // Overdue tile is danger when there ARE overdue items, primary
        // (muted) when zero — "0 overdue" is good news, not alarming.
        'color' => $overdue_count > 0 ? 'danger' : 'primary',
    ],
    [
        'label' => 'Not Enrolled',
        'value' => number_format((int) ($kpis['not_enrolled'] ?? 0)),
        'icon'  => 'user-times',
        'color' => 'warning',
    ],
    [
        'label' => 'Exempted',
        'value' => number_format((int) ($kpis['exempted'] ?? 0)),
        'icon'  => 'shield',
        'color' => 'info',
    ],
];
$matrix = ($tab === 'matrix') ? $engine::get_compliance_matrix($filterpath, $page, 50) : null;
$defaulters = ($tab === 'defaulters') ? $engine::get_defaulters($filterpath) : null;
$scorecard = ($tab === 'scorecard') ? $engine::get_department_scorecard($filterpath) : null;
$manager_report = ($tab === 'manager') ? $engine::get_manager_report($filterpath) : null;

// Config tab: compliance courses + excluded users.
$config_courses = [];
$config_excluded = [];
if ($tab === 'config') {
    $config_courses = $engine::get_managed_courses();
    $config_excluded = $engine::get_excluded_users();
}

// Get all courses for the add-course dropdown.
$allcourses = [];
if ($tab === 'config') {
    $allcourses = $DB->get_records_select('course', 'id > 1 AND visible = 1', null, 'fullname', 'id, fullname');
    $allcourses = array_values(array_map(fn($c) => ['id' => $c->id, 'name' => format_string($c->fullname)], $allcourses));
}

// Data freshness: when was the snapshot last rebuilt?
$last_snapshot_time = null;
$dbman = $DB->get_manager();
if ($dbman->table_exists('local_sentientia_compliance_snapshot')) {
    $last_snapshot_time = $DB->get_field_sql(
        "SELECT MAX(timemodified) FROM {local_sentientia_compliance_snapshot}");
}
$last_refreshed = $last_snapshot_time ? userdate($last_snapshot_time, '%d %b %Y, %I:%M %p') : null;
$is_stale = $last_snapshot_time && (time() - $last_snapshot_time > 7200); // >2 hours = stale.

$baseurl_params = ['tab' => $tab];
if ($bu) { $baseurl_params['bu'] = $bu; }
if ($dept) { $baseurl_params['dept'] = $dept; }
if ($subdept) { $baseurl_params['subdept'] = $subdept; }

$data = [
    'last_refreshed'    => $last_refreshed,
    'is_stale'          => $is_stale,
    'kpis'              => $kpis,
    'kpi_tiles'         => $kpi_tiles,  // Phase B0 iter X — stat_card-compatible KPIs
    'has_kpi_tiles'     => !empty($kpi_tiles),
    'tab_matrix'        => ($tab === 'matrix'),
    'tab_defaulters'    => ($tab === 'defaulters'),
    'tab_scorecard'     => ($tab === 'scorecard'),
    'tab_manager'       => ($tab === 'manager'),
    'tab_config'        => ($tab === 'config'),
    'matrix'            => $matrix,
    'has_matrix'        => !empty($matrix['rows']),
    'defaulters'        => $defaulters,
    'has_defaulters'    => !empty($defaulters),
    'scorecard'         => $scorecard,
    'has_scorecard'     => !empty($scorecard),
    'manager_report'    => $manager_report,
    'has_manager_report' => !empty($manager_report),
    'is_scoped'         => !empty($orgpath),
    'is_siteadmin'      => is_siteadmin(),
    // Same authority export.php enforces — keeps the button and the gate in
    // lockstep so a manager who can view never sees a button that 403s.
    'can_export'        => \local_sentientia_compliance_report\permission::can_export(),
    'baseurl'           => (new moodle_url('/local/sentientia_compliance_report/index.php'))->out(false),
    'exporturl'         => (new moodle_url('/local/sentientia_compliance_report/export.php', $baseurl_params))->out(false),
    'sesskey'           => sesskey(),
    // Filters.
    'filter_bus'        => $filter_bus,
    'has_bus'           => !empty($filter_bus),
    'filter_depts'      => $filter_depts,
    'has_depts'         => !empty($filter_depts),
    'filter_subdepts'   => $filter_subdepts,
    'has_subdepts'      => !empty($filter_subdepts),
    'selected_bu'       => $bu,
    'selected_dept'     => $dept,
    'selected_subdept'  => $subdept,
    // Config tab data.
    'config_courses'    => $config_courses,
    'has_config_courses' => !empty($config_courses),
    'config_excluded'   => $config_excluded,
    'has_config_excluded' => !empty($config_excluded),
    'allcourses'        => $allcourses,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_compliance_report/dashboard', $data);
echo $OUTPUT->footer();
