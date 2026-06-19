<?php
/**
 * Compliance audit export — CSV download.
 * Generates a CSV file with compliance status for all mandatory courses.
 * For RBI auditors and L&D reporting.
 *
 * Security/output hardening (2026-06-19, Moodle 5.2 compat audit):
 *   - require_sesskey() guards against CSRF (this streams employee PII).
 *   - Reached only via the sesskey'd button in the compliance block UI.
 *   - Streaming uses \core\dataformat::download_data() — the supported API
 *     that sends safe headers itself (no manual header()/fputcsv, no
 *     'headers already sent' risk on 5.2's stricter output handling) and
 *     auto-escapes spreadsheet formula-injection on every cell.
 *
 * @package    block_sentientia_compliance
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_courses:manage', $context);
require_sesskey();

$format = optional_param('format', 'csv', PARAM_ALPHA);
// Defensive: fall back to CSV if the requested dataformat writer is not installed
// (download_data() would otherwise throw a coding_exception).
if (!class_exists("dataformat_{$format}\\writer")) {
    $format = 'csv';
}

$now = time();

// Get all mandatory courses (with deadlines).
$mandatorycourses = $DB->get_records_select('course',
    'enddate > 0 AND visible = 1 AND id > 1',
    [], 'fullname ASC', 'id,shortname,fullname,enddate');

// Get all non-admin users.
$users = $DB->get_records_select('user',
    'deleted = 0 AND suspended = 0 AND id > 1',
    [], 'lastname ASC', 'id,firstname,lastname,email,open_employeeid,open_departmentid');

// Build data rows. Values are RAW: the dataformat CSV writer handles all
// CSV escaping + formula-injection escaping, so HTML-encoding here (s())
// would corrupt the output. format_string() resolves multilang tags but is
// told NOT to HTML-escape, for the same reason.
$stropts = ['context' => $context, 'escape' => false];
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
            $user->open_employeeid ?? 'N/A',
            $user->firstname . ' ' . $user->lastname,
            $user->email,
            format_string($course->shortname, true, $stropts),
            format_string($course->fullname, true, $stropts),
            userdate($course->enddate, '%Y-%m-%d'),
            $completed ? userdate($cc->timecompleted, '%Y-%m-%d') : 'N/A',
            $status,
        ];
    }
}

// Column headers.
$columns = [
    'Employee ID',
    'Full Name',
    'Email',
    'Course Code',
    'Course Name',
    'Deadline',
    'Completion Date',
    'Status',
];

// Auditor summary block — computed from the data rows before they are appended to.
$datacount = count($rows);
$overduecount = count(array_filter($rows, function($r) { return ($r[7] ?? '') === 'OVERDUE'; }));
$completedcount = count(array_filter($rows, function($r) { return ($r[7] ?? '') === 'Completed'; }));

$rows[] = [];
$rows[] = ['Report Generated:', date('Y-m-d H:i:s')];
$rows[] = ['Total Records:', $datacount];
$rows[] = ['Overdue:', $overduecount];
$rows[] = ['Completed:', $completedcount];

// Stream via the supported dataformat API. The filename is given WITHOUT
// extension; the writer appends '.csv'. download_data() sends headers,
// closes the session, and writes the body safely.
$filename = 'sentientia_compliance_audit_' . date('Y-m-d_His');
\core\dataformat::download_data($filename, $format, $columns, $rows);
exit;
