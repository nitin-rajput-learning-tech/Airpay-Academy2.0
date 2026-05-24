<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for Sentientia LMS AI Quiz (Phase G.1 scaffold).
 *
 * Three settings:
 *   - prompt_template   Default Anthropic system prompt (textarea).
 *                        Per-customer overrides applied at runtime via
 *                        local_airpay_core customer-config hooks.
 *   - max_tokens        Upper bound on output_tokens. Default 4000.
 *   - daily_cost_cap    Soft cap in USD per customer per day. Default 100.
 *
 * @package local_sentientia_ai_quiz
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_ai_quiz',
        get_string('pluginname', 'local_sentientia_ai_quiz')
    );

    // ── Prompt template ──────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_ai_quiz/heading_prompt',
        get_string('settings_heading_prompt', 'local_sentientia_ai_quiz'),
        get_string('settings_heading_prompt_desc', 'local_sentientia_ai_quiz')
    ));

    $default_prompt = <<<'PROMPT'
You are a Sentientia LMS quiz author. Generate {n} multichoice quiz
questions in {lang} from the source below. Each question must have
exactly 4 options and a single correct answer. Output strict JSON with
a top-level "questions" array; no markdown, no commentary.

SOURCE:
{source}
PROMPT;

    $settings->add(new admin_setting_configtextarea(
        'local_sentientia_ai_quiz/prompt_template',
        get_string('setting_prompt_template', 'local_sentientia_ai_quiz'),
        get_string('setting_prompt_template_desc', 'local_sentientia_ai_quiz'),
        $default_prompt,
        PARAM_RAW,
        60,
        12
    ));

    // ── Limits ───────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_ai_quiz/heading_limits',
        get_string('settings_heading_limits', 'local_sentientia_ai_quiz'),
        get_string('settings_heading_limits_desc', 'local_sentientia_ai_quiz')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_ai_quiz/max_tokens',
        get_string('setting_max_tokens', 'local_sentientia_ai_quiz'),
        get_string('setting_max_tokens_desc', 'local_sentientia_ai_quiz'),
        '4000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_ai_quiz/daily_cost_cap',
        get_string('setting_daily_cost_cap', 'local_sentientia_ai_quiz'),
        get_string('setting_daily_cost_cap_desc', 'local_sentientia_ai_quiz'),
        '100',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
