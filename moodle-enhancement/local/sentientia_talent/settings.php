<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for local_sentientia_talent.
 *
 * The talent suite is governed by the platform feature-flag Switchboard
 * (sentientia.talent.enabled / .opportunities), not by plugin config, so
 * this page is intentionally light: a quick-link to the relevant Switchboard
 * scope plus a read-only note about the active skills-taxonomy source so an
 * admin can confirm whether AI (skillsai) or the manual fallback is in use.
 *
 * @package local_sentientia_talent
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_sentientia_talent_settings',
        get_string('settings_pagetitle', 'local_sentientia_talent')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_talent/intro_heading',
        get_string('settings_section_general', 'local_sentientia_talent'),
        get_string('settings_section_general_desc', 'local_sentientia_talent')
    ));

    // Read-only: which skills taxonomy is currently driving skill matching.
    // class_exists/get_config guarded inside skills_bridge so this never
    // errors when the parallel skillsai plugin is absent.
    $source = \local_sentientia_talent\skills_bridge::source();
    $sourcelabel = get_string('skillsource_' . $source, 'local_sentientia_talent');
    $settings->add(new admin_setting_description(
        'local_sentientia_talent/skillsource',
        get_string('setting_skillsource', 'local_sentientia_talent'),
        '<div class="alert alert-info">'
            . get_string('setting_skillsource_desc', 'local_sentientia_talent', $sourcelabel)
            . '</div>'
    ));

    // Link to the platform Switchboard where the feature flags live.
    $switchboardurl = (new \moodle_url('/local/sentientia_platform/admin/switchboard.php'))
        ->out(false);
    $settings->add(new admin_setting_description(
        'local_sentientia_talent/switchboardlink',
        get_string('setting_switchboard', 'local_sentientia_talent'),
        get_string('setting_switchboard_desc', 'local_sentientia_talent')
            . '<p class="mt-2"><a class="btn btn-secondary" href="' . s($switchboardurl) . '">'
            . get_string('setting_switchboard_btn', 'local_sentientia_talent')
            . '</a></p>'
    ));

    $ADMIN->add('localplugins', $settings);
}
