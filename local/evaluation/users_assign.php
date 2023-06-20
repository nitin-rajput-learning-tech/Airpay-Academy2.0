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
 * @subpackage local_evaluation
 */


define('NO_OUTPUT_BUFFERING', true);
global $DB, $OUTPUT,$USER,$CFG,$PAGE;
require_once(dirname(__FILE__) . '/../../config.php');
use core_component;
$pagenavurl = new moodle_url('/local/evaluation/index.php');
require_once($CFG->dirroot.'/local/evaluation/lib.php');
$corecomponent = new core_component();
$evaluationid=optional_param('id',0, PARAM_INT);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
$submitvalue = optional_param('submitvalue','', PARAM_RAW);
$add = optional_param('add',array(), PARAM_RAW);
$remove=optional_param('remove',array(), PARAM_RAW);
$view=optional_param('view','page', PARAM_RAW);
$type=optional_param('type','', PARAM_RAW);
$lastitem=optional_param('lastitem',0, PARAM_INT);
$countval = optional_param('countval', 0, PARAM_INT);
$sesskey=sesskey();
require_login();
$url = new moodle_url('/local/evaluation/users_assign.php', array('id' => $evaluationid));

$evaluation = $DB->get_record('local_evaluations', array('id'=>$evaluationid));
$context = (new \local_evaluation\lib\accesslib())::get_module_context();
if (empty($evaluation)) {
  print_error(get_string('feedback_not_found', 'local_evaluation'));
}
if (!has_capability('local/evaluation:enroll_users', $context) ) {
    print_error(get_string('dont_have_permission', 'local_evaluation'));
}
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
if( !$add && !$remove ){
$PAGE->set_heading($evaluation->name ." : ". get_string("add_remove_users", 'local_evaluation'));
}
$PAGE->set_title($evaluation->name ." : ".get_string("add_remove_users", 'local_evaluation'));
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string("manageevaluation", 'local_evaluation'), new moodle_url($pagenavurl));
$PAGE->navbar->add($evaluation->name,new moodle_url('eval_view.php',array('id'=>$evaluationid)));
$PAGE->navbar->add(get_string("enrolusers", 'local_evaluation'));
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->js('/local/classroom/js/jquery.bootstrap-duallistbox.js',true);
$PAGE->requires->css('/local/classroom/css/bootstrap-duallistbox.css');
if($view == 'ajax'){
  if(is_string($_GET["options"])){
    $options = json_decode($_GET["options"], false);
  }else{
      $options = $_GET["options"];  
  }
  $selectfromusers=evaluation_enrolled_users($type,$evaluationid,$options,false,$offset1=-1,$perpage=50,$countval);
	echo json_encode($selectfromusers);
	exit;
}
echo $OUTPUT->header();

$time = time();
if ($evaluation->timeclose AND ($time >= $evaluation->timeclose) AND !$add AND !$remove) {
	echo get_string("warning_enrol", 'local_evaluation');
}
$data = data_submitted();
if ($evaluationid) {
  $organization = null;
  $department   = null;
  $email        = null;
  $idnumber     = null;
  $uname        = null;
  $groups        = null;
  $location     = null;
  $hrmsrole     = null;
  if(file_exists($CFG->dirroot.'/local/lib.php')){
      require_once($CFG->dirroot.'/local/lib.php');
      $filterlist = get_filterslist();
  }
  $pluginexists = $corecomponent::get_plugin_directory('local', 'courses');
  if(!empty($pluginexists)) {
      require_once($CFG->dirroot . '/local/courses/filters_form.php');
      $datasubmitted = data_submitted();
      $mform = new filters_form($url, array('filterlist'=>$filterlist,'enrolid'=>0, 'courseid'=>$evaluationid, 'action' => 'user_enrolment')+(array)$datasubmitted);
      if ($mform->is_cancelled()) {
        redirect($url);
      } else {
        $filterdata =  $mform->get_data();
        if($filterdata){
            $collapse = false;
            $show = 'show';
        } else{
            $collapse = true;
            $show = '';
        }
        $organization = !empty($filterdata->filteropen_costcenterid) ? implode(',', (array)$filterdata->filteropen_costcenterid) : null;
        $department = !empty($filterdata->filteropen_department) ? implode(',', (array)$filterdata->filteropen_department) : null;
        $subdepartment = !empty($filterdata->filteropen_subdepartment) ? implode(',', (array)$filterdata->filteropen_subdepartment) : null;
        $department4level = !empty($filterdata->filteropen_level4department) ? implode(',', (array)$filterdata->filteropen_level4department) : null;
        $email = !empty($filterdata->email) ? implode(',', (array)$filterdata->email) : null;
        $idnumber = !empty($filterdata->idnumber) ? implode(',', (array)$filterdata->idnumber) : null;
        $uname = !empty($filterdata->users) ? implode(',', (array)$filterdata->users) : null;
        $groups = !empty($filterdata->groups) ? implode(',', (array)$filterdata->groups) : null;
        $location = !empty($filterdata->location) ? implode(',', (array)$filterdata->location) : null;
        $hrmsrole = !empty($filterdata->hrmsrole) ? implode(',', (array)$filterdata->hrmsrole) : null;
      }
    // Create the user selector objects.
    $options = array('context' => $context->id, 'evaluationid' => $evaluationid, 'organization' => $organization, 'department' => $department,'subdepartment'=>$subdepartment,'department4level'=>$department4level, 'email' => $email, 'idnumber' => $idnumber, 'uname' => $uname, 'groups' => $groups, 'location' => $location, 'hrmsrole' => $hrmsrole);
    if ( $add AND confirm_sesskey()) {
        
        if($submitvalue == "Add_All_Users"){
					$options = (array)json_decode($_REQUEST["options"],false);
              $userstoassign=array_flip(evaluation_enrolled_users('add',$evaluationid,(array)$options,false,$offset1=-1,$perpage=-1));
        }else{
            $userstoassign =$add;
        }
     
        if (!empty($userstoassign)) {
					
					$progress = 0;
					$progressbar = new \core\progress\display_if_slow(get_string('enrollusers', 'local_evaluation',$evaluation->name));
					$progressbar->start_html();
					$progressbar->start_progress('',count($userstoassign)-1);
					
          $submitted = new stdClass();
          $submitted->timemodified = time();
          $submitted->timecreated = time();
          $type = 'feedback_enrollment';
          $dataobj = $evaluationid;
          $fromuserid = $USER->id;
          foreach($userstoassign as $key=>$add_user){
						$progressbar->progress($progress);
						$progress++;
            $submitted->userid = $add_user;
            $submitted->evaluationid = $evaluationid;
            $submitted->creatorid = $USER->id;
            $exist = $DB->record_exists('local_evaluation_users',array('userid'=>$add_user,'evaluationid'=>$evaluationid));
            
            if(empty($exist)) {
              $insert = $DB->insert_record('local_evaluation_users',$submitted);
              // Trigger evaluation enrolled event.
              $params = array(
                  'context' => $context,
                  'relateduserid' => $add_user,
                  'objectid' => $evaluation->id
              );
              $event = \local_evaluation\event\evaluation_enrolled::create($params);
              $event->add_record_snapshot('local_evaluation', $evaluation);
              $event->trigger();
              // db transaction for email notification
              $notification = new \local_evaluation\notification();

              //Here check if the feedback is of type supervisor or self.If supervisor then emails will be sent to only supervisor of that user//
              if($evaluation->evaluationmode=='SP'){

                //Find the supervisor of the corresponding added user//
                $supervisor = $DB->get_record('user', array('id'=>$add_user),'open_supervisorid');

                $touser=0;

                if($supervisor){
                  $touser= \core_user::get_user($supervisor->open_supervisorid);
                }

              }else{

                $touser = \core_user::get_user($add_user);
              }

              if($touser){
                $logemail = $notification->evaluation_notification($type, $touser, $USER, $evaluation);
              }              
              
            }
          } //end of for-each

					$progressbar->end_html();
					$result=new stdClass();
					$result->changecount=$progress;
					$result->feedback=$evaluation->name; 

					echo $OUTPUT->notification(get_string('enrolluserssuccess', 'local_evaluation',$result),'success');
					$button = new single_button($url, get_string('click_continue','local_evaluation'), 'get', true);
					$button->class = 'continuebutton';
					echo $OUTPUT->render($button);
          echo $OUTPUT->footer();
					die();
        }
    }
    if ( $remove && confirm_sesskey()) {
        
        if($submitvalue=="Remove_All_Users"){
					$options = (array)json_decode($_REQUEST["options"],false);
             $userstounassign = array_flip(evaluation_enrolled_users('remove',$evaluationid,(array)$options,false,$offset1=-1,$perpage=-1));
        }else{
            $userstounassign = $remove;
        }
        if (!empty($userstounassign)) {
					$progress = 0;
					$progressbar = new \core\progress\display_if_slow(get_string('un_enrollusers', 'local_evaluation',$evaluation->name));
					$progressbar->start_html();
					$progressbar->start_progress('', count($userstounassign)-1);
          foreach($userstounassign as $key=>$remove_user){
						$progressbar->progress($progress);
						$progress++;
            $getrecord = $DB->get_record('local_evaluation_users',array('evaluationid'=>$evaluationid,'userid'=>$remove_user));
            $deleterecord = $DB->delete_records('local_evaluation_users',array('id'=>$getrecord->id));
            // will delete all responses of particular user if he unenrolled from evaluation
            $deletedid = $DB->get_record('local_evaluation_completed',array('evaluation'=>$evaluationid,'userid'=>$remove_user));
            if ($deletedid) {
              $evaluationstructure = new local_evaluation_completion($evaluation, true, $deletedid->id);
              evaluation_delete_completed($evaluationstructure->get_completed(), $evaluation);
            }
            // Trigger evaluation unenrolled event.
            $params = array(
                'context' => $context,
                'relateduserid' => $remove_user,
                'objectid' => $evaluation->id
            );
            $event = \local_evaluation\event\evaluation_unenrolled::create($params);
            $event->add_record_snapshot('local_evaluation', $evaluation);
            $event->trigger();


            //For unenrollment triggering notification
            $notification = new \local_evaluation\notification();

            //Here check if the feedback is of type supervisor or self.If supervisor then emails will be sent to only supervisor of that user//

            if($evaluation->evaluationmode=='SP'){

                //Find the supervisor of the corresponding added user//
                $supervisor = $DB->get_record('user', array('id'=>$remove_user),'open_supervisorid');

                $touser=0;

                if($supervisor){
                  $touser= \core_user::get_user($supervisor->open_supervisorid);
                }

              }else{

                $touser = \core_user::get_user($remove_user);
              }



            $noti_type="feedback_unenrollment";

            if($touser){
                $logemail = $notification->evaluation_notification($noti_type, $touser, $USER, $evaluation);
            }   

           


          }
					$progressbar->end_html();
					$result=new stdClass();
					$result->changecount=$progress;
					$result->feedback=$evaluation->name; 
					
					echo $OUTPUT->notification(get_string('unenrolluserssuccess', 'local_evaluation',$result),'success');
					$button = new single_button($PAGE->url, get_string('click_continue','local_evaluation'), 'get', true);
					$button->class = 'continuebutton';
					echo $OUTPUT->render($button);
          echo $OUTPUT->footer();
					die();
        }
        redirect($url);
    }
   
    $selecttousers = evaluation_enrolled_users('add',$evaluationid, $options, false, $offset=-1, $perpage=50);
    $selecttouserstotal = evaluation_enrolled_users('add',$evaluationid,$options,true,$offset1=-1,$perpage=-1);
 
    $selectfromusers = evaluation_enrolled_users('remove', $evaluationid, $options,false,$offset1=-1,$perpage=50);
    $selectfromuserstotal = evaluation_enrolled_users('remove',$evaluationid,$options,true,$offset1=-1,$perpage=-1);

    $selectallenrolledusers='&nbsp&nbsp<button type="button" id="select_add" name="select_all" value="Select All" title="Select All"/ class="btn btn-default">'.get_string('select_all', 'local_evaluation').'</button>';
    $selectallenrolledusers.='&nbsp&nbsp<button type="button" id="add_select" name="remove_all" value="Remove All" title="Un-select All" class="btn btn-default"/>'.get_string('remove_all', 'local_evaluation').'</button>';
    
    $selectallnotenrolledusers='&nbsp&nbsp<button type="button" id="select_remove" name="select_all" value="Select All" title="Select All" class="btn btn-default"/>'.get_string('select_all', 'local_evaluation').'</button>';
    $selectallnotenrolledusers.='&nbsp&nbsp<button type="button" id="remove_select" name="remove_all" value="Remove All" title="Un-select All" class="btn btn-default"/>'.get_string('remove_all', 'local_evaluation').'</button>';
    
    
   $content='<div class="bootstrap-duallistbox-container">
           ';
		$encodedoptions = json_encode($options);
   $content.='<form  method="post" name="form_name" id="user_assign" class="form_class" ><div class="box2 col-md-5 pull-left">
    <input type="hidden" name="id" value="'.$evaluationid.'"/>
    <input type="hidden" name="sesskey" value="'.sesskey().'"/>
		<input type="hidden" name="options"  value=\''.$encodedoptions.'\' />
   <label>'.get_string('enrolled_users', 'local_evaluation',$selectfromuserstotal).'</label>'.$selectallnotenrolledusers;
   $content.='<select multiple="multiple" name="remove[]" id="bootstrap-duallistbox-selected-list_duallistbox_evaluation_users" class="dual_select">';
   foreach($selectfromusers as $key=>$selectfromuser){
          $content.="<option value='$key'>$selectfromuser</option>";
    }

   $content.='</select>';
   $content.='</div><div class="box3 col-md-2 pull-left actions"><button type="submit" class="custom_btn btn remove btn-default" disabled="disabled" title="Un Enroll Users" name="submitvalue" value="Remove Selected Users" id="user_unassign_all"/>
       '.get_string('remove_selected_users', 'local_evaluation').'
        </button></form>';
   $content.='<form  method="post" name="form_name" id="user_un_assign" class="form_class" ><button type="submit" class="custom_btn btn move btn-default" disabled="disabled" title="Enroll Users" name="submitvalue" value="Add Selected Users" id="user_assign_all" />
       '.get_string('add_selected_users', 'local_evaluation').'
        </button></div><div class="box1 col-md-5 pull-left">
    <input type="hidden" name="id" value="'.$evaluationid.'"/>
    <input type="hidden" name="sesskey" value="'.sesskey().'"/>
		<input type="hidden" name="options"  value=\''.$encodedoptions.'\' />
   <label> '.get_string('availablelist', 'local_evaluation',$selecttouserstotal).'</label>'.$selectallenrolledusers;
    $content.='<select multiple="multiple" name="add[]" id="bootstrap-duallistbox-nonselected-list_duallistbox_evaluation_users" class="dual_select">';
    foreach($selecttousers as $key=>$selecttouser){
          $content.="<option value='$key'>$selecttouser</option>";
    }
    $content.='</select>';
    $content.='</div></form>';
    $content.='</div>';
}
    echo '<a class="btn-link btn-sm" title="Filter" href="javascript:void(0);" data-toggle="collapse" data-target="#local_evaluationenrol-filter_collapse" aria-expanded="false" aria-controls="local_evaluationenrol-filter_collapse">
            <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
          </a>';
    echo  '<div class="collapse '.$show.'" id="local_evaluationenrol-filter_collapse">
                <div id="filters_form" class="card card-body p-2">';
                    $mform->display();
    echo        '</div>
            </div>';
}
if ($evaluationid) {

  $assignurl = new moodle_url($PAGE->url, array('id' => $evaluationid));
  
  $select_div='<div class="w-full pull-left">
    <div class="w-full pull-left">'.$content.'</div>
  </div>';
echo $select_div;
$myJSON = json_encode($options);
echo "<script language='javascript'>

$( document ).ready(function() {
		$('#select_remove').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_evaluation_users option').prop('selected', true);
        $('.box3 .remove').prop('disabled', false);
        $('#user_unassign_all').val('Remove_All_Users');

        $('.box3 .move').prop('disabled', true);
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_evaluation_users option').prop('selected', false);
        $('#user_assign_all').val('Add Selected Users');

    });
    $('#remove_select').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_evaluation_users option').prop('selected', false);
        $('.box3 .remove').prop('disabled', true);
        $('#user_unassign_all').val('Remove Selected Users');
    });
    $('#select_add').click(function() {
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_evaluation_users option').prop('selected', true);
        $('.box3 .move').prop('disabled', false);
        $('#user_assign_all').val('Add_All_Users');

        $('.box3 .remove').prop('disabled', true);
        $('#bootstrap-duallistbox-selected-list_duallistbox_evaluation_users option').prop('selected', false);
        $('#user_unassign_all').val('Remove Selected Users');
        
    });
    $('#add_select').click(function() {
       $('#bootstrap-duallistbox-nonselected-list_duallistbox_evaluation_users option').prop('selected', false);
        $('.box3 .move').prop('disabled', true);
        $('#user_assign_all').val('Add Selected Users');
    });
    $('#bootstrap-duallistbox-selected-list_duallistbox_evaluation_users').on('change', function() {
        if(this.value!=''){
            $('.box3 .remove').prop('disabled', false);
            $('.box3 .move').prop('disabled', true);
        }
    });
    $('#bootstrap-duallistbox-nonselected-list_duallistbox_evaluation_users').on('change', function() {
        if(this.value!=''){
            $('.box3 .move').prop('disabled', false);
            $('.box3 .remove').prop('disabled', true);
        }
    });
    jQuery(
        function($)
        {
          $('.dual_select').bind('scroll', function()
            {
              if(Math.round($(this).scrollTop() + $(this).innerHeight())>=$(this)[0].scrollHeight)
              {
                var get_id=$(this).attr('id');
                if(get_id=='bootstrap-duallistbox-selected-list_duallistbox_evaluation_users'){
                    var type='remove';
                    var total_users=$selectfromuserstotal;
                }
                if(get_id=='bootstrap-duallistbox-nonselected-list_duallistbox_evaluation_users'){
                    var type='add';
                    var total_users=$selecttouserstotal;
                   
                }
                var count_selected_list=$('#'+get_id+' option').length;
               
                var lastValue = $('#'+get_id+' option:last-child').val();
                var countval = $('#'+get_id+' option').length;
              if(count_selected_list<total_users){  
                    var selected_list_request = $.ajax({
                        method: 'GET',
                        url: M.cfg.wwwroot + '/local/evaluation/users_assign.php',
                        data: {id:'$evaluationid',sesskey:'$sesskey', type:type,view:'ajax',countval:countval,enrolid:'$enrolid', options: $myJSON},
                        dataType: 'html'
                    });  
                    var appending_selected_list = '';
                    selected_list_request.done(function(response){
                    response = jQuery.parseJSON(response);
                  
                    $.each(response, function (index, data) {
                   
                        appending_selected_list = appending_selected_list + '<option value=' + index + '>' + data + '</option>';
                    });
                    $('#'+get_id+'').append(appending_selected_list);
                    });
                }
              }
            })
        }
    );
 
});
    </script>";
}
$backurl = new moodle_url('/local/evaluation/eval_view.php', array('id'=>$evaluationid));
$continue='<div class="col-md-12 pull-right text-right mt-15">';
$continue.=$OUTPUT->single_button($backurl,get_string('continue'));
$continue.='</div>';
echo $continue;
echo $OUTPUT->footer();
