<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_roles:export', $context);
require_sesskey();

$scope = optional_param('scope', 'capabilities', PARAM_ALPHA);  // capabilities | audit

if ($scope === 'audit') {
    require_capability('local/airpay_roles:audit', $context);
}

// Stream as CSV.
$filename = 'airpay-roles-' . $scope . '-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens it correctly.
fwrite($out, "\xEF\xBB\xBF");

if ($scope === 'audit') {
    fputcsv($out, ['When', 'Who (user ID)', 'Role ID', 'Role shortname',
                   'Action', 'Capability', 'Old', 'New', 'Reason']);
    $entries = \local_airpay_roles\role_manager::list_audit(0, '', '', 0, 100000);
    foreach ($entries['rows'] as $r) {
        fputcsv($out, [
            $r['when'], $r['changedby'], $r['roleid'], $r['roleshortname'],
            $r['action'], $r['capability'], $r['oldlabel'], $r['newlabel'], $r['reason'],
        ]);
    }
} else {
    foreach (\local_airpay_roles\role_manager::csv_iterator() as $row) {
        fputcsv($out, $row);
    }
}

fclose($out);
exit;
