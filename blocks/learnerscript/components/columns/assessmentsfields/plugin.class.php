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

class plugin_assessmentsfields extends pluginbase{
	public function init(){
		$this->fullname = get_string('assessments','block_learnerscript');
		$this->type = 'undefined';
		$this->form = true;
		$this->reporttypes = array('assessments');
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
            if ($this->reportfilterparams['filter_organization']>0) {
                $costcenter = " AND bll.costcenterid IN (" .$this->reportfilterparams['filter_organization'] .", 0) AND bll.user_costcenterid =".$this->reportfilterparams['filter_organization'] ;
            }
            if ($this->reportfilterparams['filter_departments'] > 0) {
                $dept = " AND bll.departmentid IN (".$this->reportfilterparams['filter_departments'].", 0) AND bll.user_departmentid=".$this->reportfilterparams['filter_departments'] ;
            }
        } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
            $costcenter = " AND bll.costcenterid IN (".$USER->open_costcenterid.", 0) AND bll.user_costcenterid =". $USER->open_costcenterid;
            if ($this->reportfilterparams['filter_departments'] > 0) {
                $dept = " AND bll.departmentid IN (".$this->reportfilterparams['filter_departments'].", 0) AND bll.user_departmentid=".$this->reportfilterparams['filter_departments'];
            }
        }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
            $costcenter = " AND bll.costcenterid IN (".$USER->open_costcenterid .", 0) AND bll.departmentid IN (". $USER->open_departmentid.", 0) AND bll.user_costcenterid = ". $USER->open_costcenterid ." AND bll.user_departmentid =".$USER->open_departmentid ;
        } else {
            $costcenter = " AND bll.costcenterid IN (".$USER->open_costcenterid .", 0) AND bll.departmentid IN (". $USER->open_departmentid.", 0) AND bll.user_costcenterid = ". $USER->open_costcenterid ." AND bll.user_departmentid =".$USER->open_departmentid ." AND bll.subdepartment IN (".$USER->open_subdepartment .", 0) AND bll.user_subdepartment = ". $USER->open_subdepartment;
        }
        if ($this->reportfilterparams['filter_subdepartments'] > 0) {
            $subdept = " AND bll.subdepartment IN (".$this->reportfilterparams['filter_subdepartments'].", 0) AND bll.user_subdepartment=".$this->reportfilterparams['filter_subdepartments'];
        } 
    }
    if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
        $timefilter = " AND bll.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
    }
    $learning = " AND bll.learningformatid = {$row->courseid} AND bll.moduleid = 3";    
		$courserecords = $DB->get_record_sql("SELECT * FROM {course} WHERE id = $row->courseid");

		switch($data->column){ 
			case 'enrolmentdate':
                if(!isset($row->enrolmentdate) && isset($data->subquery)){
                   	$enrolmentdate = $DB->get_field_sql($data->subquery);
               	}else{
                    $enrolmentdate = $row->{$data->column};
               	}
               	$row->{$data->column} = !empty($enrolmentdate) ? strftime('%d-%m-%Y', $enrolmentdate) : '--';
            	break;
            case 'dateofcompletion':
                if(!isset($row->dateofcompletion) && isset($data->subquery)){
                   	$dateofcompletion = $DB->get_field_sql($data->subquery);
               	}else{
                    $dateofcompletion = $row->{$data->column};
               	}
               	$row->{$data->column} = !empty($dateofcompletion) ? strftime('%d-%m-%Y', $dateofcompletion) : '--';
            	break;
            case 'completiondeadline': 
                if(!isset($row->completiondeadline) && isset($data->subquery)){
                   	$completiondeadline = $DB->get_field_sql($data->subquery);
               	}else{
                    $completiondeadline = $row->{$data->column};
               	}
               	$row->{$data->column} = !empty($completiondeadline) ? strftime('%d-%m-%Y', $completiondeadline) : '--';
            	break;
            case 'progress': 
                if(!isset($row->progress) && isset($data->subquery)){
                   	$completionprogress = $DB->get_field_sql($data->subquery);
               	}else{  
               		$percent = progress::get_course_progress_percentage($courserecords, $row->userid);
                  if (!is_null($percent)) {
                      $percent = floor($percent);
                  }else{
	                    $percent = 0;
	                }
                    $completionprogress = $percent;
               	}
               	return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionprogress; progressbar'
						data-labels = 'inprogress, completed' data-link='' >" . $completionprogress . "</div>";
            	break;
          case 'upcomingdeadline': 
              if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                $upcomingdeadline = $DB->get_field_sql($data->subquery);
              }else{
                $sql = "  SELECT bll.upcomingdeadline AS upcomingdeadline
                        FROM {block_ls_learningformats} bll     
                        WHERE bll.id = $row->id AND bll.completiondate = 0 AND bll.upcomingdeadline > UNIX_TIMESTAMP() {$learning} {$costcenter} {$dept} {$subdept} {$timefilter}";
                $upcomingdeadline = $DB->get_field_sql($sql);
            }
            $row->{$data->column} = !empty($upcomingdeadline) ? strftime('%d-%m-%Y', $upcomingdeadline) : '--';
            break;
          case 'overduedeadline': 
            if(!isset($row->overduedeadline) && isset($data->subquery)){
                $overduedeadline = $DB->get_field_sql($data->subquery);
            }else{
                $sql = " SELECT bll.upcomingdeadline AS overduedeadline
                        FROM {block_ls_learningformats} bll     
                        WHERE bll.id = $row->id AND bll.completiondate = 0 AND bll.upcomingdeadline < UNIX_TIMESTAMP() AND bll.upcomingdeadline !=0 {$learning} {$costcenter} {$dept} {$subdept} {$timefilter} ";
                $overduedeadline = $DB->get_field_sql($sql);
            }
            $row->{$data->column} = !empty($overduedeadline) ? strftime('%d-%m-%Y', $overduedeadline) : '--';            
            break;              
		}
		return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
	}
}
