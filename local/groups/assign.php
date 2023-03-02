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
 * @subpackage local_groups
 */

define('NO_OUTPUT_BUFFERING', true);
require('../../config.php');
require_once($CFG->dirroot.'/local/lib.php');
require_once($CFG->dirroot . '/local/courses/filters_form.php');
$id = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$submit_value = optional_param('submit_value','', PARAM_RAW);
$add = optional_param('add',array(), PARAM_RAW);
$remove=optional_param('remove',array(), PARAM_RAW);
$view=optional_param('view','page', PARAM_RAW);
$type=optional_param('type','', PARAM_RAW);
$lastitem=optional_param('lastitem',0, PARAM_INT);
require_login();
$sesskey=sesskey();
$groups = $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);
$groupsdetails = $DB->get_record('local_groups', array('cohortid'=>$id));
$context_cat= (new \local_groups\lib\accesslib())::get_module_context();

require_capability('moodle/cohort:assign', $context_cat);

$PAGE->set_context($context);
$PAGE->set_url('/local/groups/assign.php', array('id'=>$id));
$PAGE->set_pagelayout('standard');
$url = new moodle_url('/local/groups/assign.php', array('id' => $id));

if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/local/groups/index.php', array('contextid' => $groups->contextid));
}

if (!empty($groups->component)) {
    // We can not manually edit groupss that were created by external systems, sorry.
    redirect($returnurl);
}



//i.e other than admin eg:Org.Head


//For Dept.Head

if (optional_param('cancel', false, PARAM_BOOL)) {
    redirect($url);
}


$PAGE->navbar->add(get_string('cohorts', 'local_groups'), new moodle_url('/local/groups/index.php', array('contextid' => $groups->contextid)));
$PAGE->navbar->add(get_string('assign', 'local_groups'));

$PAGE->set_title(get_string('assigncohorts', 'local_groups'));

$PAGE->set_heading($COURSE->fullname);
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->js('/local/classroom/js/jquery.bootstrap-duallistbox.js',true);
$PAGE->requires->css('/local/classroom/css/bootstrap-duallistbox.css');
if($view == 'ajax'){
  $options =(array)json_decode($_GET["options"],false);
  $select_from_users=local_group_users($type,$id,$options,false,$offset1=-1,$perpage=50,$lastitem);
	echo json_encode($select_from_users);
	exit;
}
echo $OUTPUT->header();
if(!$add&&!$remove){
echo $OUTPUT->heading(get_string('assignto', 'local_groups', format_string($groups->name)));
}

if ($groups) {
  $organization = null;
  $department   = null;
  $email        = null;
  $idnumber     = null;
  $uname        = null;
  $subdepartment=null;
  $department4level=null;
  $department5level=null;
  $states=null;
  $district=null;
  $subdistrict=null;
  $village=null;
  $filterlist = get_filterslist();
  $data_submitted=data_submitted();
  $mform = new filters_form($url, array('filterlist'=>$filterlist,'enrolid'=>0, 'courseid'=>$id, 'action' => 'user_enrolment')+(array)$data_submitted);
  
  if ($mform->is_cancelled()) {
    redirect($PAGE->url);
  } else {
    $filterdata =  $mform->get_data();
    if($filterdata){
        $collapse = false;
        $show = 'show';
    } else{
        $collapse = true;
        $show = '';
    }
    $organization = !empty($filterdata->filteropen_costcenterid) ? implode(',', $filterdata->filteropen_costcenterid) : null;
    $department = !empty($filterdata->filteropen_department) ? implode(',', $filterdata->filteropen_department) : null;
    $email = !empty($filterdata->email) ? implode(',', $filterdata->email) : null;
    $filtergroup = !empty($filterdata->groups) ? implode(',', $filterdata->groups) : null;
    $idnumber = !empty($filterdata->idnumber) ? implode(',', $filterdata->idnumber) : null;
    $uname = !empty($filterdata->users) ? implode(',', $filterdata->users) : null;
    $subdepartment = !empty($filterdata->filteropen_subdepartment) ? implode(',', $filterdata->filteropen_subdepartment) : null;
    $department4level = !empty($filterdata->filteropen_level4department) ? implode(',', $filterdata->filteropen_level4department) : null;
    $department5level = !empty($filterdata->filteropen_level5department) ? implode(',', $filterdata->filteropen_level5department) : null;
    $states = !empty($filterdata->states) ? implode(',', $filterdata->states) : null;
    $district = !empty($filterdata->district) ? implode(',', $filterdata->district) : null;
    $subdistrict = !empty($filterdata->subdistrict) ? implode(',', $filterdata->subdistrict) : null;
    $village = !empty($filterdata->village) ? implode(',', $filterdata->village) : null;
  }

    // Create the user selector objects.
    $options = array('context' => $context->id, 'groupsid' => $id, 'organization' => $organization, 'department' => $department,'subdepartment'=>$subdepartment,'department4level'=>$department4level,'department5level'=>$department5level,
    'states'=>$states,'district'=>$district,'subdistrict'=>$subdistrict,'village'=>$village, 'email' => $email, 'groups' => $filtergroup, 'idnumber' => $idnumber, 'uname' => $uname);
    
    if ( $add AND confirm_sesskey()) {        
        if($submit_value == "Add_All_Users"){
			$options =json_decode($_REQUEST["options"],false);
              $userstoassign=array_flip(local_group_users('add', $id, (array)$options, false, $offset1=-1, $perpage=-1));
        }else{
            $userstoassign =$add;
        }
     
        if (!empty($userstoassign)) {
			$progress = 0;
			$progressbar = new \core\progress\display_if_slow(get_string('enrollusers', 'local_groups',$groups->name));
			$progressbar->start_html();
			$progressbar->start_progress('',count($userstoassign)-1);
			foreach($userstoassign as $key=>$add_user){
			  $progressbar->progress($progress);
			  $progress++;
			  local_groups_add_member($groups->id, $add_user);
			}
			$progressbar->end_html();
            $result=new stdClass();
            $result->changecount=$progress;
            $result->group=$groups->name; 

            echo $OUTPUT->notification(get_string('enrolluserssuccess', 'local_groups',$result),'success');
            $button = new single_button($url, get_string('click_continue','local_groups'), 'get', true);
            $button->class = 'continuebutton';
            echo $OUTPUT->render($button);
            echo $OUTPUT->footer();
            die();
        }
    }
    if ( $remove AND confirm_sesskey()) {        
        if($submit_value=="Remove_All_Users"){
			$options =json_decode($_REQUEST["options"],false);
             $userstounassign = array_flip(local_group_users('remove',$id,(array)$options,false,$offset1=-1,$perpage=-1));
        }else{
            $userstounassign = $remove;
        }
        if (!empty($userstounassign)) {
			$progress = 0;
			$progressbar = new \core\progress\display_if_slow(get_string('un_enrollusers', 'local_groups',$groups->name));
			$progressbar->start_html();
			$progressbar->start_progress('', count($userstounassign)-1);
            foreach($userstounassign as $key=>$remove_user){
			  $progressbar->progress($progress);
			  $progress++;
              local_groups_remove_member($groups->id, $remove_user);
            }
			$progressbar->end_html();
			$result=new stdClass();
			$result->changecount=$progress;
			$result->group=$groups->name; 
			
			echo $OUTPUT->notification(get_string('unenrolluserssuccess', 'local_groups',$result),'success');
			$button = new single_button($PAGE->url, get_string('click_continue','local_groups'), 'get', true);
			$button->class = 'continuebutton';
			echo $OUTPUT->render($button);
      echo $OUTPUT->footer();
			die();
        }
    }
   
    $select_to_users = local_group_users('add', $id, $options, false, $offset=-1, $perpage=50);
    $select_to_users_total = local_group_users('add', $id, $options, true, $offset1=-1, $perpage=-1);
 
    $select_from_users = local_group_users('remove', $id, $options, false, $offset1=-1, $perpage=50);
    $select_from_users_total = local_group_users('remove', $id, $options, true, $offset1=-1, $perpage=-1);

    $select_all_enrolled_users='&nbsp&nbsp<button type="button" id="select_add" name="select_all" value="Select All" title="Select All"/ class="btn btn-default">'.get_string('select_all', 'local_groups').'</button>';
    $select_all_enrolled_users.='&nbsp&nbsp<button type="button" id="add_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>'.get_string('remove_all', 'local_groups').'</button>';
    
    $select_all_not_enrolled_users='&nbsp&nbsp<button type="button" id="select_remove" name="select_all" value="Select All" title="Select All" class="btn btn-default"/>'.get_string('select_all', 'local_groups').'</button>';
    $select_all_not_enrolled_users.='&nbsp&nbsp<button type="button" id="remove_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>'.get_string('remove_all', 'local_groups').'</button>';
    
    
   $content='<div class="bootstrap-duallistbox-container mb-3">
           ';
		   $encoded_options = json_encode($options);
   $content.='<form  method="post" name="form_name" id="user_assign" class="form_class" ><div class="box2 col-md-5 col-12 pull-left">
    <input type="hidden" name="id" value="'.$id.'"/>
    <input type="hidden" name="sesskey" value="'.sesskey().'"/>
	<input type="hidden" name="options"  value='.$encoded_options.' />
   <label>'.get_string('enrolled_users', 'local_groups',$select_from_users_total).'</label>'.$select_all_not_enrolled_users;
   $content.='<select multiple="multiple" name="remove[]" id="bootstrap-duallistbox-selected-list_duallistbox_groups_users" class="dual_select">';
   foreach($select_from_users as $key=>$select_from_user){
          $content.="<option value='$key'>$select_from_user</option>";
    }

   $content.='</select>';
   $content.='</div><div class="box3 col-md-2 col-12 pull-left actions"><button type="submit" class="custom_btn btn remove btn-default" disabled="disabled" title="Remove Selected Users" name="submit_value" value="Remove Selected Users" id="user_unassign_all"/>
       '.get_string('remove_selected_users', 'local_groups').'
        </button></form>';
   $content.='<form  method="post" name="form_name" id="user_un_assign" class="form_class" ><button type="submit" class="custom_btn btn move btn-default" disabled="disabled" title="Add Selected Users" name="submit_value" value="Add Selected Users" id="user_assign_all" />
       '.get_string('add_selected_users', 'local_groups').'
        </button></div><div class="box1 col-md-5 col-12 pull-left">
    <input type="hidden" name="id" value="'.$id.'"/>
    <input type="hidden" name="sesskey" value="'.sesskey().'"/>
	<input type="hidden" name="options"  value='.$encoded_options.' />
   <label> '.get_string('not_enrolled_users', 'local_groups',$select_to_users_total).'</label>'.$select_all_enrolled_users;
    $content.='<select multiple="multiple" name="add[]" id="bootstrap-duallistbox-nonselected-list_duallistbox_groups_users" class="dual_select">';
    foreach($select_to_users as $key=>$select_to_user){
          $content.="<option value='$key'>$select_to_user</option>";
    }
    $content.='</select>';
    $content.='</div></form>';
    $content.='</div>';
}
echo '<a class="btn-link btn-sm" title="Filter" href="javascript:void(0);" data-toggle="collapse" data-target="#local_groupenrol-filter_collapse" aria-expanded="false" aria-controls="local_groupenrol-filter_collapse">
        <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
      </a>';
echo  '<div class="collapse '.$show.'" id="local_groupenrol-filter_collapse">
            <div id="filters_form" class="card card-body p-2">';
                $mform->display();
echo        '</div>
        </div>';

if ($id) {
  $select_div='<div class="row d-block">
    <div class="w-100 pull-left">'.$content.'</div>
  </div>';
  echo $select_div;
  $myJSON = json_encode($options);
  echo "<script language='javascript'>

$( document ).ready(function() {
	
	$('#select_remove').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_groups_users option').prop('selected', true);
        $('.box3 .remove').prop('disabled', false);
        $('#user_unassign_all').val('Remove_All_Users');

        $('.box3 .move').prop('disabled', true);
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_groups_users option').prop('selected', false);
        $('#user_assign_all').val('Add Selected Users');

    });
    $('#remove_select').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_groups_users option').prop('selected', false);
        $('.box3 .remove').prop('disabled', true);
        $('#user_unassign_all').val('Remove Selected Users');
    });
    $('#select_add').click(function() {
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_groups_users option').prop('selected', true);
        $('.box3 .move').prop('disabled', false);
        $('#user_assign_all').val('Add_All_Users');

        $('.box3 .remove').prop('disabled', true);
        $('#bootstrap-duallistbox-selected-list_duallistbox_groups_users option').prop('selected', false);
        $('#user_unassign_all').val('Remove Selected Users');
        
    });
    $('#add_select').click(function() {
       $('#bootstrap-duallistbox-nonselected-list_duallistbox_groups_users option').prop('selected', false);
        $('.box3 .move').prop('disabled', true);
        $('#user_assign_all').val('Add Selected Users');
    });
    $('#bootstrap-duallistbox-selected-list_duallistbox_groups_users').on('change', function() {
        if(this.value!=''){
            $('.box3 .remove').prop('disabled', false);
            $('.box3 .move').prop('disabled', true);
        }
    });
    $('#bootstrap-duallistbox-nonselected-list_duallistbox_groups_users').on('change', function() {
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
              if($(this).scrollTop() + $(this).innerHeight()>=$(this)[0].scrollHeight)
              {
                var get_id=$(this).attr('id');
                if(get_id=='bootstrap-duallistbox-selected-list_duallistbox_groups_users'){
                    var type='remove';
                    var total_users=$select_from_users_total;
                }
                if(get_id=='bootstrap-duallistbox-nonselected-list_duallistbox_groups_users'){
                    var type='add';
                    var total_users=$select_to_users_total;
                   
                }
                var count_selected_list=$('#'+get_id+' option').length;
               
                var lastValue = $('#'+get_id+' option:last-child').val();
             
              if(count_selected_list<total_users){  
                   //alert('end reached');
                    var selected_list_request = $.ajax({
                        method: 'GET',
                        url: M.cfg.wwwroot + '/local/groups/assign.php?options=$myJSON',
                        data: {id:'$id',sesskey:'$sesskey', type:type,view:'ajax',lastitem:lastValue},
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
}
$backurl = new moodle_url('/local/groups/index.php');
$continue='<div class="col-lg-12 col-md-12 pull-right text-right p-0">';
$continue.=$OUTPUT->single_button($backurl,get_string('continue'));
$continue.='</div>';
echo $continue;
echo $OUTPUT->footer();
