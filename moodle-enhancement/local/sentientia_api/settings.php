<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for local_sentientia_api.
 *
 * The feature flags themselves live in the platform Switchboard; these
 * settings tune operational parameters (rate-limit budget, log retention).
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_api',
        get_string('pluginname', 'local_sentientia_api')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_api/headingratelimit',
        get_string('setting_ratelimit_heading', 'local_sentientia_api'),
        get_string('setting_ratelimit_desc', 'local_sentientia_api')
    ));

    // Requests allowed per user per window.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_api/rate_limit',
        get_string('setting_rate_limit', 'local_sentientia_api'),
        get_string('setting_rate_limit_desc', 'local_sentientia_api'),
        600,
        PARAM_INT
    ));

    // Window length in seconds.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_api/rate_window',
        get_string('setting_rate_window', 'local_sentientia_api'),
        get_string('setting_rate_window_desc', 'local_sentientia_api'),
        60,
        PARAM_INT
    ));

    // Audit-log retention in days.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_api/log_retention_days',
        get_string('setting_log_retention', 'local_sentientia_api'),
        get_string('setting_log_retention_desc', 'local_sentientia_api'),
        90,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);

    // ADR-030 Wave A — outbound webhooks admin page (subscriptions + delivery log).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_api_webhooks',
        get_string('webhooks_title', 'local_sentientia_api'),
        new moodle_url('/local/sentientia_api/webhooks.php'),
        'local/sentientia_api:webhooks_manage'
    ));
}
