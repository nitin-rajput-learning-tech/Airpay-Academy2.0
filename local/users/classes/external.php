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

/**
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 */

defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/lib.php");
use \core_calendar\local\event\container as event_container;
use \core_calendar\external\event_exporter;
use \core_calendar\external\events_related_objects_cache;
use \local_courses\local\general_lib as general_lib;
use \local_classroom\local\userdashboard_content as userdashboard_content;
use \local_learningplan\local\userdashboard_content as learningplan;
use \local_onlinetests\local\userdashboard_content as onlinetests;
use \local_evaluation\local\userdashboard_content as evaluation;

class local_users_external extends external_api {

        /**
         * Describes the parameters for submit_create_group_form webservice.
         * @return external_function_parameters
         */
    public static function submit_create_user_form_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'userid', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form,
                 encoded as a json array', false),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0)
            )
        );
    }

    /**
     * [submit_create_user_form description]
     * @param  [integer] $contextid
     * @param  [string] $jsonformdata
     * @return void
     */
    public function submit_create_user_form($id, $contextid, $jsonformdata, $form_status) {
        global $PAGE, $CFG, $DB;
        require_once($CFG->dirroot . '/local/users/lib.php');
        // We always must pass webservice params through validate_parameters.
        $context = context::instance_by_id($contextid, MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($jsonformdata);
        $data = array();
        parse_str($serialiseddata, $data);
        $warnings = array();
        $mform = new local_users\forms\create_user(null, array('form_status' => $form_status, 'id' => $data['id'],
         'org' => $data['open_costcenterid'], 'dept' => $data['department'], 'open_positionid' => $data['open_positionid'],
          'open_domainid' => $data['open_domainid']), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        $userlib = new local_users\functions\users();

        if ($validateddata) {
            if ($validateddata->id > 0) {
                if ($form_status == 0){
                    local_costcenter_get_costcenter_path($validateddata);
                    // print_r($validateddata);
                    set_user_preference('auth_forcepasswordchange', $validateddata->preference_auth_forcepasswordchange, $validateddata->id);

                }elseif ($form_status == 1){
                     local_users_get_userprofile_datafields($validateddata);
                }
                $uid = $userlib->update_existinguser($validateddata);
            } else {
                local_costcenter_get_costcenter_path($validateddata);
                $password = $validateddata->password;
                $uid = $userlib->insert_newuser($validateddata);
                set_user_preference('auth_forcepasswordchange', $validateddata->preference_auth_forcepasswordchange, $uid);
            }
            $formheaders = array_keys($mform->formstatus);
            $next = $form_status + 1;
            $nextform = array_key_exists($next, $formheaders);
            if ($nextform !== false) {
                $form_status = $next;
                $error = false;
            } else {
                $form_status = -1;
                $error = true;
            }
        } else {
            // Generate a warning.
            throw new moodle_exception(get_string('error_in_creation', 'local_users'));
        }
        $return = array(
            'id' => $uid,
            'form_status' => $form_status);
        return $return;
    }


    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_create_user_form_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'Userid'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }

    public function delete_user_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'userid', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false)
            )
        );
    }
    public function delete_user($id, $contextid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $id));
        if ($user) {
            $new_empid = $user->open_employeeid.'_'.time();
            $query = "UPDATE {user} SET open_employeeid= :employeeid
                    WHERE id= :id AND username= :username AND email = :email";
            $DB->execute($query, array('employeeid' => $new_empid,
                                        'id' => $user->id, 'username' => $user->username,
                                        'email' => $user->email));
            user_delete_user($user);
            $core_component = new \core_component();
            $localclassroom_plugin_exist = $core_component::get_plugin_directory('local', 'classroom');
            if (!empty($localclassroom_plugin_exist)) {
                if (method_exists(new \local_classroom\classroom(), 'delete_suspend_user_remove_classrooms')) {

                    (new \local_classroom\classroom)->delete_suspend_user_remove_classrooms($user->id);
                }
            }
            $return = true;
        } else {
            throw new moodle_exception(get_string('error_in_creation', 'local_users'));
            $return = false;
        }
        return $return;
    }
    public function delete_user_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }
    public function suspend_local_user_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'userid', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false)
            )
        );
    }
    public function suspend_local_user($id, $contextid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $id));
        if ($user) {
            if ($user->suspended) {
                $status = 0;
            } else {
                $status = 1;
            }
            $suspendedrecord = new \stdClass();
            $suspendedrecord->id = $user->id;
            $suspendedrecord->suspended = $status;
            $DB->update_record('user', $suspendedrecord);

            $core_component = new \core_component();
            $localclassroom_plugin_exist = $core_component::get_plugin_directory('local', 'classroom');
            if (!empty($localclassroom_plugin_exist)) {
                if (method_exists(new \local_classroom\classroom(), 'delete_suspend_user_remove_classrooms')) {

                        (new \local_classroom\classroom)->delete_suspend_user_remove_classrooms($user->id);
                }
            }
            $return = true;
        } else {
            throw new moodle_exception(get_string('error_in_inactivating', 'local_users'));
            $return = false;
        }
        return $return;
    }
    public function suspend_local_user_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }
    public function get_departments_list_parameters() {
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'Organization id', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the organization', false)
            )
        );
    }
    public function get_departments_list($costcenterid, $contextid) {
        $params = self::validate_parameters(self::get_departments_list_parameters(),
                                    ['costcenterid' => $costcenterid, 'contextid' => $contextid]);
        $context = \context::instance_by_id($params['contextid']);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        global $DB;
        $selectarray = array(0 => get_string('selectdepartment', 'local_users'));
        if ($params['costcenterid'] > 0) {
            try {
                $return = $selectarray + $DB->get_records_menu('local_costcenter', array('parentid' =>
                 $params['costcenterid']), '', 'id, fullname');
            } catch (Exception $e) {
                $return = $selectarray;
            }
        } else {
            $return = $selectarray;
        }
        return json_encode($return);
    }
    public function get_departments_list_returns() {
        return new external_value(PARAM_RAW, 'Data of departments');
    }
    public function get_supervisors_list_parameters() {
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'Organization id', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the organization', false)
            )
        );
    }
    public function get_supervisors_list($costcenterid, $contextid) {
        $params = self::validate_parameters(self::get_supervisors_list_parameters(),
                                    ['costcenterid' => $costcenterid, 'contextid' => $contextid]);
        $context = \context::instance_by_id($params['contextid']);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        global $DB;
            $selectarray = array(0 => get_string('selectsupervisor', 'local_users'));
        if ($params['costcenterid'] > 0) {
            try {
                $userssql = "SELECT u.id, concat(u.firstname,' ',u.lastname) FROM {user} AS u WHERE u.deleted = 0 AND u.suspended = 0 AND concat('/',u.open_path,'/') LIKE :costcenterpath ";
                $return = $selectarray + $DB->get_records_sql_menu($userssql, array('costcenterpath' => '%'.$params['costcenterid'].'%'));
            } catch (Exception $e) {
                $return = $selectarray;
            }
        } else {
            $return = $selectarray;
        }
        return json_encode($return);
    }
    public function get_supervisors_list_returns() {
        return new external_value(PARAM_RAW, 'Data of supervisors');
    }

    //added by sarath
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function manageusersview_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function manageusersview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        require_once($CFG->dirroot . '/local/users/lib.php');
        require_login();
        $PAGE->set_url('/local/users/index.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageusersview_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);
        $stable = new \stdClass();
        $stable->thead = true;
        $stable->start = $offset;
        $stable->length = $limit;
        $stable->status = $decodedata->status;
        $stable->costcenterid = $decodedata->costcenterid;
        $stable->departmentid = $decodedata->departmentid;
        $stable->subdepartmentid = $decodedata->subdepartmentid;
        $totalusers = manage_users_count($stable, $filtervalues);
        $totalcount = $totalusers['totalusers'];
        $activeusercount = $totalusers['activeusercount'];
        $inactiveusercount = $totalusers['inactiveusercount'];
        $stable->thead = false;
        $data = manage_users_content($stable, $totalusers/*$filtervalues*/);
        return [
            'totalcount' => $totalcount,
            'activeusercount' => $activeusercount,
            'inactiveusercount' => $inactiveusercount,
            'records' => $data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'wwwroot' => $CFG->wwwroot,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  manageusersview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of users in result set'),
            'activeusercount' => new external_value(PARAM_INT, 'total number of users in result set'),
            'inactiveusercount' => new external_value(PARAM_INT, 'total number of users in result set'),
            'wwwroot' => new external_value(PARAM_RAW, 'URL for wwwroot'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'visible' => new external_value(PARAM_INT, 'visible user', VALUE_OPTIONAL),
                                    'userid' => new external_value(PARAM_INT, 'userid', VALUE_OPTIONAL),

                                    'fullname' => new external_value(PARAM_RAW, 'fullname of the user', VALUE_OPTIONAL),
                                    'userpic' => new external_value(PARAM_RAW, 'user pic', VALUE_OPTIONAL),
                                    'empid' => new external_value(PARAM_RAW, 'empid of user', VALUE_OPTIONAL),
                                    'email' => new external_value(PARAM_RAW, 'email of the user', VALUE_OPTIONAL),
                                    'org' => new external_value(PARAM_RAW, 'org of the user', VALUE_OPTIONAL),
                                    'orgstring' => new external_value(PARAM_RAW, 'org string of the user', VALUE_OPTIONAL),

                                    'deptstring' => new external_value(PARAM_RAW, 'dept string challenge', VALUE_OPTIONAL),
                                    'dept' => new external_value(PARAM_RAW, 'dept of user', VALUE_OPTIONAL),
                                    'commercialunit' => new external_value(PARAM_RAW, 'dept of user', VALUE_OPTIONAL),
                                    'commercialarea' => new external_value(PARAM_RAW, 'dept of user', VALUE_OPTIONAL),
                                    'group' => new external_value(PARAM_RAW, 'group of the user', VALUE_OPTIONAL),
                                    'phno' => new external_value(PARAM_RAW, 'phone no of user', VALUE_OPTIONAL),

                                    'designation' => new external_value(PARAM_RAW, 'designation', VALUE_OPTIONAL),
                                    'designationstring' => new external_value(PARAM_RAW, 'designation string', VALUE_OPTIONAL),
                                    'supervisor' => new external_value(PARAM_RAW, 'supervisor', VALUE_OPTIONAL),
                                    'supervisiorstring' => new external_value(PARAM_RAW, 'supervisiorstring', VALUE_OPTIONAL),
                                    'lastaccess' => new external_value(PARAM_RAW, 'lastaccess', VALUE_OPTIONAL),
                                    'editcap' => new external_value(PARAM_RAW, 'editcap', VALUE_OPTIONAL),

                                    'delcap' => new external_value(PARAM_RAW, 'delcap', VALUE_OPTIONAL),
                                    'level' => new external_value(PARAM_RAW, 'delcap', VALUE_OPTIONAL),
                                    'rolecount' => new external_value(PARAM_RAW, 'delcap', VALUE_OPTIONAL),
                                    'activeicon' => new external_value(PARAM_INT, 'Active/Iactive icon user', VALUE_OPTIONAL),
                                    
                                )
                            )
                        )
        ]);
    }


    //added by sarath
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function managesyncerrors_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function managesyncerrors(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        require_once($CFG->dirroot . '/local/users/lib.php');
        require_login();
        $PAGE->set_url('/local/users/sync_errors.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::managesyncerrors_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
        $totalsyncerrors = manage_syncerrors_count($stable, $filtervalues);
        $totalsyncerrorscount = $totalsyncerrors['count'];

        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $data = manage_syncerrors_content($stable, $filtervalues);

        return [
            'totalcount' => $totalsyncerrorscount,
            'records' => $data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  managesyncerrors_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of users in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'idnumber' => new external_value(PARAM_RAW, 'idnumber of user', VALUE_OPTIONAL),
                                    'email' => new external_value(PARAM_RAW, 'email', VALUE_OPTIONAL),

                                    'mandatoryfields' => new external_value(PARAM_RAW, 'mandatory fields', VALUE_OPTIONAL),
                                    'errors' => new external_value(PARAM_RAW, 'error display', VALUE_OPTIONAL),
                                    'modifiedby' => new external_value(PARAM_RAW, 'modified date', VALUE_OPTIONAL),
                                    'createddate' => new external_value(PARAM_RAW, 'created date of error', VALUE_OPTIONAL)
                                )
                            )
                        )
        ]);
    }


    //added by sarath
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function managesyncstatics_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function managesyncstatics(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        require_once($CFG->dirroot . '/local/users/lib.php');
        require_login();
        $PAGE->set_url('/local/users/syncstatistics.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::managesyncstatics_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
        $totalsyncstatistics = manage_syncstatistics_count($stable, $filtervalues);
        $totalsyncstatisticscount = $totalsyncstatistics['count'];

        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $data = manage_syncstatistics_content($stable, $filtervalues);
        return [
            'totalcount' => $totalsyncstatisticscount,
            'records' => $data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  managesyncstatics_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of count in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'newuserscount' => new external_value(PARAM_INT, 'newuserscount of error'),
                                    'updateduserscount' => new external_value(PARAM_INT, 'updated users count'),

                                    'errorscount' => new external_value(PARAM_INT, 'errors count'),
                                    'warningscount' => new external_value(PARAM_INT, 'warnings count'),
                                    'supervisorwarningscount' => new external_value(PARAM_INT, 'supervisor warnings count'),
                                    'usercreated' => new external_value(PARAM_RAW, 'created user'),
                                    'createddate' => new external_value(PARAM_RAW, 'created date'),
                                    'modifieddate' => new external_value(PARAM_RAW, 'modified date'),
                                    'checkbox' => new external_value(PARAM_RAW, 'checkbox')
                                )
                            )
                        )
        ]);
    }

    public function deletesyncstatics_parameters() {
        return new external_function_parameters(
            array(
                'ids' => new external_value(PARAM_RAW, 'The data from the delete selected sync, encoded as a json array', false),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false)
            )
        );
    }
    public function deletesyncstatics($ids, $contextid) {
        global $DB;
        $data = json_decode($ids);
        $return = array();
        foreach ($data as $id) {
            $DB->delete_records('local_userssyncdata', array('id' => $id));
            $return[] = true;
        }
        if (count($return) > 0) {
            $returnvalue = true;
        } else {
            throw new moodle_exception(get_string('error_in_deletion', 'local_users'));
            $returnvalue = false;
        }
        return $returnvalue;
    }
    public function deletesyncstatics_returns() {
        return new external_value(PARAM_BOOL, 'returnvalue');
    }


    //added by sarath
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function profiledata_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'options'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function profiledata(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        require_once($CFG->dirroot . '/local/users/lib.php');
        require_login();
        $PAGE->set_url('/local/users/profile.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::profilemoduledata_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);

        $userclass = '\local_'.$decodedata->moduletype.'\local\user';
        if (class_exists($userclass)) {
            $pluginclass = new $userclass;
            if (method_exists($userclass, 'user_profile_content')) {
                $data = $pluginclass->user_profile_content($decodedata->userid, false, $offset, $limit);
            }
        }

        return [
            'totalcount' => count($data->navdata),
            'records' => $data->navdata,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  profiledata_returns() {

        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of count in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'userid' => new external_value(PARAM_INT, 'id of module'),
                                    'username' => new external_value(PARAM_RAW, 'name of module'),

                                    'rolename' => new external_value(PARAM_RAW, 'description'),
                                    'empid' => new external_value(PARAM_RAW, 'name of module'),

                                    'user_email' => new external_value(PARAM_RAW, 'description') ,
                                    'organisation' => new external_value(PARAM_RAW, 'name of module'),

                                    'department' => new external_value(PARAM_RAW, 'description'),
                                    'location' => new external_value(PARAM_RAW, 'name of module'),

                                    'address' => new external_value(PARAM_RAW, 'description'),
                                    'phnumber' => new external_value(PARAM_RAW, 'description'),
                                    'badgesimg' => new external_value(PARAM_RAW, 'name of module'),

                                    'certimg' => new external_value(PARAM_RAW, 'description'),
                                    'supervisorname' => new external_value(PARAM_RAW, 'description')

                                )
                            )
                        )
        ]);
    }


     //added by sarath
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function profilemoduledata_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'options'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function profilemoduledata(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        require_once($CFG->dirroot . '/local/users/lib.php');
        require_login();
        $PAGE->set_url('/local/users/profile.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::profilemoduledata_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);

        $userclass = '\local_'.$decodedata->moduletype.'\local\user';
        if (class_exists($userclass)) {
            $pluginclass = new $userclass;
            if (method_exists($userclass, 'user_profile_content')) {
                $data = $pluginclass->user_profile_content($decodedata->userid, false, $offset, $limit);
            }
        }

        return [
            'totalcount' => $data->count,
            'records' => $data->navdata,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  profilemoduledata_returns() {

        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of count in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'id of module'),
                                    'name' => new external_value(PARAM_RAW, 'name of module'),

                                    'description' => new external_value(PARAM_RAW, 'description'),
                                    'url' => new external_value(PARAM_RAW, 'url'),
                                    'module_img_url' => new external_value(PARAM_RAW, 'url', VALUE_OPTIONAL),
                                    'percentage' => new external_value(PARAM_RAW, 'percentage')
                                )
                            )
                        )
        ]);
    }
    public static function dashboard_stats_parameters() {
        return new external_function_parameters(
             array(
                'userid' => new external_value(PARAM_INT, 'UserID'),
                'module' => new external_value(PARAM_RAW, 'Module'),
                'source' => new external_value(PARAM_TEXT, 'source of call', VALUE_OPTIONAL, 'mobile')
            )
        );
    }
    public static function dashboard_stats($userid, $module, $source = 'mobile') {
        global $USER, $DB;
        $stats = array();
        $data = array();
        switch ($module) {
            case 'courses':
                $completed = general_lib::completed_coursenames_count('', $source);
                $inprogress = general_lib::inprogress_coursenames_count('', $source);
                $enrolled = general_lib::enrolled_coursenames_count('', $source);
            break;
            case 'classrooms':
                $completed = userdashboard_content::completed_classrooms_count();
                $inprogress = userdashboard_content::inprogress_classrooms_count();
                $enrolled = userdashboard_content::gettotal_classrooms();
            break;
            case 'onlinetests':
                $completed = onlinetests::completed_onlinetests_count();
                $inprogress = onlinetests::inprogress_onlinetests_count();
                $enrolled = $completed + $inprogress;
            break;
            case 'learningpaths':
                $completed = learningplan::completed_lepnames_count();
                $inprogress = learningplan::inprogress_lepnames_count();
                $enrolled = $completed + $inprogress;
            break;
            case 'evaluation':
                $completed = evaluation::completed_evaluations_count();
                $inprogress = evaluation::inprogress_evaluations_count();
                $enrolled = $completed + $inprogress;
            break;
            default: break;
        }
        $stats['completed']       = $completed;
        $stats['inprogress']      = $inprogress;
        $stats['enrolled']        = $enrolled;
        return array('stats' => $stats);
    }
    public static function dashboard_stats_returns() {
        return new external_single_structure(
            array(
                'stats' => new external_single_structure(
                    array(
                        'completed' => new external_value(PARAM_INT, 'Count of completed courses'),
                        'inprogress' => new external_value(PARAM_RAW, 'Count of inprogress courses'),
                        'enrolled' => new external_value(PARAM_RAW, 'Count of enrolled courses'),
                    )
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function pending_activities_parameters() {
        return new external_function_parameters(
            array('events' => new external_single_structure(
                array(
                    'eventids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'event ids') , 'List of event ids', VALUE_DEFAULT, array()),
                    ), 'Event details', VALUE_DEFAULT, array()),
                'options' => new external_single_structure(
                    array(
                        'userevents' => new external_value(PARAM_BOOL, "Set to true to return current user's user
                         events", VALUE_DEFAULT, true, NULL_ALLOWED),
                        'timestart' => new external_value(PARAM_INT, "Time from which events should be returned",
                         VALUE_DEFAULT, 0, NULL_ALLOWED),
                        'timeend' => new external_value(PARAM_INT, "Time to which the events should be returned.
                         We treat 0 and null as no end", VALUE_DEFAULT, 0, NULL_ALLOWED),
                        'ignorehidden' => new external_value(PARAM_BOOL, "Ignore hidden events or not", VALUE_DEFAULT,
                         true, NULL_ALLOWED),
                    ), 'Options', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Get Calendar events
     *
     * @param array $events A list of events
     * @param array $options various options
     * @return array Array of event details
     * @since Moodle 2.5
     */
    public static function pending_activities($events = array(), $options = array()) {
        global $SITE, $DB, $USER, $CFG, $PAGE;
        require_once($CFG->dirroot."/calendar/lib.php");

        // Parameter validation.
        $params = self::validate_parameters(self::pending_activities_parameters(), array('events' => $events,
         'options' => $options));
        $funcparam = array('courses' => array());
        $hassystemcap = has_capability('moodle/calendar:manageentries', (new \local_users\lib\accesslib())::get_module_context());
        $warnings = array();
        $mycourses = \local_courses\local\user::enrol_get_users_courses($USER->id, false, false, 0, 5, true);
        $mycourseids = array_keys($mycourses['data']);
        $funcparam['courses'] = $courses = $mycourseids;

        // Do we need user events?
        if (!empty($params['options']['userevents'])) {
            $funcparam['users'] = array($USER->id);
        } else {
            $funcparam['users'] = false;
        }

        // We treat 0 and null as no end.
        if (empty($params['options']['timeend'])) {
            $params['options']['timeend'] = PHP_INT_MAX;
        }
        // Event list does not check visibility and permissions, we'll check that later.
        $eventlist = calendar_get_legacy_events($params['options']['timestart'], $params['options']['timeend'],
                $USER->id, array(), $funcparam['courses'], true,
                true, array());

        // WS expects arrays.
        $events = array();

        // We need to get events asked for eventids.
        if ($eventsbyid = calendar_get_events_by_id($params['events']['eventids'])) {
            $eventlist += $eventsbyid;
        }

        foreach ($eventlist as $eventid => $eventobj) {
            $event = (array) $eventobj;
            if (empty($event['modulename'])) {
                continue;
            }
            // Description formatting.
            $calendareventobj = new calendar_event($event);
            list($event['description'], $event['format']) = $calendareventobj->format_external_text();
            $legacyevent = calendar_event::load($eventid);
            // Must check we can see this event.
            if (!calendar_view_event_allowed($legacyevent)) {
                // We can't return a warning in this case because the event is not optional.
                // We don't know the context for the event and it's not worth loading it.
                $syscontext = (new \local_users\lib\accesslib())::get_module_context();
                throw new \required_capability_exception($syscontext, 'moodle/course:view', 'nopermission', '');
            }

            $legacyevent->count_repeats();

            $eventmapper = event_container::get_event_mapper();
            $event1 = $eventmapper->from_legacy_event_to_event($legacyevent);

            $cache = new events_related_objects_cache([$event1]);
            $relatedobjects = [
                'context' => $cache->get_context($event1),
                'course' => $cache->get_course($event1),
            ];

            $exporter = new event_exporter($event1, $relatedobjects);

            $renderer = $PAGE->get_renderer('core_calendar');

            $eventdata = $exporter->export($renderer);
            // User can see everything, no further check is needed.
            $events[$eventid] = $event;
            $events[$eventid]['eventdata'] = $eventdata;
            $events[$eventid]['activity'] = $DB->get_field($event['modulename'], 'name', array('id' => $event['instance']));

        }
        return array('pendingactivities' => $events, 'warnings' => $warnings);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function  pending_activities_returns() {
        return new external_single_structure(array(
                'pendingactivities' => new external_multiple_structure( new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'event id'),
                            'name' => new external_value(PARAM_TEXT, 'event name'),
                            'activity' => new external_value(PARAM_TEXT, 'event name'),
                            'description' => new external_value(PARAM_RAW, 'Description', VALUE_OPTIONAL, null, NULL_ALLOWED),
                            'format' => new external_format_value('description'),
                            'courseid' => new external_value(PARAM_INT, 'course id'),
                            'repeatid' => new external_value(PARAM_INT, 'repeat id'),
                            'modulename' => new external_value(PARAM_TEXT, 'module name', VALUE_OPTIONAL, null, NULL_ALLOWED),
                            'instance' => new external_value(PARAM_INT, 'instance id'),
                            'eventtype' => new external_value(PARAM_TEXT, 'Event type'),
                            'timestart' => new external_value(PARAM_INT, 'timestart'),
                            'timeduration' => new external_value(PARAM_INT, 'time duration'),
                            'visible' => new external_value(PARAM_INT, 'visible'),
                            'uuid' => new external_value(PARAM_TEXT, 'unique id of ical events', VALUE_OPTIONAL,
                             null, NULL_NOT_ALLOWED),
                            'sequence' => new external_value(PARAM_INT, 'sequence'),
                            'timemodified' => new external_value(PARAM_INT, 'time modified'),
                            'eventdata' => event_exporter::get_read_structure()
                        ), 'event')
                 ),
                 'warnings' => new external_warnings()
                )
        );
    }

    /**
     * Describes the parameters for get_grade_items.
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function get_grade_items_parameters() {
        return self::get_grades_table_parameters();
    }
    /**
     * Describes the parameters for get_grades_table.
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_grades_table_parameters() {
        return new external_function_parameters (
            array(
                'courseid' => new external_value(PARAM_INT, 'Course Id', VALUE_REQUIRED),
                'userid'   => new external_value(PARAM_INT, 'Return grades only for this user (optional)', VALUE_DEFAULT, 0),
                'groupid'  => new external_value(PARAM_INT, 'Get users from this group only', VALUE_DEFAULT, 0)
            )
        );
    }
    /**
     * Returns the complete list of grade items for users in a course.
     *
     * @param int $courseid Course Id
     * @param int $userid   Only this user (optional)
     * @param int $groupid  Get users from this group only
     *
     * @return array the grades tables
     * @since Moodle 3.2
     */
    public static function get_grade_items($courseid, $userid = 0, $groupid = 0) {
        global $CFG, $USER;

        list($params, $course, $context, $user, $groupid) = self::check_report_access($courseid, $userid, $groupid);
        $userid   = $params['userid'];

        // We pass userid because it can be still 0.
        list($gradeitems, $warnings) = self::get_report_data($course, $context, $user, $userid, $groupid, false);

        foreach ($gradeitems as $gradeitem) {
            if (isset($gradeitem['feedback']) && isset($gradeitem['feedbackformat'])) {
                list($gradeitem['feedback'], $gradeitem['feedbackformat']) =
                    external_format_text($gradeitem['feedback'], $gradeitem['feedbackformat'], $context->id);
            }
        }

        $result = array();
        $result['usergrades'] = $gradeitems;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes tget_grade_items return value.
     *
     * @return external_single_structure
     * @since Moodle 3.2
     */
    public static function get_grade_items_returns() {
        return new external_single_structure(
            array(
                'usergrades' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'course id'),
                            'userid'   => new external_value(PARAM_INT, 'user id'),
                            'userfullname' => new external_value(PARAM_TEXT, 'user fullname'),
                            'maxdepth'   => new external_value(PARAM_INT, 'table max depth (needed for printing it)'),
                            'gradeitems' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'id' => new external_value(PARAM_INT, 'Grade item id'),
                                        'itemname' => new external_value(PARAM_TEXT, 'Grade item name'),
                                        'itemtype' => new external_value(PARAM_ALPHA, 'Grade item type'),
                                        'itemmodule' => new external_value(PARAM_PLUGIN, 'Grade item module'),
                                        'iteminstance' => new external_value(PARAM_INT, 'Grade item instance'),
                                        'itemnumber' => new external_value(PARAM_INT, 'Grade item item number'),
                                        'categoryid' => new external_value(PARAM_INT, 'Grade item category id'),
                                        'outcomeid' => new external_value(PARAM_INT, 'Outcome id'),
                                        'scaleid' => new external_value(PARAM_INT, 'Scale id'),
                                        'locked' => new external_value(PARAM_BOOL, 'Grade item for user locked?', VALUE_OPTIONAL),
                                        'cmid' => new external_value(PARAM_INT, 'Course module id (if type mod)', VALUE_OPTIONAL),
                                        'weightraw' => new external_value(PARAM_FLOAT, 'Weight raw', VALUE_OPTIONAL),
                                        'weightformatted' => new external_value(PARAM_NOTAGS, 'Weight', VALUE_OPTIONAL),
                                        'status' => new external_value(PARAM_ALPHA, 'Status', VALUE_OPTIONAL),
                                        'graderaw' => new external_value(PARAM_FLOAT, 'Grade raw', VALUE_OPTIONAL),
                                        'gradedatesubmitted' => new external_value(PARAM_INT, 'Grade submit date', VALUE_OPTIONAL),
                                        'gradedategraded' => new external_value(PARAM_INT, 'Grade graded date', VALUE_OPTIONAL),
                                        'gradehiddenbydate' => new external_value(PARAM_BOOL, 'Grade hidden by date?',
                                         VALUE_OPTIONAL),
                                        'gradeneedsupdate' => new external_value(PARAM_BOOL, 'Grade needs update?', VALUE_OPTIONAL),
                                        'gradeishidden' => new external_value(PARAM_BOOL, 'Grade is hidden?', VALUE_OPTIONAL),
                                        'gradeislocked' => new external_value(PARAM_BOOL, 'Grade is locked?', VALUE_OPTIONAL),
                                        'gradeisoverridden' => new external_value(PARAM_BOOL, 'Grade overridden?', VALUE_OPTIONAL),
                                        'gradeformatted' => new external_value(PARAM_NOTAGS, 'The grade formatted', VALUE_OPTIONAL),
                                        'grademin' => new external_value(PARAM_FLOAT, 'Grade min', VALUE_OPTIONAL),
                                        'grademax' => new external_value(PARAM_FLOAT, 'Grade max', VALUE_OPTIONAL),
                                        'rangeformatted' => new external_value(PARAM_NOTAGS, 'Range formatted', VALUE_OPTIONAL),
                                        'percentageformatted' => new external_value(PARAM_NOTAGS, 'Percentage', VALUE_OPTIONAL),
                                        'lettergradeformatted' => new external_value(PARAM_NOTAGS, 'Letter grade', VALUE_OPTIONAL),
                                        'rank' => new external_value(PARAM_INT, 'Rank in the course', VALUE_OPTIONAL),
                                        'numusers' => new external_value(PARAM_INT, 'Num users in course', VALUE_OPTIONAL),
                                        'averageformatted' => new external_value(PARAM_NOTAGS, 'Grade average', VALUE_OPTIONAL),
                                        'feedback' => new external_value(PARAM_RAW, 'Grade feedback', VALUE_OPTIONAL),
                                        'feedbackformat' => new external_format_value('feedback'),
                                    ), 'Grade items'
                                )
                            )
                        )
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }
    /**
     * Describes the parameters for get_course_grades.
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function get_course_grades_parameters() {
        return new external_function_parameters (
            array(
                'userid' => new external_value(PARAM_INT, 'Get grades for this user (optional, default current)', VALUE_DEFAULT, 0),
                'source' => new external_value(PARAM_TEXT, 'Parameter to validate the mobile ', VALUE_DEFAULT, 'mobile')
            )
        );
    }

    /**
     * Get the given user courses final grades
     *
     * @param int $userid get grades for this user (optional, default current)
     *
     * @return array the grades tables
     * @since Moodle 3.2
     */
    public static function get_course_grades($userid = 0, $source = 'mobile') {
        global $USER, $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/local/users/grades.php');
        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(self::get_course_grades_parameters(),
            array(
                'userid' => $userid,
                'source' => $source
            )
        );

        $userid = $params['userid'];
        if (empty($userid)) {
            $userid = $USER->id;
        }


        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

        self::validate_context($categorycontext);

        if ($USER->id != $userid) {
            // We must check if the current user can view other users grades.
            $user = core_user::get_user($userid, '*', MUST_EXIST);
            core_user::require_active_user($user);
            require_capability('moodle/grade:viewall', $categorycontext);
        }

        // We need the site course, and course context.
        $course = get_course(SITEID);
        //$context = context_course::instance($course->id);
        $context = (new \local_users\lib\accesslib())::get_module_context();
        // Force a regrade if required.
        grade_regrade_final_grades_if_required($course);
        // Get the course final grades now.
        $gpr = new grade_plugin_return(array('type' => 'report', 'plugin' => 'overview', 'courseid' => $course->id,
                                        'userid' => $userid));
        $report = new user_grades($userid, $gpr, $context);
        $coursesgrades = $report->setup_courses_data(true);
        if ($source = 'mobile') {
            $secured_courses = $DB->get_fieldset_sql("SELECT c.id FROM {course} AS c WHERE c.id>1 AND c.open_securecourse =
             :open_securecourse ", array('open_securecourse' => 1));
        } else {
            $secured_courses = [];
        }

        $grades = array();
        foreach ($coursesgrades as $coursegrade) {
            if (in_array($coursegrade['course']->id, $secured_courses)) {
                continue;
            }
            $gradeinfo = array(
                'courseid' => $coursegrade['course']->id,
                'grade' => grade_format_gradevalue($coursegrade['finalgrade'], $coursegrade['courseitem'], true),
                'rawgrade' => $coursegrade['finalgrade'],
            );
            if (isset($coursegrade['rank'])) {
                $gradeinfo['rank'] = $coursegrade['rank'];
            }
            $grades[] = $gradeinfo;
        }

        $result = array();
        $result['grades'] = $grades;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_course_grades return value.
     *
     * @return external_single_structure
     * @since Moodle 3.2
     */
    public static function get_course_grades_returns() {
        return new external_single_structure(
            array(
                'grades' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'grade' => new external_value(PARAM_RAW, 'Grade formatted'),
                            'rawgrade' => new external_value(PARAM_RAW, 'Raw grade, not formatted'),
                            'rank' => new external_value(PARAM_INT, 'Your rank in the course', VALUE_OPTIONAL),
                        )
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    public function get_positions_list_parameters() {
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'Organization id', 0),

                'domain' => new external_value(PARAM_INT, 'Domain id', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the organization', false)
            )
        );
    }
    public function get_positions_list($costcenterid, $domain, $contextid) {
        $params = self::validate_parameters(self::get_positions_list_parameters(),
                                    ['costcenterid' => $costcenterid, 'domain' => $domain, 'contextid' => $contextid]);
        $context = \context::instance_by_id($params['contextid']);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        global $DB;
        $corecomponent = new \core_component();
        $positionpluginexists = $corecomponent::get_plugin_directory('local', 'positions');
        $selectarray = array(0 => get_string('positionreq', 'local_users'));
        if ($params['costcenterid'] > 0) {
            try {
                if ($positionpluginexists) {
                    $return = $selectarray + $DB->get_records_menu('local_positions', array('costcenter' =>
                    $params['costcenterid']), '', 'id, name');
                }
            } catch (Exception $e) {
                $return = $selectarray;
            }
        }if ($params['domain'] > 0) {
            try {
                if ($positionpluginexists) {
                    $return = $selectarray + $DB->get_records_menu('local_positions', array('costcenter' =>
                     $params['costcenterid'], 'domain' => $params['domain']), '', 'id, name');
                }
            } catch (Exception $e) {
                $return = $selectarray;
            }
        } else {
            $return = $selectarray;
        }
        return json_encode($return);
    }
    public function get_positions_list_returns() {
        return new external_value(PARAM_RAW, 'Data of positions');
    }

    public function get_domains_list_parameters() {
        return new external_function_parameters(
            array(
                'costcenterid' => new external_value(PARAM_INT, 'Organization id', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the organization', false)
            )
        );
    }
    public function get_domains_list($costcenterid, $contextid) {
        $params = self::validate_parameters(self::get_domains_list_parameters(),
                                    ['costcenterid' => $costcenterid, 'contextid' => $contextid]);
        $context = \context::instance_by_id($params['contextid']);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        global $DB;
        $selectarray = array(0 => get_string('domainreq', 'local_users'));
        if ($params['costcenterid'] > 0) {
            try {
                $corecomponent = new \core_component();
                $pluginexists = $corecomponent::get_plugin_directory('local', 'domains');
                if ($pluginexists) {
                    $return = $selectarray + $DB->get_records_menu('local_domains', array('costcenter' =>
                    $params['costcenterid']), '', 'id, name');
                }
            } catch (Exception $e) {
                $return = $selectarray;
            }
        } else {
            $return = $selectarray;
        }
        return json_encode($return);
    }
    public function get_domains_list_returns() {
        return new external_value(PARAM_RAW, 'Data of domains');
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function profileskilldata_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'options'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function profileskilldata(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        require_once($CFG->dirroot . '/local/users/lib.php');
        require_login();
        $PAGE->set_url('/local/users/skillprofile.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::profileskilldata_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);

        $userclass = '\local_users\local\user';
        if (class_exists($userclass)) {
            $pluginclass = new $userclass;
            if (method_exists($userclass, 'user_profileskill_content')) {
                $data = $pluginclass->user_profileskill_content($decodedata->userid, false, $offset, $limit,
                 $decodedata->positionid);
            }
        }

        return [
            'totalcount' => $data->count,
            'records' => $data->compitencies,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  profileskilldata_returns() {

        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of count in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array('comp_name' => new external_value(PARAM_RAW, 'Competencies list',
                                 VALUE_OPTIONAL, null), 'percentage' => new external_value(PARAM_RAW,
                                  'Competencies percentage', VALUE_OPTIONAL, null), 'skillnames'
                                   => new external_value(PARAM_RAW, 'Competency skillnames', VALUE_OPTIONAL,
                                    null), 'comp_id' => new external_value(PARAM_INT, 'Competency ids',
                                     VALUE_OPTIONAL, null), 'contextid' => new external_value(PARAM_INT,
                                      'contextid', VALUE_OPTIONAL, null))
                            )
                         )
        ]);
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function profileskilldatatabs_parameters() {
        return new external_function_parameters([
                'userid' => new external_value(PARAM_INT, 'userid')
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function profileskilldatatabs(
        $userid
    ) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        require_once($CFG->dirroot . '/local/users/lib.php');
        require_login();
        $PAGE->set_url('/local/users/skillprofile.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::profileskilldatatabs_parameters(),
            [
                'userid' => $userid
            ]
        );
        $renderer   = $PAGE->get_renderer('local_users');

        $output = $renderer->employees_skill_profile_view($userid);
        return $output;
    }

    /**
     * Returns description of method result value.
     */
    public static function  profileskilldatatabs_returns() {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'userid'),
            'username' => new external_value(PARAM_RAW, 'username'),
            'contextid' => new external_value(PARAM_INT, 'The data for the service', VALUE_OPTIONAL, null),
            'currentposition_id' => new external_value(PARAM_INT, 'The current user position', VALUE_OPTIONAL, null),
            'options' => new external_single_structure(
                                    array('targetID' => new external_value(PARAM_RAW, 'targetID', VALUE_OPTIONAL, null))
                                ),
            'positionslist' => new external_multiple_structure(
                                new external_single_structure(
                                    array('id' => new external_value(PARAM_INT, 'id', VALUE_OPTIONAL, null),
                                     'positionname' => new external_value(PARAM_RAW, 'positionname', VALUE_OPTIONAL,
                                      null), 'current_position' => new external_value(PARAM_RAW,
                                       'current_position', VALUE_OPTIONAL, null))
                                )
                            ),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array('comp_name' => new external_value(PARAM_RAW, 'comp_name', VALUE_OPTIONAL,
                                 null), 'percentage' => new external_value(PARAM_RAW, 'percentage', VALUE_OPTIONAL,
                                  null))
                            )
                        ),
        ]);
    }

    public static function local_create_update_user_parameters() {
            $userfields = array(
            'organisation_code' => new external_value(PARAM_TEXT, 'Organisation name for the employee'),
            'department_code' => new external_value(PARAM_TEXT, 'Department name for the employee'),
            'auth' => new external_value(PARAM_TEXT, 'Meathod of authetication', VALUE_OPTIONAL, 'saml2'),
            'username' => new external_value(PARAM_TEXT, 'Username for employee'),
            'password' => new external_value(PARAM_RAW, 'password for employee', VALUE_OPTIONAL, 'not cached'),
            'status' => new external_value(PARAM_INT, 'status of the employee', VALUE_OPTIONAL, 1),
            'firstname' => new external_value(PARAM_TEXT, 'Firstname for the employee'),
            'lastname' => new external_value(PARAM_TEXT, 'Lastname for the employee', VALUE_OPTIONAL),
            'email' => new external_value(PARAM_RAW, 'Email id of the user to update'),
            'employeeid' => new external_value(PARAM_RAW, 'Employee id of the user to update'),
            'supervisor_empid' => new external_value(PARAM_TEXT, 'Email of the supervisor'),
            'designation' => new external_value(PARAM_RAW, 'Designation of the user Create / Update', VALUE_OPTIONAL),
            'role' => new external_value(PARAM_RAW, 'Designation of the user Create / Update', VALUE_OPTIONAL),
            'discipline' => new external_value(PARAM_RAW, 'Discipline of the user Create / Update', VALUE_OPTIONAL),
            'subdepartment' => new external_value(PARAM_RAW, 'subdepartment of the user Create / Update', VALUE_OPTIONAL),
            );
            return new external_function_parameters(
            [
                'userdata' => new external_multiple_structure(
                    new external_single_structure($userfields)
                )
            ]
        );
    }

    public static function  local_create_update_user($userdata) {
        $params = self::validate_parameters(self::local_create_update_user_parameters(), array(
            'userdata' => $userdata
        ));
        global $DB, $USER;
        $return = array();
        foreach ($userdata as $user) {
            try {
                $user = (object) $user;
                    $userinfo = clone $user;
                    $userinfo->open_designation = trim($userinfo->designation);
                    $userinfo->confirmed = $userinfo->mnethostid = 1;
                    $userinfo->deleted = 0;
                    $userinfo->email = trim(strtolower($userinfo->email));
                    $userinfo->open_costcenterid = $DB->get_field('local_costcenter', 'id', array('shortname'
                     => $user->organisation_code));
                if (!$userinfo->open_costcenterid) {
                        throw new moodle_exception("Provided Organisation {$user->organisation} doesn't exist");
                }
                    $userinfo->open_departmentid = $DB->get_field('local_costcenter', 'id', array('shortname'
                     => $user->department_code));
                if (!$userinfo->open_departmentid) {
                        throw new moodle_exception("Provided Department {$user->department} doesn't exist");
                }

                    $userinfo->open_subdepartment = $DB->get_field('local_costcenter', 'id', array('shortname'
                     => $user->subdepartment));
                if (!$userinfo->open_subdepartment) {
                        throw new moodle_exception("Provided Subdepartment {$user->subdepartment} doesn't exist");
                }
                    $field = 'open_employeeid';
                    $userinfo->open_supervisorid = $DB->get_field('user', 'id', array($field => $user->supervisor_empid));
                    $userinfo->password = $userinfo->auth == 'saml2' ? $userinfo->password :
                     hash_internal_user_password($userinfo->password);
                    $userinfo->open_employeeid = $userinfo->employeeid;
                    $userinfo->open_hrmsrole = $userinfo->role;
                    $userinfo->open_level = $userinfo->discipline;
                    $fieldsSql = "SELECT id FROM {user} WHERE  open_employeeid LIKE :employeeid";
                    $userids = $DB->get_fieldset_sql($fieldsSql, array( 'employeeid' => $userinfo->open_employeeid));
                if (count($userids) > 1) {
                        throw new moodle_exception('Multiple users exists with the combination given');
                    } else if(count($userids) == 1) {
                        $userid = implode(',', $userids);
                        $userinfo->id = $userid;
                        user_update_user($userinfo, False);

                    } else {
                        $userid = user_create_user($userinfo, False);
                    }
                    if ($userid) {

                        $return[] = array('status' => 'success', 'userid' => $userid);
                    } else {
                        $return[] = array('status' => 'error in user creation', 'userid' => NULL);
                    }
            } catch (Exception $e) {
                $return[] = array('status' => $e->getMessage(), 'userid' => null);
            }
        }
        return $return;
    }
    public static function local_create_update_user_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'status' => new external_value(PARAM_TEXT, 'status'),
                    'userid'       => new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL, null)
                )
            )
        );
    }
    public static function form_option_selector_parameters(){
        $query = new external_value(PARAM_RAW, 'Query string');
        $action = new external_value(PARAM_RAW, 'Action for the costcenter form selector');
        $options = new external_value(PARAM_RAW, 'Action for the kpichallenge form selector');
        $searchanywhere = new external_value(PARAM_BOOL, 'find a match anywhere, or only at the beginning');
        $page = new external_value(PARAM_INT, 'Page number');
        $perpage = new external_value(PARAM_INT, 'Number per page');
        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'action' => $action,
            'options' => $options,
            'searchanywhere' => $searchanywhere,
            'page' => $page,
            'perpage' => $perpage,
        ));
    }
    public static function form_option_selector($query, $context, $action, $options, $searchanywhere, $page, $perpage){
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::form_option_selector_parameters(), array(
            'query' => $query,
            'context' => $context,
            'action' => $action,
            'options' => $options,
            'searchanywhere' => $searchanywhere,
            'page' => $page,
            'perpage' => $perpage
        ));
        $query = $params['query'];
        $action = $params['action'];
        $context = self::get_context_from_params($params['context']);
        $options = $params['options'];

        $searchanywhere=$params['searchanywhere'];
        $page=$params['page'];
        $perpage=$params['perpage'];

        if (!empty($options)) {
            $formoptions = json_decode($options);
        }
        self::validate_context($context);
        $allobject = new \stdClass();
        $allobject->id = 0;
        $allobject->fullname = 'All';
        $allobjectarr = array(0 => $allobject);
        if ($action) {

            $return = array();

            switch($action) {

                case 'userprofile_element_selector':

                    $fields = array("fullname");
                    $isfilter = strpos($formoptions->columnname, 'filter');

                    $formoptions->columnname = str_replace('filter', '', $formoptions->columnname);
                    $formoptions->parentidcolumn = str_replace('filter', '', $formoptions->parentidcolumn);
                    $tablename=$DB->get_prefix().str_replace("open","local",$formoptions->columnname);

                    $fieldname=str_replace("open_","",$formoptions->columnname).'_name';

                    $parentidcolumn=str_replace("open_","",$formoptions->parentidcolumn).'id';

                    $concatsql='';

                    $sqlparams =array();


                    if (!empty($formoptions->parentid)  && $formoptions->parentid != null) {

                        list($relatedparentidsql, $relatedparentidparams) = $DB->get_in_or_equal($formoptions->parentid, SQL_PARAMS_NAMED, 'parentids');
                        $sqlparams = $relatedparentidparams;

                        $concatsql .= " AND $parentidcolumn  $relatedparentidsql";

                    }else{
                        if($isfilter === false){
                            $concatsql .= " AND $parentidcolumn=0 ";
                        }
                    }

                    $likesql = array();
                    $i = 0;
                    if(!empty($query)){
                        foreach ($fields as $field) {
                            $i++;
                            $likesql[] = $DB->sql_like($fieldname, ":queryparam$i", false);
                            $sqlparams["queryparam$i"] = "%$query%";
                        }
                        $sqlfields = implode(" OR ", $likesql);
                        $concatsql .= " AND ($sqlfields) ";
                    }
                    $fields      = "SELECT id, $fieldname as fullname";
                    $userprofile = " FROM {$tablename}
                                     WHERE 1=1 $concatsql  ";

                    $userprofile = $DB->get_records_sql($fields.$userprofile, $sqlparams, ($page * $perpage) -0, $perpage + 1);
                    if ($userprofile) {
                        $totalaccounts = count($userprofile);
                        $moreaccounts = $totalaccounts > $perpage;

                        if ($moreaccounts) {
                            // We need to discard the last record.
                            array_pop($userprofile);
                        }
                    }

                    $return = array_values(json_decode(json_encode(($userprofile)), true));
                break;
            }
        }
        return json_encode($return);
    }
    public static function form_option_selector_returns(){
        return new external_value(PARAM_RAW, 'data');
    }


}
