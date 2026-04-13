<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_assistant',
        get_string('pluginname', 'local_airpay_assistant'));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_assistant/enabled',
        get_string('enabled', 'local_airpay_assistant'),
        get_string('enabled_desc', 'local_airpay_assistant'),
        '1'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_airpay_assistant/api_key',
        get_string('apikey', 'local_airpay_assistant'),
        get_string('apikey_desc', 'local_airpay_assistant'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_airpay_assistant/rate_limit',
        get_string('ratelimit', 'local_airpay_assistant'),
        get_string('ratelimit_desc', 'local_airpay_assistant'),
        '20',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
