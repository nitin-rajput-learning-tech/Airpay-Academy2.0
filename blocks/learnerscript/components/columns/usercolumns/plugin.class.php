<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/** LearnerScript Reports
  * A Moodle block for creating customizable reports
  * @package blocks
  * @subpackage learnerscript
  * @author: sowmya<sowmya@eabyas.in>
  * @date: 2016
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;

class plugin_usercolumns extends pluginbase{
	public function init(){
		$this->fullname = get_string('usercolumns','block_learnerscript');
		$this->type = 'undefined';
		$this->form = false;
		$this->reporttypes = array('users');
	}
	public function summary($data){
		return format_string($data->columname);
	}
	public function colformat($data){
		$align = (isset($data->align))? $data->align : '';
		$size = (isset($data->size))? $data->size : '';
		$wrap = (isset($data->wrap))? $data->wrap : '';
		return array($align,$size,$wrap);
	}
	public function execute($data,$row,$user,$courseid,$starttime=0,$endtime=0){
		global $DB, $USER; 
        $context = context_system::instance();
		$reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'learnercoursesoverview'), IGNORE_MULTIPLE);
		$quizreportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'myquizs'), IGNORE_MULTIPLE);
		$assignreportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'myassignments'), IGNORE_MULTIPLE);
		$userbadgeid = $DB->get_field('block_learnerscript', 'id', array('type' => 'userbadges'), IGNORE_MULTIPLE);
        $courseoverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
        if ($this->reportfilterparams['filter_organization']) {
            $costcenter = " AND u.open_costcenterid = " .$this->reportfilterparams['filter_organization'];
        }
        if ($this->reportfilterparams['filter_departments'] > 0) {
            $dept = " AND u.open_departmentid = ".$this->reportfilterparams['filter_departments'];
        }
        if ($this->reportfilterparams['filter_subdepartments'] > 0) {
            $subdept = " AND u.open_subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
        }         
		switch ($data->column) {
			case 'enrolled':
			     if(!isset($row->enrolled)){
		            $enrolled =  $DB->get_field_sql($data->subquery);
		         }else{
		            $enrolled = $row->{$data->column};
		         }
				$allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
					array('id' => $reportid, 'filter_users' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
				if(empty($courseoverviewpermissions) || empty($reportid)){
					$row->{$data->column} = $enrolled;
				} else{
					$row->{$data->column} = html_writer::tag('a', $enrolled,
					array('href' => $allurl));
				}
	            break;
			case 'inprogress':
			     if(!isset($row->inprogress)){
		            $inprogress =  $DB->get_field_sql($data->subquery);
		         }else{
		            $inprogress = $row->{$data->column};
		         }
				$inprogressurl = new moodle_url('/blocks/learnerscript/viewreport.php',
					array('id' => $reportid, 'filter_users' => $row->id, 'filter_status' => 'inprogress', 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
				if(empty($courseoverviewpermissions) || empty($reportid)){
					$row->{$data->column} = $inprogress;
				} else{
					$row->{$data->column} = html_writer::tag('a', $inprogress,
					array('href' => $inprogressurl));
				}
	            break;
			case 'completed':
			     if(!isset($row->completed)){
		            $completed =  $DB->get_field_sql($data->subquery);
		         }else{
		            $completed = $row->{$data->column};
		         }
				$completedurl = new moodle_url('/blocks/learnerscript/viewreport.php',
					array('id' => $reportid, 'filter_users' => $row->id, 'filter_status' => 'completed', 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
				if(empty($courseoverviewpermissions) || empty($reportid)){
					$row->{$data->column} = $completed;
				} else{
					$row->{$data->column} = html_writer::tag('a', $completed,
					array('href' => $completedurl));
				}
	            break;
			case 'assignments':
        		$assignpermissions = empty($assignreportid) ? false : (new reportbase($assignreportid))->check_permissions($USER->id, $context);
				$assignmenturl = new moodle_url('/blocks/learnerscript/viewreport.php',
					array('id' => $assignreportid, 'filter_users' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
				if(empty($assignpermissions) || empty($assignreportid)){
					$row->{$data->column} = $row->assignments;
				} else{
					$row->{$data->column} = html_writer::tag('a', $row->assignments,
					array('href' => $assignmenturl));
				}
	            break;
			case 'quizes':
        		$quizpermissions = empty($quizreportid) ? false : (new reportbase($quizreportid))->check_permissions($USER->id, $context);
				$quizurl = new moodle_url('/blocks/learnerscript/viewreport.php',
					array('id' => $quizreportid, 'filter_users' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
				if(empty($quizpermissions) || empty($quizreportid)){
					$row->{$data->column} = $row->quizes;
				} else{
					$row->{$data->column} = html_writer::tag('a', $row->quizes,
					array('href' => $quizurl));
				}

	            break;
			case 'badges':
			     if(!isset($row->badges)){
		            $badges =  $DB->get_field_sql($data->subquery);
		         }else{
		            $badges = $row->{$data->column};
		         }
        		$badgepermissions = empty($userbadgeid) ? false : (new reportbase($userbadgeid))->check_permissions($USER->id, $context);
				$badgeurl = new moodle_url('/blocks/learnerscript/viewreport.php',
					array('id' => $userbadgeid, 'filter_users' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
				if(empty($badgepermissions) || empty($userbadgeid)){
					$row->{$data->column} = $badges;
				} else{
						$row->{$data->column} = html_writer::tag('a', $badges,
					array('href' => $badgeurl));
				}
	            break;
	        case 'grade':
	        	if(!isset($row->grade)){
		            $grade =  $DB->get_field_sql($data->subquery);
		         }else{
		            $grade = $row->{$data->column};
		         }
				$row->{$data->column} = (isset($grade))? $grade : '--';
	            break; 
	        case 'progress':
	        	if(!isset($row->progress)){
		            $progress =  $DB->get_field_sql($data->subquery);
		         }else{
		            $progress = $row->{$data->column};
		         }
		         $progress = empty($progress) ? 0 : $progress;
				return "<div class='spark-report' id='".html_writer::random_id()."' style='width:100px;' data-sparkline='$progress; progressbar' 
						data-labels = 'progress' >" . $progress . "</div>";
			break;
			 case 'status':
			 	$userstatus = $DB->get_record_sql('SELECT suspended, deleted FROM {user} WHERE id = ' . $row->id);

			    if($userstatus->suspended){
			    	$userstaus = '<span class="label label-warning">' . get_string('suspended') .'</span>';
			    } else if($userstatus->deleted){
			    	$userstaus = '<span class="label label-warning">' . get_string('deleted') .'</span>';
			    } else{
			    	$userstaus =  '<span class="label label-success">' . get_string('active') .'</span>';
			    }
                $row->{$data->column} = $userstaus;

            break;
			case 'upcomingdeadline':
			    if(isset($row->upcomingdeadline)){
		            $upcomingdeadline =  $DB->get_field_sql($data->subquery);
		        }else{
	                $sql = " SELECT COUNT(ue.id) AS 'Upcoming deadlines'
						FROM mdl_user_enrolments ue
						JOIN mdl_enrol e ON e.id = ue.enrolid 
						JOIN mdl_role_assignments ra ON ra.userid = ue.userid
						JOIN mdl_context ct ON ct.id = ra.contextid
						JOIN mdl_role rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
						JOIN mdl_user u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
						JOIN mdl_course c ON c.id = e.courseid AND c.id = ct.instanceid 
						JOIN mdl_local_courses_learningformat clf ON clf.id = c.open_learningformat
						WHERE ue.completiondate != 0 AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND ue.completiondate > UNIX_TIMESTAMP() AND ue.id NOT IN (SELECT DISTINCT ue.id 
						FROM mdl_user_enrolments ue
						JOIN mdl_enrol e ON e.id = ue.enrolid 
						JOIN mdl_role_assignments ra ON ra.userid = ue.userid
						JOIN mdl_context ct ON ct.id = ra.contextid
						JOIN mdl_role rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
						JOIN mdl_user u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
						JOIN mdl_course_completions as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid 
						JOIN mdl_course c ON c.id = e.courseid AND c.id = ct.instanceid 
						JOIN mdl_local_courses_learningformat clf ON clf.id = c.open_learningformat
						WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') {$costcenter} {$dept} {$subdept} AND ue.userid = {$row->userid}) {$costcenter} {$dept} {$subdept} AND ue.userid = {$row->userid} ";
					$upcomingdeadline =  $DB->get_field_sql($sql);
		        }
              	$row->{$data->column} = !empty($upcomingdeadline) ? $upcomingdeadline : '--';
	            break;
			case 'overduedeadline':
			    if(isset($row->overduedeadline)){
		            $overduedeadline =  $DB->get_field_sql($data->subquery);
		        }else{
	                $sql = "  SELECT COUNT(ue.id) AS 'Overdue deadlines'
						FROM mdl_user_enrolments ue
						JOIN mdl_enrol e ON e.id = ue.enrolid 
						JOIN mdl_role_assignments ra ON ra.userid = ue.userid
						JOIN mdl_context ct ON ct.id = ra.contextid
						JOIN mdl_role rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
						JOIN mdl_user u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
						JOIN mdl_course c ON c.id = e.courseid AND c.id = ct.instanceid 
						JOIN mdl_local_courses_learningformat clf ON clf.id = c.open_learningformat 
						WHERE ue.completiondate !=0 AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND ue.completiondate < UNIX_TIMESTAMP() AND ue.id NOT IN (SELECT DISTINCT ue.id 
						FROM mdl_user_enrolments ue
						JOIN mdl_enrol e ON e.id = ue.enrolid 
						JOIN mdl_role_assignments ra ON ra.userid = ue.userid
						JOIN mdl_context ct ON ct.id = ra.contextid
						JOIN mdl_role rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
						JOIN mdl_user u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
						JOIN mdl_course_completions as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid 
						JOIN mdl_course c ON c.id = e.courseid AND c.id = ct.instanceid 
						JOIN mdl_local_courses_learningformat clf ON clf.id = c.open_learningformat 
						WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') {$costcenter} {$dept} {$subdept} AND ue.userid = {$row->userid} ) {$costcenter} {$dept} {$subdept} AND ue.userid = {$row->userid} ";
					$overduedeadline =  $DB->get_field_sql($sql);
		        }
              	$row->{$data->column} = !empty($overduedeadline) ? $overduedeadline : '--';
	            break;	                        
		}
		return (isset($row->{$data->column}))? $row->{$data->column} : '';
	}
}
