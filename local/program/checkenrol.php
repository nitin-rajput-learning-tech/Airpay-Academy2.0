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
 * @subpackage local_costcenter
 */

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $USER;
$programid = required_param('programid',  PARAM_INT);
$courseid = optional_param('courseid',  0,  PARAM_INT);
$action = optional_param('action',  '',  PARAM_TEXT);
$program = new local_program\program();
$redirecturl = '';
switch ($action) {
	case 'courseuserenrol':
		$userid = optional_param('userid', $USER->id, PARAM_INT);
		$exists = $DB->record_exists_sql("SELECT lpu.id FROM {local_program_users} AS lpu JOIN {local_program_level_courses} AS lplc ON lplc.programid = lpu.programid WHERE lplc.courseid = :courseid AND lpu.userid = :userid ",  array('courseid' => $courseid, 'userid' => $userid));
		if($exists){
            $program->manage_bclevel_course_enrolments($courseid, $userid, $role = 'employee',$type = 'enrol', $pluginname = 'program',$programid);
	        $redirecturl = $CFG->wwwroot.'/course/view.php?id='.$courseid;
	    }else{
	    	$redirecturl = $CFG->wwwroot.'/my';
	    }
	break;
	default:
	break;
}
if(!empty($redirecturl)){
	redirect($redirecturl);
}