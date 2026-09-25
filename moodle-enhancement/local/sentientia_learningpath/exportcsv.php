<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Learning path CSV export — Phase 5 B.5.
 *
 * Two modes:
 *   ?mode=paths       (default) → all paths with course + user counts
 *   ?mode=path_users&id=N       → users enrolled in a specific path with completion
 *
 * @package local_sentientia_learningpath
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url('/local/sentientia_learningpath/exportcsv.php');
require_capability('local/sentientia_learningpath:view', $ctx);

$mode = optional_param('mode', 'paths', PARAM_ALPHA);
$pathid = optional_param('id', 0, PARAM_INT);

// ADR-031: refuse another tenant's path BEFORE any CSV header goes out.
if ($mode === 'path_users' && $pathid > 0) {
    \local_sentientia_learningpath\path_manager::require_path_tenant($pathid);
}

$filename = 'sentientia_learningpath_' . $mode
          . ($pathid ? "_$pathid" : '')
          . '_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

if ($mode === 'paths') {
    fputcsv($out, ['Path ID', 'Name', 'Status', 'Courses', 'Users', 'Created']);
    // ADR-031: the caller's tenant only ('1=1' cross-tenant, '1=0' no tenant).
    // Until 2026-09-25 this exported every tenant's paths.
    [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('lp');
    $rows = $DB->get_records_sql("
        SELECT lp.id, lp.name, lp.status, lp.timecreated,
               (SELECT COUNT(*) FROM {local_sentientia_learningpath_courses}
                 WHERE pathid = lp.id) AS course_count,
               (SELECT COUNT(*) FROM {local_sentientia_learningpath_users}
                 WHERE pathid = lp.id) AS user_count
          FROM {local_sentientia_learningpath} lp
         WHERE $tnsql
      ORDER BY lp.name ASC", $tnargs, 0, 10000);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r->id,
            $r->name,
            $r->status == 1 ? 'Active' : 'Archived',
            $r->course_count,
            $r->user_count,
            $r->timecreated ? userdate($r->timecreated, '%Y-%m-%d') : '',
        ]);
    }
} else if ($mode === 'path_users' && $pathid > 0) {
    $path = \local_sentientia_learningpath\path_manager::require_path_tenant($pathid);
    fputcsv($out, ['Path', $path->name]);
    fputcsv($out, []);
    fputcsv($out, ['User ID', 'Name', 'Email', 'Employee ID', 'Enrolled',
                   'Courses in path', 'Completed in path', 'Completion %']);
    // Get users + their per-course completion within this path's courses.
    // ADR-031: an in-tenant path can still hold other tenants' or pathless
    // learners (site-admin, request/approval-flow or pre-fix enrolments); a
    // scoped caller exports only their own tenant's ('1=1' cross-tenant).
    [$rosql, $roparams] = \local_sentientia_learningpath\path_manager::roster_scope(true, 'u');
    $users = $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname, u.email, u.open_employeeid,
               lpu.timecreated AS enrolled_on
          FROM {local_sentientia_learningpath_users} lpu
          JOIN {user} u ON u.id = lpu.userid
         WHERE lpu.pathid = :pid AND u.deleted = 0 AND $rosql
      ORDER BY u.lastname ASC", ['pid' => $pathid] + $roparams, 0, 10000);
    $path_courses = $DB->get_fieldset_select('local_sentientia_learningpath_courses',
        'courseid', 'pathid = :pid', ['pid' => $pathid]);
    $course_count = count($path_courses);
    foreach ($users as $u) {
        $completed = 0;
        foreach ($path_courses as $cid) {
            $cc = $DB->get_record('course_completions',
                ['userid' => $u->id, 'course' => $cid]);
            if ($cc && $cc->timecompleted) $completed++;
        }
        $pct = $course_count > 0 ? round(100 * $completed / $course_count, 1) : 0;
        fputcsv($out, [
            $u->id,
            trim($u->firstname . ' ' . $u->lastname),
            $u->email,
            $u->open_employeeid ?? '',
            $u->enrolled_on ? userdate($u->enrolled_on, '%Y-%m-%d') : '',
            $course_count,
            $completed,
            $pct . '%',
        ]);
    }
} else {
    fputcsv($out, ['ERROR', 'Invalid mode or missing path id']);
}

fclose($out);
exit;
