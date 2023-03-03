<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');
global $CFG, $PAGE, $OUTPUT,$DB, $USER;
// $adminediting = optional_param('adminedit', -1, PARAM_BOOL);

require_login();
if (isguestuser()) {
    print_error('noguest');
}

$context = (new \local_costcenter\lib\accesslib())::get_module_context();
$PAGE->set_context($context);

// if ($PAGE->user_allowed_editing() && $adminediting != -1) {
//     $USER->editing = $adminediting;
// }

$seturl ='/blocks/trainerdashboard/dashboard.php';
$pagepattentype = 'blocks-trainerdashboard-dashboard';

$PAGE->set_url($seturl);
$PAGE->set_pagetype($pagepattentype);
$PAGE->set_pagelayout('base');
$PAGE->add_body_class('trainerdashboard');
$PAGE->navbar->ignore_active();
$navurl = new moodle_url('/blocks/trainerdashboard/dashboard.php', array());
$PAGE->navbar->add(get_string('trainerdashboard', 'block_trainerdashboard'), $navurl);

require_capability('block/trainerdashboard:viewtrainerslist', $context);


$output = $PAGE->get_renderer('block_trainerdashboard');

$regions = array('side-db-first', 'side-db-second', 'side-db-third',
                 'side-db-four', 'side-db-one', 'side-db-two',
                 'side-db-three', 'side-db-main', 'center-first', 'center-second','reports-db-one','reports-db-two',
                 'reportdb-one','reportdb-second','reportdb-third','first-maindb');
$PAGE->blocks->add_regions($regions);

if (has_capability('local/classroom:trainer_viewclassroom', $context) && !is_siteadmin()) {

    $header = get_string('mytrainerdashboard', 'block_trainerdashboard');

}else{

    $header = get_string('trainerdashboard', 'block_trainerdashboard');

}
$PAGE->set_title($header);
$PAGE->set_heading($header);

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('block_trainerdashboard/trainerdashboard', 'init',array());
$renderer = $PAGE->get_renderer('block_trainerdashboard');
echo $OUTPUT->header();

// echo html_writer::start_tag('div', array());

// $configuredinstances = $DB->count_records('block_instances', array(
//                             'pagetypepattern' => $pagepattentype));

//     $editingon = false;
//     if (is_siteadmin() && isset($USER->editing) && $USER->editing) {
//         $editingon = true;
//     }
//     $turnediting = '';
//     if ($PAGE->user_allowed_editing()) {
//         $url = clone ($PAGE->url);
//         if ($PAGE->user_is_editing()) {
//             $caption = get_string('blockseditoff');
//             $url->param('adminedit', 'off');
//         } else {
//             $caption = get_string('blocksediton');
//             $url->param('adminedit', 'on');
//         }
//         $turnediting = $OUTPUT->single_button($url, $caption, 'get') . '</span>';
//     }
//     echo html_writer::start_tag('div', array('class' => 'width-container'));
//     echo $OUTPUT->blocks('side-db-first', 'width-default width-3');
//     echo $OUTPUT->blocks('side-db-second', 'width-default width-3');
//     echo $OUTPUT->blocks('side-db-third', 'width-default width-3');
//     echo $OUTPUT->blocks('side-db-four', 'width-default width-3');
//     echo html_writer::end_tag('div');
//     echo html_writer::start_tag('div', array('class' => 'width-container'));
//     echo $OUTPUT->blocks('first-maindb', 'width-default width-12');
//     echo html_writer::end_tag('div');
//     echo html_writer::start_tag('div', array('class' => 'width-container reports-act-graphs'));
//     echo $OUTPUT->blocks('reportdb-one', 'width-default width-4 ml0');
//     echo $OUTPUT->blocks('reportdb-second', 'width-default width-4');
//     echo $OUTPUT->blocks('reportdb-third', 'width-default width-4');
//     echo html_writer::end_tag('div');

//     echo html_writer::start_tag('div', array('class' => 'width-container'));
//     echo $OUTPUT->blocks('center-first', 'width-default width-9');
//     echo $OUTPUT->blocks('center-second', 'width-default width-3');
//     echo html_writer::end_tag('div');

//     echo html_writer::start_tag('div', array('class' => 'width-container'));
//     echo $OUTPUT->blocks('reports-db-one', 'width-default width-6');
//     echo $OUTPUT->blocks('reports-db-two', 'width-default width-6');
//     echo html_writer::end_tag('div');

//     echo html_writer::start_tag('div', array('class' => 'width-container'));
//     echo $OUTPUT->blocks('side-db-main', 'width-default width-12');
//     echo html_writer::end_tag('div');

//     echo html_writer::start_tag('div', array('class' => 'width-container'));
//     echo $OUTPUT->blocks('side-db-one', 'width-default width-6');
//     echo $OUTPUT->blocks('side-db-two', 'width-default width-6');
//     echo html_writer::end_tag('div');
//     echo html_writer::start_tag('div', array('class' => 'width-container'));
//     echo $OUTPUT->blocks('side-db-three', 'width-default width-12');
//     echo html_writer::end_tag('div');


// echo html_writer::end_tag('div');
echo "<div><h4>".get_string('trainerlist','block_trainerdashboard')."</h4></div>";
echo $renderer->get_trainerdashboards(block_trainerdashboard_manager::TRAINERLIST);
echo "<div><h4>".get_string('upcomingtrainings','block_trainerdashboard')."</h4></div>";
echo $renderer->get_trainerdashboards(block_trainerdashboard_manager::UPCOMINGTRAININGS);
echo "<div><h4>".get_string('trainermanhours','block_trainerdashboard')."</h4></div>";
echo $renderer->get_trainerdashboards(block_trainerdashboard_manager::TRAINERMANHOURS);
echo "<div><h4>".get_string('conductedtrainings','block_trainerdashboard')."</h4></div>";
echo $renderer->get_trainerdashboards(block_trainerdashboard_manager::CONDUCTEDTRAININGS);
echo $OUTPUT->footer();
