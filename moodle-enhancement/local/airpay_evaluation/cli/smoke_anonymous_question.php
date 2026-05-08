<?php
// Smoke test: per-question anonymous toggle round-trip + CSV row hiding.
//
// Run: php public/local/airpay_evaluation/cli/smoke_anonymous_question.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_airpay_evaluation\evaluation_manager;

// Seed evaluation with 2 questions: one named, one anonymous.
$evalid = evaluation_manager::create((object) [
    'name' => 'Smoke anonymous-q test',
    'description' => '',
    'kirkpatrick_level' => 1,
    'trigger_event' => 'manual',
    'days_after' => 0,
    'anonymous' => 0, // eval is NOT anonymous overall
    'costcenterid' => 0,
    'status' => evaluation_manager::STATUS_DRAFT,
]);

$q1 = evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'rating',
    'questiontext' => 'Was the training useful?',
    'options' => '',
    'required' => 1,
    'anonymous' => 0,
    'sortorder' => 1,
]);
$q2 = evaluation_manager::create_question((object) [
    'evaluationid' => $evalid,
    'questiontype' => 'text',
    'questiontext' => 'Any concerns about your manager?',
    'options' => '',
    'required' => 0,
    'anonymous' => 1,  // ← per-question anonymous
    'sortorder' => 2,
]);

echo "Seeded eval=$evalid, q1=$q1 (named), q2=$q2 (anonymous)\n";

// Verify the flag persisted.
$row = $DB->get_record('local_airpay_evaluation_questions', ['id' => $q2]);
if ((int) $row->anonymous !== 1) {
    fwrite(STDERR, "FAIL: anonymous flag not stored: "
        . var_export($row->anonymous, true) . "\n");
    exit(1);
}
echo "Per-question anonymous flag stored ✓\n";

// Verify update_question can clear it.
evaluation_manager::update_question($q2, (object) ['anonymous' => 0]);
$row = $DB->get_record('local_airpay_evaluation_questions', ['id' => $q2]);
if ((int) $row->anonymous !== 0) {
    fwrite(STDERR, "FAIL: anonymous flag not cleared.\n"); exit(2);
}
echo "Update clears anonymous flag ✓\n";

// Set it back for the CSV test.
evaluation_manager::update_question($q2, (object) ['anonymous' => 1]);

// Insert a fake response from a real user.
$user = $DB->get_record_sql(
    "SELECT id FROM {user} WHERE deleted=0 AND id > 2 LIMIT 1");
$resp_id = $DB->insert_record('local_airpay_evaluation_responses', (object) [
    'evaluationid'  => $evalid,
    'userid'        => $user->id,
    'response_data' => json_encode([
        $q1 => 5,
        $q2 => 'My manager is great',
    ]),
    'timesubmitted' => time(),
]);

$response = $DB->get_record('local_airpay_evaluation_responses',
    ['id' => $resp_id]);
$eval = evaluation_manager::get($evalid);
$questions = evaluation_manager::get_questions($evalid);

$csv_row = evaluation_manager::response_to_csv_row($response, $questions, $eval);
// Row layout: [Submitted, Respondent, Email, courseid, programid, classroomid, Q1, Q2]
if ($csv_row[1] !== '(question-anonymous)') {
    fwrite(STDERR, "FAIL: row should hide responder when an anon-q exists, got '"
        . $csv_row[1] . "'\n");
    exit(3);
}
if ($csv_row[2] !== '') {
    fwrite(STDERR, "FAIL: email should be blank, got '"
        . $csv_row[2] . "'\n");
    exit(4);
}
if ((string) $csv_row[6] !== '5') {
    fwrite(STDERR, "FAIL: Q1 answer (rating 5) should be in row, got '"
        . $csv_row[6] . "'\n");
    exit(5);
}
echo "CSV row hides identity but keeps answers ✓\n";

// Now flip Q2 to NOT anonymous → identity should be revealed.
evaluation_manager::update_question($q2, (object) ['anonymous' => 0]);
$questions = evaluation_manager::get_questions($evalid);
$csv_row = evaluation_manager::response_to_csv_row($response, $questions, $eval);
if (strpos($csv_row[1], '(question-anonymous)') !== false) {
    fwrite(STDERR, "FAIL: row should show identity when no anon-q, got '"
        . $csv_row[1] . "'\n");
    exit(6);
}
echo "Identity revealed when no anonymous question ✓\n";

// Cleanup.
$DB->delete_records('local_airpay_evaluation_responses',
    ['evaluationid' => $evalid]);
evaluation_manager::delete($evalid);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
