<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for Sentientia LMS AI Quiz Generation.
 *
 * Phase G.0 (MVP) — three settings:
 *   - api_key            Anthropic API key (passwordunmask)
 *   - default_model      Model identifier (defaults to claude-sonnet-4-6)
 *   - max_questions      Per-request question count ceiling (default 10)
 *   - daily_token_cap    Soft cap on tokens/day before generate is blocked
 *
 * @package local_sentientia_aiquiz
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_aiquiz',
        get_string('pluginname', 'local_sentientia_aiquiz')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_aiquiz/heading_api',
        get_string('settings_heading_api', 'local_sentientia_aiquiz'),
        get_string('settings_heading_api_desc', 'local_sentientia_aiquiz')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_aiquiz/api_key',
        get_string('setting_api_key', 'local_sentientia_aiquiz'),
        get_string('setting_api_key_desc', 'local_sentientia_aiquiz'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_aiquiz/default_model',
        get_string('setting_default_model', 'local_sentientia_aiquiz'),
        get_string('setting_default_model_desc', 'local_sentientia_aiquiz'),
        'claude-sonnet-4-6',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_aiquiz/heading_limits',
        get_string('settings_heading_limits', 'local_sentientia_aiquiz'),
        get_string('settings_heading_limits_desc', 'local_sentientia_aiquiz')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_aiquiz/max_questions',
        get_string('setting_max_questions', 'local_sentientia_aiquiz'),
        get_string('setting_max_questions_desc', 'local_sentientia_aiquiz'),
        '10',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_aiquiz/daily_token_cap',
        get_string('setting_daily_token_cap', 'local_sentientia_aiquiz'),
        get_string('setting_daily_token_cap_desc', 'local_sentientia_aiquiz'),
        '500000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_aiquiz/max_source_words',
        get_string('setting_max_source_words', 'local_sentientia_aiquiz'),
        get_string('setting_max_source_words_desc', 'local_sentientia_aiquiz'),
        '4000',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
