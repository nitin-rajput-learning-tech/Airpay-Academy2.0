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
namespace local_myteam\output;

defined('MOODLE_INTERNAL') || die;
use core_component;
use local_myteam\output\team_approvals_lib;
use local_request\api\requestapi;

class team_approvals {
    public function team_approvals_view($value =false,$search = false) {
        global $CFG, $USER, $PAGE,$OUTPUT;
        $teamapprovals = array();
        $teamapprovals['existplugins'] = $this->team_approval_actions();
        
        $teamapprovals['teamusers'] = $this->team_approval_records_list();

        if($value){
            return $OUTPUT->render_from_template('local_myteam/teamapprovals', $teamapprovals);
        }else{
            $data = array();
            $data[] = $teamapprovals;
            return $data;
        }
    }

    public function team_approval_actions(){
        $core_component = new core_component();
        $courses_exists = false;
        $course_plugin = $core_component::get_plugin_directory('local', 'courses');
        if(!empty($course_plugin)){
            $courses_exists = true;
        }
        $classroom_exists = false;
        $classroom_plugin = $core_component::get_plugin_directory('local', 'classroom');
        if(!empty($classroom_plugin)){
            $classroom_exists = true;
        }
        $program_exists = false;
        $program_plugin = $core_component::get_plugin_directory('local', 'program');
        if(!empty($program_plugin)){
            $program_exists = true;
        }


        $existplugins = array();
        $existplugins['coursesexist'] = $courses_exists;
        $existplugins['classroomexist'] = $classroom_exists;
        $existplugins['programexist'] = $program_exists;

        $data = array();
        $data[] = $existplugins;
        return $existplugins;
    }

    public function team_approval_records_list($learningtype = 'elearning', $search=""){
        global $DB, $OUTPUT;
        $team_approvals_lib = new team_approvals_lib();
        $team_requests = $team_approvals_lib->get_team_approval_requests($learningtype, $search);
        $requestsdata = array();
        $classroom=false;
        if(!empty($team_requests)){

            foreach ($team_requests as $team_request) {
                $requestid = $team_request->id;
                $request_user = $team_request->createdbyid;
                $component_name = $team_request->compname;
                $componentid = $team_request->componentid;
                $status = $team_request->status;
                $requested_user = \core_user::get_user($request_user);
                $requested_username = fullname($requested_user);
                $actual_component_name = get_string('requestedfor','local_myteam',$requested_username);
                if($component_name == 'classroom'){//for classrooms
                    $icons = 'tasks';
                    $classroom=true;
                    // $actual_component_name = fullname($requested_user).' has requested for ';
                    // $actual_component_name = get_string('requestedfor','local_myteam',$requested_username);
                    $actual_component_name .= $DB->get_field('local_classroom', 'name', array('id' => $componentid, 'visible' => 1));
                }elseif($component_name == 'learningplan'){//for learningplans
                    $icons = 'map';
                    // $actual_component_name = fullname($requested_user).' has requested for ';
                    $actual_component_name .= $DB->get_field('local_learningplan', 'name', array('id' => $componentid, 'visible' => 1));
                }elseif($component_name == 'program'){//for program
                    $icons = 'tasks';
                    // $actual_component_name = fullname($requested_user).' has requested for ';
                    $actual_component_name .= $DB->get_field('local_program', 'name', array('id' => $componentid, 'visible' => 1));
                }else{//default for courses/e-learning
                    $icons = 'book';
                    // $actual_component_name = fullname($requested_user).' has requested for ';
                    $actual_component_name .= $DB->get_field('course', 'fullname', array('id' => $componentid, 'visible' => 1));
                }
                
                if($status == 'PENDING' || $status == 'REJECTED'){
                    $checked = '';
                    $disattr = '';
                }else{
                    $checked = 'checked';
                    $disattr = 'disabled';
                }


                $returndata = array();
                $returndata['disattr'] = $disattr;
                $returndata['checked'] = $checked;
                $returndata['requestid'] = $requestid;
                $returndata['componentname'] = $component_name;
                $returndata['actualcomponentname'] = $actual_component_name;
                $returndata['icons'] = $icons;
                $returndata['classroom_check'] =$classroom;

                $requestsdata[] = $returndata;
            }
        }
        return $requestsdata;
    }

    public function team_requests_approved($learningtype, $requeststoapprove){
        global $DB;
        $return = array();
        if(empty($learningtype) || empty($requeststoapprove)){
            $return['approverequest'] = 0;
            return $return;
        }
        $requestapi = new requestapi();
        //$requeststoapprove = explode(',', $requeststoapprove);
        
        foreach ($requeststoapprove as $request) {
            $record_exists = $DB->record_exists('local_request_records', array('id' => $request, 'status' => 'APPROVED'));
            if(!$record_exists){
                $return['approverequest'] = $requestapi->approve($request);
            }else{
                $return['approverequest'] = 0;
            }
        }
        
        $data = array();
        $data[] = $return;

        return $data;
    }

}
