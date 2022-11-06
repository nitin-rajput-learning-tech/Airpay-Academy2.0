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
require_once("../../../config.php");
global $CFG, $DB;

require_once("$CFG->libdir/formslib.php");
require_once('../../../course/lib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once('../lib/course_lib.php');
require_login();

$PAGE->set_url('/blocks/request/admin/approve_course_new.php');
$PAGE->set_context((new \local_request\lib\accesslib())::get_module_context());
$PAGE->set_title(get_string('pluginname', 'block_request'));


$context =(new \local_request\lib\accesslib())::get_module_context();
if (has_capability('block/request:approverecord',$context)) {
} else {
  print_error(get_string('cannotapproverecord', 'block_request'));
}


if(isset($_GET['id'])){
	$mid = required_param('id', PARAM_INT);
	$_SESSION['mid'] = $mid;
} else {

	$mid = $_SESSION['mid'];
}


	// Create the course by record ID      
	$nid = block_request_create_new_course_by_record_id($mid, true);
	
	
  if(empty($nid)){
  	
	echo get_string('modidnotset','local_request');
	die;
	  
	  
  } else {
	
	echo '<script> window.location ="../../../course/edit.php?id=' .$nid . '";</script>';
  }



?>
