<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #33 (2026-05-20) — first settings file for this plugin. Currently
// only exposes the deadline-reminder cron knobs. Closes audit item #16
// from parity-audit-2026-05-15/airpay_exams.md.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_exams',
        get_string('pluginname', 'local_airpay_exams'));

    // ── P1 #33 — Deadline reminder cron ──────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_airpay_exams/reminder_heading',
        get_string('reminder_settings_heading', 'local_airpay_exams'),
        get_string('reminder_settings_intro', 'local_airpay_exams')));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_exams/reminder_enabled',
        get_string('reminder_enabled', 'local_airpay_exams'),
        get_string('reminder_enabled_help', 'local_airpay_exams'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_airpay_exams/reminder_days_before',
        get_string('reminder_days_before', 'local_airpay_exams'),
        get_string('reminder_days_before_help', 'local_airpay_exams'),
        '7,3,1', PARAM_TEXT));

    $settings->add(new admin_setting_configtext(
        'local_airpay_exams/reminder_max_per_run',
        get_string('reminder_max_per_run', 'local_airpay_exams'),
        get_string('reminder_max_per_run_help', 'local_airpay_exams'),
        '500', PARAM_INT));

    $lastrun  = (int) get_config('local_airpay_exams', 'reminder_last_run');
    $lastsent = (int) get_config('local_airpay_exams', 'reminder_last_sent');
    if ($lastrun > 0) {
        $body = get_string('reminder_last_run_value', 'local_airpay_exams',
            (object) ['time' => userdate($lastrun), 'count' => $lastsent]);
    } else {
        $body = get_string('reminder_last_run_never', 'local_airpay_exams');
    }
    $settings->add(new admin_setting_heading(
        'local_airpay_exams/reminder_status',
        get_string('reminder_status', 'local_airpay_exams'),
        $body));

    $ADMIN->add('localplugins', $settings);
}
