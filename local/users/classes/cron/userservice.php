<?php
//
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
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_users
 */
namespace local_users\cron;
class userservice {
    public $data = [];
    public $errors = [];
    public $warnings = [];

    private $insertedcount = 0;
    private $updatedcount = 0;
    public function create_user_object($servicedata) {
        $user = new \stdClass();
        $user->auth = 'oidc';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->password = 'not cached';
        $username = $servicedata['username'];
        $username = preg_replace(" /[�]/ ", "", $username);
        $user->username = strtolower(trim($username));
        $user->firstname = $servicedata['firstname'];
        $user->lastname = $servicedata['lastname'];
        $user->email = $servicedata['email'];
        $user->open_employeeid = $servicedata['open_employeeid'];
        $user->idnumber = $servicedata['open_employeeid'];
        $userids = $this->get_userid($user->username, $user->open_employeeid, $user->email, $user->idnumber);
        $error = false;
        if (count($userids) > 1) {
            $this->errors[$servicedata['open_employeeid']][] = "Multiple Users exist with same data in system cannot
             Create / Update";
            $error = true;
        } else if (count($userids) == 1) {
            $user->id = array_pop($userids);
        }
        $user->open_costcenterid = $this->get_costcenter_data($servicedata['costcentername']);
        if (!$user->open_costcenterid) {
            $this->errors[$servicedata['open_employeeid']][] = 'Company name '.$servicedata['costcentername'].
            ' doesn\'t exist in system.';
            $error = true;
        }
        $user->open_departmentid = $this->get_costcenter_data($servicedata['departmentname'], $user->open_costcenterid, 2);
        if (!$user->open_departmentid) {
            $this->errors[$servicedata['open_employeeid']][] = 'BU name '.$servicedata['departmentname'].
            ' doesn\'t exist under company '.$servicedata['costcentername'].' in system.';
            $error = true;
        }
        $user->open_subdepartment = $this->get_costcenter_data($servicedata['subdepartmentname'],
         $user->open_departmentid, 3);
        if (!$user->open_subdepartment) {
            $this->errors[$servicedata['open_employeeid']][] = 'Function name '.$servicedata['subdepartmentname'].
            ' doesn\'t exist under company '.$servicedata['costcentername'].' and BU  '.$servicedata['departmentname'].
            ' in system.';
            $error = true;
        }
        $user->open_subsubdepartment = $this->get_costcenter_data($servicedata['subsubdepartmentname'],
         $user->open_subdepartment, 4);
        if (!$user->open_subsubdepartment) {
            $this->errors[$servicedata['open_employeeid']][] = 'Sub Function name '.$servicedata['subsubdepartmentname'].
            ' doesn\'t exist  under company '.$servicedata['costcentername'].' and BU  '.$servicedata['departmentname'].
            ' and function '.$servicedata['subdepartmentname'].' in system.';
            $error = true;
        }
        if ($error) {
            return false;
        }
        $user->suspended = $servicedata['suspended'];
        $user->deleted = 0;
        $user->open_grade = $servicedata['open_grade'];
        $user->open_band = $servicedata['open_band'];
        $user->open_department = $servicedata['open_department'];
        $user->open_section = $servicedata['open_section'];
        $user->open_location = $servicedata['open_location'];
        $user->open_role = $servicedata['open_role'];
        $user->phone1 = $servicedata['phone1'];
        $user->open_reportingname = $this->get_reporting_reviewername($servicedata['open_supervisorempid'],
         $user->open_costcenterid);
        if (!$user->open_reportingname) {
            $this->warnings[$servicedata['open_employeeid']][] = 'Reporting manager with empid '.$servicedata['
            open_supervisorempid']. ' doesn\'t exist in system.';
        }
        $user->open_supervisorempid = $servicedata['open_supervisorempid'];
        $user->open_reviewername = $this->get_reporting_reviewername($servicedata['open_reviewerempid'],
         $user->open_costcenterid);
        return $user;
    }
    public function get_costcenter_data($costcentername, $parent = 0, $depth = 1) {
        global $DB;
        return $DB->get_field('local_costcenter', 'id', array('parentid' => $parent, 'depth' => $depth, 'fullname' =>
         $costcentername));
    }
    public function get_reporting_reviewername($empid, $costcenterid) {
        global $DB;
        return $DB->get_field('user', 'id', array('open_employeeid' => $empid, 'open_costcenterid' => $costcenterid));
    }
    public function get_userid($username, $open_employeeid, $email, $idnumber) {
        global $DB;
        return $DB->get_records_sql_menu("SELECT id, id as idval FROM {user} WHERE username LIKE :username
         OR open_employeeid LIKE :open_employeeid OR idnumber LIKE :idnumber ", array('username' => $username,
          'open_employeeid' => $open_employeeid, 'email' => $email, 'idnumber' => $idnumber));
    }
    public function write_error_db($userobject) {
        global $DB;
        $userobject = (object)$userobject;
        // write error message to db and inform admin
        $syncerrors = new \stdclass();
        $today = date('Y-m-d');
        $syncerrors->date_created = time();
        $errors_list = is_array($this->errors[$userobject->open_employeeid]) ? implode(',',
            $this->errors[$userobject->open_employeeid]) : $this->errors[$userobject->open_employeeid];
        $syncerrors->error = $errors_list;
        $syncerrors->modified_by = 2;
        if (empty($userobject->email)) {
            $syncerrors->email = '-';
        } else {
            $syncerrors->email = $userobject->email;
        }
        if (empty($userobject->open_employeeid)) {
            $syncerrors->idnumber = '-';
        } else {
            $syncerrors->idnumber = $userobject->open_employeeid;
        }
            $syncerrors->firstname = $userobject->firstname;
            $syncerrors->lastname = $userobject->lastname;
            $syncerrors->sync_file_name = "Service";
            $DB->insert_record('local_syncerrors', $syncerrors);
    } // end of write_error_db method
}
