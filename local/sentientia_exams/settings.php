<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #33 (2026-05-20) — first settings file for this plugin. Currently
// only exposes the deadline-reminder cron knobs. Closes audit item #16
// from parity-audit-2026-05-15/sentientia_exams.md.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_exams',
        get_string('pluginname', 'local_sentientia_exams'));

    // ── P1 #33 — Deadline reminder cron ──────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_sentientia_exams/reminder_heading',
        get_string('reminder_settings_heading', 'local_sentientia_exams'),
        get_string('reminder_settings_intro', 'local_sentientia_exams')));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_exams/reminder_enabled',
        get_string('reminder_enabled', 'local_sentientia_exams'),
        get_string('reminder_enabled_help', 'local_sentientia_exams'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_exams/reminder_days_before',
        get_string('reminder_days_before', 'local_sentientia_exams'),
        get_string('reminder_days_before_help', 'local_sentientia_exams'),
        '7,3,1', PARAM_TEXT));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_exams/reminder_max_per_run',
        get_string('reminder_max_per_run', 'local_sentientia_exams'),
        get_string('reminder_max_per_run_help', 'local_sentientia_exams'),
        '500', PARAM_INT));

    $lastrun  = (int) get_config('local_sentientia_exams', 'reminder_last_run');
    $lastsent = (int) get_config('local_sentientia_exams', 'reminder_last_sent');
    if ($lastrun > 0) {
        $body = get_string('reminder_last_run_value', 'local_sentientia_exams',
            (object) ['time' => userdate($lastrun), 'count' => $lastsent]);
    } else {
        $body = get_string('reminder_last_run_never', 'local_sentientia_exams');
    }
    $settings->add(new admin_setting_heading(
        'local_sentientia_exams/reminder_status',
        get_string('reminder_status', 'local_sentientia_exams'),
        $body));

    // ── P1 #34 (2026-05-20) — overdue manager-escalation cron ──────
    $settings->add(new admin_setting_heading(
        'local_sentientia_exams/overdue_heading',
        get_string('overdue_settings_heading', 'local_sentientia_exams'),
        get_string('overdue_settings_intro', 'local_sentientia_exams')));

    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_exams/overdue_enabled',
        get_string('overdue_enabled', 'local_sentientia_exams'),
        get_string('overdue_enabled_help', 'local_sentientia_exams'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_exams/overdue_days_after',
        get_string('overdue_days_after', 'local_sentientia_exams'),
        get_string('overdue_days_after_help', 'local_sentientia_exams'),
        '1,7,14', PARAM_TEXT));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_exams/overdue_max_per_run',
        get_string('overdue_max_per_run', 'local_sentientia_exams'),
        get_string('overdue_max_per_run_help', 'local_sentientia_exams'),
        '500', PARAM_INT));

    $lastrun_o  = (int) get_config('local_sentientia_exams', 'overdue_last_run');
    $lastsent_o = (int) get_config('local_sentientia_exams', 'overdue_last_sent');
    if ($lastrun_o > 0) {
        $body_o = get_string('overdue_last_run_value', 'local_sentientia_exams',
            (object) ['time' => userdate($lastrun_o), 'count' => $lastsent_o]);
    } else {
        $body_o = get_string('overdue_last_run_never', 'local_sentientia_exams');
    }
    $settings->add(new admin_setting_heading(
        'local_sentientia_exams/overdue_status',
        get_string('overdue_status', 'local_sentientia_exams'),
        $body_o));

    $ADMIN->add('localplugins', $settings);
}
