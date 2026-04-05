<?php
/**
 * Compliance audit export — CSV download.
 * Generates a CSV file with compliance status for all mandatory courses.
 * For RBI auditors and L&D reporting.
 *
 * @package    block_airpay_compliance
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/courses:manage', $context);

$format = optional_param('format', 'csv', PARAM_ALPHA);
$now = time();

// Get all mandatory courses (with deadlines).
$mandatorycourses = $DB->get_records_select('course',
    'enddate > 0 AND visible = 1 AND id > 1',
    [], 'fullname ASC', 'id,shortname,fullname,enddate');

// Get all non-admin users.
$users = $DB->get_records_select('user',
    'deleted = 0 AND suspended = 0 AND id > 1',
    [], 'lastname ASC', 'id,firstname,lastname,email,open_employeeid,open_departmentid');

// Build data rows.
$rows = [];
foreach ($users as $user) {
    foreach ($mandatorycourses as $course) {
        // Check if enrolled.
        $enrolled = $DB->record_exists_sql(
            "SELECT 1 FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => $course->id, 'uid' => $user->id]);

        if (!$enrolled) continue;

        // Check completion.
        $cc = $DB->get_record('course_completions', [
            'userid' => $user->id,
            'course' => $course->id,
        ]);
        $completed = ($cc && $cc->timecompleted);
        $overdue = (!$completed && $course->enddate < $now);

        $status = 'Not Started';
        if ($completed) {
            $status = 'Completed';
        } else if ($overdue) {
            $status = 'OVERDUE';
        } else {
            $daysremaining = max(0, round(($course->enddate - $now) / 86400));
            $status = $daysremaining <= 7 ? "Due Soon ({$daysremaining}d)" : "On Track ({$daysremaining}d)";
        }

        $rows[] = [
            s($user->open_employeeid ?? 'N/A'),
            s($user->firstname . ' ' . $user->lastname),
            s($user->email),
            format_string($course->shortname),
            format_string($course->fullname),
            userdate($course->enddate, '%Y-%m-%d'),
            $completed ? userdate($cc->timecompleted, '%Y-%m-%d') : 'N/A',
            $status,
        ];
    }
}

// Output CSV.
$filename = 'airpay_compliance_audit_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Header row.
fputcsv($output, [
    'Employee ID',
    'Full Name',
    'Email',
    'Course Code',
    'Course Name',
    'Deadline',
    'Completion Date',
    'Status',
]);

// Data rows.
foreach ($rows as $row) {
    fputcsv($output, $row);
}

// Summary row.
fputcsv($output, []);
fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Total Records:', count($rows)]);
fputcsv($output, ['Overdue:', count(array_filter($rows, function($r) { return $r[7] === 'OVERDUE'; }))]);
fputcsv($output, ['Completed:', count(array_filter($rows, function($r) { return $r[7] === 'Completed'; }))]);

fclose($output);
exit;
