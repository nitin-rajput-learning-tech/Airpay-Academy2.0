<?php
 
 // This file is part of eAbyas
 //
 // Copyright eAbyas Info Solutons Pvt Ltd, India
 //
 // This program is free software; you can redistribute it and/or modify
 // it under the terms of the GNU General Public License as published by
 // the Free Software Foundation; either version 3 of the License, or
 // (at your option) any later version.
 //
 // This program is distributed in the hope that it will be useful,
 // but WITHOUT ANY WARRANTY; without even the implied warranty of
 // MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 // GNU General Public License for more details.
 //
 // You should have received a copy of the GNU General Public License
 // along with this program.  If not, see <http://www.gnu.org/licenses/>.

  /**
   * @author eabyas  <info@eabyas.in>
   * @package BizLMS
   * @subpackage local_users
   */

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');

global $CFG, $USER, $PAGE, $OUTPUT, $DB;
require_once($CFG->dirroot . '/local/users/lib.php');
$filtervalues = json_decode($_REQUEST['formdata']);
$categorycontext = (new \local_users\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
require_login();
$totalusers = manage_users_count($stable, $filtervalues);
$table = new html_table();
$table->id = "users";
$table->head[] = get_string('prefix', 'local_users');
$table->head[] = get_string('fullname', 'local_users');
$table->head[] = get_string('username', 'local_users');
$table->head[] = get_string('gender', 'local_users');
$table->head[] = get_string('employeeid', 'local_users');
$table->head[] = get_string('email', 'local_users');
$table->head[] = get_string('organization', 'local_users');
$table->head[] = get_string('department', 'local_users');
$table->head[] = get_string('commercialunit', 'local_users');
$table->head[] = get_string('commercialarea', 'local_users');
$table->head[] = get_string('phonenumber', 'local_users');
$table->head[] = get_string('designation', 'local_users');
$table->head[] = get_string('employmenttype', 'local_users');
$table->head[] = get_string('employmentstatus', 'local_users');
$table->head[] = get_string('region', 'local_users');
$table->head[] = get_string('branch', 'local_users');
$table->head[] = get_string('subbranch', 'local_users');
$table->head[] = get_string('grade', 'local_users');
$table->head[] = get_string('level', 'local_users');
$table->head[] = get_string('skilltype', 'local_users');
$table->head[] = get_string('supervisor', 'local_users');
$table->head[] = get_string('dateofbirth', 'local_users');
$table->head[] = get_string('joiningdate', 'local_users');
$table->head[] = get_string('lastaccess', 'local_users');
$stable = new \stdClass();
$stable->thead = false;
$stable->start = 0;
$stable->length = 0;
$userdata = manage_users_content($stable, $totalusers);
$data = [];
foreach ($userdata as $user) {
    $data[] = [$user['prefix'],$user['fullname'], $user['username'], $user['gender'], $user['empid'], $user['email'], $user['orgstring'],$user['deptstring'],$user['commercialunit'],$user['commercialarea'],
        $user['phno'], $user['designationstring'],
        $user['employmenttype'], $user['employmentstatus'], $user['region'], $user['branch'], $user['subbranch'],$user['grade'], $user['level'], $user['skilltype'],
            $user['supervisiorstring'],$user['dateofbirth'], $user['dateofjoining'], $user['lastaccess']];
}
$table->id = "users";
$table->data = $data;
 require_once($CFG->libdir . '/csvlib.class.php');
    $matrix = array();
    $filename = 'users';
if (!empty($table->head)) {
        $countcols = count($table->head);
        $keys = array_keys($table->head);
        $lastkey = end($keys);
    foreach ($table->head as $key => $heading) {
            $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
    }
}
if (!empty($table->data)) {
    foreach ($table->data as $rkey => $row) {
        foreach ($row as $key => $item) {
                $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
        }
    }
}
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
foreach ($matrix as $ri => $col) {
        $csvexport->add_data($col);
}
    $csvexport->download_file();
    exit;

