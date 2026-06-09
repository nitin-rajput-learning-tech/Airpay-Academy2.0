<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_recompletion',
        get_string('pluginname', 'local_sentientia_recompletion'));

    $settings->add(new admin_setting_configtext('local_sentientia_recompletion/pre_notify_days',
        get_string('settings_pre_notify_days', 'local_sentientia_recompletion'),
        get_string('settings_pre_notify_days_desc', 'local_sentientia_recompletion'),
        '30', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_sentientia_recompletion/max_batch',
        get_string('settings_max_batch', 'local_sentientia_recompletion'),
        get_string('settings_max_batch_desc', 'local_sentientia_recompletion'),
        '500', PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('local_sentientia_recompletion/dry_run_default',
        get_string('settings_dry_run_default', 'local_sentientia_recompletion'),
        get_string('settings_dry_run_default_desc', 'local_sentientia_recompletion'),
        '0'));

    $ADMIN->add('localplugins', $settings);
}
