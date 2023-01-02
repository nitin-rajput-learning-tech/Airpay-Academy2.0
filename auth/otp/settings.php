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
 * Admin settings and defaults
 *
 * @package auth_otp
 * @copyright  2022 Sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext('auth_otp/otpserviceip', get_string('auth_otpserviceip', 'auth_otp'),
            '','', PARAM_RAW));
    $settings->add(new admin_setting_configtext('auth_otp/apikey', get_string('auth_apikey', 'auth_otp'),
            '','', PARAM_RAW));
    $settings->add(new admin_setting_configtext('auth_otp/ipusername', get_string('auth_ipusername', 'auth_otp'),
            '','', PARAM_RAW));
    $settings->add(new admin_setting_configtext('auth_otp/senderid', get_string('auth_senderid', 'auth_otp'),
            '','', PARAM_RAW));
    $settings->add(new admin_setting_configtext('auth_otp/entityid', get_string('auth_entityid', 'auth_otp'),
            '','', PARAM_RAW));
 	$settings->add(new admin_setting_configtext('auth_otp/templateid', get_string('auth_templateid', 'auth_otp'),'','', PARAM_RAW));

}
