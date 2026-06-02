<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for enrol_sentientiasub (ADR-023).
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading(
        'enrol_sentientiasub_settings',
        '',
        get_string('pluginname_desc', 'enrol_sentientiasub')
    ));

    if (!during_initial_install()) {
        // Default role granted to active subscribers (scope=course).
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_sentientiasub/roleid',
            get_string('defaultrole', 'enrol_sentientiasub'),
            get_string('defaultrole_desc', 'enrol_sentientiasub'),
            $student ? $student->id : null,
            $options
        ));
    }
}
