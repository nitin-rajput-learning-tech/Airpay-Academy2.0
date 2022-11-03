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
 * @subpackage local_costcenter
 */


require_once('../../config.php');
require_once($CFG->dirroot.'/local/costcenter/lib.php');
require_once($CFG->dirroot.'/local/costcenter/renderer.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$deptid = optional_param('deptid', 0, PARAM_INT);
global $DB,$OUTPUT,$CFG, $PAGE;
/* ---First level of checking--- */
require_login();

/* ---Get the records from the database--- */
if (!$depart = $DB->get_record('local_costcenter', array('id' => $id))) {
    print_error('invalidschoolid');
}
if($depart->parentid){

    $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context($depart->parentid);

}else{

    $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
}

if(!has_capability('local/costcenter:view', $systemcontext)) {
    print_error('nopermissiontoviewpage');
}
/*OL-2166- Added the below condition for checking  */

if(!is_siteadmin() || !has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
    if($depart->parentid){
    	if(!($DB->record_exists('user',array('open_departmentid' => $id, 'id' => $USER->id)) || has_capability('local/costcenter:manage_ownorganization', $systemcontext))){
            print_error('nopermissiontoviewpage');
    	}
    }else if(!$DB->record_exists('user',array('open_costcenterid'=>$id,'id'=>$USER->id))){
            print_error('nopermissiontoviewpage');
    }
}

$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
$PAGE->requires->jquery('ui-css');

$PAGE->requires->js_call_amd('local_costcenter/costcenterdatatables', 'costcenterDatatable', array());
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->requires->js_call_amd('local_costcenter/newsubdept', 'load', array());
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');

$PAGE->set_pagelayout('standard');
/* ---check the context level of the user and check whether the user is login to the system or not--- */
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/costcenter/costcenterview.php');
/* ---Header and the navigation bar--- */
$PAGE->navbar->ignore_active();

if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
    $PAGE->navbar->add(get_string('orgmanage', 'local_costcenter'), new moodle_url('/local/costcenter/index.php'));
}
if (!((is_siteadmin()) || has_capability('local/costcenter:manage_multiorganizations', $systemcontext) || has_capability('local/costcenter:manage_ownorganization', $systemcontext))) {
    if($USER->open_departmentid != $id){
        redirect($CFG->wwwroot . '/local/costcenter/costcenterview.php?id='.$USER->open_departmentid);
    }
}

if($depart->parentid){
    if(!has_capability('local/costcenter:manage_owndepartments', $systemcontext) || is_siteadmin()){
        $PAGE->navbar->add($DB->get_field('local_costcenter', 'fullname', array('id' => $depart->parentid)), new moodle_url('/local/costcenter/costcenterview.php', array('id' => $depart->parentid)));
	   $PAGE->navbar->add(get_string('viewsubdepartments', 'local_costcenter'));
    }
    $PAGE->set_heading(get_string('department_structure', 'local_costcenter'));
    $PAGE->set_title(get_string('department_structure', 'local_costcenter'));
}else{
	$PAGE->navbar->add(get_string('viewcostcenter', 'local_costcenter'));
    $PAGE->set_heading(get_string('orgStructure', 'local_costcenter'));
    $PAGE->set_title(get_string('orgStructure', 'local_costcenter'));
}


echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_costcenter');
echo $renderer->get_dept_view_btns($id);
if($depart->parentid){ // display department page
    echo $renderer->department_view($id, $systemcontext);
}else{// display organization page
    echo $renderer->costcenterview($id, $systemcontext);
}
echo $OUTPUT->footer();
