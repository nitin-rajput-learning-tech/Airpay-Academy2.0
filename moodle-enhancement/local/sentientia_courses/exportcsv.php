<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CSV export for the sentientia_courses management page.
 *
 * Re-uses list_courses' WHERE-assembly logic so the export reflects exactly
 * what the admin sees on screen. Datatable.js builds the URL with current
 * search + sort + filter_* params; we honour them here.
 *
 * Closes the standalone "Course CSV export" item from FEATURE-PARITY-AUDIT.md.
 * Mirrors the pattern of local/sentientia_users/exportcsv.php.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/sentientia_courses/exportcsv.php');
require_capability('local/sentientia_courses:view', $context);

global $DB, $USER;

// Read URL params written by theme_sentientia/datatable's export click handler.
// Datatable passes a single JSON `filters` blob for non-trivial filters; we
// also accept flat filter_* params for human-driven URL building (parity with
// the users exporter).
$search   = optional_param('search',  '', PARAM_TEXT);
$sort     = optional_param('sort',    'fullname', PARAM_ALPHAEXT);
$sortdir  = optional_param('sortdir', 'asc', PARAM_ALPHA);
$categoryid = optional_param('filter_categoryid', 0, PARAM_INT);
$visibility = optional_param('filter_visibility', 'all', PARAM_ALPHA);

// Sort whitelist (mirror list_courses::execute).
$allowed_sort = ['fullname', 'shortname', 'timecreated', 'visible', 'category'];
if (!in_array($sort, $allowed_sort, true)) {
    $sort = 'fullname';
}
$sortdir = strtolower($sortdir) === 'desc' ? 'DESC' : 'ASC';
$orderby = "c.{$sort} {$sortdir}, c.id ASC";

// WHERE assembly — must match list_courses so the export equals what's on screen.
$where = ['c.id > 1']; // Exclude site course.
$sqlparams = [];

// Tenant scope: non-siteadmin only sees their org tree (+ legacy NULL paths).
if (!is_siteadmin()) {
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    if ($top > 0) {
        $where[] = '(c.open_path = :corgexact OR c.open_path LIKE :corgprefix OR c.open_path IS NULL)';
        $sqlparams['corgexact']  = '/' . $top;
        $sqlparams['corgprefix'] = $DB->sql_like_escape('/' . $top . '/') . '%';
    }
}

if ($categoryid > 0) {
    $where[] = 'c.category = :catid';
    $sqlparams['catid'] = $categoryid;
}

if ($visibility === 'visible') {
    $where[] = 'c.visible = 1';
} else if ($visibility === 'hidden') {
    $where[] = 'c.visible = 0';
}

if (!empty($search)) {
    $term = '%' . $DB->sql_like_escape($search) . '%';
    $where[] = '(' .
        $DB->sql_like('c.fullname',  ':s1', false) . ' OR ' .
        $DB->sql_like('c.shortname', ':s2', false) . ' OR ' .
        $DB->sql_like('c.idnumber',  ':s3', false) .
    ')';
    $sqlparams['s1'] = $term;
    $sqlparams['s2'] = $term;
    $sqlparams['s3'] = $term;
}

$wheresql = implode(' AND ', $where);

// Cap export at 10,000 rows to prevent memory blow-up. Airpay has 411 courses
// on production today; 10K is ~24× over.
$exportlimit = 10000;

// Pull rows with category name + enrolled + completion counts in a single
// query. The subselect for completion count is bounded — Moodle's course
// completion table grows linearly with enrolments, but the EXISTS-style
// inner SELECT only sums per-course rows so it's O(1) per outer row.
$sql = "SELECT c.id, c.fullname, c.shortname, c.idnumber, c.visible,
               c.timecreated, c.startdate, c.enddate,
               cat.name AS catname,
               (SELECT COUNT(*) FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = c.id) AS enrolled_count,
               (SELECT COUNT(*) FROM {course_completions}
                 WHERE course = c.id
                   AND timecompleted IS NOT NULL
                   AND timecompleted > 0) AS completed_count
          FROM {course} c
     LEFT JOIN {course_categories} cat ON cat.id = c.category
         WHERE {$wheresql}
      ORDER BY {$orderby}";

$rows = $DB->get_records_sql($sql, $sqlparams, 0, $exportlimit);

// CSV output. Filename includes timestamp so repeated exports don't overwrite.
$filename = 'sentientia_courses_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens with correct encoding.
fwrite($out, "\xEF\xBB\xBF");

// Header row.
fputcsv($out, [
    'Course ID',
    'Full Name',
    'Short Name',
    'ID Number',
    'Category',
    'Visibility',
    'Enrolled',
    'Completed',
    'Completion %',
    'Start Date',
    'End Date',
    'Created',
]);

foreach ($rows as $c) {
    $enrolled  = (int) ($c->enrolled_count ?? 0);
    $completed = (int) ($c->completed_count ?? 0);
    $completion_pct = $enrolled > 0
        ? number_format(($completed / $enrolled) * 100, 1) . '%'
        : '—';

    fputcsv($out, [
        $c->id,
        $c->fullname,
        $c->shortname ?? '',
        $c->idnumber ?? '',
        $c->catname ?? '',
        $c->visible ? 'Visible' : 'Hidden',
        $enrolled,
        $completed,
        $completion_pct,
        $c->startdate ? userdate($c->startdate, '%Y-%m-%d') : '',
        $c->enddate   ? userdate($c->enddate,   '%Y-%m-%d') : '',
        $c->timecreated ? userdate($c->timecreated, '%Y-%m-%d') : '',
    ]);
}

fclose($out);
exit;
