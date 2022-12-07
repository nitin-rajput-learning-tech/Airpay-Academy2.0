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

class plugin_learnerstatus extends pluginbase{
	public function init(){
		$this->fullname = get_string('learnersinfocolumns','block_learnerscript');
		$this->type = 'undefined';
		$this->form = true;
		$this->reporttypes = array('learnersinfo');
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
                $costcenter = " AND bll.user_costcenterid = ".$this->reportfilterparams['filter_organization'];
            }
            if ($this->reportfilterparams['filter_departments'] > 0) {
                $dept = " AND bll.user_departmentid = ".$this->reportfilterparams['filter_departments'];
            }
        } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
            $costcenter = " AND bll.user_costcenterid = ".$USER->open_costcenterid;
            if ($this->reportfilterparams['filter_departments'] > 0) {
                $dept = " AND bll.user_departmentid = ".$this->reportfilterparams['filter_departments'];
            }
        }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
            $costcenter = " AND bll.user_costcenterid = ".$USER->open_costcenterid;
            $dept = " AND bll.user_departmentid = ".$USER->open_departmentid ;
        } else {
            $costcenter = " AND bll.user_costcenterid = ".$USER->open_costcenterid;
            $dept = " AND bll.user_departmentid = ".$USER->open_departmentid ;
            $subdept = " AND bll.user_subdepartment = ".$USER->open_subdepartment ;
        }
        if ($this->reportfilterparams['filter_subdepartments'] > 0) {
          $subdept = " AND bll.user_subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
        } 
    }
      $reportid = $DB->get_field('block_learnerscript', 'id', array('name' => 'Learners overview', 'type' => 'users'), IGNORE_MULTIPLE);
      $learneroverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
		  switch($data->column){ 
          case 'email':
              if(!isset($row->email) && isset($data->subquery)){
                   $email = $DB->get_field_sql($data->subquery);
              }else{
                   $email = $row->{$data->column};
              }
              $row->{$data->column} = !empty($email) ? $email : '--';              
          break;
          case 'learner':
              if(!isset($row->learner) && isset($data->subquery)){
                   $learner = $DB->get_field_sql($data->subquery);
              }else{
                   $learner = $row->{$data->column};
              }
              $row->{$data->column} = !empty($learner) ? $learner : '--';              
          break;
          case 'course':
              if(!isset($row->course) && isset($data->subquery)){
                   $course = $DB->get_field_sql($data->subquery);
              }else{
                   $course = $row->{$data->column};
              }
              $row->{$data->column} = !empty($course) ? $course : '--';              
          break;
          case 'progress':
              if(!isset($row->progress) && isset($data->subquery)){
                   $progress = $DB->get_field_sql($data->subquery);
              }else{
                if($row->type == 'Online Course' || $row->type == 'Lab' || $row->type == 'Assessment' ){
                    $courserecords = $DB->get_record_sql("SELECT * FROM {course} WHERE id = $row->courseid");                  
                    $percent = progress::get_course_progress_percentage($courserecords, $row->userid);
                    if (!is_null($percent)) {
                        $percent = floor($percent);
                    }else{
                        $percent = 0;
                    }
                    $completionprogress = $percent;
                } elseif($row->type == 'Learning Path'){
                    $sql = " SELECT GROUP_CONCAT(llpc.courseid) AS courses 
                        FROM {local_learningplan_user} AS llpu 
                        JOIN {local_learningplan} AS llp ON llp.id = llpu.planid 
                        JOIN {local_learningplan_courses} AS llpc ON llpc.planid=llpu.planid  
                        WHERE 1=1 AND llpc.planid = $row->courseid AND llpu.userid = $row->userid ";
                    $records = $DB->get_field_sql($sql);
                    $courses = explode(',',$records);
                    $enrolled = count($courses);
                    $i = $completed = 0;
                    foreach($courses as $course) {
                        if($course>0) {
                            $courserecords = $DB->get_record_sql("SELECT * FROM {course} WHERE id = $course");
                            $percent = progress::get_course_progress_percentage($courserecords, $row->userid);
                            if (!is_null($percent)) {
                                $percent = floor($percent);
                                if($percent == 100){
                                    $completed = ++$i;
                                }
                            }
                        }
                    }
                    $completionprogress = ROUND(($completed/$enrolled)*100,0);
                } elseif($row->type == 'classroom'){
                    $completionprogress = '--';
                } elseif($row->type == 'Program'){
                    $sql = " SELECT lblc.levelid AS levels 
                        FROM {local_bc_level_completions} AS lblc   
                        WHERE 1=1 AND lblc.programid = $row->courseid AND lblc.userid = $row->userid ";
                    $records = $DB->get_field_sql($sql);
                    $levels = explode(',',$records);
                    $enrolled = count($levels);
                    $completed = 0;
                    foreach($levels as $level) {
                        if($level > 0){
                          $courserecords = $DB->get_record_sql("SELECT lblc.completiondate 
                            FROM {local_bc_level_completions} AS lblc
                             WHERE lblc.levelid = $level AND lblc.completion_status != 0 AND lblc.completiondate != 0 ");
                          if($courserecords != 0 ){
                              $completed = ++$i;
                          }
                        }
                    }
                    $completionprogress = ROUND(($completed/$enrolled)*100,0);
                }
              }
              return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionprogress; progressbar'
            data-labels = 'inprogress, completed' data-link='' >" . $completionprogress . "</div>";
              break;
          case 'completed':
              if(!isset($row->completed) && isset($data->subquery)){
                   $completed = $DB->get_field_sql($data->subquery);
              }else{
                $completedsql = "SELECT bll.completiondate FROM {block_ls_learningformats} bll WHERE bll.completiondate != 0 AND bll.learningformatid = {$row->courseid} AND bll.userid = {$row->userid} AND bll.moduleid = {$row->moduleid}";
                $completed = $DB->get_field_sql($completedsql);
              }
              $row->{$data->column} = !empty($completed) ? strftime('%d-%m-%Y', $completed) : '--';              
          break;
          case 'upcomingdeadline':
          $upcomingdeadlinesql = "SELECT bll.upcomingdeadline AS upcomingdeadline
                        FROM {block_ls_learningformats} bll  
                        WHERE bll.learningformatid = {$row->courseid} AND bll.userid = {$row->userid} AND bll.upcomingdeadline > UNIX_TIMESTAMP() AND bll.completiondate = 0";
                $upcomingdeadline = $DB->get_field_sql($upcomingdeadlinesql);
                $row->{$data->column} = !empty($upcomingdeadline) ? strftime('%d-%m-%Y', $upcomingdeadline) : '--';
          break;
          case 'overduedeadline':
              $overduedeadlinesql = "SELECT bll.upcomingdeadline AS overduedeadline
                        FROM {block_ls_learningformats} bll
                        WHERE bll.learningformatid = {$row->courseid} AND bll.userid = {$row->userid} AND bll.completiondate = 0 AND bll.upcomingdeadline < UNIX_TIMESTAMP() AND bll.upcomingdeadline != 0";
                $overduedeadline = $DB->get_field_sql($overduedeadlinesql);
                $row->{$data->column} = !empty($overduedeadline) ? strftime('%d-%m-%Y', $overduedeadline) : '--';            
          break;
		}
		return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
	}
}
