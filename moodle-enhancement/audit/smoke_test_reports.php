<?php
// Smoke-test the 4 built-in report runners.
// Run from CLI: php smoke_test_reports.php

define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');

global $DB, $CFG;

echo "=== Airpay Reports Smoke Test ===\n\n";

$reports = $DB->get_records('local_airpay_reports', null, 'id ASC');
foreach ($reports as $r) {
    echo "--- Report #{$r->id}: {$r->name} ({$r->report_type}) ---\n";
    try {
        $start = microtime(true);
        $result = \local_airpay_reports\report_manager::run_report($r->id);
        $elapsed = round((microtime(true) - $start) * 1000);

        echo "  Columns: " . count($result['columns']) . "\n";
        echo "  Rows:    " . count($result['rows']) . "\n";
        echo "  Summary: ";
        foreach ($result['summary'] as $s) {
            echo "{$s['label']}={$s['value']}; ";
        }
        echo "\n";
        echo "  Time:    {$elapsed}ms\n";

        // First row preview
        if (!empty($result['rows'])) {
            $first = $result['rows'][0];
            echo "  Preview row: " . substr(json_encode($first), 0, 150) . "...\n";
        }

        // CSV size
        $csv = \local_airpay_reports\report_manager::rows_to_csv($result);
        echo "  CSV size: " . strlen($csv) . " bytes\n";

        echo "  STATUS: PASS\n";
    } catch (\Throwable $e) {
        echo "  STATUS: FAIL — " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "Done.\n";
