<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_proctoring',
        get_string('pluginname', 'local_sentientia_proctoring'));

    $settings->add(new admin_setting_heading('local_sentientia_proctoring/h_id',
        'Identity verification', ''));

    $settings->add(new admin_setting_configselect('local_sentientia_proctoring/provider',
        get_string('settings_provider', 'local_sentientia_proctoring'),
        get_string('settings_provider_desc', 'local_sentientia_proctoring'),
        'mock', ['aws' => 'AWS Rekognition', 'mock' => 'Mock (dev/testing)']));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/aws_region',
        get_string('settings_aws_region', 'local_sentientia_proctoring'),
        '', 'ap-south-1', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/aws_key',
        get_string('settings_aws_key', 'local_sentientia_proctoring'),
        '', '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('local_sentientia_proctoring/aws_secret',
        get_string('settings_aws_secret', 'local_sentientia_proctoring'),
        '', ''));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/aws_s3_bucket',
        get_string('settings_aws_s3_bucket', 'local_sentientia_proctoring'),
        '', 'airpay-academy-proctoring', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/match_threshold',
        get_string('settings_match_threshold', 'local_sentientia_proctoring'),
        get_string('settings_match_threshold_desc', 'local_sentientia_proctoring'),
        '85', PARAM_INT));

    $settings->add(new admin_setting_heading('local_sentientia_proctoring/h_rec',
        'Recording', ''));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/retention_days',
        get_string('settings_retention_days', 'local_sentientia_proctoring'),
        get_string('settings_retention_days_desc', 'local_sentientia_proctoring'),
        '90', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/chunk_secs',
        get_string('settings_recording_chunk_secs', 'local_sentientia_proctoring'),
        get_string('settings_recording_chunk_secs_desc', 'local_sentientia_proctoring'),
        '30', PARAM_INT));

    $settings->add(new admin_setting_heading('local_sentientia_proctoring/h_rev',
        'Review queue', ''));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/default_reviewer',
        get_string('settings_default_reviewer', 'local_sentientia_proctoring'),
        get_string('settings_default_reviewer_desc', 'local_sentientia_proctoring'),
        '2', PARAM_INT));

    $ADMIN->add('localplugins', $settings);
}
