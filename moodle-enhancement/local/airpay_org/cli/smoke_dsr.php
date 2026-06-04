<?php
// Cross-cutting GDPR DSR (Data Subject Request) smoke test.
//
// Walks Moodle's privacy framework for one user and checks that:
//  1. All airpay_* plugins are discoverable (have a privacy provider).
//  2. The metadata collection includes our airpay-owned tables.
//  3. get_contexts_for_userid runs without error for each plugin.
//
// Run: php public/local/airpay_org/cli/smoke_dsr.php [--userid=N]
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

// Use admin's userid if not provided — we just need a user with data.
$userid = (int) ($argv[1] ?? 0);
if (preg_match('/--userid=(\d+)/', (string) ($argv[1] ?? ''), $m)) {
    $userid = (int) $m[1];
}
if ($userid <= 0) {
    $userid = (int) get_admin()->id;
}

echo "DSR smoke test — userid=$userid\n";
echo str_repeat('─', 60) . "\n";

// All airpay plugins.
$airpay_plugins = [
    'airpay_users', 'airpay_org', 'airpay_courses', 'airpay_evaluation',
    'airpay_classroom', 'airpay_programs', 'airpay_learningpath',
    'sentientia_catalog', 'airpay_compliance_report', 'airpay_emails',
    'airpay_exams', 'sentientia_integrations', 'sentientia_lifecycle',
    'airpay_notifications', 'sentientia_analytics', 'sentientia_assistant',
    'airpay_roles', 'airpay_challenge', 'airpay_manager', 'airpay_skills',
];

$found_providers = 0;
$null_providers = 0;
$full_providers = 0;
$missing = [];
$total_tables_declared = 0;

foreach ($airpay_plugins as $plugin) {
    if (!is_dir(__DIR__ . '/../../../local/' . $plugin)) {
        continue;
    }
    $cls = '\\local_' . $plugin . '\\privacy\\provider';
    if (!class_exists($cls)) {
        $missing[] = $plugin;
        continue;
    }
    $found_providers++;

    if (is_subclass_of($cls,
            \core_privacy\local\metadata\null_provider::class)) {
        $null_providers++;
        echo "  ✓ {$plugin} (null_provider)\n";
        continue;
    }
    if (is_subclass_of($cls,
            \core_privacy\local\metadata\provider::class)) {
        $full_providers++;
        // Walk the metadata collection.
        try {
            $collection = new \core_privacy\local\metadata\collection($plugin);
            $cls::get_metadata($collection);
            $items = $collection->get_collection();
            $tables = 0;
            foreach ($items as $item) {
                if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                    $tables++;
                }
            }
            $total_tables_declared += $tables;
            echo "  ✓ {$plugin} (full_provider, {$tables} tables declared)\n";
        } catch (\Throwable $e) {
            fwrite(STDERR, "FAIL: {$plugin} get_metadata threw: "
                . $e->getMessage() . "\n");
            exit(2);
        }
        // Walk get_contexts_for_userid.
        if (is_subclass_of($cls,
                \core_privacy\local\request\plugin\provider::class)) {
            try {
                $contextlist = $cls::get_contexts_for_userid($userid);
                $count = count($contextlist->get_contextids());
                echo "      get_contexts_for_userid → $count context(s)\n";
            } catch (\Throwable $e) {
                fwrite(STDERR, "FAIL: {$plugin} get_contexts_for_userid threw: "
                    . $e->getMessage() . "\n");
                exit(3);
            }
        }
    }
}

echo str_repeat('─', 60) . "\n";
echo "Total plugins: " . count($airpay_plugins) . "\n";
echo "Providers found: $found_providers\n";
echo "  - null_provider: $null_providers\n";
echo "  - full_provider: $full_providers\n";
echo "Tables declared: $total_tables_declared\n";

if (!empty($missing)) {
    fwrite(STDERR, "Missing providers (" . count($missing) . "): "
        . implode(', ', $missing) . "\n");
    exit(1);
}

echo "\nALL OK ✓\n";
exit(0);
