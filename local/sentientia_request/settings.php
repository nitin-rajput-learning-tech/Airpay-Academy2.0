<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_request',
        get_string('pluginname', 'local_sentientia_request'));

    $settings->add(new admin_setting_configtext('local_sentientia_request/sla_hours',
        get_string('settings_sla_hours', 'local_sentientia_request'),
        get_string('settings_sla_hours_desc', 'local_sentientia_request'),
        '48', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_sentientia_request/default_approver',
        get_string('settings_default_approver', 'local_sentientia_request'),
        get_string('settings_default_approver_desc', 'local_sentientia_request'),
        '2', PARAM_INT));  // 2 = first admin user

    $settings->add(new admin_setting_configtext('local_sentientia_request/auto_expire_days',
        get_string('settings_auto_expire_days', 'local_sentientia_request'),
        get_string('settings_auto_expire_days_desc', 'local_sentientia_request'),
        '30', PARAM_INT));

    $ADMIN->add('localplugins', $settings);
}
