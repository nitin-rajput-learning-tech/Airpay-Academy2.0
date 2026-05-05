<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');

global $DB;

echo "=== Manager Drill-down Smoke Test ===\n\n";

// Pick a manager with reports.
$managerid = 1536; // Binay Upadhyay — 34 reports
echo "[1] Loading team for manager #{$managerid}...\n";
$start = microtime(true);
$team = \local_airpay_manager\team_manager::get_team($managerid);
echo "    -> " . count($team) . " direct reports loaded in " . round((microtime(true) - $start) * 1000) . " ms\n\n";

echo "[2] Building summary (batched aggregates)...\n";
$start = microtime(true);
$summary = \local_airpay_manager\team_manager::summarize_team($team);
$elapsed = round((microtime(true) - $start) * 1000);
echo "    -> " . count($summary) . " rows, {$elapsed} ms\n\n";

echo "[3] Sample first 3 rows:\n";
$count = 0;
foreach ($summary as $row) {
    if (++$count > 3) break;
    echo "    -> {$row['fullname']}: enrolled={$row['enrolled']}, completed={$row['completed']}, "
       . "rate={$row['rate']}%, overdue={$row['overdue']}, last={$row['lastlogin']}\n";
}

echo "\n[4] Drill-down on first team member:\n";
$first_member = array_values($team)[0] ?? null;
if ($first_member) {
    $start = microtime(true);
    $detail = \local_airpay_manager\team_manager::get_member_detail((int) $first_member->id);
    $elapsed = round((microtime(true) - $start) * 1000);
    echo "    -> {$detail['user']->firstname} {$detail['user']->lastname}\n";
    echo "    -> enrolments={$detail['enrolments_total']}, completed={$detail['completions_total']}, "
       . "in_progress={$detail['in_progress']}, not_started={$detail['not_started']}\n";
    echo "    -> certificates: " . count($detail['certificates']) . "\n";
    echo "    -> {$elapsed} ms\n";

    if (!empty($detail['courses'])) {
        echo "\n    First course row:\n";
        $c = $detail['courses'][0];
        echo "      title:    {$c['fullname']}\n";
        echo "      progress: {$c['progress_text']} ({$c['progress_pct']}%)\n";
        echo "      status:   {$c['status_label']}\n";
    }
}

echo "\n[5] can_view_member access check matrix:\n";
echo "    self → self: " . (\local_airpay_manager\team_manager::can_view_member($managerid, $managerid) ? 'YES' : 'no') . "\n";
echo "    manager → direct report: " . (\local_airpay_manager\team_manager::can_view_member($managerid, $first_member->id) ? 'YES' : 'no') . "\n";
echo "    other manager → unrelated user: " . (\local_airpay_manager\team_manager::can_view_member(499, $first_member->id) ? 'YES' : 'NO (correct)') . "\n";

echo "\nDone.\n";
