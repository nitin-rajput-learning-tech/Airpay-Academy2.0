<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
// Bug fix 2026-05-22 (Goal A audit) — supervisor-or-capability check.
\local_sentientia_manager\team_manager::require_manage();
require_sesskey();

$filename = 'airpay-manager-decisions-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens it correctly.
fwrite($out, "\xEF\xBB\xBF");

foreach (\local_sentientia_manager\approval_manager::csv_iterator_decisions((int) $USER->id) as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
