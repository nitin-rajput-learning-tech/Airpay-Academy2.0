<?php
// Airpay Reports — CSV export endpoint.
//
// @package    local_airpay_reports
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_reports:export', $context);

$reportid = required_param('id', PARAM_INT);
$report = \local_airpay_reports\report_manager::get($reportid);
if (!$report) {
    throw new moodle_exception('invalidreport', 'local_airpay_reports');
}

// Run the report (full result, no row limit applied).
$result = \local_airpay_reports\report_manager::run_report($reportid);

// Build a safe filename.
$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($report->name));
$slug = trim($slug, '-');
if (empty($slug)) $slug = 'report';
$filename = $slug . '-' . date('Ymd-His') . '.csv';

// Convert rows to CSV.
$csv = \local_airpay_reports\report_manager::rows_to_csv($result);

// Send headers + body. Use BOM for Excel-friendly UTF-8.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo "\xEF\xBB\xBF"; // UTF-8 BOM
echo $csv;
exit;
