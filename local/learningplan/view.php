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

global $CFG, $PAGE, $OUTPUT;
require_login();
require_once($CFG->dirroot . '/local/learningplan/lib.php');
if(file_exists($CFG->dirroot . '/local/includes.php')){
	require_once($CFG->dirroot . '/local/includes.php');
}

$PAGE->set_url('/local/learningplan/view.php');

$PAGE->requires->jquery();
//$PAGE->requires->jquery_plugin('ui');
//$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('local_learningplan/courseenrol', 'load');
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$id = optional_param('id', null, PARAM_INT);
$systemcontext = (new \local_learningplan\lib\accesslib())::get_module_context($id);
$PAGE->set_context($systemcontext);

$PAGE->set_title(get_string('pluginname', 'local_learningplan'));
$PAGE->set_pagelayout('iltfullpage');

$plan_record = $DB->get_record('local_learningplan', array('id' => $id));
$PAGE->set_heading($plan_record->name);
$PAGE->navbar->ignore_active();
$PAGE->navbar->add( get_string('pluginname', 'local_learningplan'), new moodle_url('/local/learningplan/view.php?id='.$id.''));
$PAGE->requires->jquery();

$is_enrolled = $DB->record_exists('local_learningplan_user', array('planid' => $id, 'userid' => $USER->id));
if(!$is_enrolled){
	redirect($CFG->wwwroot.'/my');
}

$renderer =new local_learningplan\render\view();
$headerlink = $renderer->display_unenrol_button($id, $plan_record->name);
$content = $renderer->learningplaninfo_for_employee($id);
echo $OUTPUT->header();
    if($id){
    	echo $content;
		//echo $headerlink;
    	
    } 
echo $OUTPUT->footer();
