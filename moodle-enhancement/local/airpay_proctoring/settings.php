<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_proctoring',
        get_string('pluginname', 'local_airpay_proctoring'));

    $settings->add(new admin_setting_heading('local_airpay_proctoring/h_id',
        'Identity verification', ''));

    $settings->add(new admin_setting_configselect('local_airpay_proctoring/provider',
        get_string('settings_provider', 'local_airpay_proctoring'),
        get_string('settings_provider_desc', 'local_airpay_proctoring'),
        'mock', ['aws' => 'AWS Rekognition', 'mock' => 'Mock (dev/testing)']));

    $settings->add(new admin_setting_configtext('local_airpay_proctoring/aws_region',
        get_string('settings_aws_region', 'local_airpay_proctoring'),
        '', 'ap-south-1', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_airpay_proctoring/aws_key',
        get_string('settings_aws_key', 'local_airpay_proctoring'),
        '', '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('local_airpay_proctoring/aws_secret',
        get_string('settings_aws_secret', 'local_airpay_proctoring'),
        '', ''));

    $settings->add(new admin_setting_configtext('local_airpay_proctoring/aws_s3_bucket',
        get_string('settings_aws_s3_bucket', 'local_airpay_proctoring'),
        '', 'airpay-academy-proctoring', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_airpay_proctoring/match_threshold',
        get_string('settings_match_threshold', 'local_airpay_proctoring'),
        get_string('settings_match_threshold_desc', 'local_airpay_proctoring'),
        '85', PARAM_INT));

    $settings->add(new admin_setting_heading('local_airpay_proctoring/h_rec',
        'Recording', ''));

    $settings->add(new admin_setting_configtext('local_airpay_proctoring/retention_days',
        get_string('settings_retention_days', 'local_airpay_proctoring'),
        get_string('settings_retention_days_desc', 'local_airpay_proctoring'),
        '90', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_airpay_proctoring/chunk_secs',
        get_string('settings_recording_chunk_secs', 'local_airpay_proctoring'),
        get_string('settings_recording_chunk_secs_desc', 'local_airpay_proctoring'),
        '30', PARAM_INT));

    $settings->add(new admin_setting_heading('local_airpay_proctoring/h_rev',
        'Review queue', ''));

    $settings->add(new admin_setting_configtext('local_airpay_proctoring/default_reviewer',
        get_string('settings_default_reviewer', 'local_airpay_proctoring'),
        get_string('settings_default_reviewer_desc', 'local_airpay_proctoring'),
        '2', PARAM_INT));

    $ADMIN->add('localplugins', $settings);
}
