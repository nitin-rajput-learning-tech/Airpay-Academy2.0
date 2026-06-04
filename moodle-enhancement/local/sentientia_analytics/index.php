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

if (!$isadmin) {
    throw new moodle_exception('nopermission');
}

$PAGE->set_url(new moodle_url('/local/sentientia_analytics/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('analytics', 'local_sentientia_analytics'));
$PAGE->set_pagelayout('standard');

$range = optional_param('range', '30d', PARAM_ALPHA);
$orgid = optional_param('orgid', 0, PARAM_INT);

// Determine org scope — siteadmin can pick any org via dropdown.
$orgpath = '';
if ($orgid > 0) {
    $org = $DB->get_record('local_airpay_org', ['id' => $orgid]);
    if ($org) {
        $orgpath = $org->path;
    }
} else if (!is_siteadmin()) {
    $parts = explode('/', $USER->open_path ?? '');
    $orgpath = '/' . ($parts[1] ?? '1');
}

// Build org filter options for siteadmin.
$org_options = [];
if (is_siteadmin()) {
    $orgs = $DB->get_records('local_airpay_org', ['depth' => 1, 'visible' => 1], 'fullname ASC');
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
    'org_label'     => $orgid > 0 ? ($org->fullname ?? 'Selected') : 'All Business Units',
    'baseurl'       => (new moodle_url('/local/sentientia_analytics/index.php'))->out(false),
    'filterurl'     => (new moodle_url('/local/sentientia_analytics/index.php'))->out(false),
    'exporturl'     => (new moodle_url('/local/sentientia_analytics/export.php', ['range' => $range, 'format' => 'csv', 'orgid' => $orgid]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_analytics/dashboard', $data);
echo $OUTPUT->footer();
