<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_compliance_report',
        get_string('pluginname', 'local_sentientia_compliance_report'));

    $settings->add(new admin_setting_heading('compliance_heading',
        get_string('settingsheading', 'local_sentientia_compliance_report'),
        get_string('settingsdesc', 'local_sentientia_compliance_report')));

    // Auto-enrol when not enrolled.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_compliance_report/auto_enrol',
        get_string('autoenrol', 'local_sentientia_compliance_report'),
        get_string('autoenrol_desc', 'local_sentientia_compliance_report'),
        1)); // ON by default.

    // Reminder after N days of no activity.
    $settings->add(new admin_setting_configtext(
        'local_sentientia_compliance_report/reminder_days',
        get_string('reminderdays', 'local_sentientia_compliance_report'),
        get_string('reminderdays_desc', 'local_sentientia_compliance_report'),
        '7', PARAM_INT));

    // Manager escalation.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_compliance_report/manager_escalation',
        get_string('managerescalation', 'local_sentientia_compliance_report'),
        get_string('managerescalation_desc', 'local_sentientia_compliance_report'),
        1));

    // Weekly report email.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_compliance_report/weekly_report',
        get_string('weeklyreport', 'local_sentientia_compliance_report'),
        get_string('weeklyreport_desc', 'local_sentientia_compliance_report'),
        0));

    $ADMIN->add('localplugins', $settings);
}
