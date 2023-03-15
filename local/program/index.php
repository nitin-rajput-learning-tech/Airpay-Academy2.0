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
 * @subpackage local_program
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/local/program/filters_form.php');
$categorycontext = (new \local_program\lib\accesslib())::get_module_context();
require_login();
$value = '';
$id = optional_param('id', 0, PARAM_INT); // program id
$hide = optional_param('hide', 0, PARAM_INT);
$show = optional_param('show', 0, PARAM_INT);

$status = optional_param('status', '', PARAM_TEXT);

$costcenterid = optional_param('costcenterid', '', PARAM_INT);
$departmentid = optional_param('departmentid', '', PARAM_INT);
$subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
$l4department = optional_param('l4department', '', PARAM_INT);
$l5department = optional_param('l5department', '', PARAM_INT);
$programid = optional_param('program', '', PARAM_INT);


$formattype = optional_param('formattype', 'card', PARAM_TEXT);

if ($formattype == 'card') {
     $formattype_url = 'table';
    $display_text = get_string('listtype','local_program');
    $display_icon = get_string('listicon','local_program');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype','local_program');
    $display_icon = get_string('cardicon','local_program');
}
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->set_url($CFG->wwwroot . '/local/program/index.php');
$PAGE->set_context($categorycontext);
if (!is_siteadmin() && !(has_capability('local/program:manageprogram', $categorycontext))) {
	$PAGE->set_title(get_string('my_programs', 'local_program'));
	$PAGE->set_heading(get_string('my_programs', 'local_program'));
}else{
	$PAGE->set_title(get_string('browse_programs', 'local_program'));
	$PAGE->set_heading(get_string('browse_programs', 'local_program'));
}
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/local/program/css/jquery.dataTables.min.css', true);
$PAGE->requires->js_call_amd('local_program/ajaxforms', 'load');

$corecomponent = new core_component();
$epsilonpluginexist = $corecomponent::get_plugin_directory('theme', 'epsilon');
if (!empty($epsilonpluginexist)) {
	$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
}
$renderer = $PAGE->get_renderer('local_program');
$PAGE->navbar->add(get_string("pluginname", 'local_program'));
echo $OUTPUT->header();

// hide the program.
if ($hide AND $id) {
	$program = $DB->get_record('local_program', array('id'=>$id));
	$DB->set_field('local_program', 'visible', 0, array('id'=>$id));
	redirect('index.php');
}
//show the program
if ($show AND $id) {
	$program = $DB->get_record('local_program', array('id'=>$id));
	$DB->set_field('local_program', 'visible', 1, array('id'=>$id));
	redirect('index.php');
}
$enabled = check_programenrol_pluginstatus($value);


$display_url = new moodle_url('/local/program/index.php');
if($costcenterid){
  $display_url->param('costcenterid', $costcenterid);
}
if($departmentid){
 $display_url->param('departmentid',$departmentid);
}
if($subdepartmentid){
 $display_url->param('subdepartmentid',$subdepartmentid);
}
if($l4department){
 $display_url->param('l4department',$l4department);
}
if($l5department){
 $display_url->param('l5department',$l5department);
}
if($formattype_url){
 $display_url->param('formattype', $formattype_url);
}

$thisfilters = array('hierarchy_fields','categories','program', 'status');

$formdata = new stdClass();
$formdata->filteropen_costcenterid = $costcenterid;
$formdata->filteropen_department = $departmentid;
$formdata->filteropen_subdepartment = $subdepartmentid;
$formdata->filteropen_level4department = $l4department;
$formdata->filteropen_level5department = $l5department;
$formdata->filteropen_level5department = $l5department;

$datasubmitted = data_submitted() ? data_submitted() : $formdata;
$mform = new filters_form(new moodle_url('/local/program/index.php',array('formattype'=>$formattype)), array('filterlist'=> $thisfilters)+(array)$datasubmitted);
$filterdata = null;     
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/local/program/index.php');
} else{
    $filterdata =  $mform->get_data();
    if($filterdata){
        $collapse = false;
        $show = 'show';
    } else{
        $collapse = true;
        $show = '';
    }
}
$mform->set_data($datasubmitted);

echo '<a class="btn-link btn-sm" href="javascript:void(0);" data-toggle="collapse" data-target="#local_courses-filter_collapse" aria-expanded="false" aria-controls="local_courses-filter_collapse">
        <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
      </a>';
echo  '<div class="collapse '.$show.'" id="local_courses-filter_collapse">
            <div id="filters_form" class="card card-body p-2">';
                $mform->display();
echo        '</div>
        </div>';

$displaytype_div = '<div class="col-12 d-inline-block">';
$displaytype_div .= '<a class="btn btn-outline-secondary pull-right" href="' . $display_url . '">';
$displaytype_div .= '<span class="'.$display_icon.'"></span>' . $display_text;
$displaytype_div .= '</a>';
$displaytype_div .= '</div>';

echo $displaytype_div;


echo $renderer->get_program_tabs($datasubmitted,$programid,$status,$formattype);

$organization = !empty(array_filter($datasubmitted->filteropen_costcenterid)) ? implode(',', array_filter($datasubmitted->filteropen_costcenterid)) : '';
$department = !empty(array_filter($datasubmitted->filteropen_department)) ? implode(',', array_filter($datasubmitted->filteropen_department)) : '';
$subdepartment = !empty(array_filter($datasubmitted->filteropen_subdepartment)) ? implode(',', array_filter($datasubmitted->filteropen_subdepartment)) : '';
$department4level = !empty(array_filter($datasubmitted->filteropen_level4department)) ? implode(',', array_filter($datasubmitted->filteropen_level4department)) : '';
$department5level = !empty(array_filter($datasubmitted->filteropen_level5department)) ? implode(',', array_filter($datasubmitted->filteropen_level5department)) : '';

$selectedstatus = !empty(array_filter($status)) ? implode(',', array_filter($status)) : '';
$categories = !empty(array_filter($datasubmitted->categories)) ? implode(',', array_filter($datasubmitted->categories)) : '';

$PAGE->requires->js_call_amd('local_program/program', 'programDatatable',
                    array(array('programstatus' => -1,'selectedcostcenterid' => $organization,'selecteddepartmentid' => $department,'selectedsubdepartmentid' => $subdepartment,'selectedl4department' => $department4level,'selectedl5department' => $department5level,'selectedprogram' => $programid ,'selectedstatus' => $selectedstatus,'formattype'=>$formattype,'selectedcategories'=>$categories)));

echo $OUTPUT->footer();
