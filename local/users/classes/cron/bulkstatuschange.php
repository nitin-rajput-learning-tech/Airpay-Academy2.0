<?php
// This file is part of BizLMS
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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_users
 * @copyright   2024 Moodle India Information Solutions Pvt Ltd
 * @author      2024 Moodle IN
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_users\cron;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/admin/tool/uploaduser/locallib.php');

use html_writer;
use stdClass;

define('MANUAL_ENROLL', 1);
define('LDAP_ENROLL', 2);
define('SAML2', 3);
define('ADWEBSERVICE', 4);
define('ADD_UPDATE', 3);

/**
 * bulkstatuschange.
 *
 * @package   local_users
 * @copyright 2024 Moodle India Information Solutions Pvt Ltd
 * @author    2024 Moodle IN
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulkstatuschange {
    private $errors = [];
    /** @var array */
    private $warnings = [];
    /** @var int */
    private $errorcount = 0;
    /** @var int */
    private $warningscount = 0;
    /** @var int */
    private $updatesupervisorwarningscount = 0;
    /** @var int */
    private $insertedcount = 0;
    /** @var int */
    private $updatedcount = 0;
    /** @var array */
    private $displayerror = null;

    /**
     * process constructor.
     *
     * @param object
     * @throws \coding_exception
     */
    public function __construct($data = null) {
        global $CFG;
        $this->data = $data;
        $this->timezones = \core_date::get_list_of_timezones($CFG->forcetimezone);
    }
    /**
     * Front end form.
     *
     * @param  $cir csv_import_reader Object
     * @param  $filecolumns colums fields in csv form
     * @param array $formdata data in the csv
     **/
    public function bulk_statuschange_method($cir, $filecolumns, $formdata) {
        global $DB, $USER, $CFG;
        $linenum = 1;
        $corecomponent = new \core_component();
        $mandatoryfields = [
            'employee_code',
            'status'
        ];
        $this->mandatoryfieldcount = 0;
        while ($line = $cir->next()) {
            $this->orgcount = 0;
            $linenum++;
            $user = new stdClass();
            foreach ($line as $keynum => $value) {
                if (!isset($filecolumns[$keynum])) {
                    continue;
                }
                $key = $filecolumns[$keynum];
                $user->$key = trim($value);
            }
            $this->data[] = $user;
            $this->errors = [];
            $this->warnings = [];
            $this->mfields = [];
            $this->excellinenumber = $linenum;
            $this->displayerror .= $this->employee_status_validation($user);
            $this->displayerror .= $this->empid_validation($user);

            $userobject = $this->preparing_users_object($user, $formdata);
            // To display error messages.
            if (count($this->errors) == 0) {
                $this->update_row($user, $userobject, $formdata);
            }
            if (count($this->warnings) > 0) {
                $this->updatesupervisorwarningscount = count($this->warnings);
            }
        }
        if (empty($line = $cir->next())) {
            if ($this->mandatoryfieldcount == 0) {
                foreach ($mandatoryfields as $field) {
                    // Mandatory field validation.
                    $this->displayerror .= $this->mandatory_field_validation($user, $field);
                }
            }
        }
        $uploadinfo = '<div class="critera_error1"><h3 style="text-decoration: underline;">'
            . get_string('bulkusersstatuschange', 'local_users') . '</h3>';
        $uploadinfo .= $this->displayerror;
        $uploadinfo .= '<div class=local_users_sync_success>' . get_string(
            'updatedusers_msg',
            'local_users',
            $this->updatedcount
        ) . '</div>';

        $button = html_writer::tag('button', get_string('button', 'local_users'), ['class' => 'btn btn-primary']);
        $link = html_writer::tag('a', $button, ['href' => $CFG->wwwroot . '/local/users/index.php']);
        $uploadinfo .= '<div class="w-full pull-left text-xs-center">' . $link . '</div>';
        mtrace($uploadinfo);
    } //end of main_hrms_frontendform_method

    /**
     * Status validation in excel row.
     * @param  $excel having excel row data
     * 
     **/
    public function employee_status_validation($excel) {
        global $DB;
        $strings = new stdClass;
        $strings->learner_id = $excel->employee_code;
        $strings->line = $this->excellinenumber;
        $strings->status = $excel->status;

        $userdelete = $DB->get_field('user', 'deleted', ['open_employeeid' => $excel->employee_code]);

        if ($userdelete) {
            $returndata = "<div class='local_users_sync_error'>"
                    .get_string('useralreadydeleted', 'local_users', $strings).
                "</div>";
            $this->errors[] = get_string('useralreadydeleted', 'local_users', $strings);
            $this->mfields[] = "status";
            $this->errorcount++;
        } elseif (!in_array(strtolower($excel->status), ['active', 'inactive', 'delete'])) {
            $returndata = "<div class='local_users_sync_error'>"
                            .get_string('invalidstatus', 'local_users', $strings).
                          "</div>";
            $this->errors[] = get_string('invalidstatus', 'local_users', $strings);
            $this->mfields[] = "status";
            $this->errorcount++;
        }
        return $returndata;
    } // end of  employee_status_validation method

    /**
     * EmployeeID validation in excel row.
     * @param  $excel having excel row data
     * 
     **/
    public function empid_validation($excel) {
        global $DB;
        $strings = new stdClass();
        $strings->learner_id = $excel->employee_code;
        $strings->line = $this->excellinenumber;
        $this->learnerid = $excel->employee_code;

        $condition = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'open_path');


        $empidsql = "SELECT *
                       FROM {user} 
                       WHERE open_employeeid = :learnerid $condition";
        $params['learnerid'] = $strings->learner_id;
        $existsempid = $DB->get_record_sql($empidsql, $params);

        if (!$existsempid) {
            $returndata = '<div class="local_users_sync_error">' 
                    . get_string('employeeid_notexists', 'local_users', $strings) .
                 '</div>';
            $this->errors[] = get_string('employeeid_notexists', 'local_users', $strings);
            $this->mfields[] = "useremployeeid";
            $this->errorcount++;
        }
        return $returndata;
    }
    /**
     * User validation using employeecode and returns user data.
     * @param $excel having excel row data
     * @param $formdata having uploading form data
     * 
     **/
    public function preparing_users_object($excel, $formdata = null) {
        global $USER, $DB, $CFG;
        $user = new \stdclass();       
        $user->suspended = $excel->status;
        $user->open_employeeid = $excel->employee_code;
        $result = preg_grep("/profile_field_/", array_keys((array)$excel));
        if (count($result) > 0) {
            foreach ($result as $key => $val) {
                $user->$val = $excel->$val;
            }            
        }
        return $user;
    } // end of  preparing_users_object method
    /**
     * User validation using employeecode and returns user data.
     * @param $excel having excel row data
     * @param $user1 having uploading user data
     * @param $formdata having uploading form data
     * 
     **/
    public function update_row($excel, $user1, $formdata) {
        global $USER, $DB, $CFG;
        $userid = $DB->get_field('user', 'id', ['open_employeeid' => $excel->employee_code]);
        if ($userid) {
            $user = clone $user1;
            $user->id = $userid;
            $user->status = $excel->status;
            local_user_status_update($user);
            $this->updatedcount++;
        }
    } // end of  update_row method
    /**
     * User validation using employeecode and returns user data.
     * @param $user having uploading user data
     * @param $field having fieldname
     * 
     **/
    public function mandatory_field_validation($user, $field) {
        // Validation for mandatory missing fields.
        if (empty(trim($user->$field))) {
            $strings = new stdClass;
            $strings->field = $field;
            $strings->linenumber = $this->excellinenumber;
            $returndata =  '<div class=local_users_sync_error>'
                             .get_string('missing', 'local_users', $strings).
                            '</div>';
            $this->errors[] = get_string('missing', 'local_users', $strings);
            $this->mfields[] = $field;
            $this->orgcount++;
            $this->errorcount++;
        }
    } //end of mandatory_field_validation
} 
//end of class
