<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sample CSV downloads for bulk-import + bulk-status-change.
 *
 * Closes Phase 5 A.6 — gives admins a known-good template to start from.
 *
 * @package local_airpay_users
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$type = required_param('type', PARAM_ALPHA);  // import|status|enrol

$ctx = context_system::instance();
require_capability('local/airpay_users:view', $ctx);

$filename = 'airpay_sample_' . $type . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");  // UTF-8 BOM for Excel

switch ($type) {
    case 'import':
        // Sample for bulk_import.php — create new users.
        fputcsv($out, ['firstname', 'lastname', 'email', 'open_employeeid',
                       'open_designation', 'open_department']);
        fputcsv($out, ['Asha', 'Kumar', 'asha.kumar@airpay.co.in', 'EMP-1001',
                       'Manager', 'Operations']);
        fputcsv($out, ['Rohan', 'Sharma', 'rohan.sharma@airpay.co.in', 'EMP-1002',
                       'Senior Executive', 'Sales']);
        fputcsv($out, ['Priya', 'Singh', 'priya.singh@airpay.co.in', 'EMP-1003',
                       'Assistant Manager', 'Customer Success']);
        break;

    case 'status':
        // Sample for bulk_csv.php — change status (suspend/activate).
        fputcsv($out, ['email', 'action']);
        fputcsv($out, ['user1@airpay.co.in', 'suspend']);
        fputcsv($out, ['user2@airpay.co.in', 'activate']);
        fputcsv($out, ['user3@airpay.co.in', 'suspend']);
        break;

    case 'enrol':
        // Sample for enrol_csv.php (delegates to airpay_courses but kept here for convenience).
        fputcsv($out, ['email', 'courseshortname', 'role']);
        fputcsv($out, ['asha.kumar@airpay.co.in', 'POSH-2026', 'student']);
        fputcsv($out, ['rohan.sharma@airpay.co.in', 'AML-2026', 'student']);
        break;

    default:
        fclose($out);
        throw new \moodle_exception('invalidparameter');
}

fclose($out);
exit;
