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

// use local_learningplan\lib\lib;
use local_learningplan\render\view;
require_once(dirname(__FILE__) . '/../../config.php');

global $CFG, $PAGE, $OUTPUT;
require_login();
$PAGE->set_url('/local/learningplan/lpathinfo.php');

$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_learningplan/courseenrol', 'load');
$PAGE->requires->js_call_amd('local_search/courseinfo', 'load', array());
$PAGE->requires->js_call_amd('local_request/requestconfirm', 'load', array());
        
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$id = required_param('id', PARAM_INT);
$PAGE->set_title(get_string('pluginname', 'local_learningplan'));
$PAGE->set_pagelayout('iltfullpage');

$lpathname = $DB->get_field('local_learningplan', 'name', array('id' => $id));
$PAGE->set_heading($lpathname);
$PAGE->navbar->ignore_active();
$PAGE->navbar->add( get_string('pluginname', 'local_learningplan'), new moodle_url('/local/search/allcourses.php?tab=lpath'));
// $is_enrolled = $DB->record_exists('local_learningplan_user', array('planid' => $id, 'userid' => $USER->id));
// if(!$is_enrolled){
//  redirect($CFG->wwwroot.'/my');
// }

echo $OUTPUT->header();

    $renderer =new local_learningplan\render\view();
//  echo $renderer->learningplaninfo_for_employee($id);
    echo $renderer->lpathinfo_for_employee($id);

echo $OUTPUT->footer();
