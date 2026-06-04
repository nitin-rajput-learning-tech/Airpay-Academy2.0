<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings — Phase A1.
 *
 * Registers three admin pages under Site Admin → Plugins → Local plugins:
 *   1. Channel settings (provider credentials, [CONFIRM] gate banner)
 *   2. DLT template manager
 *   3. Channel analytics dashboard
 *
 * @package local_sentientia_whatsapp
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $category = 'localplugins';

    // ── Settings page (provider API keys + general config) ───────────
    $settings = new admin_settingpage(
        'local_sentientia_whatsapp',
        get_string('settings_pagetitle', 'local_sentientia_whatsapp')
    );
    $ADMIN->add($category, $settings);

    $settings->add(new admin_setting_heading(
        'local_sentientia_whatsapp/heading_live_mode',
        get_string('settings_heading_live_mode', 'local_sentientia_whatsapp'),
        get_string('settings_heading_live_mode_desc', 'local_sentientia_whatsapp')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_whatsapp/karix_api_key',
        get_string('settings_karix_api_key', 'local_sentientia_whatsapp'),
        get_string('settings_karix_api_key_desc', 'local_sentientia_whatsapp'),
        '',
        PARAM_RAW_TRIMMED,
        64
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_whatsapp/msg91_api_key',
        get_string('settings_msg91_api_key', 'local_sentientia_whatsapp'),
        get_string('settings_msg91_api_key_desc', 'local_sentientia_whatsapp'),
        '',
        PARAM_RAW_TRIMMED,
        64
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_whatsapp/dlt_principal_entity_id',
        get_string('settings_dlt_pe_id', 'local_sentientia_whatsapp'),
        get_string('settings_dlt_pe_id_desc', 'local_sentientia_whatsapp'),
        '',
        PARAM_ALPHANUM,
        32
    ));

    // ── C14/F-082 stabilization (2026-05-28): Unified landing page ───
    // Registered FIRST so it's the top entry under the local-plugins
    // node (Moodle preserves registration order). Surfaces a stats
    // snapshot + links to templates + analytics + settings.
    $ADMIN->add($category, new admin_externalpage(
        'local_sentientia_whatsapp_index',
        get_string('admin_index_title', 'local_sentientia_whatsapp'),
        new moodle_url('/local/sentientia_whatsapp/admin/index.php'),
        'moodle/site:config'
    ));

    // ── DLT templates page ───────────────────────────────────────────
    $ADMIN->add($category, new admin_externalpage(
        'local_sentientia_whatsapp_templates',
        get_string('templates_pagetitle', 'local_sentientia_whatsapp'),
        new moodle_url('/local/sentientia_whatsapp/admin/templates.php'),
        'moodle/site:config'
    ));

    // ── Channel analytics page ──────────────────────────────────────
    $ADMIN->add($category, new admin_externalpage(
        'local_sentientia_whatsapp_analytics',
        get_string('analytics_pagetitle', 'local_sentientia_whatsapp'),
        new moodle_url('/local/sentientia_whatsapp/admin/analytics.php'),
        'moodle/site:config'
    ));
}
