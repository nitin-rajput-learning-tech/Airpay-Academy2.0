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
 * @subpackage local_myteam
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

use local_myteam\output\myteam;
use local_myteam\output\courseallocation;
use local_myteam\output\team_approvals;

global $DB, $OUTPUT, $USER, $PAGE;

// $supervisor = $DB->get_field('user', 'id', array('open_supervisorid' => $USER->id));
$supervisor = $DB->record_exists('user', array('open_supervisorid' => $USER->id));
if(empty($supervisor)){
  print_error('nopermissiontoviewpage');
}

$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
require_login();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/myteam/team.php');
$PAGE->set_pagelayout('standard');

//Header and the navigation bar
$PAGE->set_title(get_string('myteam', 'local_myteam'));
$PAGE->set_heading(get_string('myteam', 'local_myteam'));
$PAGE->navbar->add(get_string('myteam', 'local_myteam'));

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('local_myteam/courseallocation', 'load', array());
$PAGE->requires->js_call_amd('local_myteam/team_approvals', 'init', array());
$PAGE->requires->js_call_amd('local_myteam/popupcount', 'init',
	        	array(array('selector'=>'.team_employeestatus' ,'contextid'=>$systemcontext->id)));

$teamclass = new myteam();
$courseallocation = new courseallocation();
$teamapprovals = new team_approvals();

echo $OUTPUT->header();

// $violetcolorlabel = get_string('violet', 'local_myteam');
// $greencolorlabel = get_string('green','local_myteam');
// $redcolorlabel = get_string('red','local_myteam');

// $voiletcolorsspan = html_writer::tag('span',$violetcolorlabel, array('class' => 'colorstring'));
// $greencolorsspan = html_writer::tag('span',$greencolorlabel, array('class' => 'colorstring'));
// $redcolorsspan = html_writer::tag('span',$redcolorlabel, array('class' => 'colorstring'));
// $violetcolorcontent = html_writer::tag('span','', array('class' => 'violetcolorcontent colorpallet'));
// $violetcolorcontainer = html_writer::tag('div',$violetcolorcontent . $voiletcolorsspan , array('class' => 'violetcolorcontainer colorcontainer'));


// $greencolorcontent = html_writer::tag('span','', array('class' => 'greencolorcontent colorpallet'));
// $greencolorcontainer = html_writer::tag('div',$greencolorcontent . $greencolorsspan, array('class' => 'greencolorcontainer colorcontainer'));


// $redcolorcontent = html_writer::tag('span','', array('class' => 'redcolorcontent colorpallet'));
// $redcolorcontainer = html_writer::tag('div',$redcolorcontent . $redcolorsspan, array('class' => 'redcolorcontainer colorcontainer'));

// $colorinfo =  html_writer::tag('div',$violetcolorcontainer . $greencolorcontainer . $redcolorcontainer, array('class' => 'd-flex flex-row flex-wrap justify-content-end align-items-center'));

// echo html_writer::tag('div',$colorinfo,array('class'=>'w-full'));
echo html_writer::start_tag('div', array('class' => 'block_team_status block team_status_wrapper'));
echo $teamclass->team_status_view();
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', array('class' => 'w-100 pull-left my-2'));
echo html_writer::start_tag('div', array('class' => 'block_courseallocation block course_allocation_wrapper col-12 col-md-7 pull-left'));
echo $courseallocation->courseallocation_view(true);
echo html_writer::end_tag('div');

if(has_capability('local/myteam:approve_myteam_request_record', $systemcontext)){
	echo html_writer::start_tag('div', array('class' => 'team_approvals col-12 col-md-5 pull-left pr-0'));
	echo $teamapprovals->team_approvals_view(true);
	echo html_writer::end_tag('div');
}

echo html_writer::end_tag('div');

echo $OUTPUT->footer();
