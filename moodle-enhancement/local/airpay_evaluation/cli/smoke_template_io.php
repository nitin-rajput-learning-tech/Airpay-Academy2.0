<?php
// Smoke test: evaluation template export → import round-trip.
//
// Run: php public/local/airpay_evaluation/cli/smoke_template_io.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_airpay_evaluation\evaluation_manager;

// Seed evaluation + 3 questions.
$evalid = evaluation_manager::create((object) [
    'name' => 'Smoke template eval',
    'description' => 'Round-trip test',
    'kirkpatrick_level' => 2,
    'trigger_event' => 'course_completion',
    'days_after' => 7,
    'anonymous' => 1,
    'costcenterid' => 0,
    'status' => evaluation_manager::STATUS_DRAFT,
]);

evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'rating',
    'questiontext' => 'How would you rate the overall course?',
    'options' => null,
    'required' => 1,
    'sortorder' => 1,
]);
evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'multichoice',
    'questiontext' => 'Which topic was most useful?',
    'options' => "Compliance basics\nAML detection\nCustomer onboarding",
    'required' => 0,
    'sortorder' => 2,
]);
evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'text',
    'questiontext' => 'What would you change about this training?',
    'options' => null,
    'required' => 0,
    'sortorder' => 3,
]);

echo "Seeded eval id=$evalid with 3 questions\n";

// 1. Export.
$payload = evaluation_manager::export_template($evalid);
if ((int) $payload['format'] !== 1) {
    fwrite(STDERR, "FAIL: format mismatch.\n"); exit(1);
}
if ($payload['evaluation']['name'] !== 'Smoke template eval') {
    fwrite(STDERR, "FAIL: exported name mismatch.\n"); exit(2);
}
if (count($payload['questions']) !== 3) {
    fwrite(STDERR, "FAIL: expected 3 questions in export, got "
        . count($payload['questions']) . "\n");
    exit(3);
}
if (!is_array($payload['questions'][1]['options'])
    || count($payload['questions'][1]['options']) !== 3) {
    fwrite(STDERR, "FAIL: multichoice options not exported as array.\n");
    exit(4);
}
echo "Exported: " . count($payload['questions']) . " questions, "
    . count($payload['questions'][1]['options']) . " options on q2 ✓\n";

// Round-trip via JSON to simulate the file.
$json = json_encode($payload);
$decoded = json_decode($json, true);

// 2. Import.
$result = evaluation_manager::import_template($decoded, 0,
    evaluation_manager::STATUS_DRAFT);
if ((int) $result['id'] === (int) $evalid) {
    fwrite(STDERR, "FAIL: import re-used same ID.\n"); exit(5);
}
if ((int) $result['question_count'] !== 3) {
    fwrite(STDERR, "FAIL: imported question_count="
        . $result['question_count'] . " (expected 3).\n");
    exit(6);
}
echo "Imported into id={$result['id']} — name={$result['name']} "
    . "q={$result['question_count']} ✓\n";

// 3. Verify the imported evaluation matches.
$new_eval = evaluation_manager::get((int) $result['id']);
if ((int) $new_eval->kirkpatrick_level !== 2) {
    fwrite(STDERR, "FAIL: kirkpatrick_level not preserved.\n"); exit(7);
}
if ($new_eval->trigger_event !== 'course_completion') {
    fwrite(STDERR, "FAIL: trigger_event not preserved.\n"); exit(8);
}
if ((int) $new_eval->anonymous !== 1) {
    fwrite(STDERR, "FAIL: anonymous flag not preserved.\n"); exit(9);
}
if ((int) $new_eval->status !== evaluation_manager::STATUS_DRAFT) {
    fwrite(STDERR, "FAIL: imported should be DRAFT, got status="
        . $new_eval->status . "\n");
    exit(10);
}
echo "Imported state matches ✓ (Kirkpatrick=2, trigger=course_completion, "
   . "anonymous=1, status=DRAFT)\n";

// 4. Bad format → reject.
try {
    evaluation_manager::import_template(['format' => 99,
        'evaluation' => ['name' => 'x'], 'questions' => []]);
    fwrite(STDERR, "FAIL: future-format payload should reject.\n"); exit(11);
} catch (\Throwable $e) {
    echo "Future-format rejection ✓ ({$e->getMessage()})\n";
}

// Cleanup.
evaluation_manager::delete($evalid);
evaluation_manager::delete((int) $result['id']);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
