<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// B1 (ADR-038 D2, 2026-11-20): the identity-provider choice (AWS Rekognition or mock) and the face-match threshold
// are gone with the photo step (decision D10: no automated ID-to-face matching in v1). The AWS settings that remain
// are the recording store's, which the daily purge task reads. Headings come from the language pack.

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_proctoring',
        get_string('pluginname', 'local_sentientia_proctoring'));

    $settings->add(new admin_setting_heading('local_sentientia_proctoring/h_store',
        get_string('settings_h_store', 'local_sentientia_proctoring'),
        get_string('settings_h_store_desc', 'local_sentientia_proctoring')));

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

    $settings->add(new admin_setting_heading('local_sentientia_proctoring/h_rec',
        get_string('settings_h_rec', 'local_sentientia_proctoring'), ''));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/retention_days',
        get_string('settings_retention_days', 'local_sentientia_proctoring'),
        get_string('settings_retention_days_desc', 'local_sentientia_proctoring'),
        '90', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/chunk_secs',
        get_string('settings_recording_chunk_secs', 'local_sentientia_proctoring'),
        get_string('settings_recording_chunk_secs_desc', 'local_sentientia_proctoring'),
        '30', PARAM_INT));

    $settings->add(new admin_setting_heading('local_sentientia_proctoring/h_rev',
        get_string('settings_h_rev', 'local_sentientia_proctoring'), ''));

    $settings->add(new admin_setting_configtext('local_sentientia_proctoring/default_reviewer',
        get_string('settings_default_reviewer', 'local_sentientia_proctoring'),
        get_string('settings_default_reviewer_desc', 'local_sentientia_proctoring'),
        '2', PARAM_INT));

    $ADMIN->add('localplugins', $settings);
}
