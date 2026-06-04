<?php
// Airpay Reports — execute a saved report and display results.
//
// @package    local_sentientia_reports
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_reports:view', $context);

$reportid = required_param('id', PARAM_INT);
$report = \local_sentientia_reports\report_manager::get($reportid);
if (!$report) {
    throw new moodle_exception('invalidreport', 'local_sentientia_reports');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_reports/run.php', ['id' => $reportid]));
$PAGE->set_title('Report — ' . format_string($report->name));
$PAGE->set_heading('Report — ' . format_string($report->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add(get_string('pluginname', 'local_sentientia_reports'),
    new moodle_url('/local/sentientia_reports/index.php'));
$PAGE->navbar->add(format_string($report->name));

$can_export = is_siteadmin() || has_capability('local/sentientia_reports:export', $context);

// Run the report.
$result = \local_sentientia_reports\report_manager::run_report($reportid);

// Resolve org name for display.
$orgname = '';
$dbman = $DB->get_manager();
if ($report->costcenterid > 0 && $dbman->table_exists('local_airpay_org')) {
    $org = $DB->get_record('local_airpay_org', ['id' => $report->costcenterid], 'fullname');
    if ($org) {
        $orgname = format_string($org->fullname);
    }
}

$type_short = \local_sentientia_reports\report_manager::REPORT_TYPE_SHORT;
$typelabel = $type_short[$report->report_type] ?? ucfirst(str_replace('_', ' ', $report->report_type));

// Build template rows with cell-by-column layout.
$row_limit = 500;
$rendered_rows = [];
foreach (array_slice($result['rows'], 0, $row_limit) as $row) {
    $cells = [];
    foreach ($result['columns'] as $col) {
        $val = $row[$col['key']] ?? '';
        $css = '';
        // Special CSS for known fields.
        if ($col['key'] === 'rate' && isset($row['rate_class'])) {
            $css = $row['rate_class'];
        }
        $cells[] = [
            'value'    => is_string($val) ? format_string($val) : $val,
            'cssclass' => $css,
        ];
    }
    $rendered_rows[] = ['cells' => $cells];
}

$data = [
    'reportid'     => $report->id,
    'name'         => format_string($report->name),
    'description'  => format_string($report->description ?? ''),
    'typelabel'    => $typelabel,
    'orgname'      => $orgname,
    'columns'      => $result['columns'],
    'rows'         => $rendered_rows,
    'has_rows'     => !empty($rendered_rows),
    'summary'      => $result['summary'] ?? [],
    'has_summary'  => !empty($result['summary']),
    'row_count'    => $row_limit,
    'row_limit_reached' => count($result['rows']) > $row_limit,
    'backurl'      => (new moodle_url('/local/sentientia_reports/index.php'))->out(false),
    'can_export'   => $can_export,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_reports/run', $data);
echo $OUTPUT->footer();
