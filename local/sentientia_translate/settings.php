<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for Sentientia LMS AI Content Translation.
 *
 * Phase T.0 (MVP) — these settings:
 *   - api_key                Anthropic API key (passwordunmask)
 *   - default_model          Model identifier (default claude-sonnet-4-6)
 *   - max_output_tokens      Hard cap on Anthropic max_tokens
 *   - max_source_words       Reject sources longer than this
 *   - daily_cost_cap_tokens  Per-customer daily soft cap on tokens
 *   - prompt_template_note   Free-text override hint (informational)
 *
 * Plus a link to the brand-override management page.
 *
 * @package local_sentientia_translate
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_translate',
        get_string('pluginname', 'local_sentientia_translate')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_translate/heading_api',
        get_string('settings_heading_api', 'local_sentientia_translate'),
        get_string('settings_heading_api_desc', 'local_sentientia_translate')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_translate/api_key',
        get_string('setting_api_key', 'local_sentientia_translate'),
        get_string('setting_api_key_desc', 'local_sentientia_translate'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_translate/default_model',
        get_string('setting_default_model', 'local_sentientia_translate'),
        get_string('setting_default_model_desc', 'local_sentientia_translate'),
        'claude-sonnet-4-6',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_translate/max_output_tokens',
        get_string('setting_max_output_tokens', 'local_sentientia_translate'),
        get_string('setting_max_output_tokens_desc', 'local_sentientia_translate'),
        '8192',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_translate/heading_limits',
        get_string('settings_heading_limits', 'local_sentientia_translate'),
        get_string('settings_heading_limits_desc', 'local_sentientia_translate')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_translate/max_source_words',
        get_string('setting_max_source_words', 'local_sentientia_translate'),
        get_string('setting_max_source_words_desc', 'local_sentientia_translate'),
        '4000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_translate/daily_cost_cap_tokens',
        get_string('setting_daily_cost_cap', 'local_sentientia_translate'),
        get_string('setting_daily_cost_cap_desc', 'local_sentientia_translate'),
        '3000000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_translate/heading_prompt',
        get_string('settings_heading_prompt', 'local_sentientia_translate'),
        get_string('settings_heading_prompt_desc', 'local_sentientia_translate')
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_sentientia_translate/prompt_template_note',
        get_string('setting_prompt_template_note', 'local_sentientia_translate'),
        get_string('setting_prompt_template_note_desc', 'local_sentientia_translate'),
        '',
        PARAM_RAW
    ));

    $ADMIN->add('localplugins', $settings);

    // C16 (Bucket C, 2026-05-28): unified admin queue/landing.
    // Listed FIRST so admins clicking "Translation" hit the dashboard
    // rather than the single-row diff page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_translate_queue',
        get_string('admin_index_title', 'local_sentientia_translate'),
        new moodle_url('/local/sentientia_translate/admin/index.php'),
        'local/sentientia_translate:translate'
    ));

    // External pages: the translate UI + brand-override manager.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_translate_brands',
        get_string('brands_page_title', 'local_sentientia_translate'),
        new moodle_url('/local/sentientia_translate/brands.php'),
        'local/sentientia_translate:manage_brands'
    ));
}
