<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for local_sentientia_xapi.
 *
 * Registers the LRS viewer and credential settings pages.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // External page: LRS Statement Viewer (managers + site admins).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_xapi_viewer',
        get_string('lrs_viewer_title', 'local_sentientia_xapi'),
        new moodle_url('/local/sentientia_xapi/index.php'),
        'local/sentientia_xapi:viewstatements'
    ));

    // Settings page: LRS credentials and retention.
    $settings = new admin_settingpage(
        'local_sentientia_xapi_settings',
        get_string('settings_pagetitle', 'local_sentientia_xapi')
    );

    // Bearer token (stored in plaintext in config_plugins — admin must rotate).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_xapi/lrs_token',
        get_string('setting_lrs_token', 'local_sentientia_xapi'),
        get_string('setting_lrs_token_desc', 'local_sentientia_xapi'),
        ''
    ));

    // HTTP Basic username.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_xapi/lrs_basic_user',
        get_string('setting_lrs_basic_user', 'local_sentientia_xapi'),
        get_string('setting_lrs_basic_user_desc', 'local_sentientia_xapi'),
        '',
        PARAM_ALPHANUMEXT
    ));

    // HTTP Basic password.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_xapi/lrs_basic_pass',
        get_string('setting_lrs_basic_pass', 'local_sentientia_xapi'),
        get_string('setting_lrs_basic_pass_desc', 'local_sentientia_xapi'),
        ''
    ));

    // Statement retention period.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_xapi/retention_days',
        get_string('setting_retention_days', 'local_sentientia_xapi'),
        get_string('setting_retention_days_desc', 'local_sentientia_xapi'),
        '730',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
