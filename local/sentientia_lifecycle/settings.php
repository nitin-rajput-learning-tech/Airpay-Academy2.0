<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Settings for local_sentientia_lifecycle (added 2026-08-07 with the
 * mandatory-course definition — ADR-029).
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_lifecycle',
        get_string('pluginname', 'local_sentientia_lifecycle'));

    $settings->add(new admin_setting_heading('lifecycle_autoenrol_heading',
        get_string('autoenrol_heading', 'local_sentientia_lifecycle'),
        get_string('autoenrol_heading_desc', 'local_sentientia_lifecycle')));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_lifecycle/mandatory_tag',
        get_string('mandatory_tag', 'local_sentientia_lifecycle'),
        get_string('mandatory_tag_desc', 'local_sentientia_lifecycle'),
        'mandatory', PARAM_TAG));

    $ADMIN->add('localplugins', $settings);
}
