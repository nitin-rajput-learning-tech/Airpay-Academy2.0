<?php
/**
 * Airpay Compliance Report — main dashboard page.
 *
 * Shows compliance matrix, KPIs, department scorecard, defaulters, manager report.
 * Siteadmin sees global data, tenant admin sees own org.
 *
 * @package    local_airpay_compliance_report
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

$PAGE->set_url(new moodle_url('/local/airpay_compliance_report/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('compliancereport', 'local_airpay_compliance_report'));
$PAGE->set_pagelayout('standard');

$tab = optional_param('tab', 'matrix', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

// Tenant scoping.
$orgpath = '';
if (!is_siteadmin()) {
    $parts = explode('/', $USER->open_path ?? '');
    $orgpath = '/' . ($parts[1] ?? '1');
}

$engine = \local_airpay_compliance_report\compliance_engine::class;

// Get data based on active tab.
$kpis = $engine::get_summary_kpis($orgpath);
$matrix = ($tab === 'matrix') ? $engine::get_compliance_matrix($orgpath, $page, 50) : null;
$defaulters = ($tab === 'defaulters') ? $engine::get_defaulters($orgpath) : null;
$scorecard = ($tab === 'scorecard') ? $engine::get_department_scorecard($orgpath) : null;
$manager_report = ($tab === 'manager') ? $engine::get_manager_report($orgpath) : null;

$data = [
    'kpis'              => $kpis,
    'tab_matrix'        => ($tab === 'matrix'),
    'tab_defaulters'    => ($tab === 'defaulters'),
    'tab_scorecard'     => ($tab === 'scorecard'),
    'tab_manager'       => ($tab === 'manager'),
    'matrix'            => $matrix,
    'has_matrix'        => !empty($matrix['rows']),
    'defaulters'        => $defaulters,
    'has_defaulters'    => !empty($defaulters),
    'scorecard'         => $scorecard,
    'has_scorecard'     => !empty($scorecard),
    'manager_report'    => $manager_report,
    'has_manager_report' => !empty($manager_report),
    'is_scoped'         => !empty($orgpath),
    'baseurl'           => (new moodle_url('/local/airpay_compliance_report/index.php'))->out(false),
    'exporturl'         => (new moodle_url('/local/airpay_compliance_report/export.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_compliance_report/dashboard', $data);
echo $OUTPUT->footer();
