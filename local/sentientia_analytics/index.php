<?php
/**
 * Airpay Advanced Analytics Dashboard.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$systemcontext = context_system::instance();

// Capability layer added 2026-09-22. This page used to gate on
// local/courses:manage - renamed by ADR-025 and undefined since, so
// has_capability() answered false with a debugging notice - plus a hardcoded
// role id 9 at category context. Net effect: the manager role could not open
// the dashboard built for it, and role id 9 names a different role on every
// other Sentientia deployment. permission::can_view() checks a real
// capability at system context AND at the category contexts where BizLMS
// assigns its org-admin shell.
if (!\local_sentientia_analytics\permission::can_view()) {
    throw new moodle_exception('nopermission');
}

$canviewall = \local_sentientia_analytics\permission::can_view_all_orgs();
$canexport  = \local_sentientia_analytics\permission::can_export();

$PAGE->set_url(new moodle_url('/local/sentientia_analytics/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('analytics', 'local_sentientia_analytics'));
$PAGE->set_pagelayout('standard');

$range = optional_param('range', '30d', PARAM_ALPHA);
$orgid = optional_param('orgid', 0, PARAM_INT);

// Determine org scope. Whatever is asked for is clamped to what this user may
// see. Two defects lived here before 2026-09-22:
//   - the ?orgid= branch was not gated at all, so any viewer could read
//     another tenant's numbers by editing the query string;
//   - the fallback was '/' . ($parts[1] ?? '1'), so a user whose open_path
//     was missing or malformed silently got tenant 1's data.
$org = null;
$requestedpath = '';
if ($orgid > 0) {
    $org = $DB->get_record('local_sentientia_org', ['id' => $orgid]);
    if ($org) {
        $requestedpath = $org->path;
    }
}

$orgpath = \local_sentientia_analytics\permission::clamp_org_path($requestedpath);
if ($orgpath === null) {
    // No tenant could be established for this user. Refuse: the only
    // fallbacks available are tenant 1 (someone else's data) and the empty
    // string, which analytics_manager reads as every tenant at once.
    throw new moodle_exception('nopermission');
}

if ($org !== null && $orgpath !== rtrim($requestedpath, '/')) {
    // Clamped away from what was requested - do not label the page with an
    // org whose numbers are not the ones being shown.
    $org = null;
    $orgid = 0;
}

// The org picker is only meaningful to someone who may cross org boundaries.
$org_options = [];
if ($canviewall) {
    $orgs = $DB->get_records('local_sentientia_org', ['depth' => 1, 'visible' => 1], 'fullname ASC');
    foreach ($orgs as $o) {
        $org_options[] = [
            'id' => $o->id,
            'name' => format_string($o->fullname),
            'selected' => ($o->id == $orgid),
        ];
    }
}
$PAGE->set_secondary_navigation(false);

$manager = \local_sentientia_analytics\analytics_manager::class;

$kpis       = $manager::get_kpis($range, $orgpath);
$funnel     = $manager::get_funnel($orgpath);
$heatmap    = $manager::get_compliance_heatmap($orgpath);
$top_courses = $manager::get_course_effectiveness(10, $orgpath);

// Add drill-down URLs to heatmap departments.
$drillbase = new moodle_url('/local/sentientia_analytics/drilldown.php');
foreach ($heatmap as $k => $dept) {
    $dept = (array) $dept;
    $dept['drilldown_url'] = $drillbase->out(false) . '?type=department&path=' . urlencode($dept['path'] ?? $orgpath);
    $heatmap[$k] = $dept;
}

// Add drill-down URLs to course effectiveness — $DB returns stdClass, convert to arrays.
foreach ($top_courses as $k => $course) {
    $course = (array) $course;
    $course['course_drilldown_url'] = $drillbase->out(false) . '?type=course&courseid=' . (int)($course['id'] ?? 0);
    $top_courses[$k] = $course;
}

$data = [
    'kpis'          => $kpis,
    'has_kpis'      => !empty($kpis),
    'funnel'        => $funnel,
    'has_funnel'    => !empty($funnel),
    'heatmap'       => $heatmap,
    'has_heatmap'   => !empty($heatmap),
    'top_courses'   => $top_courses,
    'has_courses'   => !empty($top_courses),
    'range'         => $range,
    'range_7d'      => ($range === '7d'),
    'range_30d'     => ($range === '30d'),
    'range_90d'     => ($range === '90d'),
    'range_ytd'     => ($range === 'ytd'),
    'is_scoped'     => !empty($orgpath),
    'orgid'         => $orgid,
    'org_options'   => $org_options,
    'has_org_filter' => !empty($org_options),
    'org_label'     => ($orgid > 0 && $org !== null)
        ? format_string($org->fullname)
        : get_string('allbusinessunits', 'local_sentientia_analytics'),
    'can_export'    => $canexport,
    'baseurl'       => (new moodle_url('/local/sentientia_analytics/index.php'))->out(false),
    'filterurl'     => (new moodle_url('/local/sentientia_analytics/index.php'))->out(false),
    'exporturl'     => (new moodle_url('/local/sentientia_analytics/export.php', ['range' => $range, 'format' => 'csv', 'orgid' => $orgid]))->out(false),
];

// ── P1.2 Predictive surfaces — feature-flagged, DEFAULT OFF ───────────
// The existing dashboard data above is always computed; predictive data
// is computed ONLY when the flag is ON, so there is zero performance
// impact when the flag is OFF (the Airpay Academy default).
$show_predictive = class_exists('\local_sentientia_platform\feature_flags')
    && \local_sentientia_platform\feature_flags::is_enabled('sentientia.analytics.predictive.enabled');

$show_roi = class_exists('\local_sentientia_platform\feature_flags')
    && \local_sentientia_platform\feature_flags::is_enabled('sentientia.analytics.roi.enabled');

$data['show_predictive'] = $show_predictive;
$data['show_roi']        = $show_roi;

if ($show_predictive) {
    $engine     = \local_sentientia_analytics\predictive_engine::class;
    $atrisk     = $engine::get_at_risk_users($orgpath, 50);
    $skillgaps  = $engine::get_skill_gap_projection($orgpath);

    $data['atrisk']               = $atrisk;
    $data['has_atrisk']           = !empty($atrisk);
    $data['atrisk_description']   = get_string('atrisk_description', 'local_sentientia_analytics');
    $data['skillgaps']            = $skillgaps;
    $data['has_skillgaps']        = !empty($skillgaps);
    $data['skillgap_description'] = get_string('skillgap_description', 'local_sentientia_analytics');
}

if ($show_roi) {
    $roi = \local_sentientia_analytics\roi_calculator::compute($range, $orgpath);
    $data['roi']             = $roi;
    $data['roi_empty']       = empty($roi['completions'] ?? $roi['raw_metrics']['completions'] ?? false);
    $data['roi_description'] = get_string('roi_description', 'local_sentientia_analytics');
    // Flatten ROI into data for template convenience.
    if (!empty($roi)) {
        foreach ($roi as $k => $v) {
            if (!is_array($v)) {
                $data['roi_' . $k] = $v;
            }
        }
        $data['roi_components']   = $roi['components']   ?? [];
        $data['roi_assumptions']  = $roi['assumptions']  ?? [];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_analytics/dashboard', $data);
echo $OUTPUT->footer();
