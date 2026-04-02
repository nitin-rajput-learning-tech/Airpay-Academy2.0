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
require_once($CFG->dirroot . '/local/learningplan/filters_form.php');

global $DB, $USER, $CFG,$PAGE,$OUTPUT;
use core_component;
$value = '';
$course_enrol=optional_param('courseid', 0, PARAM_INT);
$userid=optional_param('userid', 0, PARAM_INT);
$planid=optional_param('planid', 0, PARAM_INT);

$status1 = optional_param('status1', '', PARAM_RAW);
$costcenterid = optional_param('costcenterid', '', PARAM_INT);
$departmentid = optional_param('departmentid', '', PARAM_INT);
$subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
$formattype = optional_param('formattype', 'card', PARAM_TEXT);
if ($formattype == 'card') {  
    $formattype_url = 'table';
    $display_text = get_string('listtype','local_learningplan');
    $display_icon = get_string('listicon','local_learningplan');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype','local_learningplan');
    $display_icon = get_string('cardicon','local_learningplan');
}

$PAGE->requires->jquery();
$PAGE->requires->css('/local/learningplan/css/jquery.dataTables.css');
$PAGE->requires->js('/local/learningplan/js/jquery.dataTables.min.js', true);
$core_component = new core_component();
$epsilon_plugin_exist = $core_component::get_plugin_directory('theme', 'epsilon');
if(!empty($epsilon_plugin_exist)){
    $PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
}

$return_url = new moodle_url('/local/learningplan/managelearningplan.php');
$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
$PAGE->requires->js_call_amd('local_learningplan/lpcreate', 'load', array());
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'downloadtrigger',array());

//check the context level of the user and check whether the user is login to the system or not
$PAGE->set_context($categorycontext);
require_login();
$PAGE->set_url('/local/learningplan/index.php');
$PAGE->set_title(get_string('pluginname', 'local_learningplan'));
$PAGE->set_pagelayout('standard');
//Header and the navigation bar
$PAGE->set_heading(get_string('pluginname', 'local_learningplan'));
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string("pluginname", 'local_learningplan'));
$learningplan_renderer = new local_learningplan\render\view();
$learningplan_lib = new local_learningplan\lib\lib();

$costcenterid = optional_param('costcenterid', '', PARAM_INT);
$departmentid = optional_param('departmentid', '', PARAM_INT);
$subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
$l4department = optional_param('l4department', '', PARAM_INT);
$l5department = optional_param('l5department', '', PARAM_INT);
echo $OUTPUT->header(); 
$enabled = check_learningplanenrol_pluginstatus($value);

if($course_enrol && $planid && $userid){

    $enrol=$learningplan_lib->to_enrol_users($planid,$userid,$course_enrol);
    if($enrol){
        $plan_url = new moodle_url('/course/view.php', array('id' => $course_enrol));
        redirect($plan_url);
    }
}
if(!is_siteadmin()){
    require_capability('local/learningplan:manage', $categorycontext);
}
$out = "<ul class='course_extended_menu_list learning_plan'>";
if(is_siteadmin() ||(
        has_capability('local/learningplan:create', $categorycontext)|| has_capability('local/learningplan:update', $categorycontext)||has_capability('local/learningplan:manage', $categorycontext))){
     $sql = "SELECT id,name FROM {block_learnerscript} WHERE category = 'local_learningplan'" ;
    $learningplanreports = $DB->get_records_sql($sql);
      //print_object($courses);
     // exit;
    if($learningplanreports){

        $out .= '<li><div class="dropdown"><a href="#" tabindex="0" class=" dropdown-toggle icon-no-margin" id="dropdown-1" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="reportswitch" title="'.get_string('learningplan_reports','local_learningplan').'">            
                <i class="icon fa fa-signal" aria-hidden="true"></i>
            </a>';


        $out .= '<div class="dropdown-menu dropdown-menu-right menu  align-tr-br" id="reportswitch" data-rel="menu-content" aria-labelledby="action-menu-toggle-1" role="menu" data-align="tr-br">';
        $reports_info = array();
        foreach($learningplanreports as $learningplan){
            $reports = array();
            $reports['id'] = $learningplan->id;
            $reports['name'] = $learningplan->name;
            // $reports_info[] = $reports;
            $reports_info[] = '<a href='.$CFG->wwwroot.'/blocks/learnerscript/viewreport.php?id='.$learningplan->id.' class="dropdown-item menu-action" role="menuitem" data-title="'.$learningplan->name.'" aria-label="'.$learningplan->name.'"target="_blank">
                <span class="menu-action-text">
                    '.$learningplan->name.'
                </span>
            </a>';

        }
        $out .= implode('<div class="dropdown-divider" role="presentation"><span class="filler">&nbsp;</span></div>', $reports_info);
        $out .= '</div></div></li>';
    }
    if(is_siteadmin() || has_capability('local/learningplan:exportplans', $categorycontext)){
        $out .= "<li>
            <div class='coursebackup course_extended_menu_itemcontainer'>
                <a id='extended_menu_downloadusers' title='".get_string('exportlearningplans','local_learningplan')."' class='course_extended_menu_itemlink custom_content_download' data-href='$CFG->wwwroot/local/learningplan/exportcsv.php' href='javascript:void(0);'>
                    <i class='icon fa fa-download fa-fw' aria-hidden='true' aria-label=''></i>
                </a>
            </div>
        </li>";
    }
}
if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
        $out .= "<li>    
            <div class = 'coursebackup course_extended_menu_itemcontainer'>
                <a href='".$CFG->wwwroot."/local/request/index.php?component=learningplan' class='course_extended_menu_itemlink' title='".get_string('request','local_learningplan')."'><i class='icon fa fa-share-square' aria-hidden='true'></i>
                </a>
            </div>
        </li>";
}
if (is_siteadmin() || (has_capability('local/learningplan:create', $categorycontext) && has_capability('local/learningplan:manage', $categorycontext))) {
    $titlestring = get_string('addnew_learningplans','local_learningplan');
    $out .= "<li>    
                <div class = 'coursebackup course_extended_menu_itemcontainer'>
                    <a class='course_extended_menu_itemlink' data-action='createlpmodal' title='$titlestring' onclick ='(function(e){ require(\"local_learningplan/lpcreate\").init({selector:\"createlpmodal\", contextid:$categorycontext->id, planid:0, form_status:0, callback:\"learningplan_form\"}) })(event)'><span class='createicon'><i class='icon fa fa-map' aria-hidden='true' aria-label=''></i><i class='fa fa-plus createiconchild' aria-hidden='true'></i></span>
                    </a>
                </div>
            </li>";
}

$out .= "</ul>";
echo $out;
//$depth = $USER->useraccess['currentroleinfo']['depth'];
$thisfilters = array(/*'organizations', 'departments',
    'subdepartment', 'department4level','department5level',*/'hierarchy_fields','categories','learningplan','status');
if(!is_siteadmin()) {
$thisfilters = array('hierarchy_fields','categories','learningplan','status');
}

    $formdata = new stdClass();
    $formdata->filteropen_costcenterid = $costcenterid;
    $formdata->filteropen_department = $departmentid;
    $formdata->filteropen_subdepartment = $subdepartmentid;
    $formdata->filteropen_level4department = $l4department;
    $formdata->filteropen_level5department = $l5department;

$datasubmitted = data_submitted() ? data_submitted() : $formdata;
$mform = new filters_form(null, array('filterlist'=> $thisfilters)+(array)$datasubmitted);
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/local/learningplan/index.php');
} else{
    $filterdata =  $mform->get_data();
    if($filterdata){
        $collapse = false;
    } else{
        $collapse = true;
    }
}
if(empty($filterdata) && !empty($jsonparam)){
    $filterdata = json_decode($jsonparam);
    foreach($thisfilters AS $filter){
        if(empty($filterdata->$filter)){
            unset($filterdata->$filter);
        }
    }
    $mform->set_data($filterdata);
}
if(!empty($costcenterid)|| !empty($status1) || !empty($departmentid) || !empty($subdepartmentid)){   
    $formdata = new stdClass();
    $formdata->filteropen_costcenterid[] = $costcenterid;
    $formdata->filteropen_department[] = $departmentid;
    $formdata->filteropen_subdepartment[] = $subdepartmentid;
    $formdata->filteropen_level4department[] = $l4department;
    $formdata->filteropen_level5department[] = $l5department;
    $formdata->status[] = $status1;
    $mform->set_data($formdata);
echo '<span id="global_filter" class="hidden" data-filterdata='.json_encode($formdata).'></span>';

}

if($filterdata){
    $collapse = false;
    $show = 'show';
} else{
    $collapse = true;
    $show = '';
}

echo '<a class="btn-link btn-sm" data-toggle="collapse" data-target="#local_courses-filter_collapse" aria-expanded="false" aria-controls="local_courses-filter_collapse">
        <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
      </a>';
echo  '<div class="collapse '.$show.'" id="local_courses-filter_collapse">
            <div id="filters_form" class="card card-body p-2">';
                $mform->display();
echo        '</div>
        </div>';
echo '<span id="global_filter" class="hidden" data-filterdata='.json_encode($filterdata).'></span>';

    $condition="";
  
// if (is_siteadmin()){
    $display_url = new moodle_url('/local/learningplan/index.php');
    if($costcenterid){
      $display_url->param('costcenterid', $costcenterid);  
    }
    if($departmentid){
     $display_url->param('departmentid',$departmentid);
    }
    if($subdepartmentid){
     $display_url->param('subdepartmentid',$subdepartmentid);
    }
    if($status1){
     $display_url->param('status1',$status1);
    }
    if($formattype_url){
     $display_url->param('formattype', $formattype_url);      
    } 
    $displaytype_div = '<div class="col-12 d-inline-block">';
    $displaytype_div .= '<a class="btn btn-outline-secondary pull-right" href="' . $display_url . '">';
    $displaytype_div .= '<span class="'.$display_icon.'"></span>' . $display_text;
    $displaytype_div .= '</a>';
    $displaytype_div .= '</div>';

        echo $displaytype_div;
// }
echo $learningplan_renderer->all_learningplans($condition,$normal=array(),false,null,$filterdata,$formattype);
echo $OUTPUT->footer();
