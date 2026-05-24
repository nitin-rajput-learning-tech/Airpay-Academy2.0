<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "=== Exam CRUD test ===\n";

// Web services
$funcs = $DB->get_records_select('external_functions',
    "name LIKE 'local_airpay_exams_%'", [], 'name ASC', 'name');
foreach ($funcs as $f) echo "  service: {$f->name}\n";

// Find an existing quiz to wrap.
$quiz = $DB->get_record_sql(
    "SELECT q.id, q.name FROM {quiz} q
       JOIN {course} c ON c.id = q.course
      WHERE c.id > 1 AND c.visible = 1
      LIMIT 1");

if (!$quiz) {
    echo "  No quizzes available to test wrapping\n";
    exit;
}
echo "  Test quiz: id={$quiz->id} name=" . substr($quiz->name, 0, 40) . "\n";

// Get available quizzes (should exclude already-registered)
$registered = \local_airpay_exams\exam_manager::get_registered_quiz_ids();
echo "  Already registered: " . count($registered) . " quizzes\n";

$options = \local_airpay_exams\exam_manager::get_quiz_options();
echo "  Available picker options: " . count($options) . " (excludes already-registered)\n";

// Create exam wrapper
$examid = \local_airpay_exams\exam_manager::create((object) [
    'name' => 'Claude Test Exam',
    'quizid' => $quiz->id,
    'duration' => 1800,
    'passinggrade' => 70.5,
    'costcenterid' => 0,
    'status' => 1,
]);
echo "  Created exam id=$examid\n";

$e = $DB->get_record('local_airpay_exams', ['id' => $examid]);
echo "  name={$e->name} quizid={$e->quizid} duration={$e->duration} passinggrade={$e->passinggrade} status={$e->status}\n";

// Test duplicate registration protection
try {
    \local_airpay_exams\exam_manager::create((object) [
        'name' => 'Duplicate', 'quizid' => $quiz->id,
    ]);
    echo "  Duplicate registration: NOT BLOCKED (BUG)\n";
} catch (\Throwable $ex) {
    echo "  Duplicate registration blocked: ok\n";
}

// Test invalid quiz
try {
    \local_airpay_exams\exam_manager::create((object) [
        'name' => 'Bad', 'quizid' => 999999,
    ]);
    echo "  Invalid quiz: NOT REJECTED (BUG)\n";
} catch (\Throwable $ex) {
    echo "  Invalid quiz rejected: ok\n";
}

// Update
\local_airpay_exams\exam_manager::update($examid, (object) [
    'name' => 'Claude Test Exam (renamed)',
    'duration' => 3600,
    'passinggrade' => 80,
]);
$e = $DB->get_record('local_airpay_exams', ['id' => $examid]);
echo "  After update: name={$e->name} duration={$e->duration} passinggrade={$e->passinggrade}\n";

// Toggle
\local_airpay_exams\exam_manager::toggle_status($examid, false);
$e = $DB->get_record('local_airpay_exams', ['id' => $examid]);
echo "  After deactivate: status={$e->status}\n";

// Delete (should NOT delete underlying quiz)
\local_airpay_exams\exam_manager::delete($examid);
$e = $DB->get_record('local_airpay_exams', ['id' => $examid]);
echo "  After delete (wrapper): " . ($e ? 'STILL EXISTS' : 'gone') . "\n";

$q = $DB->get_record('quiz', ['id' => $quiz->id]);
echo "  Underlying quiz preserved: " . ($q ? 'yes ✓' : 'GONE — BUG') . "\n";
