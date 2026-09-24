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

// Who may see the report, and how much of it: site admin -> every tenant;
// compliance admin or report viewer -> their tenant; line manager -> their
// reporting tree (direct reports and everyone below them). See viewer_scope.
// Until 2026-09-24 a line manager was scoped to their whole tenant.
$scope = \local_sentientia_compliance_report\viewer_scope::for_user($USER);
if ($scope === null) {
    // Plain lang string, not required_capability_exception: no single
    // capability decides this gate (it mixes site admin, a role id, a
    // capability and the supervisor relationship), so naming one capability
    // would tell the user something false. N5 (2026-09-24): this used to be
    // moodle_exception('nopermission'), a key core does not have, which
    // rendered as the bare identifier "error/nopermission". Also refused: a
    // non-admin whose tenant cannot be resolved (every query reads '' as the
    // whole site).
    // Someone who qualifies but whose account has no resolvable organisation
    // is told so - "you are not allowed" would describe the wrong problem.
    $reason = \local_sentientia_compliance_report\viewer_scope::refusal_reason($USER);
    throw new moodle_exception(
        $reason === \local_sentientia_compliance_report\viewer_scope::REFUSED_NO_TENANT ? 'error_notenant' : 'error_noaccess',
        'local_sentientia_compliance_report');
}
// null for site admins and tenant-level viewers; for a line manager, exactly
// the people they may see. Passed to every report query below.
$teamuserids = $scope->userids;

$PAGE->set_url(new moodle_url('/local/sentientia_compliance_report/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('compliancereport', 'local_sentientia_compliance_report'));
$PAGE->set_pagelayout('standard');

$tab    = optional_param('tab', 'matrix', PARAM_ALPHA);
if ($tab === 'config' && !$scope->can_configure()) {
    // Not offered to anyone else (see $canconfigure below); a hand-typed
    // ?tab=config used to list every tenant's excluded users, with emails.
    $tab = 'matrix';
}
$page   = optional_param('page', 0, PARAM_INT);
$bu     = optional_param('bu', 0, PARAM_INT);
$dept   = optional_param('dept', 0, PARAM_INT);
$subdept = optional_param('subdept', 0, PARAM_INT);

// Configuration - the Configure tab and the four actions below - is SITE-ADMIN
// ONLY. The tab link has only ever been rendered for site admins
// ({{#is_siteadmin}} in dashboard.mustache), but until 2026-09-24 nothing on
// the server enforced it: anyone past the view gate above, including a line
// manager with one direct report or any holder of moodle/site:viewreports,
// could open ?tab=config (every tenant's excluded users, with their emails) or
// POST action=exclude for any user in any tenant, or deactivate a mandatory
// course site-wide. The compliance_engine mutators check nothing and are not
// tenant-scoped, so the gate has to be here. Widening configuration to tenant
// admins needs tenant-scoped engine methods first.
$canconfigure = $scope->can_configure();

// Handle admin actions: manage courses, exclude users.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action && !$canconfigure) {
    throw new moodle_exception('nopermissions', 'error', '',
        get_string('configure', 'local_sentientia_compliance_report'));
}
if ($action && confirm_sesskey()) {
    $engine_cls = \local_sentientia_compliance_report\compliance_engine::class;
    switch ($action) {
        case 'addcourse':
            $courseid = required_param('courseid', PARAM_INT);
            $entityid = optional_param('entityid', 0, PARAM_INT);
            $days = optional_param('days', 30, PARAM_INT);
            $engine_cls::add_compliance_course($courseid, $entityid, $days);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                get_string('msg_course_added', 'local_sentientia_compliance_report'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'removecourse':
            $id = required_param('id', PARAM_INT);
            $engine_cls::remove_compliance_course($id);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                get_string('msg_course_removed', 'local_sentientia_compliance_report'), null, \core\output\notification::NOTIFY_WARNING);
            break;
        case 'exclude':
            $excludeid = required_param('userid', PARAM_INT);
            $reason = optional_param('reason', 'Operations exclusion', PARAM_TEXT);
            $engine_cls::exclude_user($excludeid, $reason);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                get_string('msg_user_excluded', 'local_sentientia_compliance_report'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'include':
            $includeid = required_param('userid', PARAM_INT);
            $engine_cls::include_user($includeid);
            redirect(new moodle_url('/local/sentientia_compliance_report/index.php', ['tab' => 'config']),
                get_string('msg_user_included', 'local_sentientia_compliance_report'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

// Tenant scoping: '' only for a site admin (viewer_scope never yields '' for
// anyone else).
$orgpath = $scope->orgpath;

// A scoped admin may only drill down INSIDE their own tenant. The BU dropdown
// never offers another root org, but a hand-edited ?bu= used to widen the
// report to that tenant's people (2026-09-16 UAT finding).
[$bu, $dept, $subdept] = \local_sentientia_compliance_report\compliance_engine::clamp_filter_to_tenant(
    $orgpath, $bu, $dept, $subdept);

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
// Headcounts in the dropdowns follow the scope: a line manager sees their
// team's numbers, not the tenant's. The ids were clamped above.
$filter_bus = $engine::get_org_hierarchy_level(1, $orgpath, $teamuserids); // Business Units.
$filter_depts = ($bu > 0) ? $engine::get_org_hierarchy_children($bu, $teamuserids) : [];
$filter_subdepts = ($dept > 0) ? $engine::get_org_hierarchy_children($dept, $teamuserids) : [];

// Mark selected values.
foreach ($filter_bus as &$item) { $item['selected'] = ($item['id'] == $bu); }
foreach ($filter_depts as &$item) { $item['selected'] = ($item['id'] == $dept); }
foreach ($filter_subdepts as &$item) { $item['selected'] = ($item['id'] == $subdept); }
unset($item);

// Get data based on active tab — use cache for expensive queries.
$cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'local_sentientia_compliance_report', 'dashboard');
// Keyed on the scope too: two line managers in one tenant share $filterpath,
// and must not be served each other's figures (or the tenant's).
$cachekey = $scope->kpi_cache_key($filterpath);
$kpis = $cache->get($cachekey);
if ($kpis === false) {
    $kpis = $engine::get_summary_kpis($filterpath, $teamuserids);
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
        'label' => get_string('compliancerate', 'local_sentientia_compliance_report'),
        'value' => $compliance_rate . '%',
        'icon'  => $is_healthy ? 'check-circle' : 'exclamation-circle',
        'color' => $is_healthy ? 'success' : 'warning',
    ],
    [
        'label' => get_string('status_completed', 'local_sentientia_compliance_report'),
        'value' => number_format((int) ($kpis['completed'] ?? 0)),
        'icon'  => 'graduation-cap',
        'color' => 'success',
    ],
    [
        'label' => get_string('overduecount', 'local_sentientia_compliance_report'),
        'value' => number_format($overdue_count),
        'icon'  => 'exclamation-triangle',
        // Overdue tile is danger when there ARE overdue items, primary
        // (muted) when zero — "0 overdue" is good news, not alarming.
        'color' => $overdue_count > 0 ? 'danger' : 'primary',
    ],
    [
        'label' => get_string('notenrolledcount', 'local_sentientia_compliance_report'),
        'value' => number_format((int) ($kpis['not_enrolled'] ?? 0)),
        'icon'  => 'user-times',
        'color' => 'warning',
    ],
    [
        'label' => get_string('status_exempted', 'local_sentientia_compliance_report'),
        'value' => number_format((int) ($kpis['exempted'] ?? 0)),
        'icon'  => 'shield',
        'color' => 'info',
    ],
];
$matrix = ($tab === 'matrix') ? $engine::get_compliance_matrix($filterpath, $page, 50, $teamuserids) : null;
$defaulters = ($tab === 'defaulters') ? $engine::get_defaulters($filterpath, 100, $teamuserids) : null;
$scorecard = ($tab === 'scorecard') ? $engine::get_department_scorecard($filterpath, $teamuserids) : null;
$manager_report = ($tab === 'manager') ? $engine::get_manager_report($filterpath, $teamuserids) : null;
// Only a team view lists a manager who has left (their reports are still in
// the tree); say so rather than show a stale name as if they were current.
foreach ($manager_report ?? [] as $mr) {
    $mr->mgr_left = !empty($mr->mgr_deleted);
}

// Config tab: compliance courses + excluded users.
$config_courses = [];
$config_excluded = [];
if ($tab === 'config' && $canconfigure) {
    $config_courses = $engine::get_managed_courses();
    $config_excluded = $engine::get_excluded_users();
}

// Get all courses for the add-course dropdown.
$allcourses = [];
if ($tab === 'config' && $canconfigure) {
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
    'is_team_scope'     => ($scope->level === \local_sentientia_compliance_report\viewer_scope::LEVEL_TEAM),
    'team_size'         => count($teamuserids ?? []),
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
