<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for Sentientia LMS Microsoft 365 integration.
 *
 * Phase C.1 — four settings:
 *   - azure_tenant_id   GUID of the Azure / Microsoft Entra tenant
 *   - azure_client_id   GUID of the application registration
 *   - redirect_uri      OAuth callback URL (must match Azure registration)
 *   - allowed_scopes    Multiselect of optional OAuth scopes
 *
 * The default scope set (openid, profile, offline_access, User.Read) is
 * always requested at connect time and is not configurable here — those
 * are the minimum scopes the OAuth flow itself needs to run. The
 * multiselect controls which ADDITIONAL scopes the connect UI may offer
 * to the user (and thus which Graph endpoints become reachable in
 * Phase C.2 onward).
 *
 * The Azure client SECRET is NOT a setting on this page. Confidential
 * clients on the server-side flow require a secret, but Phase C.1
 * scaffolds the public-client PKCE flow (no secret). If Phase C.2 needs
 * a confidential client, the secret will live in $CFG (config.php)
 * rather than the database — per .claude/rules/api.md, no secrets in
 * settings rows.
 *
 * @package local_sentientia_m365
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_sentientia_m365',
        get_string('pluginname', 'local_sentientia_m365')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_m365/heading_azure',
        get_string('settings_heading_azure', 'local_sentientia_m365'),
        get_string('settings_heading_azure_desc', 'local_sentientia_m365')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_m365/azure_tenant_id',
        get_string('setting_azure_tenant_id', 'local_sentientia_m365'),
        get_string('setting_azure_tenant_id_desc', 'local_sentientia_m365'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_m365/azure_client_id',
        get_string('setting_azure_client_id', 'local_sentientia_m365'),
        get_string('setting_azure_client_id_desc', 'local_sentientia_m365'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_m365/redirect_uri',
        get_string('setting_redirect_uri', 'local_sentientia_m365'),
        get_string('setting_redirect_uri_desc', 'local_sentientia_m365'),
        '',
        PARAM_URL
    ));

    // Optional scopes the user may request — minimum (openid, profile,
    // offline_access, User.Read) is always requested and is not listed
    // here.
    $scope_options = [
        'Sites.Read.All'        => get_string('scope_sites_read_all', 'local_sentientia_m365'),
        'Files.Read.All'        => get_string('scope_files_read_all', 'local_sentientia_m365'),
        'Calendars.Read'        => get_string('scope_calendars_read', 'local_sentientia_m365'),
        'Calendars.ReadWrite'   => get_string('scope_calendars_readwrite', 'local_sentientia_m365'),
        'TeamMember.Read.All'   => get_string('scope_team_member_read_all', 'local_sentientia_m365'),
        'Mail.Read'             => get_string('scope_mail_read', 'local_sentientia_m365'),
    ];

    $settings->add(new admin_setting_configmultiselect(
        'local_sentientia_m365/allowed_scopes',
        get_string('setting_allowed_scopes', 'local_sentientia_m365'),
        get_string('setting_allowed_scopes_desc', 'local_sentientia_m365'),
        [],
        $scope_options
    ));

    $ADMIN->add('localplugins', $settings);

    // C15 (Bucket C, 2026-05-28): OAuth admin landing dashboard.
    // Surfaces config status, feature flag, connected-token count
    // and the C.1–C.6 roadmap on one page so admins clicking
    // "Microsoft 365" in any nav land somewhere readable.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_m365_index',
        get_string('admin_index_title', 'local_sentientia_m365'),
        new moodle_url('/local/sentientia_m365/admin/index.php'),
        'moodle/site:config'
    ));
}
