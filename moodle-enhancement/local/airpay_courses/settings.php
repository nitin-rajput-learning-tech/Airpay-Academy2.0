<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Admin settings — Airpay Courses.
//
// P1 #28 (2026-05-20) — first settings file for this plugin. Currently
// only exposes the deadline-reminder cron knobs. Closes audit item #14
// from parity-audit-2026-05-15/airpay_courses.md.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_courses',
        get_string('pluginname', 'local_airpay_courses'));

    // ── P1 #28 — Deadline reminder cron ────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_airpay_courses/reminder_heading',
        get_string('reminder_settings_heading', 'local_airpay_courses'),
        get_string('reminder_settings_intro', 'local_airpay_courses')));

    $settings->add(new admin_setting_configcheckbox(
        'local_airpay_courses/reminder_enabled',
        get_string('reminder_enabled', 'local_airpay_courses'),
        get_string('reminder_enabled_help', 'local_airpay_courses'),
        0));

    $settings->add(new admin_setting_configtext(
        'local_airpay_courses/reminder_days_before',
        get_string('reminder_days_before', 'local_airpay_courses'),
        get_string('reminder_days_before_help', 'local_airpay_courses'),
        '7,3,1', PARAM_TEXT));

    $settings->add(new admin_setting_configtext(
        'local_airpay_courses/reminder_max_per_run',
        get_string('reminder_max_per_run', 'local_airpay_courses'),
        get_string('reminder_max_per_run_help', 'local_airpay_courses'),
        '500', PARAM_INT));

    // Read-only status pane — surfaces "last run + count" so the admin
    // can confirm the cron is firing without diving into mtrace logs.
    $lastrun  = (int) get_config('local_airpay_courses', 'reminder_last_run');
    $lastsent = (int) get_config('local_airpay_courses', 'reminder_last_sent');
    if ($lastrun > 0) {
        $body = get_string('reminder_last_run_value', 'local_airpay_courses',
            (object) ['time' => userdate($lastrun), 'count' => $lastsent]);
    } else {
        $body = get_string('reminder_last_run_never', 'local_airpay_courses');
    }
    $settings->add(new admin_setting_heading(
        'local_airpay_courses/reminder_status',
        get_string('reminder_status', 'local_airpay_courses'),
        $body));

    $ADMIN->add('localplugins', $settings);
}
