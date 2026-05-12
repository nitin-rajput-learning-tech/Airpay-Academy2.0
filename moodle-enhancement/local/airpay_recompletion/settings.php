<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_recompletion',
        get_string('pluginname', 'local_airpay_recompletion'));

    $settings->add(new admin_setting_configtext('local_airpay_recompletion/pre_notify_days',
        get_string('settings_pre_notify_days', 'local_airpay_recompletion'),
        get_string('settings_pre_notify_days_desc', 'local_airpay_recompletion'),
        '30', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_airpay_recompletion/max_batch',
        get_string('settings_max_batch', 'local_airpay_recompletion'),
        get_string('settings_max_batch_desc', 'local_airpay_recompletion'),
        '500', PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('local_airpay_recompletion/dry_run_default',
        get_string('settings_dry_run_default', 'local_airpay_recompletion'),
        get_string('settings_dry_run_default_desc', 'local_airpay_recompletion'),
        '0'));

    $ADMIN->add('localplugins', $settings);
}
