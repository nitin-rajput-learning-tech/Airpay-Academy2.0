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
 * @subpackage local_users
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

$blockinstanceid = required_param('instanceid',  PARAM_INT);

//systemcontest defining
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);	

//set url and layout and title
$pageurl = new moodle_url('/blocks/trending_modules/index.php');
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$heading = get_string('trending_modules', 'block_trending_modules');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
//$PAGE->requires->js_call_amd('local_catalog/courseinfo', 'load', array());
$PAGE->requires->js_call_amd('local_search/courseinfo', 'load', array());
$PAGE->navbar->add($heading);
$renderer = $PAGE->get_renderer('block_trending_modules');
$filterparams = $renderer->trending_modules_content($blockinstanceid, true);
echo $OUTPUT->header();
// echo $renderer->display_additional_filters($filterparams);
// echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);
echo $renderer->trending_modules_content($blockinstanceid);
echo $OUTPUT->footer();
