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
 * @package BizLMS
 * @subpackage local_learningplan
 */

use local_learningplan\lib\lib;
use local_learningplan\render\view;
require_once(dirname(__FILE__) . '/../../config.php');

global $DB, $USER, $CFG,$PAGE,$OUTPUT;
require_once($CFG->dirroot . '/local/learningplan/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$curr_tab = optional_param('tab', 'courses', PARAM_TEXT);
$condition = optional_param('condtion','view', PARAM_TEXT);
// $enrol=optional_param('enrolid', 0, PARAM_INT);
$course_enrol=optional_param('courseid', 0, PARAM_INT);
$checkingid=optional_param('couid', 0, PARAM_INT);
$userid=optional_param('userid', 0, PARAM_INT);
$planid=optional_param('planid', 0, PARAM_INT);
$systemcontext = (new \local_learningplan\lib\accesslib())::get_module_context($id);
//check the context level of the user and check whether the user is login to the system or not
$PAGE->set_context($systemcontext);
require_login();
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_learningplan/lpcreate', 'load', array());
$PAGE->requires->js_call_amd('local_learningplan/courseenrol', 'tabsFunction', array('id' => $id));
//This js added by sharath for moduletypw selection in assigning courses
//$PAGE->requires->js_call_amd('local_learningplan/module', 'init', array());


$PAGE->set_url('/local/learningplan/plan_view.php', array('id' => $id));
$PAGE->set_title(get_string('pluginname', 'local_learningplan'));
$PAGE->set_pagelayout('standard');
//Header and the navigation bar
$plan_record = $DB->get_record('local_learningplan', array('id' => $id));
$PAGE->set_heading($plan_record->name);
$PAGE->navbar->ignore_active();
$PAGE->navbar->add( get_string('pluginname', 'local_learningplan'), new moodle_url('/local/learningplan/index.php'));
$PAGE->navbar->add($plan_record->name);
$learningplan = $DB->get_record('local_learningplan',array('id' => $id));
$is_enrolled = $DB->record_exists('local_learningplan_user',  array('planid' => $id, 'userid' => $USER->id));
if(!($is_enrolled || is_siteadmin() || has_capability('local/learningplan:manage', $systemcontext))){

    // $is_Oh = has_capability('local/costcenter:manage_ownorganization', $systemcontext);
    // $costcenterid=(new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=1);
    // if($is_Oh && explode('/', $learningplan->open_path)[1] != $costcenterid){
    //     redirect($CFG->wwwroot . '/local/learningplan/index.php');
    // }
    
    // if ((has_capability('local/costcenter:manage_owndepartments', $systemcontext))) {
    //         $learningplans = $DB->get_record('local_learningplan',array('id'=>$id),$fields = 'id');
    //         $costcenterid=(new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=2);
    //         if(!in_array(explode('/', $learningplan->open_path)[2],explode(',',$costcenterid))){
    //              redirect($CFG->wwwroot . '/local/learningplan/index.php');  
    //          }
    // }
    // echo 'teting';exit;
    $sql="SELECT lp.id ";
    $sql.=" FROM {local_learningplan} lp WHERE id = :id "; 
    $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path');
    $learningplans=$DB->get_records_sql($sql .$costcenterpathconcatsql,array('id'=>$id));
    if(empty($learningplans)){
        redirect($CFG->wwwroot . '/local/learningplan/index.php');  
    }
 }
if(!is_siteadmin()){
    require_capability('local/learningplan:manage', $systemcontext);
}

$PAGE->requires->js_call_amd('local_learningplan/courseenrol', 'load', array());
$learningplan_renderer = new view();
$learningplan_lib = new lib();
$return_url = new moodle_url('/local/learningplan/plan_view.php',array('id'=>$id));
echo $OUTPUT->header();
echo $learningplan_renderer->get_editand_publish_icons($id);
if($id <= 0){
    print_error('invalid_learningplan_id', 'local_learningplan');
}
/** Assign Users to learning plan code **/
// if($enrol>0){ /***condition to assign or enrol users to LEP***/
//     $data = new stdClass();
//     $data->planid = $enrol;
//     $data->userid = $USER->id;
//     $data->timecreated = time();
//     $data->usercreated = $USER->id;
//     $data->timemodified = 0;
//     $data->usermodified = 0;
//     $create_record = $learningplan_lib->assign_users_to_learningplan($data);/**Function to assign users**/
//     /**Function to Send Notification after enrol by user**/
//     $users=array();
//     $users[]=$USER->id;
//     $notification=$learningplan_lib->notification_for_user_enrol($users,$data);
//     /**********************End of the Function*****************/
//     $return_url = new moodle_url('/local/learningplan/view.php',array('id'=>$id));
//     redirect($return_url);
// }
/**The query Check Whether user enrolled to LEP or NOT**/
$sql="SELECT id FROM {local_learningplan_user} WHERE planid = :planid AND userid = :userid ";
$check=$DB->get_record_sql($sql, array('planid' => $id, 'userid' => $USER->id));
/*End of Query*/

/**The Below query is check the approval status for the LOGIN USERS on the his LEP**/
// $check_approvalstatus=$DB->get_record('local_learningplan_approval',array('planid'=>$plan_record->id,'userid'=>$USER->id));
/*End of Query*/

// if($check){ /**condition to check user already enrolled to the LEP If Enroled he get option enrolled **/
// }else{/**Else he has 4 option like the Send Request or Waiting or Rejected or Enroled**/
//         if(!is_siteadmin()){
//             if($condition!='manage'){ /**condition to check the manage page or browse page**/
            
//                 if($plan_record->approvalreqd==1  && (!empty($check_approvalstatus))) 
//                 /**If user has LEP with approve with 1 means request yes and empty not check approval status means he has sent request**/
//                 {    
//                     $check_users= $learningplan_lib->check_courses_assigned_target_audience($USER->id,$plan_record->id);
//                     /**The above Function is to check the user is present in the target audience or not**/
                    
//                     if($check_users==1){/*if there then he will be shown the options*/
                    
//                     $check_approvalstatus=$DB->get_record('local_learningplan_approval',array('planid'=>$plan_record->id,'userid'=>$USER->id));
                    
//                     if($check_approvalstatus->approvestatus==0 && !empty($check_approvalstatus)){
//                     $back_url = "#";
//                     echo html_writer::link($back_url, 'Waiting', array('class' => 'pull-right actions nourl'));  
//                     }elseif($check_approvalstatus->approvestatus==2 && !empty($check_approvalstatus)){
//                     $back_url = "#";
//                     echo html_writer::link($back_url, 'Rejected',array('class' => 'pull-right actions','title'=>'Your Request has been Rejected contact supervisor'));
//                     }    
//                     if(empty($check_approvalstatus)){
//                     $notify = new stdClass();
//                     $notify->name = $plan_record->name;
//                     $PAGE->requires->event_handler("#enroll",
//                  'click', 'M.util.bajaj_show_confirm_dialog', array('message' => get_string('enroll_notify','local_learningplan',$notify),
//                                                          'callbackargs' => array('confirmdelete' =>$plan_record->id)));
//                     }
//                     }
//                 }else if(($plan_record->approvalreqd==1) && (empty($check_approvalstatus))){
                
//                     $check_users= $learningplan_lib->check_courses_assigned_target_audience($USER->id,$plan_record->id);
                    
//      //                if($check_users==1){   
//      //                echo  html_writer::link(new moodle_url('/local/learningplan/index.php', array('approval' => $plan_record->id)),
//      //                'Send Request', array('class' => 'pull-right enrol_to_plan nourl','id'=>'request'));
//      //                $notify_info = new stdClass();
//      //                $notify_info->name = $plan_record->name;
//      //                $PAGE->requires->event_handler("#request",
//                  // 'click', 'M.util.bajaj_show_confirm_dialog', array('message' => get_string('delete_notify','local_learningplan',$notify_info),
//                  //                                      'callbackargs' => array('confirmdelete' =>$plan_record->id)));
                    
//      //                }
//                 }else if($plan_record->approvalreqd==0  && (empty($check_approvalstatus))){
//                     $notify = new stdClass();
//                     $notify->name = $plan_record->name;
//                     $PAGE->requires->event_handler("#enroll",
//                  'click', 'M.util.bajaj_show_confirm_dialog', array('message' => get_string('enroll_notify','local_learningplan',$notify),
//                                                          'callbackargs' => array('confirmdelete' =>$plan_record->id)));
//                 }
//             }
//         }
// }/** End of condtion **/


// $learning_plan_assigned = $DB->record_exists('local_learningplan_user', array('planid' => $id, 'userid' => $USER->id));

echo $learningplan_renderer->single_plan_view($id);

// if(is_siteadmin() || has_capability('local/costcenter:assign_multiple_departments_manage', $systemcontext)){ /**Condition for to whom the tab should display**/
/*view of the tabs*/
    echo $learningplan_renderer->plan_tabview($id, $curr_tab,$condition);

// }
// else{ 
    
//     if($condition=='manage'){
//         /*condition to check the to browse page or manage page*/
//         /*view of the tabs*/    
//         echo $learningplan_renderer->plan_tabview($id, $curr_tab,$condition);
//     }
   
//     if($condition!='manage' && (empty($checkingid))){
    
//     echo $learningplan_renderer->assigned_learningplans_courses_employee_view($id, $USER->id,$condition);
//     }
//     // if($checkingid){
//     //     $check_approvalstatus=$DB->get_record('local_learningplan_approval',array('planid'=>$plan_record->id,'userid'=>$USER->id));
         
//     //     if(($plan_record->approvalreqd==1 && $check_approvalstatus->approvestatus==1) || ($plan_record->approvalreqd!=1 && empty($check_approvalstatus))){   
//     //     $condition='';
//     //     echo $learningplan_renderer->assigned_learningplans_courses_browse_employee_view($id, $USER->id,$condition);
//     //     }  
//     // } 
// }
/*end of the condition*/
// echo "
// <script>
// console.log($('#request'));
// $('#request').on('click', function() {

//     $('#request').disable();
// });
// </script>
// ";
echo $OUTPUT->footer();
?>
