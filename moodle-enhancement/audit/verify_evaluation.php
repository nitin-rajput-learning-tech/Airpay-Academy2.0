<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;
$dbman = $DB->get_manager();

echo "=== Evaluation tables ===\n";
foreach (['local_airpay_evaluation', 'local_airpay_evaluation_questions', 'local_airpay_evaluation_responses'] as $t) {
    echo "  $t: " . ($dbman->table_exists($t) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\n=== Web services ===\n";
$funcs = $DB->get_records_select('external_functions',
    "name LIKE 'local_airpay_evaluation_%'", [], 'name ASC', 'name');
foreach ($funcs as $f) echo "  service: {$f->name}\n";

echo "\n=== CRUD test ===\n";
$id = \local_airpay_evaluation\evaluation_manager::create((object) [
    'name' => 'Claude Test Evaluation',
    'description' => 'Auto-test post-training feedback',
    'kirkpatrick_level' => 1,
    'trigger_event' => 'course_completion',
    'days_after' => 7,
    'anonymous' => 1,
    'costcenterid' => 0,
    'status' => 0,
]);
echo "  Created id=$id\n";

$r = $DB->get_record('local_airpay_evaluation', ['id' => $id]);
echo "  name={$r->name} kirkpatrick={$r->kirkpatrick_level} trigger={$r->trigger_event} days={$r->days_after} anonymous={$r->anonymous} status={$r->status}\n";

\local_airpay_evaluation\evaluation_manager::update($id, (object) [
    'name' => 'Claude Test Eval (renamed)',
    'kirkpatrick_level' => 3,
    'days_after' => 30,
]);
$r = $DB->get_record('local_airpay_evaluation', ['id' => $id]);
echo "  After update: name={$r->name} kirkpatrick={$r->kirkpatrick_level} days={$r->days_after}\n";

\local_airpay_evaluation\evaluation_manager::change_status($id, 1);
$r = $DB->get_record('local_airpay_evaluation', ['id' => $id]);
echo "  After publish: status={$r->status}\n";

\local_airpay_evaluation\evaluation_manager::delete($id);
$r = $DB->get_record('local_airpay_evaluation', ['id' => $id]);
echo "  After delete: " . ($r ? 'STILL EXISTS' : 'gone') . "\n";

echo "\n=== Validation ===\n";
try {
    \local_airpay_evaluation\evaluation_manager::create((object) [
        'name' => 'Bad', 'kirkpatrick_level' => 99,
    ]);
    echo "  Bad kirkpatrick: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  Bad kirkpatrick rejected: ok\n";
}

try {
    \local_airpay_evaluation\evaluation_manager::create((object) [
        'name' => 'Bad', 'trigger_event' => 'not_a_real_trigger',
    ]);
    echo "  Bad trigger: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  Bad trigger rejected: ok\n";
}
