<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings — Airpay User Engine.
 *
 * Replaces get_config('local_users', ...) with local_airpay_users config.
 *
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_users',
        get_string('pluginname', 'local_airpay_users'));

    $settings->add(new admin_setting_heading('local_airpay_users/heading',
        get_string('settings_heading', 'local_airpay_users'), ''));

    $settings->add(new admin_setting_configtext('local_airpay_users/organization_shortname',
        get_string('organization_shortname', 'local_airpay_users'),
        '', '', PARAM_TEXT));

    $settings->add(new admin_setting_configcheckbox('local_airpay_users/activeregistration',
        get_string('activeregistration', 'local_airpay_users'),
        get_string('activeregistration_help', 'local_airpay_users'), 0));

    // W1-8 (2026-05-16) — Public-tenant signup configuration.
    $settings->add(new admin_setting_heading('local_airpay_users/signup_heading',
        get_string('signup_settings_heading', 'local_airpay_users'),
        get_string('signup_settings_intro', 'local_airpay_users')));

    $settings->add(new admin_setting_configtext('local_airpay_users/signup_tenant_path',
        get_string('signup_tenant_path', 'local_airpay_users'),
        get_string('signup_tenant_path_help', 'local_airpay_users'),
        '/77', PARAM_TEXT));

    $settings->add(new admin_setting_confightmleditor(
        'local_airpay_users/custom_privacy_policy_html',
        get_string('custom_privacy_policy_html', 'local_airpay_users'),
        get_string('custom_privacy_policy_html_help', 'local_airpay_users'),
        ''));

    $settings->add(new admin_setting_confightmleditor(
        'local_airpay_users/custom_tos_html',
        get_string('custom_tos_html', 'local_airpay_users'),
        get_string('custom_tos_html_help', 'local_airpay_users'),
        ''));

    $ADMIN->add('localplugins', $settings);
}
