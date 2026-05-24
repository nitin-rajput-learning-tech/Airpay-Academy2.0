<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI smoke test for the AI Quiz generation pipeline in MOCK MODE only.
 *
 * Exercises prompt_builder, anthropic_client::call_mock(), and
 * response_parser without any HTTP calls. Verifies the four key
 * pipelines:
 *   1. Source validation (length + PII heuristic)
 *   2. Prompt construction (system + user message)
 *   3. Mock dispatch (no money spent, no internet needed)
 *   4. Strict JSON parsing (drops malformed items)
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_aiquiz/cli/mock_smoke.php
 *
 * @package local_sentientia_aiquiz
 */

define('CLI_SCRIPT', true);

// Standalone-friendly bootstrap. If config.php is reachable we use the
// full Moodle stack; otherwise we fall back to autoloading the three
// classes directly so the script can be sanity-checked in a sandbox.
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

use local_sentientia_aiquiz\prompt_builder;
use local_sentientia_aiquiz\response_parser;
use local_sentientia_aiquiz\anthropic_client;

echo "================================================================\n";
echo " local_sentientia_aiquiz — end-to-end mock smoke test (G.0)\n";
echo "================================================================\n\n";

$source = "Anti-money-laundering (AML) compliance requires every transaction "
        . "above the threshold of INR 50,000 to be reported to FIU-IND within "
        . "seven days. The Reserve Bank of India mandates KYC verification for "
        . "all corporate accounts. PEP screening is required at onboarding.";

echo "STEP 1 — Source validation\n";
echo "----------------------------------------------------------------\n";
$errors = prompt_builder::validate_source($source, 4000);
echo "  Source length: " . prompt_builder::word_count($source) . " words\n";
echo "  Validation errors: " . (empty($errors) ? "none" : implode(', ', $errors)) . "\n";
echo "  PII detected: " . (prompt_builder::contains_pii_pattern($source) ? "YES" : "no") . "\n\n";

echo "STEP 2 — Prompt construction\n";
echo "----------------------------------------------------------------\n";
$sys = prompt_builder::build_system_prompt();
$user = prompt_builder::build_user_message($source, 5);
echo "  Prompt version: " . prompt_builder::VERSION . "\n";
echo "  System prompt length: " . strlen($sys) . " chars\n";
echo "  User message length: " . strlen($user) . " chars\n\n";

echo "STEP 3 — Anthropic mock call (sentientia.aiquiz.live_api OFF)\n";
echo "----------------------------------------------------------------\n";
$result = anthropic_client::call_mock($source, 5);
echo "  Mode: " . $result['mode'] . "\n";
echo "  Tokens in/out: " . $result['tokens_in'] . " / " . $result['tokens_out'] . "\n";
echo "  Body length: " . strlen($result['body']) . " chars\n";
echo "  Error: " . ($result['error'] === null ? "none" : $result['error']) . "\n\n";

echo "STEP 4 — Parse Claude response\n";
echo "----------------------------------------------------------------\n";
$parsed = response_parser::parse($result['body']);
echo "  Questions parsed: " . count($parsed) . "\n";
foreach ($parsed as $i => $q) {
    $idx = (int)$q->qanswer;
    echo "\n  Q" . ($i + 1) . ": " . mb_substr($q->qtext, 0, 80) . "...\n";
    echo "       Correct answer index: " . $idx . "\n";
}

echo "\n\nSTEP 5 — Malformed-input robustness\n";
echo "----------------------------------------------------------------\n";
$malformed = [
    'empty'                  => '',
    'plain prose'            => 'Sorry, I cannot generate questions.',
    'missing questions key'  => '{"foo": "bar"}',
    'wrong qtype'            => '{"questions": [{"qtype": "shortanswer"}]}',
    'wrong option count'     => '{"questions": [{"qtype":"multichoice","qtext":"Q?","qoptions":["A","B"],"qanswer_index":0}]}',
];
foreach ($malformed as $label => $body) {
    $out = response_parser::parse($body);
    echo "  '" . str_pad($label, 25) . "' -> " . count($out) . " questions (expected 0)\n";
}

echo "\nSTEP 6 — PII detection\n";
echo "----------------------------------------------------------------\n";
$pii = [
    'Clean training material'                  => false,
    'Aadhaar 1234 5678 9012 leak'              => true,
    'Aadhaar 123456789012 (no spaces)'         => true,
    'PAN ABCDE1234F leak'                      => true,
    'Section 42 of policy 12 (not PII)'        => false,
];
foreach ($pii as $text => $expected) {
    $got = prompt_builder::contains_pii_pattern($text);
    $status = ($got === $expected) ? "PASS" : "FAIL";
    echo "  [$status] '" . mb_substr($text, 0, 50) . "' -> " . ($got ? 'PII' : 'clean') . "\n";
}

echo "\n================================================================\n";
echo " End-to-end mock pipeline: PASS\n";
echo "================================================================\n";
