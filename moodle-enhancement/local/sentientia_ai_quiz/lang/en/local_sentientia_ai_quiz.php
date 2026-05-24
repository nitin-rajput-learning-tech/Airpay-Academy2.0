<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English strings — Sentientia LMS AI Quiz (Phase G.1 scaffold).
 *
 * Parity contract: every key in this file MUST exist in
 * lang/hi/local_sentientia_ai_quiz.php (Hindi). The parity test in
 * tests/feature_flags_test.php verifies this.
 *
 * @package local_sentientia_ai_quiz
 */

defined('MOODLE_INTERNAL') || die();

// ── Plugin identity ───────────────────────────────────────────────
$string['pluginname']      = 'Sentientia LMS — AI Quiz (Hindi / per-customer prompts)';
$string['plugin_tagline']  = 'Hindi quiz generation with per-customer Anthropic prompt templates.';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_ai_quiz:generate'] = 'Generate AI quiz drafts (Phase G.1)';

// ── Exception / error messages ────────────────────────────────────
$string['confirm_required']     = 'A per-call [CONFIRM] gate is required. The Phase G.1 scaffold blocks every generate_quiz() call until the live wiring chip is reviewed.';
$string['error_feature_off']    = 'AI Quiz (G.1) is disabled. Ask an administrator to enable sentientia_ai_quiz_enabled in the Switchboard.';
$string['error_no_capability']  = 'You do not have permission to generate AI quiz drafts.';
$string['error_invalid_lang']   = 'Unsupported language code: {$a}. Supported values: en, hi.';
$string['error_empty_source']   = 'Source content is required.';
$string['error_source_too_long'] = 'Source content exceeds the configured word limit.';
$string['error_cost_cap']       = 'Your customer has used {$a->used} of {$a->cap} USD in budget today.';

// ── Settings ──────────────────────────────────────────────────────
$string['settings_heading_prompt']      = 'Prompt template';
$string['settings_heading_prompt_desc'] = 'Default Anthropic system prompt for the G.1 Hindi quiz generator. Per-customer overrides are applied at runtime via local_airpay_core customer-config hooks.';
$string['setting_prompt_template']      = 'Prompt template';
$string['setting_prompt_template_desc'] = 'Anthropic system prompt. Use placeholders {source} and {lang}. Customer-specific overrides take precedence over this default.';
$string['setting_max_tokens']           = 'Maximum output tokens per request';
$string['setting_max_tokens_desc']      = 'Upper bound on output_tokens sent to Anthropic for any one generate request. Default 4000. Lower to reduce cost; raise to fit longer quizzes.';
$string['settings_heading_limits']      = 'Cost limits';
$string['settings_heading_limits_desc'] = 'Soft caps that block further generate calls when exceeded. Audited per customer per day.';
$string['setting_daily_cost_cap']       = 'Daily cost cap per customer (USD)';
$string['setting_daily_cost_cap_desc']  = 'Soft cap in US dollars on total Anthropic spend per customer per calendar day. Default 100. Once exceeded, generate_quiz() refuses further calls until midnight.';

// ── Language labels ───────────────────────────────────────────────
$string['lang_en'] = 'English';
$string['lang_hi'] = 'Hindi';

// ── Privacy provider strings ──────────────────────────────────────
$string['privacy:metadata']               = 'Sentientia LMS AI Quiz (G.1) stores a per-call audit log so customers can analyse Anthropic spend and reproduce historical generation requests. The plugin never persists the source text itself in this scaffold; only a SHA-256 hash of (prompt template || source) is stored.';
$string['privacy:metadata:log']           = 'AI Quiz generation audit log. One row per generate_quiz() call.';
$string['privacy:metadata:log:userid']    = 'The user who invoked generate_quiz().';
$string['privacy:metadata:log:courseid']  = 'The course the draft was associated with (0 = site-level).';
$string['privacy:metadata:log:prompt_hash'] = 'SHA-256 hex digest of the prompt template and source text. Used for deduplication and audit, not for reconstructing source content.';
$string['privacy:metadata:log:model']     = 'Anthropic model identifier used for the call.';
$string['privacy:metadata:log:tokens']    = 'Anthropic input + output tokens reported for cost tracking.';
$string['privacy:metadata:log:success']   = 'Whether the call succeeded (1) or failed (0).';
$string['privacy:metadata:log:error']     = 'Short failure detail when success = 0 (never includes API keys or source text).';
$string['privacy:metadata:log:timecreated'] = 'Unix timestamp when the call was attempted.';

// ── External subsystem (Anthropic) ────────────────────────────────
$string['privacy:metadata:anthropic']            = 'When the live-wiring chip is enabled, source text is transmitted to Anthropic Claude for quiz generation. The Phase G.1 scaffold does not yet make any live calls.';
$string['privacy:metadata:anthropic:sourcetext'] = 'The trainer-supplied source text passed to Anthropic.';
$string['privacy:metadata:anthropic:lang']       = 'The target language code (en or hi) telling Claude which language to produce questions in.';
