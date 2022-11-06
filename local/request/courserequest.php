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
 * @subpackage local_request
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
$component = optional_param('component', null, PARAM_RAW);
$courseid        = optional_param('courseid', '', PARAM_INT);
global $OUTPUT, $PAGE, $USER, $DB;

require_login(); 
$title = get_string('viewrequest', 'local_request');

// Set up the page.
$url = new moodle_url("/local/request/index.php", array('courseid'=>$courseid));
//$PAGE->set_context($pagecontext);

$PAGE->set_pagelayout('standard');
$PAGE->set_url($url);
$coursename = $DB->get_field('course','fullname',array('id'=>$courseid));
$PAGE->set_title($coursename);
$heading = $title.' '.'for'.' '.$coursename;
$PAGE->navbar->add(get_string('manage_courses','local_courses'), new moodle_url('/local/courses/courses.php'));
$PAGE->navbar->add(get_string("pluginname", 'local_request'));
$PAGE->set_heading($heading);
$PAGE->requires->js_call_amd('local_request/requestconfirm', 'load', array());

$output = $PAGE->get_renderer('local_request');
echo $OUTPUT->header();

    $usercontext =(new \local_request\lib\accesslib())::get_module_context();
    $return = '';
	$courses = $DB->get_records('local_request_records', array('compname' =>'elearning','componentid'=>$courseid));
    $output = $PAGE->get_renderer('local_request');
    $component = 'elearning';
        if($courses){
            $return = $output->render_requestview(new \local_request\output\requestview($courses, $component,true));
        }else{
        	$return = '<div class="alert alert-info">'.get_string('requestavail', 'local_classroom').'</div>';
        }
	
echo $return;
echo $OUTPUT->footer();