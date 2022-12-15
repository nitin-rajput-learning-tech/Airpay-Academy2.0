<?php
/**
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
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
use local_myteam\output\team_status_lib;
use local_myteam\output\myteam;
use local_myteam\output\courseallocation;
use local_myteam\output\courseallocation_lib;
use local_myteam\output\team_approvals;
use local_myteam\output\team_approvals_lib;

use context_system;
use core_component;

class local_myteam_external extends external_api {

	//added by sarath
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function manageteamview_parameters() {
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
     * @param int $limit Maximum number of results to return
     * @param int $contextid context of getting the data.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function manageteamview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/myteam/lib.php');
        require_login();
        $PAGE->set_url('/local/myteam/team.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageteamview_parameters(),
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
        $teamstatus = new team_status_lib();
		$teammemberscount = $teamstatus->get_team_members(true);

        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $myteam = new myteam();
        $data = $myteam->team_members_contentview($stable,$filtervalues);
        return [
            'totalcount' => $teammemberscount,
            'headers' =>$data['headers'],
            'teammembersexist' =>$data['teammembersexist'],
            'records' =>$data['data'],
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  manageteamview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of users in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'headers' => new external_single_structure(
                                array(
                                    'members' => new external_value(PARAM_RAW, 'fullname of the user', VALUE_OPTIONAL),
                                    'certification' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                    'classroom' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                    'courses' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                    'supervisorevaluation' => new external_value(PARAM_RAW, 'supervisorevaluation', VALUE_OPTIONAL),
                                    'evaluation' => new external_value(PARAM_RAW, 'evaluation', VALUE_OPTIONAL),
                                    'learningplan' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                    'onlinetests' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                    'program' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                    'badges' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                )
                            ),
            'teammembersexist' => new external_value(PARAM_INT, 'team exist or not'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                	'userid' => new external_value(PARAM_INT, 'userid', VALUE_OPTIONAL),
                                    'userfullname' => new external_value(PARAM_RAW, 'fullname of the user', VALUE_OPTIONAL),
                                    'certification' => new external_single_structure(
		                                array(
		                                    'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
		                                    'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
		                                    'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
		                                    'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
		                                    'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
		                                    'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
		                                ), 'Certification Data', VALUE_OPTIONAL
		                            ),

		                            'classroom' => new external_single_structure(
		                                array(
		                                    'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
		                                    'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
		                                    'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
		                                    'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
		                                    'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
		                                    'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
		                                ), 'Classroom Data', VALUE_OPTIONAL
		                            ),

		                            'courses' => new external_single_structure(
		                                array(
		                                    'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
		                                    'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
		                                    'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
		                                    'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
		                                    'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
		                                    'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
		                                ), 'Courses Data', VALUE_OPTIONAL
		                            ),

                                    'supervisorevaluation' => new external_single_structure(
                                        array(
                                            'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
                                            'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
                                            'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
                                            'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                            'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
                                            'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
                                        ), 'supervisorevaluation Data', VALUE_OPTIONAL
                                    ),

                                    'evaluation' => new external_single_structure(
                                        array(
                                            'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
                                            'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
                                            'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
                                            'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                            'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
                                            'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
                                        ), 'evaluation Data', VALUE_OPTIONAL
                                    ),


		                            'learningplan' => new external_single_structure(
		                                array(
		                                    'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
		                                    'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
		                                    'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
		                                    'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
		                                    'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
		                                    'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
		                                ), 'learning Data', VALUE_OPTIONAL
		                            ),


		                            'onlinetests' => new external_single_structure(
		                                array(
		                                    'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
		                                    'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
		                                    'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
		                                    'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
		                                    'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
		                                    'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
		                                ), 'Onlinetest Data', VALUE_OPTIONAL
		                            ),

		                            'program' => new external_single_structure(
		                                array(
		                                    'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
		                                    'completed' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
		                                    'enrolled' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
		                                    'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
		                                    'userid' => new external_value(PARAM_INT, 'useridd', VALUE_OPTIONAL),
		                                    'modulename' => new external_value(PARAM_RAW, 'module name', VALUE_OPTIONAL),
		                                ), 'program Data', VALUE_OPTIONAL
		                            ),
                                    'badgescount' => new external_single_structure(
		                                array(
		                                    'elementcolor' => new external_value(PARAM_RAW, 'elementcolor', VALUE_OPTIONAL),
		                                    'issuedbadges' => new external_value(PARAM_INT, 'completed count', VALUE_OPTIONAL),
		                                    'totalbadges' => new external_value(PARAM_INT, 'enrolled count', VALUE_OPTIONAL),
		                                    'userid' => new external_value(PARAM_INT, 'userid', VALUE_OPTIONAL),
		                                ), 'Badges Data', VALUE_OPTIONAL
		                            ),
                                    
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
    public static function courseallocationdependencies_parameters() {
        return new external_function_parameters([
                'action' => new external_value(PARAM_RAW, 'action of the request'),
                'user' => new external_value(PARAM_INT, 'userid',VALUE_DEFAULT, 0),
                'learningtype' => new external_value(PARAM_INT, 'learningtype',VALUE_DEFAULT, 0),
                'search' => new external_value(PARAM_RAW, 'search'),
                'allocatecourse' => new external_value(PARAM_RAW, 'allocatecourse')
        ]);
    }

    /**
     * Based on selected we are getting modules data
     *
     * @param int $action need to give name of the action
     * @param int $user need to give selected userid
     * @param int $learningtype need to send learning type
     * @param int $search searchvalue
     * @param int $allocatecourse if select any course.
     * @return array The allocation.
     */
    public static function courseallocationdependencies(
        $action,
        $user = 0,
        $learningtype = 0,
        $search,
        $allocatecourse
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/myteam/lib.php');
        require_login();
        $PAGE->set_url('/local/myteam/team.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::courseallocationdependencies_parameters(),
            [
                'action' => $action,
                'user' => $user,
                'learningtype' => $learningtype,
                'search' => $search,
                'allocatecourse' => $allocatecourse
            ]
        );

        $courseallocation = new courseallocation;
        $courseallocation_lib = new courseallocation_lib();

        switch($action) {
            case 'departmentmodules':
                if($learningtype == 1){
                    $return = $courseallocation->get_team_courses_view($user, $search = false);
                }elseif($learningtype == 2){
                    $return = $courseallocation->get_team_classrooms_view($user, $search = false);
                }elseif($learningtype == 3){
                    $return = $courseallocation->get_team_programs_view($user, $search = false);
                }elseif($learningtype == 4){
                    $return = '';
                }else{
                    $return = $courseallocation->get_team_courses_view($user, $search = false);
                }
            break;
            case 'searchdata':
                if($learningtype == 'myteam'){
                    $return = $courseallocation->courseallocation_view($search);
                }else{
                    // searchtype
                    if($learningtype == 1){
                        $return = $courseallocation->get_team_courses_view($user, $search);
                    }elseif($learningtype == 2){
                        $return = $courseallocation->get_team_classrooms_view($user, $search);
                    }elseif($learningtype == 3){
                        $return = $courseallocation->get_team_programs_view($user, $search);
                    }elseif($learningtype == 4){
                        $return = '';
                    }else{
                        $return = $courseallocation->get_team_courses_view($user, $search);
                    }
                }
            break;
            case 'courseallocate':
                $return = $courseallocation_lib->courseallocation($learningtype, $user, $allocatecourse);
            break;
        }
        return [
            'records' => $return
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  courseallocationdependencies_returns() {
        return new external_single_structure([
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'disattr' => new external_value(PARAM_RAW, 'disattr',VALUE_OPTIONAL),
                                    'checked' => new external_value(PARAM_RAW, 'checked',VALUE_OPTIONAL),
                                    'user' => new external_value(PARAM_INT, 'user',VALUE_DEFAULT, 0),
                                    'moduleid' => new external_value(PARAM_INT, 'moduleid',VALUE_DEFAULT,0),
                                    'extraclass' => new external_value(PARAM_RAW, 'extraclass',VALUE_OPTIONAL),
                                    'modulename' => new external_value(PARAM_RAW, 'modulename'),
                                    'icons' => new external_value(PARAM_RAW, 'icons')
                                    
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
    public static function teamallocationview_parameters() {
        return new external_function_parameters([
                'action' => new external_value(PARAM_RAW, 'action of the request'),
                'user' => new external_value(PARAM_RAW, 'userid',VALUE_OPTIONAL),
                'learningtype' => new external_value(PARAM_RAW, 'learningtype',VALUE_OPTIONAL),
                'search' => new external_value(PARAM_RAW, 'search'),
        ]);
    }

    /**
     * Based on selected we are getting modules data
     *
     * @param int $action need to give name of the action
     * @param int $user need to give selected userid
     * @param int $learningtype need to send learning type
     * @param int $search searchvalue
     * @param int $allocatecourse if select any course.
     * @return array The allocation.
     */
    public static function teamallocationview(
        $action,
        $user,
        $learningtype,
        $search
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/myteam/lib.php');
        require_login();
        $PAGE->set_url('/local/myteam/team.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::teamallocationview_parameters(),
            [
                'action' => $action,
                'user' => $user,
                'learningtype' => $learningtype,
                'search' => $search,
            ]
        );

        $courseallocation = new courseallocation;

        if($action == 'searchdata'){
            if($learningtype == 'myteam'){
                $return = $courseallocation->courseallocation_view(false,$search);
            }
        }

        return [
            'records' => $return
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  teamallocationview_returns() {
        return new external_single_structure([
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'existplugins' => new external_single_structure(
                                        array(
                                            'coursesexist' => new external_value(PARAM_INT, 'coursesexist'),
                                            'classroomexist' => new external_value(PARAM_INT, 'classroomexist'),
                                            'programexist' => new external_value(PARAM_INT, 'programexist'),
                                        )
                                    ),
                                    'teamusers' => new external_multiple_structure(
                                                    new external_single_structure(
                                                        array(
                                                            'id' => new external_value(PARAM_INT, 'id'),
                                                            'picture' => new external_value(PARAM_RAW, 'picture'),
                                                            'fullname' => new external_value(PARAM_RAW, 'fullname'),
                                                        )
                                                     )
                                                )
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
    public static function modulecourseallocation_parameters() {
        return new external_function_parameters([
                'action' => new external_value(PARAM_RAW, 'action of the request'),
                'user' => new external_value(PARAM_RAW, 'userid',VALUE_OPTIONAL),
                'learningtype' => new external_value(PARAM_RAW, 'learningtype',VALUE_OPTIONAL),
                'search' => new external_value(PARAM_RAW, 'search'),
                'allocatecourse' => new external_value(PARAM_RAW, 'allocatecourse',false)
        ]);
    }

    /**
     * Based on selected we are getting modules data
     *
     * @param int $action need to give name of the action
     * @param int $user need to give selected userid
     * @param int $learningtype need to send learning type
     * @param int $search searchvalue
     * @param int $allocatecourse if select any course.
     * @return array The allocation.
     */
    public static function modulecourseallocation(
        $action,
        $user,
        $learningtype,
        $search,
        $allocatecourse
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/myteam/lib.php');
        require_login();
        $PAGE->set_url('/local/myteam/team.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::modulecourseallocation_parameters(),
            [
                'action' => $action,
                'user' => $user,
                'learningtype' => $learningtype,
                'search' => $search,
                'allocatecourse' => $allocatecourse
            ]
        );

        $allocationcourses = json_decode($allocatecourse);

        $courseallocation_lib = new courseallocation_lib();

        if($action == 'courseallocate'){
            $return = $courseallocation_lib->courseallocation($learningtype, $user, $allocationcourses);
        }

        return [
            'records' => $return
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  modulecourseallocation_returns() {
        return new external_single_structure([
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'enrolledornot' => new external_value(PARAM_BOOL, 'enrolledornot'),
                                    'return_status' => new external_value(PARAM_RAW, 'return_status')
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
    public static function teamapprovalsview_parameters() {
        return new external_function_parameters([
                'action' => new external_value(PARAM_RAW, 'action of the request'),
                'learningtype' => new external_value(PARAM_RAW, 'learningtype',VALUE_OPTIONAL),
                'search' => new external_value(PARAM_RAW, 'search'),
        ]);
    }

    /**
     * Based on selected we are getting modules data
     *
     * @param int $action need to give name of the action
     * @param int $user need to give selected userid
     * @param int $learningtype need to send learning type
     * @param int $search searchvalue
     * @param int $allocatecourse if select any course.
     * @return array The allocation.
     */
    public static function teamapprovalsview(
        $action,
        $learningtype,
        $search
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/myteam/lib.php');
        require_login();
        $PAGE->set_url('/local/myteam/team.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::teamapprovalsview_parameters(),
            [
                'action' => $action,
                'learningtype' => $learningtype,
                'search' => $search,
            ]
        );

        $team_approvals = new team_approvals();

        if($action == 'change_learningtype'){
            $search = '';
        }

        $return = $team_approvals->team_approval_records_list($learningtype, '');

        return [
            'records' => $return
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  teamapprovalsview_returns() {
         return new external_single_structure([
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'disattr' => new external_value(PARAM_RAW, 'disattr',VALUE_OPTIONAL),
                                    'checked' => new external_value(PARAM_RAW, 'checked',VALUE_OPTIONAL),
                                    'requestid' => new external_value(PARAM_INT, 'requestid'),
                                    'actualcomponentname' => new external_value(PARAM_RAW, 'actualcomponentname',VALUE_OPTIONAL),
                                    'componentname' => new external_value(PARAM_RAW, 'componentname'),
                                    'icons' => new external_value(PARAM_RAW, 'icons')
                                    
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
    public static function teamapprovalsactions_parameters() {
        return new external_function_parameters([
                'action' => new external_value(PARAM_RAW, 'action of the request'),
                'requeststoapprove' => new external_value(PARAM_RAW, 'requeststoapprove'),
                'learningtype' => new external_value(PARAM_RAW, 'learningtype')
        ]);
    }

    /**
     * Based on selected we are getting modules data
     *
     * @param int $action need to give name of the action
     * @param int $requeststoapprove if select any request.
     * @return array The allocation.
     */
    public static function teamapprovalsactions(
        $action,
        $requeststoapprove,
        $learningtype
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/myteam/lib.php');
        require_login();
        $PAGE->set_url('/local/myteam/team.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::teamapprovalsactions_parameters(),
            [
                'action' => $action,
                'requeststoapprove' => $requeststoapprove,
                'learningtype' => $learningtype
            ]
        );

        $requestapproves = json_decode($requeststoapprove);

        $team_approvals = new team_approvals();

        if($action == 'requestapproved'){
            $return = $team_approvals->team_requests_approved($learningtype, $requestapproves);
        }
        return [
            'records' => $return
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  teamapprovalsactions_returns() {
        return new external_single_structure([
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'approverequest' => new external_value(PARAM_INT, 'approverequest')
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
    public static function myteamdisplaymodulewise_parameters() {
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
     * @param int $limit Maximum number of results to return
     * @param int $contextid context of getting the data.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function myteamdisplaymodulewise(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/myteam/lib.php');
        require_login();
        $PAGE->set_url('/local/myteam/team.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::myteamdisplaymodulewise_parameters(),
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

        $userclass = '\local_'.$decodedata->moduletype.'\local\user';
        
        if(class_exists($userclass)){
            $pluginclass = new $userclass;
            $moduletype = $decodedata->moduletype;
            if(method_exists($userclass, 'enrol_get_users_'.$moduletype)){
                $functionname = 'enrol_get_users_'.$moduletype;
                $usermodulecount = $pluginclass->$functionname($decodedata->userid);
            }
            if(method_exists($userclass, 'user_modulewise_content')){
                $data = $pluginclass->user_modulewise_content($decodedata->userid,$offset,$limit);
            }
        }else if($decodedata->moduletype == 'supervisorevaluation'){
            $moduletype = $decodedata->moduletype;
            $userclass = '\local_evaluation\local\user';
            if(class_exists($userclass)){
                $pluginclass = new $userclass;
                if(method_exists($userclass, 'enrol_get_users_supervisor_evaluation')){
                    $functionname = 'enrol_get_users_supervisor_evaluation';
                    $usermodulecount = $pluginclass->$functionname($decodedata->userid);
                }
                if(method_exists($userclass, 'supervisor_user_modulewise_content')){
                    $data = $pluginclass->supervisor_user_modulewise_content($decodedata->userid,$offset,$limit);
                }
            }
        }

        return [
            'evaluation' => $moduletype == 'supervisorevaluation' ? TRUE : FALSE,
            'totalcount' => $usermodulecount['count'],
            'records' =>$data->navdata,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  myteamdisplaymodulewise_returns() {
        return new external_single_structure([
            'evaluation' => new external_value(PARAM_BOOL, 'Evaluation or not'),
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of users in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'name' => new external_value(PARAM_RAW, 'name', VALUE_OPTIONAL),
                                    'code' => new external_value(PARAM_RAW, 'code', VALUE_OPTIONAL),
                                    'enrolldate' => new external_value(PARAM_RAW, 'enrolldate', VALUE_OPTIONAL),
                                    'status' => new external_value(PARAM_RAW, 'status', VALUE_OPTIONAL),
                                    'completiondate' => new external_value(PARAM_RAW, 'Completion date', VALUE_OPTIONAL),
                                    'evaluation_button' => new external_value(PARAM_RAW, 'evaluation action button', VALUE_OPTIONAL),
                                )
                            )
                        )
        ]);
    }
}
