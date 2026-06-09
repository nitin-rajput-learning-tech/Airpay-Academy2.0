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
        global $DB;
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

        // All-access cohort (scope=allaccess): subscribers are added here on activation;
        // an admin cohort-syncs it into the catalogue (ADR-023 increment 5, cohort-sync model).
        $cohortoptions = [0 => get_string('none')] + $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');
        $settings->add(new admin_setting_configselect(
            'enrol_sentientiasub/allaccess_cohortid',
            get_string('allaccesscohort', 'enrol_sentientiasub'),
            get_string('allaccesscohort_desc', 'enrol_sentientiasub'),
            0,
            $cohortoptions
        ));
    }
}
