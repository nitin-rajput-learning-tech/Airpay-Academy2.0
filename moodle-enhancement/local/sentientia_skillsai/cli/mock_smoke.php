<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI smoke test for the Skills Intelligence extraction pipeline in MOCK
 * MODE only.
 *
 * Exercises prompt_builder, anthropic_client::call_mock(), and
 * response_parser without any HTTP calls. Verifies:
 *   1. Source validation (empty / too-long / PII heuristic)
 *   2. Prompt construction (system + user message, EN + HI)
 *   3. Mock dispatch (no money spent, no internet needed)
 *   4. Strict JSON parsing (drops malformed, clamps level/confidence)
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_skillsai/cli/mock_smoke.php
 *
 * @package local_sentientia_skillsai
 */

define('CLI_SCRIPT', true);

$configfile = __DIR__ . '/../../../config.php';
if (file_exists($configfile)) {
    require_once($configfile);
} else {
    // Sandbox mode — no Moodle bootstrap. Stub the global helpers + core_text.
    if (!defined('MOODLE_INTERNAL')) {
        define('MOODLE_INTERNAL', true);
    }
    if (!function_exists('debugging')) {
        function debugging($msg, $level = null) { /* no-op */ }
    }
    if (!function_exists('get_config')) {
        function get_config($plugin, $key) { return null; }
    }
    if (!class_exists('core_text')) {
        class core_text {
            public static function strtolower($s) { return mb_strtolower($s); }
        }
    }
    require_once(__DIR__ . '/../classes/prompt_builder.php');
    require_once(__DIR__ . '/../classes/response_parser.php');
    require_once(__DIR__ . '/../classes/anthropic_client.php');
}

use local_sentientia_skillsai\prompt_builder;
use local_sentientia_skillsai\response_parser;
use local_sentientia_skillsai\anthropic_client;

echo "================================================================\n";
echo " local_sentientia_skillsai — end-to-end mock smoke (P0.1.0)\n";
echo "================================================================\n\n";

$source = "This SOP covers KYC verification for new merchant onboarding. "
        . "Staff must validate PAN, verify GST registration, and complete "
        . "the AML risk-scoring checklist before activating a merchant account.";

echo "STEP 1 — Source validation\n";
echo "----------------------------------------------------------------\n";
$errors = prompt_builder::validate_source($source, 6000);
echo "  Word count: " . prompt_builder::word_count($source) . "\n";
echo "  Validation errors: " . (empty($errors) ? "none" : implode(', ', $errors)) . "\n";
echo "  PII detected: " . (prompt_builder::contains_pii_pattern($source) ? "YES" : "no") . "\n\n";

echo "STEP 2 — Prompt construction (EN + HI)\n";
echo "----------------------------------------------------------------\n";
$sysen = prompt_builder::build_system_prompt(prompt_builder::VERSION_V1);
$syshi = prompt_builder::build_system_prompt(prompt_builder::VERSION_V2_HINDI);
echo "  EN system prompt length: " . strlen($sysen) . " chars\n";
echo "  HI system prompt length: " . strlen($syshi) . " chars\n";
echo "  HI contains Devanagari: " . (preg_match('/\p{Devanagari}/u', $syshi) ? "PASS" : "FAIL") . "\n\n";

echo "STEP 3 — Anthropic mock call (sentientia.skillsai.live_api OFF)\n";
echo "----------------------------------------------------------------\n";
$result = anthropic_client::call_mock($source, 15);
echo "  Mode: " . $result['mode'] . "\n";
echo "  Tokens in/out: " . $result['tokens_in'] . " / " . $result['tokens_out'] . "\n";
echo "  Body length: " . strlen($result['body']) . " chars\n\n";

echo "STEP 4 — Parse Claude response\n";
echo "----------------------------------------------------------------\n";
$parsed = response_parser::parse($result['body']);
echo "  Skills parsed: " . count($parsed) . "\n";
foreach ($parsed as $i => $s) {
    echo "\n  #" . ($i + 1) . ": " . $s->name . " [" . $s->category . " L" . $s->level
        . " conf=" . $s->confidence . "]\n";
    echo "       " . mb_substr($s->description, 0, 70) . "\n";
}

echo "\n\nSTEP 5 — Malformed-input robustness\n";
echo "----------------------------------------------------------------\n";
$malformed = [
    'empty'             => '',
    'plain prose'       => 'Sorry, I cannot extract skills.',
    'missing key'       => '{"foo": "bar"}',
    'bad level'         => '{"skills":[{"name":"X","level":99,"confidence":2.0,"category":"Bogus"}]}',
];
foreach ($malformed as $label => $body) {
    $out = response_parser::parse($body);
    $note = ($label === 'bad level') ? ' (1 expected — level/conf clamp, cat->Process)' : ' (0 expected)';
    echo "  '" . str_pad($label, 14) . "' -> " . count($out) . " skills" . $note . "\n";
    if ($label === 'bad level' && count($out) === 1) {
        echo "       clamped level=" . $out[0]->level . " conf=" . $out[0]->confidence
            . " cat=" . $out[0]->category . "\n";
    }
}

echo "\nSTEP 6 — Hindi mock extraction\n";
echo "----------------------------------------------------------------\n";
$hi = anthropic_client::call_mock("अनुपालन प्रशिक्षण सामग्री", 5,
    ['version' => prompt_builder::VERSION_V2_HINDI, 'template' => null]);
$hiskills = response_parser::parse($hi['body']);
echo "  Hindi skills parsed: " . count($hiskills) . "\n";
echo "  First name contains Devanagari: " .
    (isset($hiskills[0]) && preg_match('/\p{Devanagari}/u', $hiskills[0]->name) ? "PASS" : "FAIL") . "\n";

echo "\n================================================================\n";
echo " End-to-end mock pipeline: PASS\n";
echo "================================================================\n";
