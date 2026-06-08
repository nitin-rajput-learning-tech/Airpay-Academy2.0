<?php
/**
 * Excel export for compliance report.
 * Outputs the compliance matrix as downloadable .xlsx.
 *
 * @package    local_sentientia_compliance_report
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->libdir . '/excellib.class.php');

// Gate on the dedicated export capability (see classes/permission.php). This
// supersedes the earlier inline C-002 fix. It authorises site admins, course
// managers, and the BizLMS Compliance Officer role (id 9, assigned at category
// context) — but NOT line managers: bulk PII export is deliberately tighter
// than the dashboard's view access, which managers still retain in index.php.
// Using the capability also drops the phantom `local/courses:manage` reference
// (that capability is only registered on the BizLMS production stack).
if (!\local_sentientia_compliance_report\permission::can_export()) {
    throw new moodle_exception('nopermission');
}

$orgpath = '';
if (!is_siteadmin()) {
    $orgpath = \local_sentientia_org\tenant_manager::get_tenant_path();
}

$format = optional_param('format', 'xlsx', PARAM_ALPHA);

$engine = \local_sentientia_compliance_report\compliance_engine::class;
$matrix = $engine::get_compliance_matrix($orgpath, 0, 10000); // All employees.
$kpis = $engine::get_summary_kpis($orgpath);

// CSV export option.
if ($format === 'csv') {
    $filename = 'Compliance_Report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM.

    // Header row — mirror the xlsx Sheet 1 columns + one column per mandatory course.
    $headers = ['Employee ID', 'Email', 'Full Name', 'Designation', 'Department'];
    foreach ($matrix['courses'] as $mc) {
        $headers[] = $mc->coursename;
    }
    fputcsv($output, $headers);

    // Data rows — use the exact shape compliance_engine::get_compliance_matrix()
    // returns ('courses' = objects with ->coursename; row keys employee_id /
    // fullname / designation / courses, each course having status_label). C-005:
    // the previous code read array keys (`empid`, `statuses`, `$course['shortname']`)
    // that never existed and fatally errored on the object-as-array access.
    foreach ($matrix['rows'] as $r) {
        $csvrow = [
            $r['employee_id'],
            $r['email'],
            $r['fullname'],
            $r['designation'],
            $r['department'],
        ];
        foreach ($r['courses'] as $cs) {
            $csvrow[] = $cs['status_label'];
        }
        fputcsv($output, $csvrow);
    }

    // Summary — mirror the xlsx Summary sheet.
    fputcsv($output, []);
    fputcsv($output, ['=== SUMMARY ===']);
    fputcsv($output, ['Total Items', $kpis['total'] ?? 0]);
    fputcsv($output, ['Completed', $kpis['completed'] ?? 0]);
    fputcsv($output, ['Overdue', $kpis['overdue'] ?? 0]);
    fputcsv($output, ['Not Enrolled', $kpis['not_enrolled'] ?? 0]);
    fputcsv($output, ['Exempted', $kpis['exempted'] ?? 0]);
    fputcsv($output, ['Compliance Rate', ($kpis['compliance_rate'] ?? 0) . '%']);
    fputcsv($output, ['Generated', date('d M Y H:i')]);

    fclose($output);
    die();
}

$filename = 'Compliance_Report_' . date('Y-m-d') . '.xlsx';

$workbook = new \MoodleExcelWorkbook($filename);

// Sheet 1: Compliance Matrix.
$sheet1 = $workbook->add_worksheet('Compliance Report');
$format_header = $workbook->add_format(['bold' => 1, 'bg_color' => '#0066A7', 'color' => 'white']);
$format_completed = $workbook->add_format(['bg_color' => '#d4edda', 'color' => '#155724']);
$format_overdue = $workbook->add_format(['bg_color' => '#f8d7da', 'color' => '#721c24']);
$format_default = $workbook->add_format();

// Headers.
$col = 0;
$sheet1->write(0, $col++, 'Employee ID', $format_header);
$sheet1->write(0, $col++, 'Email', $format_header);
$sheet1->write(0, $col++, 'Full Name', $format_header);
$sheet1->write(0, $col++, 'Designation', $format_header);
$sheet1->write(0, $col++, 'Department', $format_header);

foreach ($matrix['courses'] as $mc) {
    $sheet1->write(0, $col++, $mc->coursename, $format_header);
}

// Data rows.
$row = 1;
foreach ($matrix['rows'] as $r) {
    $col = 0;
    $sheet1->write($row, $col++, $r['employee_id'], $format_default);
    $sheet1->write($row, $col++, $r['email'], $format_default);
    $sheet1->write($row, $col++, $r['fullname'], $format_default);
    $sheet1->write($row, $col++, $r['designation'], $format_default);
    $sheet1->write($row, $col++, $r['department'], $format_default);

    foreach ($r['courses'] as $cs) {
        $fmt = ($cs['status'] === 'completed') ? $format_completed :
               (($cs['status'] === 'overdue') ? $format_overdue : $format_default);
        $sheet1->write($row, $col++, $cs['status_label'], $fmt);
    }
    $row++;
}

// Sheet 2: Summary.
$sheet2 = $workbook->add_worksheet('Summary');
$sheet2->write(0, 0, 'Metric', $format_header);
$sheet2->write(0, 1, 'Value', $format_header);

$sheet2->write(1, 0, 'Total Items');
$sheet2->write(1, 1, $kpis['total']);
$sheet2->write(2, 0, 'Completed');
$sheet2->write(2, 1, $kpis['completed']);
$sheet2->write(3, 0, 'Overdue');
$sheet2->write(3, 1, $kpis['overdue']);
$sheet2->write(4, 0, 'Not Enrolled');
$sheet2->write(4, 1, $kpis['not_enrolled']);
$sheet2->write(5, 0, 'Exempted');
$sheet2->write(5, 1, $kpis['exempted']);
$sheet2->write(6, 0, 'Compliance Rate');
$sheet2->write(6, 1, $kpis['compliance_rate'] . '%');
$sheet2->write(7, 0, 'Report Date');
$sheet2->write(7, 1, date('Y-m-d H:i'));

$workbook->close();
exit;
