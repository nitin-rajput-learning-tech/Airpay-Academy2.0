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
 * @package Bizlms 
 * @subpackage local_program
 */
define('NO_OUTPUT_BUFFERING', true);
use \local_program\program as program;
use core_component;
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/program/lib.php');
$core_component = new core_component();
$courses_plugin_exists = $core_component::get_plugin_directory('local', 'courses');
if (!empty($courses_plugin_exists)) {
    require_once($CFG->dirroot . '/local/courses/filters_form.php');
}

$programid = required_param('bcid', PARAM_INT);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
$submit_value = optional_param('submit_value', '', PARAM_RAW);
$add = optional_param_array('add', array(), PARAM_RAW);
$remove = optional_param_array('remove', array(), PARAM_RAW);
$view = optional_param('view', 'page', PARAM_RAW);
$type = optional_param('type', '', PARAM_RAW);
$lastitem = optional_param('lastitem', 0, PARAM_INT);
$countval = optional_param('countval', 0, PARAM_INT);
$url = new moodle_url('/local/program/enrollusers.php', array('bcid' => $programid));
$renderer = $PAGE->get_renderer('local_program');

$program=$renderer->programview_check($programid);


$categorycontext = (new \local_program\lib\accesslib())::get_module_context();
$sesskey = sesskey();
$programclass = new program();
// Security.
require_login();
require_capability('local/program:manageprogram', $categorycontext);
require_capability('local/program:manageusers', $categorycontext);
if ($view == 'ajax') {
    if(is_string($_GET["options"])){
        $options = json_decode($_GET["options"], false);
    }else{
        $options = $_GET["options"];  
    }
     $select_from_users = (new program)->select_to_and_from_users($type, $programid, $options,false, $offset1=-1, $perpage=50, $countval);
    echo json_encode($select_from_users);
    exit;
}
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->js('/local/program/js/jquery.bootstrap-duallistbox.js',true);
$PAGE->requires->css('/local/program/css/bootstrap-duallistbox.css');
$PAGE->requires->js_call_amd('local_program/program', 'init',
    array(array('programid' => $programid)));
$pageurl = new moodle_url($url);
if ($returnurl) {
    $url->param('returnurl', $returnurl);
}
$PAGE->set_url($url);
$PAGE->set_context($categorycontext);
$PAGE->set_title($program->name);
$PAGE->set_pagelayout('standard');
$data_submitted = data_submitted();
if ($programid) {

    if (file_exists($CFG->dirroot . '/local/lib.php')) {
        require_once($CFG->dirroot . '/local/lib.php');
        $filterlist = get_filterslist();
    }
    if (!empty($courses_plugin_exists)) {
        $mform = new filters_form($url, array('filterlist'=>$filterlist, 'action' => 'user_enrolment')+(array)$data_submitted);
        if ($mform->is_cancelled()) {
            redirect($PAGE->url);
        } else {
            $filterdata = $mform->get_data();
            if ($filterdata) {
                $collapse = false;
                $show = 'show';
            } else {
                $collapse = true;
                $show = '';
            }
            $organization = !empty($filterdata->filteropen_costcenterid) ? implode(',', (array)$filterdata->filteropen_costcenterid) : null;
            $department = !empty($filterdata->filteropen_department) ? implode(',', (array)$filterdata->filteropen_department) : null;
            $subdepartment = !empty($filterdata->filteropen_subdepartment) ? implode(',', (array)$filterdata->filteropen_subdepartment) : null;
            $department4level = !empty($filterdata->filteropen_level4department) ? implode(',', (array)$filterdata->filteropen_level4department) : null;
            $department5level = !empty($filterdata->filteropen_level5department) ? implode(',', (array)$filterdata->filteropen_level5department) : null;
            $states = !empty($filterdata->states) ? implode(',', (array)$filterdata->states) : null;
            $district = !empty($filterdata->district) ? implode(',', (array)$filterdata->district) : null;
            $subdistrict = !empty($filterdata->subdistrict) ? implode(',', (array)$filterdata->subdistrict) : null;
            $village = !empty($filterdata->village) ? implode(',', (array)$filterdata->village) : null;

            $email = !empty($filterdata->email) ? implode(',', (array)$filterdata->email) : null;
            $idnumber = !empty($filterdata->idnumber) ? implode(',',(array) $filterdata->idnumber) : null;
            $uname = !empty($filterdata->users) ? implode(',', (array)$filterdata->users) : null;
            $groups = !empty($filterdata->groups) ? implode(',', (array)$filterdata->groups) : null;
            $location = !empty($filterdata->location) ? implode(',', (array)$filterdata->location) : null;
            $hrmsrole = !empty($filterdata->hrmsrole) ? implode(',', (array)$filterdata->hrmsrole) : null;
        }
    }

    // Create the user selector objects.
    $options = array('context' => $categorycontext->id, 'programid' => $programid, 'organization' => $organization, 'department' => $department,'subdepartment' => $subdepartment,'department4level' => $department4level,'department5level' => $department5level,'states' => $states,'district' => $district,'subdistrict' => $subdistrict,'village' => $village, 'email' => $email, 'idnumber' => $idnumber, 'uname' => $uname, 'groups' => $groups, 'hrmsrole' => $hrmsrole, 'location' => $location);

    if ($add && confirm_sesskey()) {
        if ($submit_value == "Add_All_Users") {
            $options = (array)json_decode($_REQUEST["options"], false);
            $userstoassign = array_flip((new program)->select_to_and_from_users('add',
                $programid,$options, false, $offset1=-1, $perpage=-1));
        } else {
            $userstoassign = $add;
        }

        if (!empty($userstoassign)) {
            echo $OUTPUT->header();
                $result=$programclass->program_add_assignusers($programid, $userstoassign);

                echo $OUTPUT->notification(get_string('enrolluserssuccess', 'local_program',$result),'success');
                $button = new single_button($url, get_string('click_continue','local_program'), 'get', true);
                $button->class = 'continuebutton';
                echo $OUTPUT->render($button);
            echo $OUTPUT->footer();
            die();
        }
        //redirect($PAGE->url);
    }
    if ($remove && confirm_sesskey()) {
        if ($submit_value == "Remove_All_Users") {
            $options = (array)json_decode($_REQUEST["options"], false);
            $userstounassign = array_flip((new program)->select_to_and_from_users('remove',
                $programid, $options, false, $offset1=-1, $perpage=-1));
        } else {
            $userstounassign = $remove;
        }
        if (!empty($userstounassign)) {
            echo $OUTPUT->header();
                $result=$programclass->program_remove_assignusers($programid, $userstounassign);
                echo $OUTPUT->notification(get_string('unenrolluserssuccess', 'local_program',$result),'success');
                $button = new single_button($url, get_string('click_continue','local_program'), 'get', true);
                $button->class = 'continuebutton';
                echo $OUTPUT->render($button);
            echo $OUTPUT->footer();
            die();
        }
        //redirect($PAGE->url);
    }

    $select_to_users = (new program)->select_to_and_from_users('add', $programid, $options, false, $offset=-1, $perpage=50);
    $select_to_users_total = (new program)->select_to_and_from_users('add', $programid, $options, true, $offset1=-1, $perpage=-1);
    $select_from_users = (new program)->select_to_and_from_users('remove', $programid, $options,false, $offset1=-1, $perpage=50);
    $select_from_users_total = (new program)->select_to_and_from_users('remove', $programid,
        $options, true, $offset1=-1, $perpage=-1);

    $select_all_enrolled_users = '&nbsp&nbsp<button type="button" id="select_add" name="select_all" value="Select All" title="Select All"/ class="btn btn-default">' . get_string('select_all', 'local_program') . '</button>';
    $select_all_enrolled_users.='&nbsp&nbsp<button type="button" id="add_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>' . get_string('remove_all', 'local_program') . '</button>';

    $select_all_not_enrolled_users='&nbsp&nbsp<button type="button" id="select_remove" name="select_all" value="Select All" title="Select All" class="btn btn-default"/>' . get_string('select_all', 'local_program') . '</button>';
    $select_all_not_enrolled_users.='&nbsp&nbsp<button type="button" id="remove_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>' . get_string('remove_all', 'local_program') . '</button>';

    $content = '<div class="bootstrap-duallistbox-container">';
    $content .= '<form  method="post" name="form_name" id="user_assign" class="form_class" ><div class="box2 col-md-5 col-12 pull-left">
        <input type="hidden" name="bcid" value="'.$programid.'"/>
        <input type="hidden" name="sesskey" value="'.sesskey().'"/>
        <input type="hidden" name="options"  value=\''.json_encode($options).'\' />
        <label>' . get_string('enrolled_users', 'local_program', $select_from_users_total) . '</label>'.$select_all_not_enrolled_users;
    $content .= '<select multiple="multiple" name="remove[]" id="bootstrap-duallistbox-selected-list_duallistbox_program_users" class="dual_select">';
    foreach($select_from_users as $key=>$select_from_user){
        $content .= "<option value='$key'>$select_from_user</option>";
    }

    $content.='</select>';
    $content.='</div><div class="box3 col-md-2 col-12 pull-left actions"><button type="submit" class="custom_btn btn remove btn-default" disabled="disabled" title="Remove Selected Users" name="submit_value" value="Remove Selected Users" id="user_unassign_all"/>
       '.get_string('remove_selected_users', 'local_program').'
        </button></form>';
    $content.='<form  method="post" name="form_name" id="user_un_assign" class="form_class" ><button type="submit" class="custom_btn btn move btn-default" disabled="disabled" title="Add Selected Users" name="submit_value" value="Add Selected Users" id="user_assign_all" />
       '.get_string('add_selected_users', 'local_program').'
        </button></div><div class="box1 col-md-5 col-12 pull-left">
    <input type="hidden" name="bcid" value="'.$programid.'"/>
    <input type="hidden" name="sesskey" value="'.sesskey().'"/>
    <input type="hidden" name="options"  value=\''.json_encode($options).'\' />
   <label> '.get_string('not_enrolled_users', 'local_program',$select_to_users_total).'</label>'.$select_all_enrolled_users;
    $content.='<select multiple="multiple" name="add[]" id="bootstrap-duallistbox-nonselected-list_duallistbox_program_users" class="dual_select">';
    foreach($select_to_users as $key=>$select_to_user){
          $content.="<option value='$key'>$select_to_user</option>";
    }
    $content.='</select>';
    $content.='</div></form>';
    $content.='</div>';
}
$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('index.php',array('bcid'=>$program->id)));
$PAGE->navbar->add($program->name, new moodle_url('view.php',array('bcid'=>$program->id)));
$PAGE->navbar->add(get_string("enrolusers", 'local_program'));
$PAGE->set_heading(get_string('assignusers_heading', 'local_program',$program->name));
echo $OUTPUT->header();

if (!empty($courses_plugin_exists)) {
    // print_collapsible_region_start(' ', 'filters_form', ' ' . ' ' . get_string('filters'), false, $collapse);
    // $mform->display();
    // print_collapsible_region_end();
    echo '<a class="btn-link btn-sm" href="javascript:void(0);" data-toggle="collapse" data-target="#local_programenrol-filter_collapse" aria-expanded="false" aria-controls="local_programenrol-filter_collapse">
            <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
          </a>';
    echo  '<div class="mt-2 collapse '.$show.'" id="local_programenrol-filter_collapse">
                <div id="filters_form" class="card card-body p-2">';
                    $mform->display();
    echo        '</div>
            </div>';
}
if ($programid) {

    $assignurl = new moodle_url($PAGE->url, array('bcid' => $programid));
    $select_div = '<div class="row d-block">
                        <div class="w-100 pull-left">'.$content.'</div>
                   </div>';
echo $select_div;
$myJSON = json_encode($options);

echo "<script language='javascript'>

$( document ).ready(function() {
    $('#select_remove').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', true);
        $('.box3 .remove').prop('disabled', false);
        $('#user_unassign_all').val('Remove_All_Users');

        $('.box3 .move').prop('disabled', true);
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected', false);
        $('#user_assign_all').val('Add Selected Users');

    });
    $('#remove_select').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', false);
        $('.box3 .remove').prop('disabled', true);
        $('#user_unassign_all').val('Remove Selected Users');
    });
    $('#select_add').click(function() {
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected', true);
        $('.box3 .move').prop('disabled', false);
        $('#user_assign_all').val('Add_All_Users');

        $('.box3 .remove').prop('disabled', true);
        $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', false);
        $('#user_unassign_all').val('Remove Selected Users');

    });
    $('#add_select').click(function() {
       $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected', false);
        $('.box3 .move').prop('disabled', true);
        $('#user_assign_all').val('Add Selected Users');
    });
    $('#bootstrap-duallistbox-selected-list_duallistbox_program_users').on('change', function() {
        if(this.value!=''){
            $('.box3 .remove').prop('disabled', false);
            $('.box3 .move').prop('disabled', true);
        }
    });
    $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users').on('change', function() {
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
                if(get_id=='bootstrap-duallistbox-selected-list_duallistbox_program_users'){
                    var type='remove';
                    var total_users=$select_from_users_total;
                }
                if(get_id=='bootstrap-duallistbox-nonselected-list_duallistbox_program_users'){
                    var type='add';
                    var total_users=$select_to_users_total;

                }
                var count_selected_list=$('#'+get_id+' option').length;

                var lastValue = $('#'+get_id+' option:last-child').val();
                var countval = $('#'+get_id+' option').length;
                if(count_selected_list<total_users){
                   //alert('end reached');
                    var selected_list_request = $.ajax({
                        method: 'GET',
                        url: M.cfg.wwwroot + '/local/program/enrollusers.php',
                        data: {bcid:'$programid',sesskey:'$sesskey', type:type,view:'ajax',countval:countval, options: $myJSON},
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
  $continue='<div class="col-lg-12 col-md-12 pull-right text-right mt-15">';
  $continue.='<a href='.$CFG->wwwroot.'/local/program/view.php?bcid='.$programid.' class="singlebutton"><button class="btn">'.get_string('continue', 'local_program').'</button></a>';
  $continue.='</div>';
  echo $continue;
echo $OUTPUT->footer();