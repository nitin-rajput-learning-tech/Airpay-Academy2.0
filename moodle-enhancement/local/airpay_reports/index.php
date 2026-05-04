<?php
// Airpay Reports — admin management page (saved report definitions).
//
// @package    local_airpay_reports
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_reports:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_reports/index.php'));
$PAGE->set_title('Reports');
$PAGE->set_heading('Reports');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_manage = is_siteadmin() || has_capability('local/airpay_reports:manage', $context);
$can_export = is_siteadmin() || has_capability('local/airpay_reports:export', $context);

$dbman = $DB->get_manager();
$rows = [];
$total = 0;
$active = 0;
$archived = 0;
$total_runs = 0;

if ($dbman->table_exists('local_airpay_reports')) {
    $total    = \local_airpay_reports\report_manager::count_reports();
    $active   = \local_airpay_reports\report_manager::count_reports(
        \local_airpay_reports\report_manager::STATUS_ACTIVE);
    $archived = \local_airpay_reports\report_manager::count_reports(
        \local_airpay_reports\report_manager::STATUS_ARCHIVED);
    $total_runs = (int) $DB->get_field_sql("SELECT COALESCE(SUM(runcount), 0) FROM {local_airpay_reports}");

    // Load reports with org name resolved.
    $org_table_exists = $dbman->table_exists('local_airpay_org');

    $records = $DB->get_records_sql(
        "SELECT r.*" . ($org_table_exists ? ", o.fullname AS orgname" : ", '' AS orgname") . "
           FROM {local_airpay_reports} r
      " . ($org_table_exists ? "LEFT JOIN {local_airpay_org} o ON o.id = r.costcenterid" : "") . "
       ORDER BY r.timemodified DESC, r.id DESC", [], 0, 200);

    $type_short = \local_airpay_reports\report_manager::REPORT_TYPE_SHORT;
    $type_css = [
        'course_completion'   => 'badge-type-completion',
        'compliance_overview' => 'badge-type-compliance',
        'user_activity'       => 'badge-type-activity',
        'enrolment_trend'     => 'badge-type-trend',
    ];

    foreach ($records as $r) {
        $status = (int) $r->status;
        $statuslabel = $status === 1 ? 'Active' : 'Archived';
        $statuscss = $status === 1 ? 'badge-success' : 'badge-warning';

        $type_label = $type_short[$r->report_type] ?? ucfirst(str_replace('_', ' ', $r->report_type));
        $typecss = $type_css[$r->report_type] ?? 'badge-type-completion';

        $rows[] = [
            'id'           => (int) $r->id,
            'name'         => format_string($r->name),
            'description'  => format_string($r->description ?? ''),
            'typelabel'    => $type_label,
            'typecss'      => $typecss,
            'orgname'      => !empty($r->orgname) ? format_string($r->orgname) : '',
            'lastrun_label' => $r->lastrun ? userdate($r->lastrun, '%d %b %Y, %H:%M') : '',
            'runcount'     => (int) $r->runcount,
            'is_active'    => ($status === 1),
            'active_int'   => $status,
            'statuslabel'  => $statuslabel,
            'statuscss'    => $statuscss,
        ];
    }
}

$data = [
    'total'        => $total,
    'active'       => $active,
    'archived'     => $archived,
    'total_runs'   => $total_runs,
    'reports'      => $rows,
    'has_reports'  => !empty($rows),
    'can_manage'   => $can_manage,
    'can_export'   => $can_export,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_reports/manage', $data);
echo $OUTPUT->footer();
