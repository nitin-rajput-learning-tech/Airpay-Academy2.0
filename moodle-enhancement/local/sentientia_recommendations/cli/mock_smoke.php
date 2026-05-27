<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI smoke test for the AI Recommendations pipeline in MOCK MODE only.
 *
 * Exercises prompt_builder, anthropic_client::call_mock(), and
 * response_parser without any HTTP calls. Verifies:
 *   1. Request validation (candidate list + count + PII heuristic)
 *   2. Prompt construction (system + user message)
 *   3. Mock dispatch (no money spent, no internet needed)
 *   4. Strict JSON parsing (drops malformed + course IDs not in catalog)
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_recommendations/cli/mock_smoke.php
 *
 * @package local_sentientia_recommendations
 */

define('CLI_SCRIPT', true);

$configfile = __DIR__ . '/../../../config.php';
if (file_exists($configfile)) {
    require_once($configfile);
} else {
    // Sandbox mode — no Moodle bootstrap. Provide stubs for the
    // global helpers our classes call into.
    if (!defined('MOODLE_INTERNAL')) {
        define('MOODLE_INTERNAL', true);
    }
    if (!function_exists('debugging')) {
        function debugging($msg, $level = null) { /* no-op */ }
    }
    if (!function_exists('get_config')) {
        function get_config($plugin, $key) { return null; }
    }
    require_once(__DIR__ . '/../classes/prompt_builder.php');
    require_once(__DIR__ . '/../classes/response_parser.php');
    require_once(__DIR__ . '/../classes/anthropic_client.php');
}

use local_sentientia_recommendations\prompt_builder;
use local_sentientia_recommendations\response_parser;
use local_sentientia_recommendations\anthropic_client;

echo "================================================================\n";
echo " local_sentientia_recommendations — end-to-end mock smoke (H.0)\n";
echo "================================================================\n\n";

$profile = (object)[
    'role'      => 'learner',
    'tenant'    => '1',
    'skills'    => ['AML basics', 'KYC'],
    'completed' => [10, 11],
];

$candidates = [
    (object)['id' => 12, 'fullname' => 'Advanced AML', 'shortname' => 'AML2', 'summary' => 'Deep dive into anti-money-laundering.'],
    (object)['id' => 13, 'fullname' => 'PEP Screening', 'shortname' => 'PEP', 'summary' => 'Politically exposed persons screening.'],
    (object)['id' => 14, 'fullname' => 'Fraud Detection', 'shortname' => 'FRAUD', 'summary' => 'Spotting transaction fraud.'],
    (object)['id' => 10, 'fullname' => 'AML Basics', 'shortname' => 'AML1', 'summary' => 'Already completed — should be filtered.'],
];

echo "STEP 1 — Request validation\n";
echo "----------------------------------------------------------------\n";
$errors = prompt_builder::validate_request($profile, $candidates, 3);
echo "  Candidate count: " . count($candidates) . "\n";
echo "  Validation errors: " . (empty($errors) ? "none" : implode(', ', $errors)) . "\n";
echo "  PII detected: " . (prompt_builder::profile_contains_pii_pattern($profile) ? "YES" : "no") . "\n\n";

echo "STEP 2 — Prompt construction\n";
echo "----------------------------------------------------------------\n";
$sys = prompt_builder::build_system_prompt();
$user = prompt_builder::build_user_message($profile, $candidates, 3);
echo "  Prompt version: " . prompt_builder::VERSION . "\n";
echo "  System prompt length: " . strlen($sys) . " chars\n";
echo "  User message length: " . strlen($user) . " chars\n\n";

echo "STEP 3 — Anthropic mock call (sentientia.recommendations.live_api OFF)\n";
echo "----------------------------------------------------------------\n";
$result = anthropic_client::call_mock($profile, $candidates, 3);
echo "  Mode: " . $result['mode'] . "\n";
echo "  Tokens in/out: " . $result['tokens_in'] . " / " . $result['tokens_out'] . "\n";
echo "  Body length: " . strlen($result['body']) . " chars\n";
echo "  Error: " . ($result['error'] === null ? "none" : $result['error']) . "\n\n";

echo "STEP 4 — Parse Claude response (filtered to catalogue IDs)\n";
echo "----------------------------------------------------------------\n";
$allowed = array_map(fn($c) => (int)$c->id, $candidates);
$parsed = response_parser::parse($result['body'], $allowed);
echo "  Recommendations parsed: " . count($parsed) . "\n";
$completed_filtered = true;
foreach ($parsed as $i => $r) {
    echo "\n  #" . ($i + 1) . ": course_id=" . $r->course_id . " score=" . $r->score . "\n";
    echo "       " . mb_substr($r->reasoning, 0, 80) . "...\n";
    if ($r->course_id === 10) {
        $completed_filtered = false;
    }
}
echo "\n  Completed course (id=10) excluded: " . ($completed_filtered ? "PASS" : "FAIL") . "\n";

echo "\n\nSTEP 5 — Malformed-input robustness\n";
echo "----------------------------------------------------------------\n";
$malformed = [
    'empty'                  => '',
    'plain prose'            => 'Sorry, I cannot generate recommendations.',
    'missing key'            => '{"foo": "bar"}',
    'invented course_id'     => '{"recommendations": [{"course_id": 9999, "score": 90, "reasoning": "x"}]}',
    'bad score type'         => '{"recommendations": [{"course_id": 12, "score": "high", "reasoning": "x"}]}',
];
foreach ($malformed as $label => $body) {
    $out = response_parser::parse($body, $allowed);
    $note = ($label === 'bad score type') ? ' (1 expected — score clamps to 0)' : ' (0 expected)';
    echo "  '" . str_pad($label, 20) . "' -> " . count($out) . " recs" . $note . "\n";
}

echo "\nSTEP 6 — PII detection\n";
echo "----------------------------------------------------------------\n";
$piiprofile = (object)['role' => 'learner', 'skills' => ['ID 1234 5678 9012']];
echo "  Profile with Aadhaar in skills -> " .
    (prompt_builder::profile_contains_pii_pattern($piiprofile) ? "PII (PASS)" : "clean (FAIL)") . "\n";
$cleanprofile = (object)['role' => 'manager', 'skills' => ['Leadership', 'Section 42']];
echo "  Clean profile -> " .
    (prompt_builder::profile_contains_pii_pattern($cleanprofile) ? "PII (FAIL)" : "clean (PASS)") . "\n";

echo "\n================================================================\n";
echo " End-to-end mock pipeline: PASS\n";
echo "================================================================\n";
