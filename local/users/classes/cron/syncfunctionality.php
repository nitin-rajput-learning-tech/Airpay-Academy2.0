<?php

namespace local_users\cron;
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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_users
 */

defined('MOODLE_INTERNAL') || die;


require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');

use costcenter;
use core_text;
use core_user;
use DateTime;
use html_writer;
use stdClass;

define('MANUAL_ENROLL', 1);
define('LDAP_ENROLL', 2);
define('SAML2', 3);
define('ADWEBSERVICE', 4);
define('ADD_UPDATE', 3);
class syncfunctionality
{
    private $data;
    private $errors = array();
    private $mfields = array();
    private $warnings = array();
    private $wmfields = array();
    private $errorcount = 0;
    private $warningscount = 0;
    private $updatesupervisor_warningscount = 0;
    private $errormessage;
    private $insertedcount = 0;
    private $updatedcount = 0;
    private $formdata;
    private $existing_user;
    private $username_exist;
    private $employeeid_exist;
    private $mobileno;

    public function __construct($data = null)
    {
        global $CFG;
        $this->data = $data;
        $this->timezones = \core_date::get_list_of_timezones($CFG->forcetimezone);
    } // end of constructor
    public function main_hrms_frontendform_method($cir, $filecolumns, $formdata)
    {
        global $DB, $USER, $CFG;
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        $inserted = 0;
        $updated = 0;
        $linenum = 1;
        $this->organizations = $this->get_organizations();
        $corecomponent = new \core_component();
        $pluginexists = $corecomponent::get_plugin_directory('local', 'domains');
        $positionpluginexists = $corecomponent::get_plugin_directory('local', 'positions');
        if ($pluginexists) {
            $this->domainlist = $this->get_domainlist();
        }
        if ($positionpluginexists) {
            $this->positionlist = $this->get_positionlist();
        }
        $this->allusers = $this->get_allusers();
        $categorylib = new \local_courses\catslib();
        while ($line = $cir->next()) {
            $linenum++;
            $user = new \stdClass();
            foreach ($line as $keynum => $value) {
                if (!isset($filecolumns[$keynum])) {
                    continue;
                }
                $key = $filecolumns[$keynum];
                $user->$key = trim($value);
            }
            $this->data[] = $user;
            $this->errors = array();
            $this->warnings = array();
            $this->mfields = array();
            $this->wmfields = array();
            $this->excel_line_number = $linenum;
            $mandatory_fields = [
                'first_name', 'last_name', 'username', 'email', 'organization', 'employee_id', 'department',
                'employee_status', 'location'
            ];

            foreach ($mandatory_fields as $field) {
                // Mandatory field validation.
                $this->mandatory_field_validation($user, $field);
            }
           
            // To check for existing user record.
            $sql = "SELECT u.id,u.username,u.open_costcenterid, u.email FROM {user} u WHERE (u.open_employeeid = :open_employeeid) AND u.deleted = 0";
            $params = array();
            $params['username'] = $user->username;
            $params['open_employeeid'] = $user->employee_id;
            $existing_user = $DB->get_records_sql($sql, $params);
            if (count($existing_user) == 1) {
                $this->existing_user = array_values($existing_user)[0];
            } else if (count($existing_user) > 1) {
                $this->errors[] = get_string('multiple_user', 'local_users');
            } else {
                $this->existing_user = null;
            }
            // To hold costcenterid.
            $this->costcenterid = $this->get_org_hierarchyid($user->organization, $parent = 0);
            // To hold departmentid.
            $this->departmentid = $this->get_org_hierarchyid($user->department, $parent = $this->costcenterid);
            if ($user->subdepartment) {
                $this->level2_departmentid = $this->get_subdepartmentid($user->subdepartment, $this->departmentid);
            }
            if (!empty($user->organization)) {

                $this->categoryvalidations($user);
            }
            if (!empty($user->country)) {
                $this->countryvalidations($user);
            }
            if (!empty($user->timezone)) {
                $this->timezonevalidations($user);
            }
            // Validation for employee status.
            $this->employee_status_validation($user);
            //validation for mobile number
            if (!empty($user->mobileno)) {
                $this->mobilenumber_validation($user);
            }
            if (!empty($user->email)) {
                $this->emailid_validation($user);
            }
            if (!empty($user->employee_id)) {
                $this->empid_validation($user);
            }
            if (!empty($user->force_password_change)) {
                $this->force_password_change_validation($user);
            }
            if (!empty($user->password) && !check_password_policy($user->password, $errmsg)) {
                $strings = new stdClass;
                $strings->errormessage = $errmsg;
                $strings->linenumber = $this->excel_line_number;
                $this->errors[] = get_string('password_upload_error', 'local_users', $strings);
                echo '<div class=local_users_sync_error>' . get_string('password_upload_error', 'local_users', $strings) . '</div>';
                $this->errorcount++;
            }
            $userobject = $this->preparing_users_object($user, $formdata);
            // To display error messages.
            if (count($this->errors) > 0) {
                $this->write_error_in_db($user);
            } else {
                if (is_null($this->existing_user)) {
                    $this->add_row($userobject, $formdata);
                } else {
                    $this->update_row($user, $userobject, $formdata);
                }
            }
            if (count($this->warnings) > 0) {
                $this->write_warnings_db($user);
                $this->updatesupervisor_warningscount = count($this->warnings);
            }
        }
        
        if ($this->data) {
            $upload_info = '<div class="critera_error1"><h3 style="text-decoration: underline;">'
                . get_string('empfile_syncstatus', 'local_users') . '</h3>';
            $upload_info .= '<div class=local_users_sync_success>' . get_string(
                'addedusers_msg',
                'local_users',
                $this->insertedcount
            ) . '</div>';
            $upload_info .= '<div class=local_users_sync_success>' . get_string(
                'updatedusers_msg',
                'local_users',
                $this->updatedcount
            ) . '</div>';
            $upload_info .= '<div class=local_users_sync_error>' . get_string(
                'errorscount_msg',
                'local_users',
                $this->errorcount
            ) . '</div>
            </div>';
            $upload_info .= '<div class=local_users_sync_warning>' . get_string(
                'warningscount_msg',
                'local_users',
                $this->warningscount
            ) . '</div>';
            $upload_info .= '<div class=local_users_sync_warning>' . get_string(
                'superwarnings_msg',
                'local_users',
                $this->updatesupervisor_warningscount
            ) . '</div>';
            $button = html_writer::tag('button', get_string('button', 'local_users'), array('class' => 'btn btn-primary'));
            $link = html_writer::tag('a', $button, array('href' => $CFG->wwwroot . '/local/users/index.php'));
            $upload_info .= '<div class="w-full pull-left text-xs-center">' . $link . '</div>';
            mtrace($upload_info);
            $sync_data = new \stdClass();
            $sync_data->newuserscount = $this->insertedcount;
            $sync_data->updateduserscount = $this->updatedcount;
            $sync_data->errorscount = $this->errorcount;
            $sync_data->warningscount = $this->warningscount;
            $sync_data->supervisorwarningscount = $this->updatesupervisor_warningscount;
            $sync_data->usercreated = $USER->id;
            $sync_data->usermodified = $USER->id;
            $sync_data->timecreated = time();
            $sync_data->timemodified = time();
            $sync_data->costcenterid = $USER->open_costcenterid;
            $insert_sync_data = $DB->insert_record('local_userssyncdata', $sync_data);
        } else {
            echo '<div class="critera_error">' . get_string('filenotavailable', 'local_users') . '</div>';
        }
    } //end of main_hrms_frontendform_method

    public function get_organizations()
    {
        global $DB;
        $sql = "SELECT shortname, id, parentid FROM {local_costcenter}";
        $costcenterslist = $DB->get_records_sql($sql);
        return $costcenterslist;
    }

    public function categoryvalidations($excel)
    {
        global $DB, $USER;
        $strings = new stdClass;
        $strings->org = $excel->organization;
        $strings->dept = $excel->department;
        $strings->subdept = $excel->subdepartment;
        $strings->employee_id = $excel->employee_id;
        $strings->excel_line_number = $this->excel_line_number;
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        $orgerror = 0;
        $categorylib = new \local_courses\catslib();
        if (!is_siteadmin()) {
            $orgcostcenterid = $DB->get_field('local_costcenter', 'id', array('shortname' => $excel->organization));           
            if ($orgcostcenterid !== $USER->open_costcenterid) {
                echo '<div class=local_users_sync_error>' . get_string('orgcheckwithdhoh', 'local_users', $strings) . '</div>';
                $this->errors[] = get_string('orgcheckwithdhoh', 'local_users', $strings);
                $this->mfields[] = 'usercategory';
                $this->errorcount++;
                $orgerror = 1;
            }

        }
        if ($orgerror == 0) {
            if (isset($excel->department) && empty($excel->subdepartment)) {
                $orgcostcenterid = $DB->get_field('local_costcenter', 'id', array('shortname' => $excel->organization));
                $categories = $categorylib->get_categories($orgcostcenterid);
                $departmentcategory = $DB->get_field('local_costcenter', 'category', array('shortname' => $excel->department));
                $departmentcostcenterid = $DB->get_field('local_costcenter', 'id', array('shortname' => $excel->department));
                       
            if ($departmentcostcenterid !== $USER->open_departmentid && !empty($USER->open_departmentid) && !has_capability('local/costcenter:manage_ownorganization', $categorycontext)) {
                echo '<div class=local_users_sync_error>' . get_string('departmentcheckwithdh', 'local_users', $strings) . '</div>';
                $this->errors[] = get_string('departmentcheckwithdh', 'local_users', $strings);
                $this->mfields[] = 'usercategory';
                $this->errorcount++;
            } else
                if (!in_array($departmentcategory, $categories)) {
                    echo '<div class=local_users_sync_error>' . get_string('deptcheckwithorg', 'local_users', $strings) . '</div>';
                    $this->errors[] = get_string('deptcheckwithorg', 'local_users', $strings);
                    $this->mfields[] = 'usercategory';
                    $this->errorcount++;
                }
            } else if (isset($excel->department) && !empty($excel->subdepartment)) {
                $departmentcostcenterid = $DB->get_field('local_costcenter', 'id', array('shortname' => $excel->department));
                $subdepartmentcategory = $DB->get_field('local_costcenter', 'category', array('shortname' => $excel->subdepartment));
                $categories = $categorylib->get_categories($departmentcostcenterid);
                if (!in_array($subdepartmentcategory, $categories)) {
                    echo '<div class=local_users_sync_error>' . get_string('subdeptcheckwithdept', 'local_users', $strings) . '</div>';
                    $this->errors[] = get_string('subdeptcheckwithdept', 'local_users', $strings);
                    $this->mfields[] = 'usercategory';
                    $this->errorcount++;
                }
            }
        }
    }

    public function countryvalidations($excel)
    {
        $strings = new stdClass;
        $strings->employee_id = $excel->employee_id;
        $strings->excel_line_number = $this->excel_line_number;
        $country = get_string_manager()->get_list_of_countries();
        if (!array_key_exists($excel->country, $country)) {
            echo '<div class=local_users_sync_error>' . get_string('invalidcountrycode', 'local_users', $strings) . '</div>';
            $this->errors[] = get_string('invalidcountrycode', 'local_users', $strings);
            $this->mfields[] = 'usercategory';
            $this->errorcount++;
        }
    }
    public function timezonevalidations($excel)
    {
        $strings = new stdClass;
        $strings->employee_id = $excel->employee_id;
        $strings->excel_line_number = $this->excel_line_number;
        if (!array_key_exists($excel->timezone, $this->timezones)) {
            echo '<div class=local_users_sync_error>' . get_string('invalidtimezone', 'local_users', $strings) . '</div>';
            $this->errors[] = get_string('invalidtimezone', 'local_users', $strings);
            $this->mfields[] = 'usercategory';
            $this->errorcount++;
        }
    }
    public function get_org_hierarchyid($fieldvalue, $parent)
    {
        global $DB;
        $datalist = $this->organizations;
        $datal = $datalist[$fieldvalue];
        if ($datal) {
            if ($parent == $datal->parentid) {
                return $datal->id;
            }
        } else {
            $strings = new stdClass;
            if ($parent == 0) {
                $identifier = 'organization';
                $strings->orgid = $fieldvalue;
            } else {
                $identifier = 'department';
                $strings->orgid = $fieldvalue;
            }
            $strings->identifier = $identifier;
            $strings->line = $this->excel_line_number;
            echo '<div class=local_users_sync_error>' . get_string('noorganizationidfound', 'local_users', $strings) . '</div>';
            $this->errors[] = get_string('noorganizationidfound', 'local_users', $strings);
            $this->mfields[] = $fieldvalue;
            $this->errorcount++;
        }
    } //end of get_org_hierarchyid method

    public function mandatory_field_validation($user, $field)
    {
        //validation for mandatory missing fields
        if (empty(trim($user->$field))) {
            $strings = new stdClass;
            $strings->field = $field;
            $strings->linenumber = $this->excel_line_number;
            echo '<div class=local_users_sync_error>' . get_string('missing', 'local_users', $strings) . '</div>';
            $this->errors[] = get_string('missing', 'local_users', $strings);
            $this->mfields[] = $field;
            $this->errorcount++;
        }
    } //end of mandatory_field_validation
    public function employee_status_validation($excel)
    {
        //validation for employee status
        $strings = new stdClass;
        $strings->employee_id = $excel->employee_id;
        $strings->excel_line_number = $this->excel_line_number;
        $employee_status = $excel->employee_status;
        $this->deletestatus = 0;
        if (array_key_exists('employee_status', $excel)) {
            if (strtolower($excel->employee_status) == 'active') {
                $this->activestatus = 0;
            } else if (strtolower($excel->employee_status) == 'inactive') {
                $this->activestatus = 1;
            } else if (strtolower($excel->employee_status) == 'delete') {
                $this->deletestatus = 1;
            } else {
                $strings = new stdClass;
                $strings->line = $this->excel_line_number;
                echo '<div class=local_users_sync_error>' . get_string('statusvalidation', 'local_users', $strings) . '</div>';
                $this->errors[] = get_string('statusvalidation', 'local_users', $strings);
                $this->mfields[] = $excel->employee_status;
                $this->errorcount++;
            }
        } else {
            echo '<div class=local_users_sync_error>Error in arrangement of columns in uploaded excelsheet at line
             ' . $this->excel_line_number . '</div>';
            $this->errormessage = get_string('columnsarragement_error', 'local_users', $excel);
            $this->errorcount++;
        }
    } // end of  employee_status_validation method

    public function empid_validation($excel)
    {
        global $DB;
        $strings = new stdClass();
        $strings->employee_id = $excel->employee_id;
        $strings->excel_line_number = $this->excel_line_number;
        $this->employee_id = $excel->employee_id;

        if (preg_match('/[^a-z0-9 ]+/i', $excel->employee_id)) {
            echo '<div class="local_users_sync_error">' . get_string(
                'employeeid_nospecialcharacters',
                'local_users',
                $strings
            ) . '</div>';
            $this->errors[] = get_string('employeeid_nospecialcharacters', 'local_users', $strings);
            $this->mfields[] = "useremployeeid";
            $this->errorcount++;
        }
        // echo $userid."=>userid";die;
        // if ($user = $DB->record_exists('user', array('open_employeeid' => $excel->employee_id))) {
        //     if ($user = $DB->get_record('user', array('open_employeeid' => $excel->employee_id, 'open_costcenterid' =>
        //     $this->costcenterid))) {
        //         if ($user->open_costcenterid == $this->costcenterid) {
        //             if (!isset($userid) || $user->id != $userid) {
        //                 echo '<div class="local_users_sync_error">' . get_string(
        //                     'employeeid_alreadyexists',
        //                     'local_users',
        //                     $strings
        //                 ) . '</div>';
        //                 $this->errors[] = get_string('employeeid_alreadyexists', 'local_users', $strings);
        //                 $this->mfields[] = "useremployeeid";
        //                 $this->errorcount++;
        //             }
        //         }
        //     }
        // }
    }

    private function write_error_in_db($excel)
    {
        global $DB, $USER;
        //condition to hold the sync errors
        $syncerrors = new \stdclass();
        $today = \local_costcenter\lib::get_userdate('Y-m-d');
        $syncerrors->date_created = time();
        $errors_list = implode(',', $this->errors);
        $mandatory_list = implode(',', $this->mfields);
        $syncerrors->error = $errors_list;
        $syncerrors->modified_by = $USER->id;
        $syncerrors->mandatory_fields = $mandatory_list;
        if (empty($excel->email)) {
            $syncerrors->email = '-';
        } else {
            $syncerrors->email = $excel->email;
        }
        if (empty($excel->employee_id)) {
            $syncerrors->idnumber = '-';
        } else {
            $syncerrors->idnumber = $excel->employee_id;
        }
        $syncerrors->firstname = $excel->first_name;
        $syncerrors->lastname = $excel->first_name;
        $syncerrors->sync_file_name = "Employee";
        $DB->insert_record('local_syncerrors', $syncerrors);
    } // end of write_error_db method

    public function get_super_userid($reportinguserid, $orgid)
    {
        $userslist = $this->allusers;
        $user = $userslist[$reportinguserid];
        if ($user) {
            if ($orgid == $user->open_costcenterid) {
                return $user->id;
            }
        } else {
            $strings = new \stdClass();
            $strings->empid = $reportinguserid;
            $strings->line = $this->excel_line_number;
            $warningmessage = get_string('nosupervisorempidfound', 'local_users', $strings);
            $this->errormessage = $warningmessage;
            echo '<div class=local_users_sync_warning>' . $warningmessage . '</div>';
            $this->warningscount++;
        }

    }

    public function get_subdepartmentid($subdepartmentid, $parentid)
    {
        global $DB;
        $datalist = $this->organizations;
        $datal = $datalist[$subdepartmentid];
        if ($datal) {
            if ($parentid == $datal->parentid) {
                return $datal->id;
            }
        } else {
            $strings = new \stdClass();
            $strings->subdepartmentid = $subdepartmentid;
            $strings->line = $this->excel_line_number;
            $warningmessage = get_string('noorsubdepartmentfound', 'local_users', $strings);
            $this->errormessage = $warningmessage;
            echo '<div class=local_users_sync_warning>' . $warningmessage . '</div>';
            $this->warningscount++;
        }
    }

    public function preparing_users_object($excel, $formdata = null)
    {
        global $USER, $DB, $CFG;
        $user = new \stdclass();
        $user->auth = "manual"; //by default accepts manual
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->suspended = $this->activestatus;
        $user->idnumber = $excel->employee_id;
        $user->open_employeeid = $excel->employee_id;
        $user->username = strtolower($excel->username);
        $user->firstname = $excel->first_name;
        $user->lastname = $excel->last_name;
        $user->middlename = $excel->middle_name ? $excel->middle_name : ' ';
        $user->phone1 = $excel->mobileno ? $excel->mobileno : '';
        $user->email = strtolower($excel->email);
        $user->country = $excel->country ? $excel->country : 'IN';
        $user->open_group = $excel->discipline ? $excel->discipline : ' ';
        $user->employee_status = $excel->employee_status;
        $user->open_location = $excel->location ? $excel->location : ' ';
        $user->open_state = $excel->state_name ? $excel->state_name : ' ';
        $user->city = $excel->location ? $excel->location : ' ';
        $user->location = $user->location;
        $user->area = $excel->area ? $excel->area : ' ';
        $user->address = $excel->address ? $excel->address : ' ';
        $user->open_team = $excel->team ? $excel->team : null;
        $user->open_grade = $excel->grade ? $excel->grade : null;
        $user->open_level = $excel->discipline ? $excel->discipline : null;
        $user->open_designation = $excel->role_designation ? $excel->role_designation : '';
        $user->open_costcenterid = $this->costcenterid;
        $user->open_departmentid = $this->departmentid;
        $user->open_subdepartment = $this->level2_departmentid;
        $user->department = $excel->department;
        $user->timezone = in_array($excel->timezone, $this->timezones) ? $excel->timezone : $CFG->forcetimezone;
        if ($excel->reportingmanager_empid) {
            $super_user = $this->get_super_userid($excel->reportingmanager_empid, $user->open_costcenterid);
            $user->open_supervisorid = $super_user;
        }
        $user->open_hrmsrole = $excel->role;
        $user->institution = $excel->department;
        $user->usermodified = $USER->id;
        if (!empty(trim($excel->password))) {
            $user->password = hash_internal_user_password(trim($excel->password));
        } else {
            unset($user->password);
        }
        if ($this->deletestatus == 1) {
            $user->deleted = 0;
            $user->username = time() . $user->username;
            $user->email = time() . $user->email;
            $user->open_employeeid = time() . $user->open_employeeid;
        }
        $user->force_password_change = (empty($excel->force_password_change)) ? 0 : $excel->force_password_change;
        return $user;
    } // end of  preparing_users_object method

    public function add_row($userobject, $formdata)
    {
        global $DB, $USER, $CFG;
        $insertnewuserfromcsv = user_create_user($userobject, false);
        $userobject = (object)$userobject;
        $userobject->id = $insertnewuserfromcsv;
        $this->allusers[$userobject->open_employeeid] = $userobject;
        if ($userobject->force_password_change == 1) {
            set_user_preference('auth_forcepasswordchange', $userobject->force_password_change, $insertnewuserfromcsv);
        }
        if ($formdata->createpassword) {
            $usernew = $DB->get_record('user', array('id' => $insertnewuserfromcsv));
            setnew_password_and_mail($usernew);
            unset_user_preference('create_password', $usernew);
            set_user_preference('auth_forcepasswordchange', 1, $usernew);
        }
        $this->insertedcount++;
    } // end of add_row method

    public function update_row($excel, $user, $formdata)
    {
        global $USER, $DB, $CFG;
        // Condition to get the userid to update the data.
        $userid = $this->existing_user->id;
        if ($userid) {
            $user->id = $userid;
            $user->timemodified = time();
            $user->suspended = $this->activestatus;
            $user->idnumber = $excel->employee_id;
            if (isset($user->open_costcenterid)) {
                $existingcostcenter = $this->existing_user->open_costcenterid;
                if ($user->open_costcenterid != $existingcostcenter) {
                    \core\session\manager::kill_user_sessions($user->id);
                }
            }
            $user->open_departmentid = $this->departmentid;
            $user->open_subdepartment = $this->level2_departmentid;
            $user->department = $excel->department;
            $user->open_hrmsrole = $excel->role;
            $user->institution = $excel->department;
            $user->phone1 = $excel->mobileno ? $excel->mobileno : '';
            $user->open_state = $excel->state_name;
            $user->open_designation = $excel->role_designation;
            $user->usermodified = $USER->id;
            $user->open_group = $excel->discipline;
            $user->open_client = $excel->client;
            $user->open_team = $excel->team;
            $user->open_grade = $excel->grade ? $excel->grade : null;
            $user->timezone = in_array($excel->timezone, $this->timezones) ? $excel->timezone : $CFG->forcetimezone;
            if (!empty($excel->password)) {
                $user->password = hash_internal_user_password($excel->password);
            } else {
                unset($user->password);
            }
            if ($this->deletestatus == 1) {
                $user->deleted = 0;
                $user->username = time() . $user->username;
                $user->email = time() . $user->email;
                $user->open_employeeid = time() . $user->open_employeeid;
            }
            user_update_user($user, false);
            if ($formdata->createpassword) {
                $usernew = $DB->get_record('user', array('id' => $user->id));
                setnew_password_and_mail($usernew);
                unset_user_preference('create_password', $usernew);
                set_user_preference('auth_forcepasswordchange', 1, $usernew);
            }
            if ($user->force_password_change == 1) {
                set_user_preference('auth_forcepasswordchange', $user->force_password_change, $user->id);
            }
            $this->updatedcount++;
        }
    } // end of  update_row method

    public function write_warnings_db($excel)
    {
        global $DB, $USER;
        if (!empty($this->warnings) && !empty($this->wmfields)) {
            $syncwarnings = new \stdclass();
            $today = \local_costcenter\lib::get_userdate('Y-m-d');
            $syncwarnings->date_created = strtotime($today);
            $werrors_list = implode(',', $this->warnings);
            $wmandatory_list = implode(',', $this->wmfields);
            $syncwarnings->error = $werrors_list;
            $syncwarnings->modified_by = $USER->id;
            $syncwarnings->mandatory_fields = $wmandatory_list;
            if (empty($excel->email)) {
                $syncwarnings->email = '-';
            } else {
                $syncwarnings->email = $excel->email;
            }
            if (empty($excel->employee_id)) {
                $syncwarnings->idnumber = '-';
            } else {
                $syncwarnings->idnumber = $excel->employee_id;
            }
            $syncwarnings->firstname = $excel->first_name;
            $syncwarnings->lastname = $excel->last_name;
            $syncwarnings->type = 'Warning';
            $DB->insert_record('local_syncerrors', $syncwarnings);
        }
    } // end of write_warning_db method

    public function mobilenumber_validation($excel)
    {
        $strings = new StdClass();
        $strings->employee_id = $excel->employee_id;
        $strings->excel_line_number = $this->excel_line_number;
        $this->mobileno = $excel->mobileno;
        if (!is_numeric($this->mobileno)) {
            echo '<div class=local_users_sync_error>' . get_string('mobileno_error', 'local_users', $strings) . '</div>';
            $this->errors[] = get_string('mobileno_error', 'local_users', $strings);
            $this->mfields[] = 'mobileno';
            $this->errorcount++;
        } else if (($this->mobileno < 999999999 || $this->mobileno > 10000000000)) {
            echo '<div class=local_users_sync_error>' . get_string('validmobileno_error', 'local_users', $strings) . '</div>';
            $this->errors[] = get_string('validmobileno_error', 'local_users', $strings);
            $this->mfields[] = 'mobileno';
            $this->errorcount++;
        }
    } //end of mobilenumber_validation method

    public function emailid_validation($excel)
    {
        global $DB;
        $strings = new StdClass();
        $strings->employee_id = $excel->employee_id;
        $strings->excel_line_number = $this->excel_line_number;
        $this->email = $excel->email;
        if (!validate_email($excel->email)) {
            echo '<div class="local_users_sync_error">' . get_string('invalidemail_msg', 'local_users', $strings) . '</div>';
            $this->errors[] = get_string('invalidemail_msg', 'local_users', $strings);
            $this->mfields[] = 'email';
            $this->errorcount++;
        }
    }

    /**
     * [force_password_change_validation description]
     * @param  [type] $excel [description]
     */
    private function force_password_change_validation($excel)
    {
        $this->force_password_change = $excel->force_password_change;
        if (!is_numeric($this->force_password_change) || !(($this->force_password_change == 1) ||
            ($this->force_password_change == 0))) {
            echo '<div class=local_users_sync_error>force_password_change column should have value as 0 or 1 at line
             ' . $this->excel_line_number . '</div>';
            $this->errors[] = 'force_password_change column should value as 0 or 1 at line ' . $this->excel_line_number . '';
            $this->mfields[] = 'force_password_change';
            $this->errorcount++;
        }
    }
    public function get_domainlist()
    {
        global $DB;
        $sql = " SELECT code, id, costcenter FROM {local_domains}";
        $domainlist = $DB->get_records_sql($sql);
        return $domainlist;
    }

    public function get_positionlist()
    {
        global $DB;
        $sql = " SELECT code, id, domain FROM {local_positions}";
        $positionlist = $DB->get_records_sql($sql);
        return $positionlist;
    }
    public function get_domainid($costcenterid, $domain)
    {
        $domainlist = $this->domainlist;
        $datal = $domainlist[$domain];
        if ($datal) {
            if ($costcenterid == $datal->costcenter) {
                return $datal->id;
            }
        } else {
            $strings = new \stdClass();
            $strings->domainid = $domain;
            $strings->orgid = $this->costcenter_shortname;
            $strings->line = $this->excel_line_number;
            $warningmessage = get_string('nodomainfound', 'local_users', $strings);
            $this->errormessage = $warningmessage;
            echo '<div class=local_users_sync_warning>' . $warningmessage . '</div>';
            $this->warningscount++;
        }
    }
    public function get_positionid($domainid, $positiond)
    {
        $positionlist = $this->positionlist;
        $data = $positionlist[$positiond];
        if ($data) {
            if ($domainid == $data->domain) {
                return $data->id;
            }
        } else {
            $strings = new \stdClass();
            $strings->positiond = $positiond;
            $strings->line = $this->excel_line_number;
            $warningmessage = get_string('nopositionfound', 'local_users', $strings);
            $this->errormessage = $warningmessage;
            echo '<div class=local_users_sync_warning>' . $warningmessage . '</div>';
            $this->warningscount++;
        }
    }

    public function get_allusers()
    {
        global $DB;
        $usersql = " SELECT open_employeeid, open_costcenterid, id FROM {user}";
        $users = $DB->get_records_sql($usersql);
        return $users;
    }

} //end of class
