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

use local_learningplan\lib\lib;
use local_learningplan\render\view;
require_once(dirname(__FILE__) . '/../../config.php');

global $DB, $USER, $CFG,$PAGE,$OUTPUT;
require_once($CFG->dirroot . '/local/learningplan/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$curr_tab = optional_param('tab', 'courses', PARAM_TEXT);
$condition = optional_param('condtion','view', PARAM_TEXT);
// $enrol=optional_param('enrolid', 0, PARAM_INT);
$course_enrol=optional_param('courseid', 0, PARAM_INT);
$checkingid=optional_param('couid', 0, PARAM_INT);
$userid=optional_param('userid', 0, PARAM_INT);
$planid=optional_param('planid', 0, PARAM_INT);
$systemcontext = (new \local_learningplan\lib\accesslib())::get_module_context($id);
//check the context level of the user and check whether the user is login to the system or not
$PAGE->set_context($systemcontext);
require_login();
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_learningplan/lpcreate', 'load', array());
$PAGE->requires->js_call_amd('local_learningplan/courseenrol', 'tabsFunction', array('id' => $id));
//This js added by sharath for moduletypw selection in assigning courses
//$PAGE->requires->js_call_amd('local_learningplan/module', 'init', array());
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$PAGE->set_url('/local/learningplan/plan_view.php', array('id' => $id));
$PAGE->set_title(get_string('pluginname', 'local_learningplan'));
$PAGE->set_pagelayout('standard');
//Header and the navigation bar
$plan_record = $DB->get_record('local_learningplan', array('id' => $id));
$PAGE->set_heading($plan_record->name);
$PAGE->navbar->ignore_active();
$PAGE->navbar->add( get_string('pluginname', 'local_learningplan'), new moodle_url('/local/learningplan/index.php'));
$PAGE->navbar->add($plan_record->name);
$learningplan = $DB->get_record('local_learningplan',array('id' => $id));
$is_enrolled = $DB->record_exists('local_learningplan_user',  array('planid' => $id, 'userid' => $USER->id));
if(!($is_enrolled || is_siteadmin() || has_capability('local/learningplan:manage', $systemcontext))){
    $sql="SELECT lp.id ";
    $sql.=" FROM {local_learningplan} lp WHERE id = :id "; 
    $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path');
    $learningplans=$DB->get_records_sql($sql .$costcenterpathconcatsql,array('id'=>$id));
    if(empty($learningplans)){
        redirect($CFG->wwwroot . '/local/learningplan/index.php');  
    }
 }
if(!is_siteadmin()){
    require_capability('local/learningplan:manage', $systemcontext);
}

$PAGE->requires->js_call_amd('local_learningplan/courseenrol', 'load', array());
$learningplan_renderer = new view();
$learningplan_lib = new lib();
$return_url = new moodle_url('/local/learningplan/plan_view.php',array('id'=>$id));
echo $OUTPUT->header();
echo $learningplan_renderer->get_editand_publish_icons($id);
if($id <= 0){
    throw new moodle_exception('invalid_learningplan_id', 'local_learningplan');
}

/**The query Check Whether user enrolled to LEP or NOT**/
$sql="SELECT id FROM {local_learningplan_user} WHERE planid = :planid AND userid = :userid ";
$check=$DB->get_record_sql($sql, array('planid' => $id, 'userid' => $USER->id));
/*End of Query*/

echo $learningplan_renderer->single_plan_view($id);

// if(is_siteadmin() || has_capability('local/costcenter:assign_multiple_departments_manage', $systemcontext)){ /**Condition for to whom the tab should display**/
/*view of the tabs*/
    echo $learningplan_renderer->plan_tabview($id, $curr_tab,$condition);

echo $OUTPUT->footer();
?>
