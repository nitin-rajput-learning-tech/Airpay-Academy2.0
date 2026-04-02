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
$costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path');

$costcentersql = "SELECT lc.id, lc.fullname,lc.parentid,lc.depth
                    FROM {local_costcenter} AS lc WHERE lc.id = $id $costcenterpathconcatsql ";

if (!$depart = $DB->get_record_sql($costcentersql)) {
    throw new moodle_exception('invalidcostcenterid');

}

if(!empty($depart) && isset($depart->path)){

    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context($depart->path);
}else{
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
}

if(!has_capability('local/costcenter:view', $categorycontext)) {
    throw new moodle_exception('nopermissiontoviewpage');
}
$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
$PAGE->requires->jquery('ui-css');

$PAGE->requires->js_call_amd('local_costcenter/costcenterdatatables', 'costcenterDatatable', array());
$PAGE->requires->js_call_amd('local_assignroles/newcostcenterassignrole', 'load', array());

$PAGE->requires->js_call_amd('local_assignroles/rolespopup', 'init',array(array('contextid' => $categorycontext->id, 'selector' => '.rolescostcenterpopup')));
$PAGE->requires->js_call_amd('local_assignroles/popup', 'Datatable', array());


$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->requires->js_call_amd('local_costcenter/newsubdept', 'load', array());
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');

$PAGE->set_pagelayout('standard');
/* ---check the context level of the user and check whether the user is login to the system or not--- */
$PAGE->set_context($categorycontext);
$PAGE->set_url('/local/costcenter/costcenterview.php');
/* ---Header and the navigation bar--- */
$PAGE->navbar->ignore_active();

if(is_siteadmin()){
    $PAGE->navbar->add(get_string('orgmanage', 'local_costcenter'), new moodle_url('/local/costcenter/index.php'));
}
else {
    $organization_url = new moodle_url('/local/costcenter/costcenterview.php',array('id' =>$depart->id));
    $organization_string = get_string('orgStructure','local_costcenter');
}
$superparentfullname = "SELECT lllc.fullname AS fullname, lllc.id AS idd FROM {local_costcenter} AS lc
    JOIN {local_costcenter} AS llc ON llc.id = lc.parentid
    JOIN {local_costcenter} AS lllc ON lllc.id = llc.parentid
    WHERE lc.id = ";

$superparentid = "SELECT lllc.id AS idd FROM {local_costcenter} AS lc
    JOIN {local_costcenter} AS llc ON llc.id = lc.parentid
    JOIN {local_costcenter} AS lllc ON lllc.id = llc.parentid
    WHERE lc.id = ";

$parentfullname = "SELECT llc.fullname AS fullname, llc.id AS idd FROM {local_costcenter} AS lc
    JOIN {local_costcenter} AS llc ON llc.id = lc.parentid
    WHERE lc.id = ";

$parentid = "SELECT llc.id AS idd FROM {local_costcenter} AS lc
    JOIN {local_costcenter} AS llc ON llc.id = lc.parentid
    WHERE lc.id = ";

if($depart->parentid && $depart->depth == 2){
    if(is_siteadmin()){
        $PAGE->navbar->add($DB->get_field('local_costcenter', 'fullname', array('id' => $depart->parentid)), new moodle_url('/local/costcenter/costcenterview.php', array('id' => $depart->parentid)));
	    $PAGE->navbar->add(get_string('viewsubdepartments', 'local_costcenter'));
    }
    $PAGE->set_heading(get_string('department_structure', 'local_costcenter'));
    $PAGE->set_title(get_string('department_structure', 'local_costcenter'));
}
else if($depart->parentid && $depart->depth == 3){
    if(is_siteadmin()){
        $pname = $parentfullname. $depart->parentid;
        $pid = $parentid. $depart->parentid;
        $PAGE->navbar->add($DB->get_field_sql($pname, array('')), new moodle_url('/local/costcenter/costcenterview.php', array('id' => $DB->get_field_sql($pid, array('')))));
        $PAGE->navbar->add($DB->get_field('local_costcenter', 'fullname', array('id' => $depart->parentid)), new moodle_url('/local/costcenter/costcenterview.php', array('id' => $depart->parentid)));
        $PAGE->navbar->add(get_string('viewsubsubdepartments', 'local_costcenter'));
    }
    $PAGE->set_heading(get_string('subdepartment_structure', 'local_costcenter'));
    $PAGE->set_title(get_string('subdepartment_structure', 'local_costcenter'));
}
else if($depart->parentid && $depart->depth == 4){
    if( is_siteadmin()){
        $spname = $superparentfullname. $depart->parentid;
        $spid = $superparentid. $depart->parentid;
        $PAGE->navbar->add($DB->get_field_sql($spname, array('')), new moodle_url('/local/costcenter/costcenterview.php', array('id' => $DB->get_field_sql($spid, array('')))));
        $pname = $parentfullname. $depart->parentid;
        $pid = $parentid. $depart->parentid;
        $PAGE->navbar->add($DB->get_field_sql($pname, array('')), new moodle_url('/local/costcenter/costcenterview.php', array('id' => $DB->get_field_sql($pid, array('')))));
        $PAGE->navbar->add($DB->get_field('local_costcenter', 'fullname', array('id' => $depart->parentid)), new moodle_url('/local/costcenter/costcenterview.php', array('id' => $depart->parentid)));
        $PAGE->navbar->add(get_string('viewsubsubsubdepartments', 'local_costcenter'));
    }
    $PAGE->set_heading(get_string('subsubdepartment_structure', 'local_costcenter'));
    $PAGE->set_title(get_string('subsubdepartment_structure', 'local_costcenter'));
}
else if($depart->parentid && $depart->depth == 5){
    $PAGE->set_heading(get_string('territory_structure', 'local_costcenter'));
    $PAGE->set_title(get_string('territory_structure', 'local_costcenter'));
}
else{
	$PAGE->navbar->add(get_string('viewcostcenter', 'local_costcenter'));
    $PAGE->set_heading(get_string('orgStructure', 'local_costcenter'));
    $PAGE->set_title(get_string('orgStructure', 'local_costcenter'));
}


echo $OUTPUT->header();

$url = new moodle_url('/local/costcenter/costcenterview.php', array('sesskey'=>sesskey()));
$costcenterpath = (new \local_costcenter\lib\accesslib())::get_user_role_switch_select_option($url,'id');

echo $costcenterpath;

$renderer = $PAGE->get_renderer('local_costcenter');
echo $renderer->get_dept_view_btns($id);
if($depart->parentid){ // display department page
    echo $renderer->department_view($id, $categorycontext);
}else{// display organization page
    echo $renderer->costcenterview($id, $categorycontext);
}
echo $OUTPUT->footer();
