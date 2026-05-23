<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings link — Phase A0 / A0.5.
 *
 * Registers the two airpay_core admin pages in Site Admin → Plugins →
 * Local plugins so they appear in the standard Moodle admin nav:
 *
 *   - The Switchboard  (Phase A0)  /local/airpay_core/admin/switchboard.php
 *   - Style Guide      (Phase A0.5) /local/airpay_core/admin/styleguide.php
 *
 * @package local_airpay_core
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_airpay_core_switchboard',
        get_string('switchboard_pagetitle', 'local_airpay_core'),
        new moodle_url('/local/airpay_core/admin/switchboard.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_airpay_core_styleguide',
        get_string('styleguide_pagetitle', 'local_airpay_core'),
        new moodle_url('/local/airpay_core/admin/styleguide.php'),
        'moodle/site:config'
    ));

    // ─────────────────────────────────────────────────────────────────
    // P0 borrow #11 (Moodle 5.2, 2026-05-23) — Backup filename template.
    // Token cheat-sheet rendered next to the field, generated from
    // \local_airpay_core\backup_filename::token_help() so a new token
    // added to the helper automatically appears in admin help text.
    // Default value matches Moodle's built-in behaviour — opt-in only
    // for callers that route through the helper today (SENTIENTIA SCORM
    // pipeline, future Sentientia LMS export jobs). The Moodle 5.2
    // upgrade will retire this in favour of upstream core_backup config.
    // ─────────────────────────────────────────────────────────────────
    $settings = new admin_settingpage(
        'local_airpay_core_settings',
        get_string('settings_pagetitle', 'local_airpay_core')
    );

    $tokenhelp = '';
    foreach (\local_airpay_core\backup_filename::token_help() as $token => $desc) {
        $tokenhelp .= html_writer::tag('code', $token) . ' &mdash; ' . s($desc) . '<br>';
    }
    $settings->add(new admin_setting_configtext(
        'local_airpay_core/' . \local_airpay_core\backup_filename::SETTING_TEMPLATE,
        get_string('setting_backup_filename_template', 'local_airpay_core'),
        get_string('setting_backup_filename_template_desc', 'local_airpay_core')
            . '<br><br><strong>'
            . get_string('setting_backup_filename_tokens', 'local_airpay_core')
            . '</strong><br>' . $tokenhelp,
        \local_airpay_core\backup_filename::DEFAULT_TEMPLATE,
        PARAM_TEXT,
        80
    ));

    $ADMIN->add('localplugins', $settings);
}
