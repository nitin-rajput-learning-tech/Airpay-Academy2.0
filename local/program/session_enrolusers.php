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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/local/program/lib.php');

use \local_program\program as program;
use core_component;

$programid = required_param('bcid', PARAM_INT);
$sessionid = required_param('sid', PARAM_INT);
$levelid = optional_param('levelid', 0, PARAM_INT);
$bclcid = optional_param('bclcid', 0, PARAM_INT);
// $PAGE->requires->jquery();
// $PAGE->requires->jquery_plugin('ui');

$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
$submit_value = optional_param('submit_value', '', PARAM_RAW);
$add = optional_param_array('add', array(), PARAM_RAW);
$remove = optional_param_array('remove', array(), PARAM_RAW);
$view = optional_param('view', 'page', PARAM_RAW);
$type = optional_param('type', '', PARAM_RAW);
$lastitem = optional_param('lastitem', 0, PARAM_INT);

$categorycontext = (new \local_program\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);

$url = new moodle_url($CFG->wwwroot . '/local/program/session_enrolusers.php',
    array('bcid' => $programid));
if ($levelid > 0) {
    $url->param('levelid', $levelid);
}
if ($bclcid > 0) {
    $url->param('bclcid', $bclcid);
}
if ($sessionid > 0) {
    $url->param('sid', $sessionid);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

$program_name = $DB->get_field('local_program','name',array('id' => $programid));
$renderer = $PAGE->get_renderer('local_program');

$program=$renderer->programview_check($programid);

$session_name = $DB->get_field('local_bc_course_sessions','name',array('id' => $sessionid));
$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('view.php',
    array('bcid' => $programid, 'sid' => $sessionid)));
$PAGE->navbar->add($program_name);
$PAGE->set_title($program_name);
$PAGE->set_heading(get_string('session_enrollusers', 'local_program', $session_name));
$renderer = $PAGE->get_renderer('local_program');
$programclass = new program();
require_login();

require_capability('local/program:manageprogram', $categorycontext);
require_capability('local/program:manageusers', $categorycontext);
// print_object($view);
// if ($view == 'ajax') {
//     $options = (array)json_decode($_GET["options"], false);
//      $select_from_users = (new program)->select_to_and_from_users_sessions($type, $programid, , $options,false, $offset1=-1, $perpage=50, $lastitem);
//     echo json_encode($select_from_users);
//     exit;
// }
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
$data_submitted = data_submitted();
if ($programid && $sessionid) {


    if (file_exists($CFG->dirroot . '/local/lib.php')) {
        require_once($CFG->dirroot . '/local/lib.php');
        $filterlist = get_filterslist();
    }
    if (!empty($courses_plugin_exists)) {
        $mform = new filters_form($url, array('filterlist'=>$filterlist, 'action' => 'user_enrolment'));
        if ($mform->is_cancelled()) {
            redirect($PAGE->url);
        } else {
            $filterdata = $mform->get_data();
            if ($filterdata) {
                $collapse = false;
            } else {
                $collapse = true;
            }
            $organization = !empty($filterdata->filteropen_costcenterid) ? implode(',', $filterdata->filteropen_costcenterid) : null;
            $department = !empty($filterdata->filteropen_department) ? implode(',', $filterdata->filteropen_department) : null;
            $subdepartment = !empty($filterdata->filteropen_subdepartment) ? implode(',', $filterdata->filteropen_subdepartment) : null;
            $department4level = !empty($filterdata->filteropen_level4department) ? implode(',', $filterdata->filteropen_level4department) : null;
            $department5level = !empty($filterdata->filteropen_level5department) ? implode(',', $filterdata->filteropen_level5department) : null;
            $states = !empty($filterdata->states) ? implode(',', $filterdata->states) : null;
            $district = !empty($filterdata->district) ? implode(',', $filterdata->district) : null;
            $subdistrict = !empty($filterdata->subdistrict) ? implode(',', $filterdata->subdistrict) : null;
            $village = !empty($filterdata->village) ? implode(',', $filterdata->village) : null;


            $email = !empty($filterdata->email) ? implode(',', $filterdata->email) : null;
            $idnumber = !empty($filterdata->idnumber) ? implode(',', $filterdata->idnumber) : null;
            $uname = !empty($filterdata->users) ? implode(',', $filterdata->users) : null;
            $groups = !empty($filterdata->groups) ? implode(',', $filterdata->groups) : null;
        }
    }

    // Create the user selector objects.

    $options = array('context' => $categorycontext->id, 'programid' => $programid, 'organization' => $organization, 'department' => $department,'subdepartment' => $subdepartment,'department4level' => $department4level,'department5level' => $department5level,'states' => $states,'district' => $district,'subdistrict' => $subdistrict,'village' => $village, 'email' => $email, 'idnumber' => $idnumber, 'uname' => $uname, 'groups' => $groups, 'hrmsrole' => $hrmsrole, 'location' => $location);


    if ($add && confirm_sesskey()) {
        if ($submit_value == "Add_All_Users") {
            $options = (array)json_decode($_REQUEST["options"], false);
            $userstoassign = array_flip((new program)->select_to_and_from_users_sessions('add',
                $programid, $levelid, $bclcid, $sessionid, $options, false, $offset1=-1, $perpage=-1));
        } else {
            $userstoassign = $add;
        }

        if (!empty($userstoassign)) {
            $programclass->session_add_assignusers($programid, $levelid, $bclcid, $sessionid, $userstoassign);
        }
        redirect($PAGE->url);
    }
    if ($remove && confirm_sesskey()) {
        if ($submit_value == "Remove_All_Users") {
            $options = (array)json_decode($_REQUEST["options"], false);
            $userstounassign = array_flip((new program)->select_to_and_from_users_sessions('remove',
                $programid, $levelid, $bclcid, $sessionid, $options, false, $offset1=-1, $perpage=-1));
        } else {
            $userstounassign = $remove;
        }
        if (!empty($userstounassign)) {
            $programclass->session_remove_assignusers($programid, $levelid, $bclcid, $sessionid, $userstounassign);
        }
        redirect($PAGE->url);
    }
    $select_to_users = (new program)->select_to_and_from_users_sessions('add', $programid, $levelid, $bclcid, $sessionid, $options, false, $offset=-1, $perpage=50);
    $select_to_users_total = (new program)->select_to_and_from_users_sessions('add', $programid, $levelid, $bclcid, $sessionid, $options, true, $offset1=-1, $perpage=-1);
    $select_from_users = (new program)->select_to_and_from_users_sessions('remove', $programid, $levelid, $bclcid, $sessionid, $options,false, $offset1=-1, $perpage=50);
    $select_from_users_total = (new program)->select_to_and_from_users_sessions('remove', $programid, $levelid, $bclcid, $sessionid,
        $options, true, $offset1=-1, $perpage=-1);

    $select_all_enrolled_users = '&nbsp&nbsp<button type="button" id="select_add" name="select_all" value="Select All" title="Select All"/ class="btn btn-default">' . get_string('select_all', 'local_program') . '</button>';
    $select_all_enrolled_users.='&nbsp&nbsp<button type="button" id="add_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>' . get_string('remove_all', 'local_program') . '</button>';

    $select_all_not_enrolled_users='&nbsp&nbsp<button type="button" id="select_remove" name="select_all" value="Select All" title="Select All" class="btn btn-default"/>' . get_string('select_all', 'local_program') . '</button>';
    $select_all_not_enrolled_users.='&nbsp&nbsp<button type="button" id="remove_select" name="remove_all" value="Remove All" title="Remove All" class="btn btn-default"/>' . get_string('remove_all', 'local_program') . '</button>';

    $content = '<div class="bootstrap-duallistbox-container">';
    $content .= '<form  method="post" name="form_name" id="user_assign" class="form_class" ><div class="box2 col-lg-6 col-md-6 pull-left">
        <input type="hidden" name="bcid" value="'.$programid.'"/>
        <input type="hidden" name="sesskey" value="'.sesskey().'"/>
        <input type="hidden" name="options"  value='.json_encode($options).' />
        <label>' . get_string('enrolled_users', 'local_program', $select_from_users_total) . '</label>'.$select_all_not_enrolled_users . '
   <span class="info-container">
        <span class="info"></span>
            <button type="button" class="custom_btn btn clear2 pull-right"></button>
        </span>
   <div class="custom_btn btn-group buttons mt-3">
        <button type="submit" class="custom_btn btn remove btn-default" disabled="disabled" title="Remove Selected Users" name="submit_value" value="Remove Selected Users"/>
       '.get_string('remove_selected_users', 'local_program').'
        </button>
         <button type="submit" class="custom_btn btn removeall btn-default" disabled="disabled" title="Remove All Users" name="submit_value" value="Remove_All_Users"/>
         '.get_string('remove_all_users', 'local_program').'
         </button>
   </div>';
    $content .= '<select multiple="multiple" name="remove[]" id="bootstrap-duallistbox-selected-list_duallistbox_program_users" class="dual_select">';
    foreach($select_from_users as $key=>$select_from_user){
        $presentsessions = $DB->count_records('local_bc_session_signups',
                    array('programid' => $programid,
                        'levelid' => $levelid, 'bclcid' => $bclcid,
                        'userid' => $key, 'completion_status' => 1));
        if($presentsessions > 0){
            $disable = "disabled";
        }else{
            $disable = " ";
        }
        $content .= "<option value='$key' $disable = $disable>$select_from_user</option>";
    }

    $content.='</select>';
    $content.='</div></form>';
    $content.='<form  method="post" name="form_name" id="user_un_assign" class="form_class" ><div class="box1 col-lg-6 col-md-6 pull-left">
    <input type="hidden" name="bcid" value="'.$programid.'"/>
    <input type="hidden" name="sesskey" value="'.sesskey().'"/>
    <input type="hidden" name="options"  value='.json_encode($options).' />
   <label> '.get_string('not_enrolled_users', 'local_program',$select_to_users_total). '</label>'.$select_all_enrolled_users.'
        <span class="info-container">
            <span class="info"></span>
                <button type="button" class="custom_btn btn clear1 pull-right"></button>
            </span>
    <div class="custom_btn btn-group buttons mt-3">

        <button type="submit" class="custom_btn btn moveall btn-default" disabled="disabled" title="Add All Users" name="submit_value" value="Add_All_Users"/>
        '.get_string('add_all_users', 'local_program').'
        </button>

        <button type="submit" class="custom_btn btn move btn-default" disabled="disabled" title="Add Selected Users" name="submit_value" value="Add Selected Users"/>
       '.get_string('add_selected_users', 'local_program').'
        </button>

    </div>';
    $content.='<select multiple="multiple" name="add[]" id="bootstrap-duallistbox-nonselected-list_duallistbox_program_users" class="dual_select">';
    foreach($select_to_users as $key=>$select_to_user){
          $content.="<option value='$key'>$select_to_user</option>";
    }
    $content.='</select>';
    $content.='</div></form>';
    $content.='</div>';
}
echo $OUTPUT->header();
if (!empty($courses_plugin_exists)) {
    print_collapsible_region_start(' ', 'filters_form', ' ' . ' ' . get_string('filters'), false, $collapse);
    $mform->display();
    print_collapsible_region_end();
}
if ($programid) {
    $session_capacity_check=(new program)->session_capacity_check($programid, $levelid, $bclcid, $sessionid);
        $capacity_check =  '';
        if($session_capacity_check){
        $capacity_check = '<div class="w-full pull-left">'.get_string('capacity_check', 'local_program').'</div>';
    }
    $select_div = '<div class="row d-block">
                        <div class="w-100 pull-left">'.$capacity_check.$content.'</div>
                   </div>';
echo $select_div;
$myJSON = json_encode($options);
echo "<script language='javascript'>

$( document ).ready(function() {
    $('#select_remove').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', true);
        //$('.box2 .removeall').addClass('submit_button');
        $('.box2 .removeall').prop('disabled', false);
        $('.box2 .remove').prop('disabled', true);

        $('.box1 .moveall').prop('disabled', true);
        $('.box1 .move').prop('disabled', true);
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected', false);

    });
    $('#remove_select').click(function() {
        $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', false);
        $('.box2 .removeall').prop('disabled', true);
        //$('.box2 .removeall').removeClass('submit_button');
        $('.box2 .remove').prop('disabled', true);

        $('.box1 .moveall').prop('disabled', true);
        $('.box1 .move').prop('disabled', true);
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected', false);

    });
    $('#select_add').click(function() {
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected', true);
        //$('.box1 .moveall').addClass('submit_button');
        $('.box1 .moveall').prop('disabled', false);
        $('.box1 .move').prop('disabled', true);

        $('.box2 .removeall').prop('disabled', true);
        $('.box2 .remove').prop('disabled', true);
         $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', false);
    });
    $('#add_select').click(function() {
        $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected',false);
        $('.box1 .moveall').prop('disabled', true);
        //$('.box1 .moveall').removeClass('submit_button');
        $('.box1 .move').prop('disabled', true);

        $('.box2 .removeall').prop('disabled', true);
        $('.box2 .remove').prop('disabled', true);
         $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', false);
    });
    $('#bootstrap-duallistbox-selected-list_duallistbox_program_users').on('change', function() {
        if(this.value!=''){
            $('.box2 .remove').prop('disabled', false);
            //$('.box2 .remove').addClass('submit_button');
            $('.box2 .removeall').prop('disabled', true);

            $('.box1 .moveall').prop('disabled', true);
            $('.box1 .move').prop('disabled', true);
            $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users option').prop('selected', false);
        }
    });
    $('#bootstrap-duallistbox-nonselected-list_duallistbox_program_users').on('change', function() {
        if(this.value!=''){
            $('.box1 .move').prop('disabled', false);
            //$('.box1 .move').addClass('submit_button');
            $('.box1 .moveall').prop('disabled', true);

            $('.box2 .removeall').prop('disabled', true);
            $('.box2 .remove').prop('disabled', true);
            $('#bootstrap-duallistbox-selected-list_duallistbox_program_users option').prop('selected', false);
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

              if(count_selected_list<total_users){
                   //alert('end reached');
                    var selected_list_request = $.ajax({
                        method: 'GET',
                        url: M.cfg.wwwroot + '/local/program/enrollusers.php?options=$myJSON',
                        data: {bcid:'$programid',sesskey:'$sesskey', type:type,view:'ajax',lastitem:lastValue},
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
$continue = '<div class="col-md-1 pull-right">';
$continue .= '<a href='.$CFG->wwwroot.'/local/program/sessions.php?bclcid='.$bclcid.''.'&levelid='.$levelid.''.'&bcid='.$programid.' class="singlebutton"><button>'.get_string('continue', 'local_program').'</button></a>';
$continue .= '</div>';
echo $continue;
echo $OUTPUT->footer();