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

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $USER, $CFG,$PAGE,$OUTPUT;
require_once($CFG->dirroot . '/local/learningplan/lib.php');
$systemcontext = (new \local_learningplan\lib\accesslib())::get_module_context();
//check the context level of the user and check whether the user is login to the system or not
$PAGE->set_context($systemcontext);
require_login();

$planid = optional_param('planid', 0, PARAM_INT);
$new_plan_courses = optional_param('learning_plan_courses', ' ', PARAM_RAW);
$new_plan_users = optional_param('learning_plan_users', ' ', PARAM_RAW);
$type = optional_param('type', '', PARAM_TEXT);
$condtion = optional_param('condtion', '', PARAM_TEXT);
$unassigncourse = optional_param('unassigncourse', 0, PARAM_INT);
$instance = optional_param('instance', 0, PARAM_INT);
$unassignuser = optional_param('unassignuser', 0, PARAM_INT);
$action=optional_param('order','',PARAM_ALPHANUMEXT);
$instanceid = optional_param('instance', 0, PARAM_INT);
$condtion_lep = optional_param('row', 0, PARAM_INT);
$base_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid));

$data=data_submitted();

if(isset($data->submit)=='Submit'){
   $next=$data->next;
   $rows=$data->row;
   foreach (array_combine($rows, $next) as $code => $name){
      echo $code;
      echo $name;
      // $execute=$DB->execute("update {local_learningplan_courses} set nextsetoperator ='$name' where id=$code");
      $updaterecord = new \stdClass();
      $updaterecord->id = $code; 
      $updaterecord->nextsetoperator = $name;
      $updaterecord->timemodified = time();
      $DB->update_record('local_learningplan_courses', $updaterecord); 
   }
   $ret = new moodle_url('/local/learningplan/plan_view.php', array('id' => $data->plan,'condtion'=>'manage'));
   redirect($ret);  
}
$learningplan_lib = new local_learningplan\lib\lib();

if($type == 'assign_courses'){
   $existing_plan_courses_record = $DB->get_records('local_learningplan_courses', array('planid'=> $planid));
   $existing_plan_timecreated = $DB->get_record('local_learningplan_courses', array('planid'=> $planid));
    
   $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid, 'tab' => 'courses')); 
   if(!empty($new_plan_courses)){          
      foreach($new_plan_courses as $plan_course){
         $i=0;
         $data = new stdClass();
         $data->planid = $planid;
         $data->courseid = $plan_course;
         $data->nextsetoperator='or';
         $data->timecreated = time();
         $data->usercreated = $USER->id;
         $data->timemodified = 0;
         $data->usermodified = 0;
         /**Check The sort order max and insert next value**/
         $sql="select  MAX(sortorder) as sort from {local_learningplan_courses} where planid=$planid";
         $last_order=$DB->get_record_sql($sql);
                
         if($last_order->sort>=0 && $last_order->sort!=''){/**Condition to check sort order and increment the sort value**/              
            $i=$last_order->sort+1;
            $data->sortorder=$i;
         }else{       
            $data->sortorder=$i;
            $i++;     
         }
         $create_record = $learningplan_lib->assign_courses_to_learningplan($data);
      }
   }
   if($condtion=='manage'){
      $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid,'condtion'=>'manage','tab' => 'courses'));
      redirect($return_url);
   }else{
      redirect($return_url);
   }
}elseif($type == 'assign_users'){
   $existing_plan_users_record = $DB->get_records('local_learningplan_user', array('planid'=> $planid));
   $existing_plan_timecreated = $DB->get_record('local_learningplan_user', array('planid'=> $planid));
   $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid, 'tab' => 'users'));
   if(!empty($new_plan_users)){
      foreach($new_plan_users as $plan_user){
         $data = new stdClass();
         $data->planid = $planid;
         $data->userid = $plan_user;
         $data->timecreated = time();
         $data->usercreated = $USER->id;
         $data->timemodified = 0;
         $data->usermodified = 0;
         $create_record = $learningplan_lib->assign_users_to_learningplan($data);
      }
      $notification=$learningplan_lib->notification_for_user_enrol($new_plan_users,$data);
   }
   if($condtion=='manage'){
      $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid,'condtion'=>'manage','tab' => 'users'));
      redirect($return_url);
   }else{
      redirect($return_url);
   }
}
if($unassigncourse > 0 && $planid > 0){ 
   $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid,'condtion'=>'manage','tab' => 'courses')); 
   $data = new stdClass();
    
   $course_record = $DB->get_record('local_learningplan_courses', array('planid' => $planid, 'id' => $unassigncourse));  
   if(!empty($course_record)){
      /*If record found then we start for delete the course*/
      $delete_data = new stdClass();
      $delete_data->id = $course_record->id;
      $delete_data->planid = $planid;
      $delete_data->courseid = $course_record->courseid;
      $delete_record = $learningplan_lib->delete_courses_to_learningplan($delete_data);/*function to delete the course in LEP lib*/
      redirect($return_url);
   }
}

/*Function To Unenrol and delete the Users from the list*/
if($unassignuser > 0 && $planid > 0){
   $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid,'condtion'=>'manage','tab' => 'users')); 
   $data = new stdClass();
   $user_record = $DB->get_record('local_learningplan_user', array('planid' => $planid, 'userid' => $unassignuser));
   if(!empty($user_record)){
      $delete_data = new stdClass();
      $delete_data->id = $user_record->id;
      $delete_data->planid = $planid;
      $delete_data->userid = $unassignuser;
      $delete_record = $learningplan_lib->delete_users_to_learningplan($delete_data);
      redirect($return_url);
   }
}
if($type=='bulkusers'){
   $planid = $_POST['planid'];
   $return_url = new moodle_url('/local/learningplan/lpusers_enroll.php', array('lpid' => $planid));
   if(isset($_POST['submit_users']) && $_POST['submit_users']=='add users'){    
      $users=$_POST['remove_users'];
     
      foreach($users as $user){
         $data = new stdClass();
         $data->planid = $_POST['planid'];
         $data->userid = $user;
         $data->timecreated = time();
         $data->usercreated = $USER->id;
         $data->timemodified = 0;
         $data->usermodified = 0;
         $create_record = $learningplan_lib->assign_users_to_learningplan($data);
      }
      $notification=$learningplan_lib->notification_for_user_enrol($users,$data);
      redirect($return_url); 
   }elseif(isset($_POST['submit_users']) && $_POST['submit_users']=='remove users'){
      $users=$_POST['add_users'];
      foreach($users as $user){
         $data = new stdClass();
         $data->planid = $_POST['planid'];
         $data->userid = $user;
         $data->timecreated = time();
         $data->usercreated = $USER->id;
         $data->timemodified = 0;
         $data->usermodified = 0;
         $create_record = $learningplan_lib->delete_users_to_learningplan($data);
      }
      redirect($return_url); 
   }
}
$sql="SELECT id,courseid,planid,sortorder FROM {local_learningplan_courses} WHERE planid=:planid ORDER BY sortorder ASC";
$instances = $DB->get_records_sql($sql, array('planid' => $planid));
$plugins   = enrol_get_plugins(false);
if($planid && $action) {
   if(isset($instances[$instance])) {
    //if (isset($instances[$instanceid]) and isset($plugins[$instances[$instanceid]->courseid])) {
      if ($action === 'up') {
         $order = array_keys($instances);
         $order = array_flip($order);
         $pos = $order[$instanceid];
         if($pos > 0) {
            $switch = $pos - 1;
            $resorted = array_values($instances);
            $temp = $resorted[$pos];
            $resorted[$pos] = $resorted[$switch];
            $resorted[$switch] = $temp;
            // now update db sortorder
            foreach ($resorted as $sortorder=>$instance) {
               if ($instance->sortorder != $sortorder) {
                  $instance->sortorder = $sortorder;
                  $instance->timemodified = time();
                  $da=$DB->update_record('local_learningplan_courses', $instance);
               }
            }
         }       
         $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid,'condtion'=>'manage'));
         redirect($return_url); 
      }else if ($action === 'down') {
         $order = array_keys($instances);
         $order = array_flip($order);
         $pos = $order[$instance];
         if($pos < (count($instances) - 1)) {
            $switch = $pos + 1;
            $resorted = array_values($instances);
            echo "Restore";
            $temp = $resorted[$pos];
            echo "Temp";
            echo "Position".$pos;
            $resorted[$pos] = $resorted[$switch];
            $resorted[$switch] = $temp;
            foreach ($resorted as $sortorder=>$instanced) {
               if ($instanced->sortorder != $sortorder) {
                  echo "check1";
                  $instanced->sortorder = $sortorder;
                  $instanced->timemodified = time();
                  $da=$DB->update_record('local_learningplan_courses', $instanced);
               }
            }
         }
         $return_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $planid,'condtion'=>'manage'));
         redirect($return_url);
      }
   }
}
redirect($base_url);
?>