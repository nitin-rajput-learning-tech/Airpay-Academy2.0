<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "=== Response submission + aggregation test ===\n";

// Web service registered
$f = $DB->get_record('external_functions', ['name' => 'local_airpay_evaluation_submit_response']);
echo "  service registered: " . ($f ? 'yes' : 'NO') . "\n";

// Create eval + 5 questions of different types
$evalid = \local_airpay_evaluation\evaluation_manager::create((object) [
    'name' => 'End-to-End Test Eval',
    'description' => '',
    'kirkpatrick_level' => 1,
    'trigger_event' => 'manual',
    'status' => \local_airpay_evaluation\evaluation_manager::STATUS_ACTIVE,
]);
echo "  Created active eval id=$evalid\n";

$q_rating = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid, 'questiontype' => 'rating',
    'questiontext' => 'How was the training?',
]);
$q_nps = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid, 'questiontype' => 'nps',
    'questiontext' => 'How likely to recommend?',
]);
$q_yesno = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid, 'questiontype' => 'yesno',
    'questiontext' => 'Would you take it again?',
]);
$q_mc = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid, 'questiontype' => 'multichoice',
    'questiontext' => 'Best part?', 'options' => "Content\nTrainer\nMaterials\nTiming",
]);
$q_text = \local_airpay_evaluation\evaluation_manager::create_question((object) [
    'evaluationid' => $evalid, 'questiontype' => 'text',
    'questiontext' => 'Suggestions?', 'required' => 0,
]);
echo "  Created 5 questions: rating=$q_rating nps=$q_nps yesno=$q_yesno mc=$q_mc text=$q_text\n";

// Submit 4 simulated responses with different patterns
$test_responses = [
    [101, [$q_rating => 5, $q_nps => 9, $q_yesno => 'yes', $q_mc => 'Trainer', $q_text => 'Great!']],
    [102, [$q_rating => 4, $q_nps => 8, $q_yesno => 'yes', $q_mc => 'Content', $q_text => 'Good pace.']],
    [103, [$q_rating => 3, $q_nps => 6, $q_yesno => 'no',  $q_mc => 'Timing',  $q_text => 'Too fast']],
    [104, [$q_rating => 5, $q_nps => 10, $q_yesno => 'yes', $q_mc => 'Trainer', $q_text => '']],
];

foreach ($test_responses as [$uid, $answers]) {
    $rid = \local_airpay_evaluation\evaluation_manager::submit_response($evalid, $uid, $answers);
    echo "  Submitted response id=$rid by user=$uid\n";
}

// Test duplicate submission protection
try {
    \local_airpay_evaluation\evaluation_manager::submit_response($evalid, 101, [
        $q_rating => 5, $q_nps => 9, $q_yesno => 'yes', $q_mc => 'Trainer',
    ]);
    echo "  Duplicate from same user: NOT BLOCKED (BUG)\n";
} catch (\Throwable $e) {
    echo "  Duplicate submission blocked: ok\n";
}

// Test required-field validation
try {
    \local_airpay_evaluation\evaluation_manager::submit_response($evalid, 999, [
        $q_rating => 5, // missing the others
    ]);
    echo "  Missing required: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  Missing required answer rejected: ok\n";
}

// Test invalid rating
try {
    \local_airpay_evaluation\evaluation_manager::submit_response($evalid, 998, [
        $q_rating => 99, $q_nps => 5, $q_yesno => 'yes', $q_mc => 'Content',
    ]);
    echo "  Invalid rating: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  Invalid rating rejected: ok\n";
}

// Now verify aggregate stats
echo "\n=== Aggregate stats ===\n";
$stats = \local_airpay_evaluation\evaluation_manager::get_response_stats($evalid);

echo "  Rating: count={$stats[$q_rating]['count']} avg={$stats[$q_rating]['avg']}\n";
echo "    Distribution: ";
foreach ($stats[$q_rating]['distribution'] as $k => $v) echo "$k=$v ";
echo "\n";

echo "  NPS: count={$stats[$q_nps]['count']} avg={$stats[$q_nps]['avg']} score={$stats[$q_nps]['nps_score']}\n";
echo "    Detractors={$stats[$q_nps]['detractors']} Passives={$stats[$q_nps]['passives']} Promoters={$stats[$q_nps]['promoters']}\n";

echo "  Yes/No: yes={$stats[$q_yesno]['yes']} no={$stats[$q_yesno]['no']} yes_pct={$stats[$q_yesno]['yes_pct']}%\n";

echo "  Multichoice distribution: ";
foreach ($stats[$q_mc]['distribution'] as $opt => $count) echo "$opt=$count ";
echo "\n";

echo "  Text samples: count=" . count($stats[$q_text]['samples']) . "\n";

// Cleanup
\local_airpay_evaluation\evaluation_manager::delete($evalid);
echo "\n  Cleanup: parent eval deleted, all data cascades\n";
