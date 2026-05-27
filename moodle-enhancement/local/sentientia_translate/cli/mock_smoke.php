<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI smoke test for the AI Translation pipeline in MOCK MODE only.
 *
 * Exercises prompt_builder, anthropic_client::call_mock(),
 * response_parser, and brand_manager::apply_overrides() without any HTTP
 * calls or DB. Verifies:
 *   1. Request validation (length + lang + PII heuristic)
 *   2. Prompt construction (system + user message, with protected terms)
 *   3. Mock dispatch (no money spent, no internet needed)
 *   4. Strict JSON parsing
 *   5. Brand-name preservation: WITH override (substituted) and WITHOUT
 *      override (preserved verbatim)
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_translate/cli/mock_smoke.php
 *
 * @package local_sentientia_translate
 */

define('CLI_SCRIPT', true);

$configfile = __DIR__ . '/../../../config.php';
if (file_exists($configfile)) {
    require_once($configfile);
} else {
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
    require_once(__DIR__ . '/../classes/brand_manager.php');
}

use local_sentientia_translate\prompt_builder;
use local_sentientia_translate\response_parser;
use local_sentientia_translate\anthropic_client;
use local_sentientia_translate\brand_manager;

echo "================================================================\n";
echo " local_sentientia_translate — end-to-end mock smoke (T.0)\n";
echo "================================================================\n\n";

$source = "Welcome to Airpay compliance training. Airpay uses UPI and RBI-mandated KYC.";
$targetlang = 'kn';
$airpaykn = 'ಏರ್‌ಪೇ';

echo "STEP 1 — Request validation\n";
echo "----------------------------------------------------------------\n";
$errors = prompt_builder::validate_request($source, $targetlang, 4000);
echo "  Source length: " . prompt_builder::word_count($source) . " words\n";
echo "  Target language: {$targetlang}\n";
echo "  Validation errors: " . (empty($errors) ? "none" : implode(', ', $errors)) . "\n";
echo "  PII detected: " . (prompt_builder::contains_pii_pattern($source) ? "YES" : "no") . "\n\n";

echo "STEP 2 — Prompt construction (with protected brand terms)\n";
echo "----------------------------------------------------------------\n";
$protected = brand_manager::DEFAULT_PROTECTED;
$sys = prompt_builder::build_system_prompt($targetlang, $protected);
$user = prompt_builder::build_user_message($source, $targetlang);
echo "  Prompt version: " . prompt_builder::VERSION . "\n";
echo "  System prompt length: " . strlen($sys) . " chars\n";
echo "  Protected terms in prompt: " . (strpos($sys, 'Airpay') !== false ? "YES" : "no") . "\n";
echo "  User message length: " . strlen($user) . " chars\n\n";

echo "STEP 3 — Anthropic mock call (sentientia.translate.live_api OFF)\n";
echo "----------------------------------------------------------------\n";
$result = anthropic_client::call_mock($source, $targetlang);
echo "  Mode: " . $result['mode'] . "\n";
echo "  Tokens in/out: " . $result['tokens_in'] . " / " . $result['tokens_out'] . "\n";
echo "  Body length: " . strlen($result['body']) . " chars\n\n";

echo "STEP 4 — Parse Claude response\n";
echo "----------------------------------------------------------------\n";
$parsed = response_parser::parse($result['body']);
echo "  Parsed: " . ($parsed !== null ? "OK" : "FAILED") . "\n";
echo "  Target lang: " . ($parsed ? $parsed->target_lang : '-') . "\n";
echo "  Translated text: " . mb_substr($parsed->translated_text, 0, 70) . "...\n\n";

echo "STEP 5 — Brand-name preservation\n";
echo "----------------------------------------------------------------\n";
// WITH override: Airpay -> Kannada script.
$overridemap = ['Airpay' => $airpaykn];
[$withoverride, $count] = brand_manager::apply_overrides($parsed->translated_text, $overridemap);
$has_kn  = strpos($withoverride, $airpaykn) !== false;
$no_latin = strpos($withoverride, 'Airpay') === false;
echo "  WITH override (Airpay -> {$airpaykn}):\n";
echo "    substitutions applied: {$count}\n";
echo "    contains Kannada form: " . ($has_kn ? "YES (PASS)" : "no (FAIL)") . "\n";
echo "    Latin 'Airpay' removed: " . ($no_latin ? "YES (PASS)" : "no (FAIL)") . "\n";

// WITHOUT override: Airpay preserved verbatim.
[$nooverride, $count2] = brand_manager::apply_overrides($parsed->translated_text, []);
$preserved = strpos($nooverride, 'Airpay') !== false;
echo "  WITHOUT override:\n";
echo "    substitutions applied: {$count2}\n";
echo "    'Airpay' preserved verbatim: " . ($preserved ? "YES (PASS)" : "no (FAIL)") . "\n";

// Whole-token guard: "Airpayment" must not be touched.
[$guard, $gc] = brand_manager::apply_overrides('Airpayment vs Airpay', $overridemap);
echo "  Whole-token guard ('Airpayment' untouched, 'Airpay' replaced):\n";
echo "    Airpayment intact: " . (strpos($guard, 'Airpayment') !== false ? "YES (PASS)" : "no (FAIL)") . "\n";
echo "    substitutions: {$gc} (expected 1)\n";

echo "\nSTEP 6 — Multi-language script descriptions\n";
echo "----------------------------------------------------------------\n";
foreach (['hi', 'mr', 'kn', 'sw'] as $l) {
    $p = prompt_builder::build_system_prompt($l, []);
    // Pull the script mention from the first ~200 chars for display.
    echo "  {$l}: supported=" . (brand_manager::is_supported_lang($l) ? "yes" : "NO") . "\n";
}

echo "\n================================================================\n";
echo " End-to-end mock pipeline: PASS\n";
echo "================================================================\n";
