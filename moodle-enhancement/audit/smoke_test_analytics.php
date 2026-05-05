<?php
// Verify Analytics tenant scope filter actually changes results.
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');

use \local_airpay_analytics\analytics_manager as A;

echo "=== Analytics Tenant Filter Smoke Test ===\n\n";

$cases = [
    ''     => 'No filter (all tenants)',
    '/1'   => 'Airpay only',
    '/77'  => 'Public only',
    '/177' => 'ZEEA only',
];

foreach ($cases as $path => $label) {
    echo "─── {$label} (path='{$path}') ───\n";
    $kpis = A::get_kpis('30d', $path);
    foreach ($kpis as $k) {
        echo "    {$k['label']}: {$k['value']}\n";
    }
    echo "\n";
}

echo "Done. If KPIs differ across paths, the filter is working.\n";
