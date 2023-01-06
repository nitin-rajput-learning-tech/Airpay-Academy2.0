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


require_once("../../config.php");
require_once("lib.php");
require_once('evaluation_form.php');

global $DB, $OUTPUT,$USER,$CFG;
require_once($CFG->dirroot . '/local/courses/filters_form.php');

$id = optional_param('id', -1, PARAM_INT); // evalauation id
$plugin = optional_param('plugin','site',PARAM_RAW);
$instance = optional_param('instance', 0, PARAM_INT); // instance id from other pluign
$delete = optional_param('delete', 0, PARAM_INT);
$tab = optional_param('tab',0,PARAM_RAW);
$userid = optional_param('userid','',PARAM_INT);
$sessiontype = optional_param('sessiontype','all',PARAM_RAW);
$status = optional_param('status', '', PARAM_RAW);
$costcenterid = optional_param('costcenterid', '', PARAM_INT);
$departmentid = optional_param('departmentid', '', PARAM_INT);
$formattype = optional_param('formattype', 'card', PARAM_TEXT);
$out = '';
if ($formattype == 'card') {
    $formattype_url = 'table';
    $display_text = get_string('listtype','local_evaluation');
    $display_icon = get_string('listicon','local_evaluation');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype','local_evaluation');
    $display_icon = get_string('cardicon','local_evaluation');
}

require_login();

$context =(new \local_evaluation\lib\accesslib())::get_module_context();
if (!has_capability('local/evaluation:view', $context) ) {
    print_error("You dont have permission to view this page.");
}
$PAGE->set_url('/local/evaluation/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('my_feedbacks', 'local_evaluation'));
$PAGE->set_heading(get_string('my_feedbacks', 'local_evaluation'));
if (is_siteadmin() OR has_capability('local/evaluation:edititems', $context)) {
    $PAGE->set_title(get_string('browse_feedback', 'local_evaluation'));
    $PAGE->set_heading(get_string('browse_feedback', 'local_evaluation'));
}
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_costcenter/fragment', 'init', array());
$PAGE->requires->js_call_amd('local_evaluation/evaluation', 'load', array());
$PAGE->requires->css('/local/evaluation/css/jquery.dataTables.css');

$core_component = new core_component();
$epsilon_plugin_exist = $core_component::get_plugin_directory('theme', 'epsilon');
if(!empty($epsilon_plugin_exist)){
    $PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
}

$current_langcode = current_language();  /* $SESSION->lang;*/
$stringman = get_string_manager();
$strings = $stringman->load_component_strings('local_evaluation', $current_langcode);   /*'en'*/
$PAGE->requires->strings_for_js(array_keys($strings), 'local_evaluation');
$PAGE->navbar->add(get_string("pluginname", 'local_evaluation'));
$renderer = $PAGE->get_renderer('local_evaluation');
$filterparams = $renderer->get_evaluations(true,$formattype);
$depth=$context->depth;
if(is_siteadmin()){
    $thisfilters = array('organizations', 'departments','subdepartment', 'evaluation', 'evaluation_type', 'status','department4level','department5level');
}else if($depth==2) {
    $thisfilters = array('departments','subdepartment', 'evaluation', 'evaluation_type', 'status','department4level','department5level');
}else if($depth==3){
    $thisfilters = array('subdepartment', 'evaluation', 'evaluation_type', 'status','department4level','department5level');
}else if($depth==4){
    $thisfilters = array('evaluation', 'evaluation_type', 'status','department4level','department5level');
}else if($depth==5){
    $thisfilters = array('evaluation', 'evaluation_type', 'status','department5level');
}
$mform = new filters_form(null, array('filterlist'=> $thisfilters, 'filterparams' => $filterparams));
     
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/local/courses/courses.php');
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
if(!empty($costcenterid) || !empty($status)){   
        $formdata = new stdClass();
        $formdata->organizations = $costcenterid;
        $formdata->departments = $departmentid;
        $formdata->subdepartment = $subdepartmentid;
        $formdata->status = $status;
        $mform->set_data($formdata);
}
if($filterdata){
    $collapse = false;
    $show = 'show';
} else{
    $collapse = true;
    $show = '';
}
$PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'load', array());
echo $OUTPUT->header();

if (is_siteadmin() OR has_capability('local/evaluation:edititems', $context)) { 
    if ($delete) {
        $evaluation = $DB->get_record('local_evaluations', array('id'=>$id));
        evaluation_delete_instance($id);        
        $params = array(
            'context' => $context,
            'objectid' => $id
        );

        $event = \local_evaluation\event\evaluation_deleted::create($params);
        $event->add_record_snapshot('local_evaluations', $evaluation);
        $event->trigger();
        redirect('index.php');
    }
    $PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'init', array('[data-action=createevaluationmodal]', $context->id, $id, $instance, $plugin));
    $PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'getdepartmentlist');
}
echo '<ul class="course_extended_menu_list">';
if(is_siteadmin() ||(
        has_capability('local/evaluation:addinstance', $context))){
    $sql = "SELECT id,name FROM {block_learnerscript} WHERE category = 'local_evaluations'" ;
    $feedbackreports = $DB->get_records_sql($sql);
    if($feedbackreports){
        $out .= '<li><div class="dropdown"><a href="#" tabindex="0" class=" dropdown-toggle icon-no-margin" id="dropdown-1" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" aria-controls="reportswitch" title="'.get_string('feedback_reports','local_evaluation').'">            
            <i class="icon fa fa-signal" aria-hidden="true"></i>
        </a>';
 

        $out .= '<div class="dropdown-menu dropdown-menu-right menu  align-tr-br" id="reportswitch" data-rel="menu-content" aria-labelledby="action-menu-toggle-1" role="menu" data-align="tr-br">';
        $reports_info = array();
        foreach($feedbackreports as $feedback){
            $reports = array();
            $reports['id'] = $feedback->id;
            $reports['name'] = $feedback->name;
            $reports_info[] = $reports;
            $out .= '<div class="dropdown-divider" role="presentation"><span class="filler">&nbsp;</span></div>
                                                <a href='.$CFG->wwwroot.'/blocks/learnerscript/viewreport.php?id='.$feedback->id.'class="dropdown-item menu-action" role="menuitem" data-title="'.$feedback->name.'" aria-labelledby="'.$feedback->name.'" target="_blank"> 
                                                    <span class="menu-action-text">
                                                        '.$feedback->name.'
                                                    </span>
                                                </a>';

        }
        $out .= '</div></div></li>';
    }
   
}
echo $out;
if(has_capability('local/evaluation:addinstance', $context)){
    echo  '<li>    
                <div class = "coursebackup course_extended_menu_itemcontainer">
                    <a class="course_extended_menu_itemlink createeval" data-value="0" data-action="createevaluationmodal" title="'.get_string("createevaluation", "local_evaluation").'">
                        <i class="icon fa fa-clipboard" aria-hidden="true"></i>
                    </a>
                </div>
            </li>';
}
echo '</ul>';
if(has_capability('local/evaluation:addinstance', $context)){
echo '<a class="btn-link btn-sm" href="javascript:void(0);" data-toggle="collapse" data-target="#local_courses-filter_collapse" aria-expanded="false" aria-controls="local_courses-filter_collapse" title="Filters">
        <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
      </a>';
}
echo  '<div class="collapse '.$show.' local_filter_collapse" id="local_courses-filter_collapse">
            <div id="filters_form" class="card card-body p-2">';
                $mform->display();
echo        '</div>
        </div>';
$filterparams['submitid'] = 'form#filteringform';
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);

    $display_url = new moodle_url('/local/evaluation/index.php');
    if($status){
     $display_url->param('status',$status);
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
echo $renderer->get_evaluations(false,$formattype);
echo $OUTPUT->footer();
