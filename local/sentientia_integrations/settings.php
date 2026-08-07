<?php
/**
 * Settings page for Airpay Integrations Hub.
 * All features disabled by default — configure in production.
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_integrations',
        get_string('pluginname', 'local_sentientia_integrations'));

    // ═══ AI FEATURES (Phase 9) ═══
    // Build the heading description with a conditional warning when the
    // BizLMS-only fields that two of four recommendation strategies depend
    // on are not present (Public + ZEEA tenants on a stock-Moodle DB).
    $aidesc = get_string('settings_desc', 'local_sentientia_integrations');
    $bizfields = \local_sentientia_integrations\ai_recommender::bizlms_fields_status();
    if (!$bizfields['all_present']) {
        $missing = [];
        if (!$bizfields['course_open_skill'])      { $missing[] = '{course}.open_skill'; }
        if (!$bizfields['user_open_departmentid']) { $missing[] = '{user}.open_departmentid'; }
        $aidesc .= '<div class="alert alert-warning mt-2 mb-0">'
            . '<strong>Heads up.</strong> The recommender strategies '
            . '<em>by skills</em> and <em>by peers</em> need Airpay-tenant '
            . 'profile fields that are not present on this database: '
            . '<code>' . implode('</code>, <code>', array_map('s', $missing)) . '</code>. '
            . 'Recommendations will silently degrade to category-based + popular-only '
            . 'until those fields are migrated. Logged in INTEGRATIONS-AUDIT.md §3.3.'
            . '</div>';
    }
    $settings->add(new admin_setting_heading('ai_heading',
        get_string('ai_heading', 'local_sentientia_integrations'),
        $aidesc));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/ai_enable',
        get_string('ai_enable', 'local_sentientia_integrations'),
        get_string('ai_enable_desc', 'local_sentientia_integrations'),
        0)); // OFF by default

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/ai_recommendations_enable',
        get_string('ai_recommendations_enable', 'local_sentientia_integrations'),
        get_string('ai_recommendations_desc', 'local_sentientia_integrations'),
        0));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/ai_quiz_enable',
        get_string('ai_quiz_enable', 'local_sentientia_integrations'),
        get_string('ai_quiz_desc', 'local_sentientia_integrations'),
        0));

    // ═══ SENTIENTIA (Phase 9) ═══
    $settings->add(new admin_setting_heading('sentientia_heading',
        get_string('sentientia_heading', 'local_sentientia_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/sentientia_enable',
        get_string('sentientia_enable', 'local_sentientia_integrations'),
        get_string('sentientia_desc', 'local_sentientia_integrations'),
        0));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_integrations/elevenlabs_apikey',
        get_string('elevenlabs_apikey', 'local_sentientia_integrations'),
        get_string('elevenlabs_apikey_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/elevenlabs_voiceid',
        get_string('elevenlabs_voiceid', 'local_sentientia_integrations'),
        get_string('elevenlabs_voiceid_desc', 'local_sentientia_integrations'),
        ''));

    // ═══ KEKA HRMS (Tier 3) ═══
    $settings->add(new admin_setting_heading('keka_heading',
        get_string('keka_heading', 'local_sentientia_integrations'),
        get_string('keka_heading_desc', 'local_sentientia_integrations')));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/keka_base_url',
        get_string('keka_base_url', 'local_sentientia_integrations'),
        get_string('keka_base_url_desc', 'local_sentientia_integrations'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_integrations/keka_api_key',
        get_string('keka_api_key', 'local_sentientia_integrations'),
        get_string('keka_api_key_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/keka_client_id',
        get_string('keka_client_id', 'local_sentientia_integrations'),
        get_string('keka_client_id_desc', 'local_sentientia_integrations'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_integrations/keka_client_secret',
        get_string('keka_client_secret', 'local_sentientia_integrations'),
        get_string('keka_client_secret_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_integrations/webhook_secret',
        get_string('webhook_secret', 'local_sentientia_integrations'),
        get_string('webhook_secret_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/keka_default_orgpath',
        get_string('keka_default_orgpath', 'local_sentientia_integrations'),
        get_string('keka_default_orgpath_desc', 'local_sentientia_integrations'),
        '/1', PARAM_TEXT));

    // ═══ MICROSOFT 365 (Phase 10) ═══
    $settings->add(new admin_setting_heading('m365_heading',
        get_string('m365_heading', 'local_sentientia_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/m365_enable',
        get_string('m365_enable', 'local_sentientia_integrations'),
        get_string('m365_desc', 'local_sentientia_integrations'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/m365_tenant_id',
        get_string('m365_tenant_id', 'local_sentientia_integrations'),
        get_string('m365_tenant_id_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/m365_client_id',
        get_string('m365_client_id', 'local_sentientia_integrations'),
        get_string('m365_client_id_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_integrations/m365_client_secret',
        get_string('m365_client_secret', 'local_sentientia_integrations'),
        get_string('m365_client_secret_desc', 'local_sentientia_integrations'),
        ''));

    // ═══ TEAMS NOTIFICATIONS (Phase 10) ═══
    $settings->add(new admin_setting_heading('teams_heading',
        get_string('teams_heading', 'local_sentientia_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/teams_enable',
        get_string('teams_enable', 'local_sentientia_integrations'),
        get_string('teams_desc', 'local_sentientia_integrations'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/teams_webhook_url',
        get_string('teams_webhook_url', 'local_sentientia_integrations'),
        get_string('teams_webhook_url_desc', 'local_sentientia_integrations'),
        ''));

    // ═══ HRMS SYNC (Phase 10) ═══
    $settings->add(new admin_setting_heading('hrms_heading',
        get_string('hrms_heading', 'local_sentientia_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/hrms_enable',
        get_string('hrms_enable', 'local_sentientia_integrations'),
        get_string('hrms_desc', 'local_sentientia_integrations'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/hrms_api_url',
        get_string('hrms_api_url', 'local_sentientia_integrations'),
        get_string('hrms_api_url_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_integrations/hrms_api_key',
        get_string('hrms_api_key', 'local_sentientia_integrations'),
        get_string('hrms_api_key_desc', 'local_sentientia_integrations'),
        ''));

    $settings->add(new admin_setting_configselect(
        'local_sentientia_integrations/hrms_sync_interval',
        get_string('hrms_sync_interval', 'local_sentientia_integrations'),
        get_string('hrms_sync_interval_desc', 'local_sentientia_integrations'),
        4,
        [1 => '1 hour', 2 => '2 hours', 4 => '4 hours', 8 => '8 hours', 24 => '24 hours']));

    // ═══ GAMIFICATION (Phase 11) ═══
    $settings->add(new admin_setting_heading('gamification_heading',
        get_string('gamification_heading', 'local_sentientia_integrations'), ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/gamification_enable',
        get_string('gamification_enable', 'local_sentientia_integrations'),
        get_string('gamification_desc', 'local_sentientia_integrations'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/gamification_xp_per_completion',
        get_string('gamification_xp_per_completion', 'local_sentientia_integrations'),
        get_string('gamification_xp_per_completion_desc', 'local_sentientia_integrations'),
        100, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/gamification_leaderboard_enable',
        get_string('gamification_leaderboard_enable', 'local_sentientia_integrations'),
        get_string('gamification_leaderboard_desc', 'local_sentientia_integrations'),
        0));

    // ═══ WEB PUSH NOTIFICATIONS (Phase 12) ═══
    $settings->add(new admin_setting_heading('webpush_heading',
        'Web Push Notifications', ''));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_integrations/webpush_enable',
        'Enable Web Push',
        'Browser push notifications via Firebase Cloud Messaging. Requires FCM server key.',
        0));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_integrations/fcm_server_key',
        'FCM Server Key',
        'Firebase Cloud Messaging server key from Firebase Console → Project Settings → Cloud Messaging.',
        ''));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_integrations/fcm_sender_id',
        'FCM Sender ID',
        'Firebase sender ID for the service worker.',
        ''));

    $ADMIN->add('localplugins', $settings);
}
