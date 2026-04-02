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
 * @subpackage local_userdashboard
 */

require_once dirname(__FILE__) . '/../../config.php';
require_once $CFG->dirroot . '/blocks/userdashboard/renderer.php';
require_login();
global $DB, $PAGE, $CFG, $USER, $OUTPUT;
$tab = required_param('tab',  PARAM_TEXT);
$subtab = required_param('subtab',  PARAM_TEXT);
$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();        
//$systemcontext = context_system::instance();
$pageurl = new moodle_url('/blocks/userdashboard/userdashboard_courses.php',array('tab' => $tab, 'subtab' => $subtab));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string($tab,'block_userdashboard'));
$PAGE->set_heading(get_string($tab,'block_userdashboard'));
$PAGE->navbar->add(get_string($tab,'block_userdashboard'));
$PAGE->requires->js_call_amd('block_userdashboard/userdashboardinit', 'init');
$PAGE->requires->js_call_amd('block_userdashboard/userdashboardinit', 'makeActive',array('tab' => $subtab));
switch($tab){
	case get_string('elear','block_userdashboard'):
		$renderable = new block_userdashboard\output\elearning_courses($subtab,'');
		break;
	case get_string('classro','block_userdashboard'):
		$renderable = new block_userdashboard\output\classroom_courses($subtab,'');
		break;
	case get_string('prog','block_userdashboard'):
		$renderable = new block_userdashboard\output\program_courses($subtab,'');
		break;
	case get_string('certific','block_userdashboard'):
		$renderable = new block_userdashboard\output\certification_courses($subtab,'');
		break;
	case get_string('learningpa','block_userdashboard'):
		$renderable = new block_userdashboard\output\learningplan_courses($subtab,'');
		break;
	case get_string('feedba','block_userdashboard'):
		$renderable = new block_userdashboard\output\evaluation_courses($subtab,'');
		break;
	case get_string('onlinete','block_userdashboard'):
		$renderable = new block_userdashboard\output\onlinetests_courses($subtab,'');
		break;
	case get_string('xse','block_userdashboard'):
		$renderable = new block_userdashboard\output\xseed($subtab,'');
		break;
}
echo $OUTPUT->header();
$output = $PAGE->get_renderer('block_userdashboard');
$data = $renderable->export_for_template($output);
$data->inprogress_elearning = json_decode($data->inprogress_elearning);
//$data->enableslider = 0;
$content = $OUTPUT->render_from_template('block_userdashboard/userdashboard_courses', $data);
echo '<div class = "divslide">'.$content.'</div>';
echo $OUTPUT->footer();
// $renderable = new block_userdashboard\output\elearning_courses($subtab,'');
