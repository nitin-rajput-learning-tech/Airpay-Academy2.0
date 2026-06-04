<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_assistant',
        get_string('pluginname', 'local_sentientia_assistant'));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_assistant/enabled',
        get_string('enabled', 'local_sentientia_assistant'),
        get_string('enabled_desc', 'local_sentientia_assistant'),
        '1'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_assistant/api_key',
        get_string('apikey', 'local_sentientia_assistant'),
        get_string('apikey_desc', 'local_sentientia_assistant'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_assistant/rate_limit',
        get_string('ratelimit', 'local_sentientia_assistant'),
        get_string('ratelimit_desc', 'local_sentientia_assistant'),
        '20',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
