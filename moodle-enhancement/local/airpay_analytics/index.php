<?php
/**
 * Airpay Advanced Analytics Dashboard.
 *
 * @package    local_airpay_analytics
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

$PAGE->set_url(new moodle_url('/local/airpay_analytics/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('analytics', 'local_airpay_analytics'));
$PAGE->set_pagelayout('standard');

$range = optional_param('range', '30d', PARAM_ALPHA);

// Determine org scope.
$orgpath = '';
if (!is_siteadmin()) {
    $parts = explode('/', $USER->open_path ?? '');
    $orgpath = '/' . ($parts[1] ?? '1');
}

$manager = \local_airpay_analytics\analytics_manager::class;

$kpis       = $manager::get_kpis($range, $orgpath);
$funnel     = $manager::get_funnel($orgpath);
$heatmap    = $manager::get_compliance_heatmap($orgpath);
$top_courses = $manager::get_course_effectiveness(10);

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
    'baseurl'       => (new moodle_url('/local/airpay_analytics/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_analytics/dashboard', $data);
echo $OUTPUT->footer();
