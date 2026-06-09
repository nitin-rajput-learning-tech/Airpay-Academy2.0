<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add link to the management panel in admin navigation.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_emails_manage',
        get_string('pluginname', 'local_sentientia_emails') . ' — Management',
        new moodle_url('/local/sentientia_emails/manage.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_emails_preview',
        get_string('pluginname', 'local_sentientia_emails') . ' — Preview',
        new moodle_url('/local/sentientia_emails/preview.php'),
        'moodle/site:config'
    ));

    // Sprint B follow-up (2026-05-14): settings panel for the ramping
    // reminder defaults. These values are the seed values used when an
    // admin creates a NEW course_incomplete rule; they don't override
    // existing rules (admin can edit each rule individually if they
    // need a different cadence per rule).
    $settings = new admin_settingpage(
        'local_sentientia_emails_settings',
        get_string('pluginname', 'local_sentientia_emails') . ' — Settings'
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_emails/ramping_heading',
        get_string('setting_ramping_heading', 'local_sentientia_emails'),
        get_string('setting_ramping_desc', 'local_sentientia_emails')
    ));

    // Default cadence — JSON array of day offsets. Validated on save
    // by our custom setting_cadence_json class: must be a JSON array
    // of positive integers, max 10 entries (any more is spammy
    // regardless of business need). Invalid input shows a clear
    // error message rather than silently falling back to the baseline.
    $settings->add(new \local_sentientia_emails\admin\setting_cadence_json(
        'local_sentientia_emails/default_cadence_days_json',
        get_string('setting_default_cadence', 'local_sentientia_emails'),
        get_string('setting_default_cadence_help', 'local_sentientia_emails'),
        '[1,3,7,14,21]',
        PARAM_TEXT,
        40
    ));

    // Default cap — 0 = unlimited; non-zero = max per (user × course).
    $settings->add(new admin_setting_configtext(
        'local_sentientia_emails/default_max_reminders',
        get_string('setting_default_cap', 'local_sentientia_emails'),
        get_string('setting_default_cap_help', 'local_sentientia_emails'),
        '5',
        PARAM_INT
    ));

    // Auto-stop on completion — checkbox; default ON.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_emails/default_auto_stop',
        get_string('setting_default_auto_stop', 'local_sentientia_emails'),
        get_string('setting_default_auto_stop_help', 'local_sentientia_emails'),
        1
    ));

    // Section separator for the certificate-attachment settings.
    $settings->add(new admin_setting_heading(
        'local_sentientia_emails/cert_heading',
        get_string('setting_cert_heading', 'local_sentientia_emails'),
        get_string('setting_cert_desc', 'local_sentientia_emails')
    ));

    // Toggle: attach the PDF or send the email without it. Useful to
    // disable globally during incident triage if tool_certificate is
    // down — emails still fire, just without the attachment.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_emails/attach_certificate_pdf',
        get_string('setting_attach_cert', 'local_sentientia_emails'),
        get_string('setting_attach_cert_help', 'local_sentientia_emails'),
        1
    ));

    $ADMIN->add('localplugins', $settings);
}
