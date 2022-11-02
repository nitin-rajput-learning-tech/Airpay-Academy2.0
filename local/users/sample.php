<?php
/*
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_users
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
$format = optional_param('format', 'csv', PARAM_ALPHA);
$systemcontext = context_system::instance();
if (!(has_capability('local/users:manage', $systemcontext) && has_capability('local/users:create', $systemcontext))) {
    echo print_error('no permission');
}
if ($format) {
    $fields = array(
    'organization' => 'organization',
    'username' => 'username',
    'password' => 'password',
    'employee_id' => 'employee_id',
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'department' => 'department',
    'subdepartment' => 'subdepartment',
    'role_designation' => 'role_designation',
    'reportingmanager_empid' => 'reportingmanager_empid',
    'discipline' => 'discipline',
    'mobileno' => 'mobileno',
    'email' => 'email',
    'employee_status' => 'employee_status',
    'country' => 'country',
    'location' => 'location',
    'role' => 'role',
    'password' => 'password',
    'timezone' => 'timezone',
    'force_password_change' => 'force_password_change'
    );

    switch ($format) {
        case 'csv' : user_download_csv($fields);
    }
    die;
}

function user_download_csv($fields) {
    global $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
    $filename = clean_filename(get_string('users'));
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
    $csvexport->add_data($fields);
    $userprofiledata = array();
    $csvexport->add_data($userprofiledata);
    $csvexport->download_file();
    die;
}
