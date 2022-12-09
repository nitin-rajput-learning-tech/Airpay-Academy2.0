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
  * @author eAbyas Info Solutions
  * @date: 2016
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\querylib;

class plugin_learneranalysis extends pluginbase{
	public function init(){
		$this->fullname = get_string('learneranalysis','block_learnerscript');
		$this->type = 'undefined';
		$this->form = true;
		$this->reporttypes = array('learneranalysis');
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
	public function execute($data,$row,$user,$courseid,$starttime=0,$endtime=0,$reporttype=null){
		global $DB, $USER; 
    $context = context_system::instance();
    if (!is_siteadmin()) {
        $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
        if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
        } else {
            $ohs = $dhs = 1;
        }
    }
    if (!$this->scheduling) {
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){
            if($data->column == 'learningpath') {
                if(isset($this->reportfilterparams['filter_organization']) && $this->reportfilterparams['filter_organization']>0) {
                    $costcenter = " AND llp.costcenter =".$this->reportfilterparams['filter_organization']; 
                    $filtercostcenter = $this->reportfilterparams['filter_organization'];
                }
                if($this->reportfilterparams['filter_departments']>0) {
                    $dept = " AND llp.department =".$this->reportfilterparams['filter_departments'];
                    $filterdept = $this->reportfilterparams['filter_departments']; 
                }
            } else {
                if ($this->reportfilterparams['filter_organization']>0) {
                    $costcenter = " AND u.open_costcenterid = " .$this->reportfilterparams['filter_organization'];
                    $filtercostcenter = $this->reportfilterparams['filter_organization'];
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND u.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                    $filterdept = $this->reportfilterparams['filter_departments'];
                }
            }
        } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) {
          if($data->column == 'learningpath') {
              $costcenter = " AND llp.costcenter = " .$USER->open_costcenterid;
              $filtercostcenter = $USER->open_costcenterid;
              if($this->reportfilterparams['filter_departments']>0) {
                  $dept = " AND llp.department =".$this->reportfilterparams['filter_departments']; 
                  $filterdept = $this->reportfilterparams['filter_departments'];
              }
          } else {
            $costcenter = " AND u.open_costcenterid = " .$USER->open_costcenterid;
            $filtercostcenter = $USER->open_costcenterid;
            if ($this->reportfilterparams['filter_departments'] > 0) {
                $dept = " AND u.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                $filterdept = $this->reportfilterparams['filter_departments'];                
            }            
          }
        }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) {
           $filtercostcenter = $USER->open_costcenterid;
                $filterdept = $USER->open_departmentid;        
            if($data->column == 'learningpath') {
                $costcenter = " AND llp.costcenter = " .$USER->open_costcenterid ." AND llp.department =".$USER->open_departmentid ;                
            } else {
                $costcenter = " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid ;
            }        
        } else {       
            if($data->column == 'learningpath') {
                $costcenter = " AND llp.costcenter = " .$USER->open_costcenterid ." AND llp.department =".$USER->open_departmentid ;                
            } else {
                $costcenter = " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid. " AND u.open_subdepartment = ".$USER->open_subdepartment ;
            }          
        }

        if ($this->reportfilterparams['filter_subdepartments'] > 0) {
            $subdept = " AND u.open_subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
                $filtersubdept = $this->reportfilterparams['filter_subdepartments'];                
        }  
    }
    $learningid = $DB->get_field('block_learnerscript', 'id', array('type' => 'alllearningformats'), IGNORE_MULTIPLE);
    $learningidpermissions = empty($learningid) ? false : (new reportbase($learningid))->check_permissions($USER->id, $context);
    $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'learningpaths'), IGNORE_MULTIPLE);
    $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
  	switch($data->column){ 
			case 'learner':
          if(!isset($row->learner) && isset($data->subquery)){
             	$learner = $DB->get_field_sql($data->subquery);
         	}else{
              $learner = $row->{$data->column};
         	}
         	$row->{$data->column} = !empty($learner) ? $learner : '--';
      	break;
      case 'learningformat':
          if(!isset($row->learningformat) && isset($data->subquery)){
             	$learningformat = $DB->get_field_sql($data->subquery);
         	}else{
            $sql = "SELECT count(ue.id) 
              FROM mdl_user_enrolments ue
              JOIN mdl_enrol e ON e.id = ue.enrolid 
              JOIN mdl_role_assignments ra ON ra.userid = ue.userid
              JOIN mdl_context ct ON ct.id = ra.contextid
              JOIN mdl_role rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
              JOIN mdl_user u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
              JOIN mdl_course c ON c.id = e.courseid AND c.id = ct.instanceid 
              JOIN mdl_local_courses_learningformat clf ON clf.id = c.open_learningformat AND clf.name IN ('Online Course', 'Assessment', 'Lab','Webinar')
              WHERE 1 = 1 AND u.id = $row->id AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') {$costcenter} {$dept} {$subdept} ";
            $learningformat = $DB->get_field_sql($sql);
         	}
          $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
            array('id' => $learningid, 'filter_organization' => $filtercostcenter, 'filter_departments' => $filterdept,'filter_subdepartments' => $filtersubdept, 'filter_user' => $row->id));
          if(empty($learningidpermissions) || empty($learningid)){
              $row->{$data->column} = $learningformat;
          } else{
              $row->{$data->column} = html_writer::tag('a', $learningformat,
            array('href' => $allurl));
          }
      	break;
      case 'learningpath': 
          if(!isset($row->learningpath) && isset($data->subquery)){
             	$learningpath = $DB->get_field_sql($data->subquery);
         	}else{
              $enrolledsql = "SELECT COUNT(llpu.id) AS enrolments  
                FROM {local_learningplan_user} AS llpu
                JOIN {local_learningplan} AS llp ON llp.id = llpu.planid
                JOIN {user} AS u ON u.id = llpu.userid AND u.confirmed = 1 AND u.deleted = 0  
                WHERE 1 = 1 AND u.id = $row->id {$costcenter} {$dept} {$subdept} ";
              $learningpath = $DB->get_field_sql($enrolledsql);
         	}
          $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
            array('id' => $reportid, 'filter_organization' => $filtercostcenter, 'filter_departments' => $filterdept,'filter_subdepartments' => $filtersubdept, 'filter_user' => $row->id));
          if(empty($learnerpermissions) || empty($reportid)){
              $row->{$data->column} = $learningpath;
          } else{
              $row->{$data->column} = html_writer::tag('a', $learningpath,
            array('href' => $allurl));
          }
      	break;
    }
		return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
	}
}
