<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "=== Question CRUD test ===\n";

// Web services
$funcs = $DB->get_records_select('external_functions',
    "name LIKE 'local_airpay_evaluation_%question%' OR name LIKE 'local_airpay_evaluation_reorder%'",
    [], 'name ASC', 'name');
foreach ($funcs as $f) echo "  service: {$f->name}\n";

// Create a parent evaluation
$evalid = \local_airpay_evaluation\evaluation_manager::create((object) [
    'name' => 'Question Test Form',
    'description' => '',
    'kirkpatrick_level' => 1,
    'trigger_event' => 'manual',
    'days_after' => 0,
]);
echo "  Created evaluation id=$evalid\n";

// Create different question types
$q1 = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'rating',
    'questiontext' => 'The training met my expectations.',
    'required' => 1,
]);
echo "  Created rating question id=$q1\n";

$q2 = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'multichoice',
    'questiontext' => 'How did you hear about this training?',
    'options' => "Manager\nEmail invite\nSelf-enrolled\nOther",
    'required' => 1,
]);
echo "  Created multichoice question id=$q2\n";

// Verify multichoice options encoded as JSON
$qrec = $DB->get_record('local_airpay_evaluation_questions', ['id' => $q2]);
$opts = \local_airpay_evaluation\evaluation_manager::decode_options($qrec->options);
echo "  Multichoice options: " . implode(' | ', $opts) . " (count=" . count($opts) . ")\n";

$q3 = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'text',
    'questiontext' => 'What could we improve?',
    'required' => 0,
]);
echo "  Created text question id=$q3\n";

// Verify auto-incrementing sortorder
$all = \local_airpay_evaluation\evaluation_manager::get_questions($evalid);
$ids_in_order = array_keys($all);
echo "  Initial order: " . implode(',', $ids_in_order) . "\n";

// Reorder: q3, q1, q2
\local_airpay_evaluation\evaluation_manager::reorder_questions($evalid, [$q3, $q1, $q2]);
$reordered = \local_airpay_evaluation\evaluation_manager::get_questions($evalid);
$reordered_ids = array_keys($reordered);
echo "  After reorder: " . implode(',', $reordered_ids) . "\n";
echo "  Reorder " . ($reordered_ids === [$q3, $q1, $q2] ? 'WORKS ok' : 'FAILED') . "\n";

// Update question
\local_airpay_evaluation\evaluation_manager::update_question($q1, (object) [
    'questiontext' => 'The training was relevant to my role.',
    'required' => 0,
]);
$qrec = $DB->get_record('local_airpay_evaluation_questions', ['id' => $q1]);
echo "  After update q1: text='{$qrec->questiontext}' required={$qrec->required}\n";

// Validation tests
echo "\n=== Validation ===\n";
try {
    \local_airpay_evaluation\evaluation_manager::create_question((object) [
        'evaluationid' => $evalid, 'questiontype' => 'multichoice',
        'questiontext' => 'Bad', 'options' => 'OnlyOne',
    ]);
    echo "  Multichoice w/ 1 option: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  Multichoice with <2 options rejected: ok\n";
}

try {
    \local_airpay_evaluation\evaluation_manager::create_question((object) [
        'evaluationid' => $evalid, 'questiontype' => 'invalid_type',
        'questiontext' => 'Bad',
    ]);
    echo "  Invalid type: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  Invalid question type rejected: ok\n";
}

// Delete one question
\local_airpay_evaluation\evaluation_manager::delete_question($q1);
$remaining = \local_airpay_evaluation\evaluation_manager::get_questions($evalid);
echo "\n  After delete q1: remaining=" . count($remaining) . "\n";

// Cleanup — delete the parent evaluation (cascades)
\local_airpay_evaluation\evaluation_manager::delete($evalid);
$remaining_questions = $DB->count_records('local_airpay_evaluation_questions', ['evaluationid' => $evalid]);
echo "  After parent delete: questions remaining for this eval=" . $remaining_questions . " (cascade " . ($remaining_questions === 0 ? 'WORKS ok' : 'FAILED') . ")\n";
