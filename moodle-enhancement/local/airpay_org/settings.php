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
 * Admin settings — Airpay Organization Engine.
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_org',
        get_string('pluginname', 'local_airpay_org'));

    $settings->add(new admin_setting_heading('local_airpay_org/heading',
        get_string('settings_heading', 'local_airpay_org'),
        get_string('settings_heading_desc', 'local_airpay_org')));

    $settings->add(new admin_setting_configtext('local_airpay_org/public_tenant_id',
        get_string('public_tenant_id', 'local_airpay_org'),
        get_string('public_tenant_id_desc', 'local_airpay_org'),
        '', PARAM_INT));

    $ADMIN->add('localplugins', $settings);
}
