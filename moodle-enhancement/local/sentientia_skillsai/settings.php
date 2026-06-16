<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for Sentientia LMS Skills Intelligence.
 *
 * P0.1.0 settings:
 *   - api_key            Anthropic API key (passwordunmask)
 *   - default_model      Model identifier (defaults to claude-sonnet-4-6)
 *   - max_skills         Per-extraction skill count ceiling (default 15)
 *   - daily_token_cap    Soft cap on tokens/day before extract is blocked
 *   - max_source_words   Per-job source word cap
 *   - per-customer prompt template override (written into the
 *     local_sentientia_platform config namespace — same crossing-namespaces
 *     pattern as sentientia_aiquiz so the platform owns the read side).
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_skillsai',
        get_string('pluginname', 'local_sentientia_skillsai')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_skillsai/heading_api',
        get_string('settings_heading_api', 'local_sentientia_skillsai'),
        get_string('settings_heading_api_desc', 'local_sentientia_skillsai')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_skillsai/api_key',
        get_string('setting_api_key', 'local_sentientia_skillsai'),
        get_string('setting_api_key_desc', 'local_sentientia_skillsai'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_skillsai/default_model',
        get_string('setting_default_model', 'local_sentientia_skillsai'),
        get_string('setting_default_model_desc', 'local_sentientia_skillsai'),
        'claude-sonnet-4-6',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_skillsai/heading_limits',
        get_string('settings_heading_limits', 'local_sentientia_skillsai'),
        get_string('settings_heading_limits_desc', 'local_sentientia_skillsai')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_skillsai/max_skills',
        get_string('setting_max_skills', 'local_sentientia_skillsai'),
        get_string('setting_max_skills_desc', 'local_sentientia_skillsai'),
        '15',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_skillsai/daily_token_cap',
        get_string('setting_daily_token_cap', 'local_sentientia_skillsai'),
        get_string('setting_daily_token_cap_desc', 'local_sentientia_skillsai'),
        '500000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_skillsai/max_source_words',
        get_string('setting_max_source_words', 'local_sentientia_skillsai'),
        get_string('setting_max_source_words_desc', 'local_sentientia_skillsai'),
        '6000',
        PARAM_INT
    ));

    // Per-customer prompt template override (single-customer today).
    $settings->add(new admin_setting_heading(
        'local_sentientia_skillsai/heading_customer_prompts',
        get_string('settings_heading_customer_prompts', 'local_sentientia_skillsai'),
        get_string('settings_heading_customer_prompts_desc', 'local_sentientia_skillsai')
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_sentientia_platform/customer_1_skillsai_prompt_template',
        get_string('setting_customer_1_prompt_template', 'local_sentientia_skillsai'),
        get_string('setting_customer_1_prompt_template_desc', 'local_sentientia_skillsai'),
        '',
        PARAM_RAW,
        80,
        12
    ));

    $ADMIN->add('localplugins', $settings);
}
