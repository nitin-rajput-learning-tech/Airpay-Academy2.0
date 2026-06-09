<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CSV export for the sentientia_users management page.
 *
 * Re-uses list_users' WHERE-assembly logic so the export reflects exactly
 * what the admin sees on screen. Datatable.js builds the URL with current
 * search + sort + filter_* params; we honour them here.
 *
 * Closes G-01 from FEATURE-PARITY-AUDIT.md.
 *
 * @package    local_sentientia_users
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/sentientia_users/exportcsv.php');
require_capability('local/sentientia_users:view', $context);

global $DB, $USER, $CFG;

// Read URL params written by theme_sentientia/datatable's export click handler.
$search   = optional_param('search',  '', PARAM_TEXT);
$sort     = optional_param('sort',    'lastname', PARAM_ALPHAEXT);
$sortdir  = optional_param('sortdir', 'asc', PARAM_ALPHA);
$orgid    = optional_param('filter_orgid',  0, PARAM_INT);
$status   = optional_param('filter_status', 'active', PARAM_ALPHANUMEXT);

// Sort whitelist (mirror list_users::execute).
$allowed_sort = ['firstname', 'lastname', 'email', 'open_employeeid',
                 'open_designation', 'lastaccess', 'suspended'];
if (!in_array($sort, $allowed_sort, true)) {
    $sort = 'lastname';
}
$sortdir = strtolower($sortdir) === 'desc' ? 'DESC' : 'ASC';
$orderby = "u.{$sort} {$sortdir}, u.id ASC";

// WHERE assembly (mirror list_users::execute, same param names so SQL stays parameterised).
$where = ['u.deleted = 0', 'u.id > 2'];
$sqlparams = [];

if ($orgid > 0) {
    $org = $DB->get_record('local_sentientia_org', ['id' => $orgid], 'path');
    if ($org && !empty($org->path)) {
        // Same tenant boundary check as list_users — non-siteadmin can only
        // export from their own tenant tree.
        if (!is_siteadmin()) {
            $caller_parts = explode('/', trim($USER->open_path ?? '', '/'));
            $caller_top = isset($caller_parts[0]) && ctype_digit($caller_parts[0])
                ? '/' . (int) $caller_parts[0] : '';
            $is_inside = ($org->path === $caller_top)
                || (strpos($org->path, $caller_top . '/') === 0);
            if (empty($caller_top) || !$is_inside) {
                throw new \moodle_exception('outoftenant', 'local_sentientia_users');
            }
        }
        $where[] = '(u.open_path = :orgexact OR u.open_path LIKE :orgprefix)';
        $sqlparams['orgexact']  = rtrim($org->path, '/');
        $sqlparams['orgprefix'] = $DB->sql_like_escape(rtrim($org->path, '/') . '/') . '%';
    }
} else if (!is_siteadmin()) {
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    if ($top > 0) {
        $where[] = '(u.open_path = :userorgexact OR u.open_path LIKE :userorgprefix)';
        $sqlparams['userorgexact']  = '/' . $top;
        $sqlparams['userorgprefix'] = $DB->sql_like_escape('/' . $top . '/') . '%';
    }
}

if ($status === 'active') {
    $where[] = 'u.suspended = 0';
} else if ($status === 'suspended') {
    $where[] = 'u.suspended = 1';
}

if (!empty($search)) {
    $term = '%' . $DB->sql_like_escape($search) . '%';
    $where[] = '(' .
        $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
        $DB->sql_like('u.lastname',  ':s2', false) . ' OR ' .
        $DB->sql_like('u.email',     ':s3', false) . ' OR ' .
        $DB->sql_like("COALESCE(u.open_employeeid, '')", ':s4', false) .
    ')';
    $sqlparams['s1'] = $term;
    $sqlparams['s2'] = $term;
    $sqlparams['s3'] = $term;
    $sqlparams['s4'] = $term;
}

$wheresql = implode(' AND ', $where);

// Cap export at 10,000 rows to prevent memory blow-up.
// 10K covers Airpay's full 3,500-user platform 2.8x over.
$exportlimit = 10000;

$sql = "SELECT u.id, u.firstname, u.lastname, u.email,
               u.open_employeeid, u.open_path, u.open_designation,
               u.suspended, u.lastaccess, u.timecreated,
               o.name AS orgname
          FROM {user} u
     LEFT JOIN {local_sentientia_org} o ON o.path = u.open_path
         WHERE {$wheresql}
      ORDER BY {$orderby}";

$rows = $DB->get_records_sql($sql, $sqlparams, 0, $exportlimit);

// CSV output.
$filename = 'sentientia_users_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens with correct encoding.
fwrite($out, "\xEF\xBB\xBF");

// Header row.
fputcsv($out, [
    'Employee ID',
    'First Name',
    'Last Name',
    'Email',
    'Designation',
    'Organisation',
    'Status',
    'Last Access',
    'Account Created',
]);

foreach ($rows as $u) {
    fputcsv($out, [
        $u->open_employeeid ?? '',
        $u->firstname,
        $u->lastname,
        $u->email,
        $u->open_designation ?? '',
        $u->orgname ?? $u->open_path ?? '',
        $u->suspended ? 'Suspended' : 'Active',
        $u->lastaccess ? userdate($u->lastaccess, '%Y-%m-%d %H:%M') : 'Never',
        $u->timecreated ? userdate($u->timecreated, '%Y-%m-%d') : '',
    ]);
}

fclose($out);
exit;
