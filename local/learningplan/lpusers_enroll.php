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

define('NO_OUTPUT_BUFFERING', true);
require_once(dirname(__FILE__) . '/../../config.php');
// require_once($CFG->dirroot.'/local/learningplan/notifications_emails.php');
global $DB, $USER, $CFG,$PAGE,$OUTPUT;
use core_component;
// use \local_learningplan\notifications_emails as learningplannotifications_emails;

$planid = required_param('lpid', PARAM_INT);
$systemcontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);
$PAGE->set_context($systemcontext);
require_login();
$pagenavurl = new moodle_url('/local/learningplan/index.php');
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
$submit_value = optional_param('submit_value','', PARAM_RAW);
$add = optional_param('add',array(), PARAM_RAW);
$remove=optional_param('remove',array(), PARAM_RAW);
$view=optional_param('view','page', PARAM_RAW);
$type=optional_param('type','', PARAM_RAW);
$lastitem=optional_param('lastitem',0, PARAM_INT);
$countval = optional_param('countval', 0, PARAM_INT);
$sesskey=sesskey();
$learningplan = $DB->get_record('local_learningplan',array('id' => $planid));
if(!(is_siteadmin() || has_capability('local/learningplan:manage', $systemcontext))){
    $sql="SELECT lp.id ";
    $sql.=" FROM {local_learningplan} lp WHERE id = :id "; 
    $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path');
    $learningplans=$DB->get_records_sql($sql .$costcenterpathconcatsql,array('id'=>$planid));
    if(empty($learningplans)){
            print_error("You don't have permissions to view this page.");
    }
}

$learningplan_renderer = new local_learningplan\render\view();
if($view=='ajax'){
    //$options =(array)json_decode($_GET["options"],false);
    if(is_string($_GET["options"])){
        $options = json_decode($_GET["options"], false);
    }else{
        $options = $_GET["options"];  
    }
    if($type=='add'){
      $select_from_users=$learningplan_renderer->select_to_users_of_learninplan($planid,$USER->id,$options,false,$offset=-1,$perpage=50,$countval);
    }
    if($type=='remove'){
      $select_from_users=$learningplan_renderer->select_from_users_of_learninplan($planid,$USER->id,$options,false,$offset=-1,$perpage=50,$countval);
    }
    echo json_encode($select_from_users);
    exit;
}

// $learningplan = $DB->get_field('local_learningplan','name',array('id'=>$planid));
$url = new moodle_url('/local/learningplan/lpusers_enroll.php', array('lpid' => $planid));
$PAGE->set_url($url);
if(!$add&&!$remove){
$PAGE->set_heading($learningplan->name ." : ". get_string("add_remove_users", 'local_learningplan'));
}
$PAGE->set_title($learningplan->name ." : ".get_string("add_remove_users", 'local_learningplan'));
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string("pluginname", 'local_learningplan'), new moodle_url($pagenavurl));

if(isset($learningplan->name) && has_capability('local/learningplan:manage', $systemcontext)){
    $PAGE->navbar->add($learningplan->name, new moodle_url('plan_view.php',array('id' => $planid)));
}
$PAGE->navbar->add(get_string("enrolusers", 'local_classroom'));
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->js('/local/learningplan/js/jquery.bootstrap-duallistbox.js',true);
$PAGE->requires->css('/local/learningplan/css/bootstrap-duallistbox.css');
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load');
$PAGE->requires->js_call_amd('local_users/newuser', 'load');

echo $OUTPUT->header();
$learningplan_lib = new local_learningplan\lib\lib();
$actionpage =$CFG->wwwroot.'/local/learningplan/lpusers_enroll.php?lpid='.$planid;
if(file_exists($CFG->dirroot.'/local/lib.php')) {
    require_once($CFG->dirroot.'/local/lib.php');
    $filterlist = get_filterslist();
}
$core_component = new core_component();
$courses_plugin_exists = $core_component::get_plugin_directory('local', 'courses');
if(!empty($courses_plugin_exists)&&!$add&&!$remove){
    require_once($CFG->dirroot . '/local/courses/filters_form.php');
    $datasubmitted = data_submitted();
    $mform = new filters_form($url, array('filterlist'=>$filterlist, 'action' => 'user_enrolment')+(array)$datasubmitted);
    $organization = null;
    $department   = null;
    $email        = null;
    $idnumber     = null;
    $uname        = null;
    $groups       = null;
    $location     = null;
    $hrmsrole     = null;

    if($mform->is_cancelled()){
    	redirect($url);
    }else{
	
	
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
    $department4level = !empty($filterdata->filteropen_department4level) ? implode(',', (array)$filterdata->filteropen_department4level) : null;
    $department5level = !empty($filterdata->filteropen_department5level) ? implode(',', (array)$filterdata->filteropen_department5level) : null;
    $states = !empty($filterdata->states) ? implode(',', (array)$filterdata->states) : null;
    $district = !empty($filterdata->district) ? implode(',', (array)$filterdata->district) : null;
    $subdistrict = !empty($filterdata->subdistrict) ? implode(',', (array)$filterdata->subdistrict) : null;
    $village = !empty($filterdata->village) ? implode(',', (array)$filterdata->village) : null;
  	$email = !empty($filterdata->email) ? implode(',', (array)$filterdata->email) : null;
  	$idnumber = !empty($filterdata->idnumber) ? implode(',', (array)$filterdata->idnumber) : null;
  	$uname = !empty($filterdata->users) ? implode(',', (array)$filterdata->users) : null;
    $groups = !empty($filterdata->groups) ? implode(',', (array)$filterdata->groups) : null;
    $location = !empty($filterdata->location) ? implode(',', (array)$filterdata->location) : null;
  	$hrmsrole = !empty($filterdata->hrmsrole) ? implode(',', (array)$filterdata->hrmsrole) : null;
    $designation = !empty($filterdata->designation) ? implode(',', (array)$filterdata->designation) : null;
    $empnumber = !empty($filterdata->empnumber) ? implode(',', (array)$filterdata->empnumber) : null;
  }
   $options = array('context' => $systemcontext->id, 'planid' => $planid, 'organization' => $organization, 'department' => $department, 'subdepartment' => $subdepartment, 'department4level' => $department4level, 'department5level' => $department5level, 'states' => $states, 'district' => $district, 'subdistrict' => $subdistrict, 'village' => $village, 'email' => $email, 'idnumber' => $idnumber, 'uname' => $uname, 'groups' => $groups, 'location' => $location, 'hrmsrole' => $hrmsrole);

  	// print_collapsible_region_start('', 'filters_form', get_string('filters'),false,$collapse);
  	// $mform->display();
  	// print_collapsible_region_end();
    echo '<a class="btn-link btn-sm" title="'.get_string('filter').'" href="javascript:void(0);" data-toggle="collapse" data-target="#local_learningplanenrol-filter_collapse" aria-expanded="false" aria-controls="local_learningplanenrol-filter_collapse">
            <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
          </a>';
    echo  '<div class="collapse '.$show.'" id="local_learningplanenrol-filter_collapse">
                <div id="filters_form" class="card card-body p-2">';
                    $mform->display();
    echo        '</div>
            </div>';
}
// $learningplaninstance = $DB->get_record('local_learningplan', array('id' => $planid));
$learningplaninstance = $learningplan;
$learningplaninstance->costcenter = explode('/',$learningplaninstance->open_path)[1];
  if ( $add AND confirm_sesskey()) {

		if($submit_value=="Add_All_Users"){
			  $options =(array)json_decode($_REQUEST["options"],false);
        $userstoassign=array_flip($learningplan_renderer->select_to_users_of_learninplan($planid,$USER->id,$options,false,$offset=-1,$perpage=-1));
        }else{
            $userstoassign =$add;
        }
		
        if (!empty($userstoassign)) {
			//echo $OUTPUT->header(); 
			$progress = 0;
            $progressbar = new \core\progress\display_if_slow(get_string('enrollusers', 'local_learningplan',$learningplan->name));
            $progressbar->start_html();
            $progressbar->start_progress('',count($userstoassign)-1);
			
            $submitted = new stdClass();
            $submitted->timemodified = time();
            $submitted->timecreated = time();
            $type = 'learningplan_enrol';
            $dataobj = $planid;
            $fromuserid = $USER->id;
            foreach($userstoassign as $key=>$add_user){
              $progressbar->progress($progress);
              $progress++;
              $record = new \stdClass();
              $record->planid = $planid;
              $record->userid = $add_user;
              $record->timecreated = time();
              $record->usercreated = $USER->id;
              $record->timemodified = 0;
              $record->usermodified = 0;
              $create_record = $learningplan_lib->assign_users_to_learningplan($record);
   
            }
			      $progressbar->end_html();
            $result=new stdClass();
            $result->changecount=$progress;
            $result->learningplan=$learningplan->name; 

            echo $OUTPUT->notification(get_string('enrolluserssuccess', 'local_learningplan',$result),'success');
            $button = new single_button($url, get_string('click_continue','local_learningplan'), 'get', true);
            $button->class = 'continuebutton';
            echo $OUTPUT->render($button);
            echo $OUTPUT->footer();
            die();
          }
          //redirect($url);
      }
      if ( $remove&& confirm_sesskey()) {
			if($submit_value=="Remove_All_Users"){
				$options =(array)json_decode($_REQUEST["options"],false);
				$userstounassign=array_flip($learningplan_renderer->select_from_users_of_learninplan($planid,$USER->id,$options,false,$offset=-1,$perpage=-1));
			}else{
				$userstounassign =$remove;
			}
          if (!empty($userstounassign)) {
				//echo $OUTPUT->header();
				$progress = 0;
				$progressbar = new \core\progress\display_if_slow(get_string('un_enrollusers', 'local_learningplan',$learningplan->name));
				$progressbar->start_html();
				$progressbar->start_progress('', count($userstounassign)-1);
				foreach($userstounassign as $key=>$remove_user){
					$progressbar->progress($progress);
					$progress++;
					$get_record = $DB->get_record('local_learningplan_user',array('planid'=>$planid,'userid'=>$remove_user));
					$deleterecord = $DB->delete_records('local_learningplan_user',array('id'=>$get_record->id));
          $sql = " SELECT * FROM {local_learningplan_courses} WHERE planid =:planid ";               
          $lpcourses = $DB->get_records('local_learningplan_courses', array('planid'=>$planid));

          foreach($lpcourses as $key => $opcourse){
            $unenrol=$learningplan_lib->to_unenrol_users($planid,$remove_user,$opcourse->courseid,false);
          }
          //Unenrolment notification//
          $touser = \core_user::get_user($remove_user);
          $emaillogs = new local_learningplan\notification();
          $noti_type="learningplan_unenrol";
          $logmail = $emaillogs->learningplan_notification($noti_type, $touser, $USER, $learningplaninstance);

				}
				$progressbar->end_html();
				$result=new stdClass();
				$result->changecount=$progress;
				$result->learningplan=$learningplan->name;
				
				echo $OUTPUT->notification(get_string('unenrolluserssuccess', 'local_learningplan',$result),'success');
				$button = new single_button($url, get_string('click_continue','local_learningplan'), 'get', true);
				$button->class = 'continuebutton';
				echo $OUTPUT->render($button);
				echo $OUTPUT->footer();
				die();
          }
          //redirect($url);
      }

		$select_to_users = $learningplan_renderer->select_to_users_of_learninplan($planid,$USER->id,$options,false,$offset=-1,$perpage=50);

    $select_to_users_total = $learningplan_renderer->select_to_users_of_learninplan($planid,$USER->id,$options,true,$offset1=-1,$perpage=-1);

		$select_from_users = $learningplan_renderer->select_from_users_of_learninplan($planid,$USER->id,$options,false,$offset=-1,$perpage=50);

    $select_from_users_total = $learningplan_renderer->select_from_users_of_learninplan($planid,$USER->id,$options,true,$offset1=-1,$perpage=-1);
		
		 $select_all_enrolled_users='&nbsp&nbsp<button type="button" id="select_add" name="select_all" value="Select All" title="Select All"/ class="btn btn-default">'.get_string('select_all', 'local_learningplan').'</button>';
    	$select_all_enrolled_users.='&nbsp&nbsp<button type="button" id="add_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>'.get_string('remove_all', 'local_learningplan').'</button>';
    
    	$select_all_not_enrolled_users='&nbsp&nbsp<button type="button" id="select_remove" name="select_all" value="Select All" title="Select All" class="btn btn-default"/>'.get_string('select_all', 'local_learningplan').'</button>';
    	$select_all_not_enrolled_users.='&nbsp&nbsp<button type="button" id="remove_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>'.get_string('remove_all', 'local_learningplan').'</button>';
    	$content='<div class="bootstrap-duallistbox-container">
           ';
     $content.='<form  method="post" name="form_name" id="user_assign" class="form_class" ><div class="box2 col-lg-5 col-md-5 pull-left">
      <input type="hidden" name="lpid" value="'.$planid.'"/>
      <input type="hidden" name="sesskey" value="'.sesskey().'"/>
	  <input type="hidden" name="options"  value=\''.json_encode($options).'\' />
     <label>'.get_string('enrolled_users', 'local_learningplan',$select_from_users_total).'</label>'.$select_all_not_enrolled_users;
     $content.='<select multiple="multiple" name="remove[]" id="bootstrap-duallistbox-selected-list_duallistbox_learningplan_users" class="dual_select">';
     foreach($select_from_users as $key=>$select_from_user){
            $content.="<option value='$key'>".$select_from_user."</option>";
      }
     
     $content.='</select>';
     $content.='</div><div class="box3 col-lg-2 col-md-2 pull-left actions"><button type="submit" class="custom_btn btn remove btn-default" disabled="disabled" title="Remove Selected Users" name="submit_value" value="Remove Selected Users" id="user_unassign_all"/>
       '.get_string('remove_selected_users', 'local_learningplan').'
        </button></form>';
     $content.='<form  method="post" name="form_name" id="user_un_assign" class="form_class" ><button type="submit" class="custom_btn btn move btn-default" disabled="disabled" title="Add Selected Users" name="submit_value" value="Add Selected Users" id="user_assign_all" />
       '.get_string('add_selected_users', 'local_learningplan').'
        </button></div><div class="box1 col-lg-5 col-md-5 pull-left">
      <input type="hidden" name="lpid" value="'.$planid.'"/>
      <input type="hidden" name="sesskey" value="'.sesskey().'"/>
	  <input type="hidden" name="options"  value=\''.json_encode($options).'\' />
   <label> '.get_string('not_enrolled_users', 'local_learningplan',$select_to_users_total).'</label>'.$select_all_enrolled_users;
      $content.='<select multiple="multiple" name="add[]" id="bootstrap-duallistbox-nonselected-list_duallistbox_learningplan_users" class="dual_select">';
      foreach($select_to_users as $key=>$select_to_user){
          $content.="<option value='$key'>".$select_to_user."</option>";
    }
    $content.='</select>';
    $content.='</div></form>';
    $content.='</div>';
    $options = array('planid' => $planid, 'designation' => $designation, 'department' => $department, 'empnumber' => $empnumber, 'organization' => $organization, 'email' => $email, 'subdepartment' => $subdepartment);

	 $select_div='<div class="row d-block">
	    <div class="w-100 pull-left">'.$content.'</div>
	  </div>';
	echo $select_div;
	$myJSON = json_encode($options);
	echo "<script language='javascript'>

$( document ).ready(function() {
	$('#select_remove').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_learningplan_users option').prop('selected', true);
        $('.box3 .remove').prop('disabled', false);
        $('#user_unassign_all').val('Remove_All_Users');

        $('.box3 .move').prop('disabled', true);
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_learningplan_users option').prop('selected', false);
        $('#user_assign_all').val('Add Selected Users');

    });
    $('#remove_select').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_learningplan_users option').prop('selected', false);
        $('.box3 .remove').prop('disabled', true);
        $('#user_unassign_all').val('Remove Selected Users');
    });
    $('#select_add').click(function() {
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_learningplan_users option').prop('selected', true);
        $('.box3 .move').prop('disabled', false);
        $('#user_assign_all').val('Add_All_Users');

        $('.box3 .remove').prop('disabled', true);
        $('#bootstrap-duallistbox-selected-list_duallistbox_learningplan_users option').prop('selected', false);
        $('#user_unassign_all').val('Remove Selected Users');
        
    });
    $('#add_select').click(function() {
       $('#bootstrap-duallistbox-nonselected-list_duallistbox_learningplan_users option').prop('selected', false);
        $('.box3 .move').prop('disabled', true);
        $('#user_assign_all').val('Add Selected Users');
    });
    $('#bootstrap-duallistbox-selected-list_duallistbox_learningplan_users').on('change', function() {
        if(this.value!=''){
            $('.box3 .remove').prop('disabled', false);
            $('.box3 .move').prop('disabled', true);
        }
    });
    $('#bootstrap-duallistbox-nonselected-list_duallistbox_learningplan_users').on('change', function() {
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
                if(get_id=='bootstrap-duallistbox-selected-list_duallistbox_learningplan_users'){
                    var type='remove';
                    var total_users=".$select_from_users_total.";
                    // alert(total_users);
                }
                if(get_id=='bootstrap-duallistbox-nonselected-list_duallistbox_learningplan_users'){
                    var type='add';
                    var total_users=".$select_to_users_total.";
                }
                var count_selected_list=$('#'+get_id+' option').length;
                var countval = $('#'+get_id+' option').length;
                var lastValue = $('#'+get_id+' option:last-child').val();
              if(count_selected_list<total_users){  
                   //alert('end reached');
                    var selected_list_request = $.ajax({
                        method: 'GET',
                        url: M.cfg.wwwroot + '/local/learningplan/lpusers_enroll.php',
                        data: {lpid:'$planid',type:type,view:'ajax',countval:countval, options: $myJSON},
                        dataType: 'html'
                    });  
                    var appending_selected_list = '';
                    selected_list_request.done(function(response){
                    //console.log(response);
                    response = jQuery.parseJSON(response);
                    //console.log(response);
                  
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

$lpcheck =has_capability('local/learningplan:manage', $systemcontext);
$continue='<div class="col-md-12 pull-right text-right mt-15">';
if($lpcheck){
  $continue.='<a href='.$CFG->wwwroot.'/local/learningplan/plan_view.php?id='.$planid.' class="singlebutton"><button class="btn">'.get_string('continue').'</button></a>';
}else{
  $continue.='<a href='.$CFG->wwwroot.'/local/learningplan/lpathinfo.php?id='.$planid.' class="singlebutton"><button class="btn">'.get_string('continue').'</button></a>';
}
$continue.='</div>';
echo $continue;

echo $OUTPUT->footer();
