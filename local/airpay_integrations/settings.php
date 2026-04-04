<?php
/**
 * Settings page for Airpay Integrations Hub.
 * All features disabled by default — configure in production.
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_integrations',
        get_string('pluginname', 'local_airpay_integrations'));

    // ═══ AI FEATURES (Phase 9) ═══
    $settings->add(new admin_setting_heading('ai_heading',
        get_string('ai_heading', 'local_airpay_integrations'),
        get_string('settings_desc', 'local_airpay_integrations')));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_integrations/ai_enable',
        get_string('ai_enable', 'local_airpay_integrations'),
        get_string('ai_enable_desc', 'local_airpay_integrations'),
        0)); // OFF by default

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_integrations/ai_recommendations_enable',
        get_string('ai_recommendations_enable', 'local_airpay_integrations'),
        get_string('ai_recommendations_desc', 'local_airpay_integrations'),
        0));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_integrations/ai_quiz_enable',
        get_string('ai_quiz_enable', 'local_airpay_integrations'),
        get_string('ai_quiz_desc', 'local_airpay_integrations'),
        0));

    // ═══ SENTIENTIA (Phase 9) ═══
    $settings->add(new admin_setting_heading('sentientia_heading',
        get_string('sentientia_heading', 'local_airpay_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_integrations/sentientia_enable',
        get_string('sentientia_enable', 'local_airpay_integrations'),
        get_string('sentientia_desc', 'local_airpay_integrations'),
        0));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_airpay_integrations/elevenlabs_apikey',
        get_string('elevenlabs_apikey', 'local_airpay_integrations'),
        get_string('elevenlabs_apikey_desc', 'local_airpay_integrations'),
        ''));

    $settings->add(new admin_setting_configtext(
        'local_airpay_integrations/elevenlabs_voiceid',
        get_string('elevenlabs_voiceid', 'local_airpay_integrations'),
        get_string('elevenlabs_voiceid_desc', 'local_airpay_integrations'),
        ''));

    // ═══ MICROSOFT 365 (Phase 10) ═══
    $settings->add(new admin_setting_heading('m365_heading',
        get_string('m365_heading', 'local_airpay_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_integrations/m365_enable',
        get_string('m365_enable', 'local_airpay_integrations'),
        get_string('m365_desc', 'local_airpay_integrations'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_airpay_integrations/m365_tenant_id',
        get_string('m365_tenant_id', 'local_airpay_integrations'),
        get_string('m365_tenant_id_desc', 'local_airpay_integrations'),
        ''));

    $settings->add(new admin_setting_configtext(
        'local_airpay_integrations/m365_client_id',
        get_string('m365_client_id', 'local_airpay_integrations'),
        get_string('m365_client_id_desc', 'local_airpay_integrations'),
        ''));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_airpay_integrations/m365_client_secret',
        get_string('m365_client_secret', 'local_airpay_integrations'),
        get_string('m365_client_secret_desc', 'local_airpay_integrations'),
        ''));

    // ═══ TEAMS NOTIFICATIONS (Phase 10) ═══
    $settings->add(new admin_setting_heading('teams_heading',
        get_string('teams_heading', 'local_airpay_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_integrations/teams_enable',
        get_string('teams_enable', 'local_airpay_integrations'),
        get_string('teams_desc', 'local_airpay_integrations'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_airpay_integrations/teams_webhook_url',
        get_string('teams_webhook_url', 'local_airpay_integrations'),
        get_string('teams_webhook_url_desc', 'local_airpay_integrations'),
        ''));

    // ═══ HRMS SYNC (Phase 10) ═══
    $settings->add(new admin_setting_heading('hrms_heading',
        get_string('hrms_heading', 'local_airpay_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_integrations/hrms_enable',
        get_string('hrms_enable', 'local_airpay_integrations'),
        get_string('hrms_desc', 'local_airpay_integrations'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_airpay_integrations/hrms_api_url',
        get_string('hrms_api_url', 'local_airpay_integrations'),
        get_string('hrms_api_url_desc', 'local_airpay_integrations'),
        ''));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_airpay_integrations/hrms_api_key',
        get_string('hrms_api_key', 'local_airpay_integrations'),
        get_string('hrms_api_key_desc', 'local_airpay_integrations'),
        ''));

    $settings->add(new admin_setting_configselect(
        'local_airpay_integrations/hrms_sync_interval',
        get_string('hrms_sync_interval', 'local_airpay_integrations'),
        get_string('hrms_sync_interval_desc', 'local_airpay_integrations'),
        4,
        [1 => '1 hour', 2 => '2 hours', 4 => '4 hours', 8 => '8 hours', 24 => '24 hours']));

    $ADMIN->add('localplugins', $settings);
}
