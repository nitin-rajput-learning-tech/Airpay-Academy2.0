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
 * local_airpay_core::get_customer_config — those don't surface in this
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
