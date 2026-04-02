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
 * @subpackage local_ratings
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
global $OUTPUT,$CFG,$PAGE, $DB;
require_login();
$itemid = optional_param('itemid', 0,  PARAM_INT);
$commentarea = optional_param('commentarea',  0,  PARAM_TEXT);
switch ($commentarea) {
	case 'local_courses':
		$modulename = $DB->get_field('course', 'fullname', array('id' => $itemid));
		$moduleurl = new moodle_url('/course/view.php', array('id' => $itemid));
	break;
	case 'local_classroom':
		$modulename = $DB->get_field('local_classroom', 'name', array('id' => $itemid));
		$moduleurl = new moodle_url('/local/classroom/view.php', array('cid' => $itemid));
	break;
	case 'local_certification':
		$modulename = $DB->get_field('local_certification', 'name', array('id' => $itemid));
		$moduleurl = new moodle_url('/local/certification/view.php', array('ctid' => $itemid));
	break;
	case 'local_program':
		$modulename = $DB->get_field('local_program', 'name', array('id' => $itemid));
		$moduleurl = new moodle_url('/local/program/view.php', array('bcid' => $itemid));
	break;
	case 'local_learningplan':
		$modulename = $DB->get_field('local_learningplan', 'name', array('id' => $itemid));
		$moduleurl = new moodle_url('/local/learningplan/view.php', array('id' => $itemid));
	break;
}
$headstring = get_string('reviews_for', 'local_ratings',trim($modulename));
$PAGE->set_title($headstring);
$PAGE->set_heading($headstring);
$PAGE->set_url(new moodle_url('/local/ratings/reviews.php',  array('itemid' => $itemid, 'commentarea' => $commentarea)));
$PAGE->set_context(context_system::instance());
$ratings_renderer = $PAGE->get_renderer('local_ratings');
$filterparams = $ratings_renderer->view_reviews(true);
/*$PAGE->requires->jquery();
$PAGE->requires->js('/local/ratings/js/jquery.rateyo.js');
$PAGE->requires->js('/local/ratings/js/ratings.js');*/
$PAGE->navbar->add($modulename, $moduleurl);
$PAGE->navbar->add($headstring);
echo $OUTPUT->header();
echo $ratings_renderer->view_reviews();
echo $OUTPUT->footer();

