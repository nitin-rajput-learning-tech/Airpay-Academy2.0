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

$categorycontext = (new \local_users\lib\accesslib())::get_module_context();
if (!(has_capability('local/users:manage', $categorycontext) && has_capability('local/users:create', $categorycontext))) {
    echo print_error('no permission');
}
if ($format) {
    $fields = array(
    'organization_code' => 'organization_code',
    'username' => 'username',
    'password' => 'password',
    'learner_id' => 'learner_id',
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'email' => 'email',
    'country_code' => 'country_code',
    'commercial_unit_code' => 'commercial_unit_code',
    'commercial_area_code' => 'commercial_area_code',
    'territory_code' => 'territory_code',    
    'reportingmanager_empid' => 'reportingmanager_empid',
    'language' => 'language',
    'designation' => 'designation',
    'state' => 'state',
    'district' => 'district',
    'subdistrict' => 'subdistrict',
    'village' => 'village',
    'contactno' => 'contactno',   
    'learner_status' => 'learner_status',
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
