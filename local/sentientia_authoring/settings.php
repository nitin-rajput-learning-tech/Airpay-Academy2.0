<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for the Sentientia LMS GenAI Authoring Studio.
 *
 * Credentials are passwordunmask fields read from .env-style secure config —
 * NEVER hardcoded. They are inert unless sentientia.authoring.live_api is ON
 * (and, for TTS, sentientia.authoring.tts ON too) AND the per-action [CONFIRM]
 * gate is passed. With the default flags OFF, the studio runs entirely in
 * mock-mode and these keys are never read.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_authoring',
        get_string('pluginname', 'local_sentientia_authoring')
    );

    // ── AI (Anthropic) ──────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_authoring/heading_ai',
        get_string('settings_heading_ai', 'local_sentientia_authoring'),
        get_string('settings_heading_ai_desc', 'local_sentientia_authoring')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_authoring/anthropic_api_key',
        get_string('setting_anthropic_api_key', 'local_sentientia_authoring'),
        get_string('setting_anthropic_api_key_desc', 'local_sentientia_authoring'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_authoring/default_model',
        get_string('setting_default_model', 'local_sentientia_authoring'),
        get_string('setting_default_model_desc', 'local_sentientia_authoring'),
        'claude-sonnet-4-6',
        PARAM_TEXT
    ));

    // ── TTS (ElevenLabs) ────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_authoring/heading_tts',
        get_string('settings_heading_tts', 'local_sentientia_authoring'),
        get_string('settings_heading_tts_desc', 'local_sentientia_authoring')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_authoring/elevenlabs_api_key',
        get_string('setting_elevenlabs_api_key', 'local_sentientia_authoring'),
        get_string('setting_elevenlabs_api_key_desc', 'local_sentientia_authoring'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_authoring/elevenlabs_voice_id',
        get_string('setting_elevenlabs_voice_id', 'local_sentientia_authoring'),
        get_string('setting_elevenlabs_voice_id_desc', 'local_sentientia_authoring'),
        '',
        PARAM_TEXT
    ));

    // ── Limits ──────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_authoring/heading_limits',
        get_string('settings_heading_limits', 'local_sentientia_authoring'),
        get_string('settings_heading_limits_desc', 'local_sentientia_authoring')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_authoring/max_cards',
        get_string('setting_max_cards', 'local_sentientia_authoring'),
        get_string('setting_max_cards_desc', 'local_sentientia_authoring'),
        '8',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_authoring/max_questions',
        get_string('setting_max_questions', 'local_sentientia_authoring'),
        get_string('setting_max_questions_desc', 'local_sentientia_authoring'),
        '10',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_authoring/max_source_words',
        get_string('setting_max_source_words', 'local_sentientia_authoring'),
        get_string('setting_max_source_words_desc', 'local_sentientia_authoring'),
        '4000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_authoring/daily_token_cap',
        get_string('setting_daily_token_cap', 'local_sentientia_authoring'),
        get_string('setting_daily_token_cap_desc', 'local_sentientia_authoring'),
        '500000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_authoring/default_mastery_score',
        get_string('setting_default_mastery_score', 'local_sentientia_authoring'),
        get_string('setting_default_mastery_score_desc', 'local_sentientia_authoring'),
        '70',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
