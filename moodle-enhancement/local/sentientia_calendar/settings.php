<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for local_sentientia_calendar — Tier 2.6 Phase 2.
 *
 * Registers a Site administration → Plugins → Local plugins → Sentientia
 * Calendar Sync page exposing:
 *
 *   - Microsoft Azure client ID + client secret (Phase 2 OAuth)
 *   - Google OAuth client ID + client secret (Phase 2 OAuth)
 *   - Read-only display of the OAuth redirect URI both providers
 *     must allow-list verbatim
 *   - A scaffolding-notice banner explaining that even when these
 *     credentials are filled in, live OAuth does not run until Phase 2.1
 *
 * The page exists in Phase 1 (master feature flag default OFF) but only
 * uses the settings once a customer rolls Phase 2 forward. Empty
 * credentials are an explicit kill switch — the corresponding "Connect …"
 * button is hidden from users.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_sentientia_calendar_settings',
        get_string('settings_pagetitle', 'local_sentientia_calendar')
    );

    // ─── Phase 2 OAuth — scaffolding notice + section heading ────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_calendar/oauth_heading',
        get_string('settings_section_oauth', 'local_sentientia_calendar'),
        get_string('settings_section_oauth_desc', 'local_sentientia_calendar')
            . '<div class="alert alert-warning mt-2">'
            . get_string('setting_scaffolding_notice', 'local_sentientia_calendar')
            . '</div>'
    ));

    // ─── Read-only redirect URI display ──────────────────────────────
    // Computed from $CFG->wwwroot so it always reflects the current
    // canonical URL. Admins paste this verbatim into the Azure /
    // Google app registration's "redirect URIs" panel.
    $redirecturi = (new \moodle_url('/local/sentientia_calendar/oauth/callback.php'))
        ->out(false);
    $settings->add(new admin_setting_description(
        'local_sentientia_calendar/redirect_uri',
        get_string('setting_redirect_uri', 'local_sentientia_calendar'),
        get_string('setting_redirect_uri_desc', 'local_sentientia_calendar')
            . '<pre class="mt-2"><code>' . s($redirecturi) . '</code></pre>'
    ));

    // ─── Microsoft Azure ─────────────────────────────────────────────
    $settings->add(new admin_setting_configtext(
        'local_sentientia_calendar/microsoft_client_id',
        get_string('setting_microsoft_client_id', 'local_sentientia_calendar'),
        get_string('setting_microsoft_client_id_desc', 'local_sentientia_calendar'),
        '',                              // default — empty disables the m365 connect button
        PARAM_ALPHANUMEXT,               // GUIDs + dashes; rejects whitespace / quotes
        80
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_calendar/microsoft_client_secret',
        get_string('setting_microsoft_client_secret', 'local_sentientia_calendar'),
        get_string('setting_microsoft_client_secret_desc', 'local_sentientia_calendar'),
        ''                               // default — empty disables m365 token exchange
    ));

    // ─── Google ──────────────────────────────────────────────────────
    $settings->add(new admin_setting_configtext(
        'local_sentientia_calendar/google_client_id',
        get_string('setting_google_client_id', 'local_sentientia_calendar'),
        get_string('setting_google_client_id_desc', 'local_sentientia_calendar'),
        '',
        PARAM_RAW_TRIMMED,               // Google's IDs contain '.' which PARAM_ALPHANUMEXT strips
        100
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_calendar/google_client_secret',
        get_string('setting_google_client_secret', 'local_sentientia_calendar'),
        get_string('setting_google_client_secret_desc', 'local_sentientia_calendar'),
        ''
    ));

    $ADMIN->add('localplugins', $settings);
}
