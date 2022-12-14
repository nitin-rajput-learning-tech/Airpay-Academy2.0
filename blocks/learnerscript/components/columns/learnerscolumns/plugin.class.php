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

class plugin_learnerscolumns extends pluginbase{
	public function init(){
		$this->fullname = get_string('learners','block_learnerscript');
		$this->type = 'undefined';
		$this->form = true;
		$this->reporttypes = array('learners');
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
      $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'learnersinfo'), IGNORE_MULTIPLE);
      $learneroverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
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
                    $costcenter = $this->reportfilterparams['filter_organization'];
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = $this->reportfilterparams['filter_departments'];
                }      
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                $costcenter = $USER->open_costcenterid; 
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = $this->reportfilterparams['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                $costcenter = $USER->open_costcenterid;
                $dept = $USER->open_departmentid;
            } else {
                $costcenter = $USER->open_costcenterid;
                $dept = $USER->open_departmentid;
                $subdept = $USER->open_subdepartment;
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
              $subdept = $this->reportfilterparams['filter_subdepartments'];
            } 
        }      
		  switch($data->column){ 
          case 'orgdept':
              if(!isset($row->orgdept) && isset($data->subquery)){
                   $orgdept = $DB->get_field_sql($data->subquery);
              }else{
                   $orgdept = $row->{$data->column};
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept, 'filter_subdepartments' => $subdept ));
                  $row->{$data->column} = html_writer::tag('a', $orgdept,
                  array('href' => $allurl));
          break;
			    case 'enrolments':
              if(!isset($row->enrolments) && isset($data->subquery)){
                 	$enrolments = $DB->get_field_sql($data->subquery);
              }else{
                  $enrolments = $row->{$data->column};
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept, 'filter_subdepartments' => $subdept, 'filter_status' => 'enrolled'));
                  $row->{$data->column} = html_writer::tag('a', $enrolments,
                  array('href' => $allurl));
          break;
          case 'completed':
              if(!isset($row->completed) && isset($data->subquery)){
                	 $completed = $DB->get_field_sql($data->subquery);
            	}else{
                 $completed = $row->{$data->column};
           	  }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept, 'filter_subdepartments' => $subdept, 'filter_status' => 'completed'));
                  $row->{$data->column} = html_writer::tag('a', $completed,
                  array('href' => $allurl));
          break;
          case 'inprogress': 
              if(!isset($row->inprogress) && isset($data->subquery)){
                  $inprogress = $DB->get_field_sql($data->subquery);
              }else{
                  $inprogress = $row->{$data->column};
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept, 'filter_subdepartments' => $subdept, 'filter_status' => 'inprogress'));
                  $row->{$data->column} = html_writer::tag('a', $inprogress,
                  array('href' => $allurl));
          break;
          case 'completionpercentage': 
              if(!isset($row->completionpercentage) && isset($data->subquery)){
                  $completionpercentage = $DB->get_field_sql($data->subquery);
              }else{
                  $completionpercentage = $row->{$data->column};
              }
              $completionprogress = $completionpercentage;
              return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionprogress; progressbar'
                        data-labels = 'inprogress, completed' data-link='' >" . $completionprogress . "</div>";
          break;
		}
		return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
	}
}
