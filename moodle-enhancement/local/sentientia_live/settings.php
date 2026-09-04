<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for local_sentientia_live — Phase E.5 (2026-05-25).
 *
 * Registers a Site administration → Plugins → Local plugins →
 * Sentientia Live engagement settings page. Today this page only
 * houses the wordcloud defaults; future chips will append openended +
 * quiz timer settings here so we don't sprinkle config across multiple
 * admin nodes.
 *
 * Defaults declared here are referenced from slide_manager's
 * validate_settings() pass and from word_cloud::persist_response when
 * the per-slide settings_json doesn't override the key. Customer-level
 * branding / profanity-denylist overrides go through
 * local_sentientia_platform::get_customer_config — those don't surface in this
 * UI (they live in the per-customer admin panel).
 *
 * @package local_sentientia_live
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_sentientia_live_settings',
        get_string('settings_pagetitle', 'local_sentientia_live')
    );

    // ── Word-cloud defaults ─────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_live/wc_defaults_heading',
        get_string('settings_wc_heading', 'local_sentientia_live'),
        get_string('settings_wc_heading_desc', 'local_sentientia_live')
    ));

    // Minimum word length — drops too-short tokens (single letters
    // etc.) during tokenisation. Per-slide settings can override.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_live/default_min_word_length',
        get_string('setting_default_min_word_length',
            'local_sentientia_live'),
        get_string('setting_default_min_word_length_desc',
            'local_sentientia_live'),
        2,
        PARAM_INT,
        4
    ));

    // Maximum submissions per learner per slide — hard cap on how
    // many words one audience member can contribute.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_live/default_max_responses',
        get_string('setting_default_max_responses',
            'local_sentientia_live'),
        get_string('setting_default_max_responses_desc',
            'local_sentientia_live'),
        3,
        PARAM_INT,
        4
    ));

    // ── H4 remediation (UAT-SECURITY-POSTURE-2026-09-03, 2026-09-04) ──
    // SSE stream limits — caps that bound the Apache worker pool a
    // volumetric DoS via stream.php can consume. See
    // classes/sse_connection_registry.php for the enforcement logic.
    $settings->add(new admin_setting_heading(
        'local_sentientia_live/sse_limits_heading',
        get_string('settings_sse_heading', 'local_sentientia_live'),
        get_string('settings_sse_heading_desc', 'local_sentientia_live')
    ));

    // Maximum wall-clock lifetime of one SSE connection before the
    // server tells the client to reconnect. Was hardcoded to 300s;
    // default lowered to 60s so a flood of connections rotates (and is
    // re-evaluated against the caps below) far more often.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_live/sse_max_seconds',
        get_string('setting_sse_max_seconds', 'local_sentientia_live'),
        get_string('setting_sse_max_seconds_desc', 'local_sentientia_live'),
        60,
        PARAM_INT,
        5
    ));

    // Global cap on concurrently-open SSE connections across all
    // sessions. Default 8 is sized for a prefork Apache host with a
    // small worker pool (UAT: ~15 workers) — leaves headroom for normal
    // page requests alongside open streams.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_live/sse_max_connections',
        get_string('setting_sse_max_connections', 'local_sentientia_live'),
        get_string('setting_sse_max_connections_desc', 'local_sentientia_live'),
        4,
        PARAM_INT,
        4
    ));

    $ADMIN->add('localplugins', $settings);

    // ── B18 / F-089 stabilization (2026-05-28) ──────────────────────
    // Per-tenant Sentientia Live kill switches admin page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_live_tenant_switches',
        get_string('tenant_switches_title', 'local_sentientia_live'),
        new moodle_url('/local/sentientia_live/admin/tenant_switches.php'),
        'moodle/site:config'
    ));
}
