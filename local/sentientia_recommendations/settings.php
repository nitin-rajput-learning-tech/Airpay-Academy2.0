<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for Sentientia LMS AI Course Recommendations.
 *
 * Phase H.0 (MVP) — these settings:
 *   - api_key                Anthropic API key (passwordunmask)
 *   - default_model          Model identifier (default claude-sonnet-4-6)
 *   - max_recommendations    Per-batch ceiling (default 5)
 *   - max_history_items      How many completion rows to feed into prompt
 *   - daily_cost_cap_tokens  Per-customer daily soft cap on tokens
 *   - prompt_template_note   Free-text override hint (read-only display)
 *
 * @package local_sentientia_recommendations
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_recommendations',
        get_string('pluginname', 'local_sentientia_recommendations')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_recommendations/heading_api',
        get_string('settings_heading_api', 'local_sentientia_recommendations'),
        get_string('settings_heading_api_desc', 'local_sentientia_recommendations')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_recommendations/api_key',
        get_string('setting_api_key', 'local_sentientia_recommendations'),
        get_string('setting_api_key_desc', 'local_sentientia_recommendations'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_recommendations/default_model',
        get_string('setting_default_model', 'local_sentientia_recommendations'),
        get_string('setting_default_model_desc', 'local_sentientia_recommendations'),
        'claude-sonnet-4-6',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_recommendations/max_output_tokens',
        get_string('setting_max_output_tokens', 'local_sentientia_recommendations'),
        get_string('setting_max_output_tokens_desc', 'local_sentientia_recommendations'),
        '2048',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_recommendations/heading_limits',
        get_string('settings_heading_limits', 'local_sentientia_recommendations'),
        get_string('settings_heading_limits_desc', 'local_sentientia_recommendations')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_recommendations/max_recommendations',
        get_string('setting_max_recommendations', 'local_sentientia_recommendations'),
        get_string('setting_max_recommendations_desc', 'local_sentientia_recommendations'),
        '5',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_recommendations/max_history_items',
        get_string('setting_max_history_items', 'local_sentientia_recommendations'),
        get_string('setting_max_history_items_desc', 'local_sentientia_recommendations'),
        '50',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_recommendations/daily_cost_cap_tokens',
        get_string('setting_daily_cost_cap', 'local_sentientia_recommendations'),
        get_string('setting_daily_cost_cap_desc', 'local_sentientia_recommendations'),
        '2000000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_recommendations/heading_prompt',
        get_string('settings_heading_prompt', 'local_sentientia_recommendations'),
        get_string('settings_heading_prompt_desc', 'local_sentientia_recommendations')
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_sentientia_recommendations/prompt_template_note',
        get_string('setting_prompt_template_note', 'local_sentientia_recommendations'),
        get_string('setting_prompt_template_note_desc', 'local_sentientia_recommendations'),
        '',
        PARAM_RAW
    ));

    $ADMIN->add('localplugins', $settings);
}
