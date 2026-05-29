<?php
/**
 * Excel export for compliance report.
 * Outputs the compliance matrix as downloadable .xlsx.
 *
 * @package    local_airpay_compliance_report
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->libdir . '/excellib.class.php');

$systemcontext = context_system::instance();
// C-002 fix (2026-05-29 QA walk, P1): mirror index.php's access logic so anyone who
// can VIEW the compliance report can also EXPORT it. The old check accepted only
// siteadmin OR local/courses:manage, which locked out compliance officers / tenant
// admins who reach the report via the BizLMS administrator role at a category context
// or via moodle/site:viewreports — they saw an Export button that threw nopermission.
$canaccess = is_siteadmin() || has_capability('local/courses:manage', $systemcontext);
if (!$canaccess) {
    // BizLMS administrator role at a category context (tenant admins / compliance officers).
    $canaccess = $DB->record_exists_sql(
        "SELECT 1 FROM {role_assignments} ra
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE ra.userid = :uid AND ra.roleid = 9 AND ctx.contextlevel = 40",
        ['uid' => $USER->id]);
}
if (!$canaccess) {
    // Managers / HRBP / trainers with the report-view capability.
    $canaccess = has_capability('moodle/site:viewreports', $systemcontext);
}
if (!$canaccess) {
    throw new moodle_exception('nopermission');
}

$orgpath = '';
if (!is_siteadmin()) {
    $orgpath = \local_airpay_org\tenant_manager::get_tenant_path();
}

$format = optional_param('format', 'xlsx', PARAM_ALPHA);

$engine = \local_airpay_compliance_report\compliance_engine::class;
$matrix = $engine::get_compliance_matrix($orgpath, 0, 10000); // All employees.
$kpis = $engine::get_summary_kpis($orgpath);

// CSV export option.
if ($format === 'csv') {
    $filename = 'Compliance_Report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM.

    // Header row.
    $headers = ['Employee ID', 'Name', 'Email', 'Department'];
    if (!empty($matrix['courses'])) {
        foreach ($matrix['courses'] as $course) {
            $headers[] = $course['shortname'] ?? $course['fullname'] ?? 'Course';
        }
    }
    fputcsv($output, $headers);

    // Data rows.
    if (!empty($matrix['rows'])) {
        foreach ($matrix['rows'] as $row) {
            $csvrow = [
                $row['empid'] ?? '',
                ($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''),
                $row['email'] ?? '',
                $row['department'] ?? '',
            ];
            if (!empty($row['statuses'])) {
                foreach ($row['statuses'] as $status) {
                    $csvrow[] = $status['label'] ?? $status['status'] ?? '';
                }
            }
            fputcsv($output, $csvrow);
        }
    }

    // Summary.
    fputcsv($output, []);
    fputcsv($output, ['=== SUMMARY ===']);
    fputcsv($output, ['Compliance Rate', ($kpis['compliance_rate'] ?? 0) . '%']);
    fputcsv($output, ['Completed', $kpis['completed'] ?? 0]);
    fputcsv($output, ['Overdue', $kpis['overdue'] ?? 0]);
    fputcsv($output, ['Not Enrolled', $kpis['not_enrolled'] ?? 0]);
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
