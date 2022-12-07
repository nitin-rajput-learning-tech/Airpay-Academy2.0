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
  * @author Revanth Kumar Grandhi
  * @date: 2021
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\querylib;

class plugin_learningcolumns extends pluginbase{
	public function init(){
		$this->fullname = get_string('learning','block_learnerscript');
		$this->type = 'undefined';
		$this->form = true;
		$this->reporttypes = array('learning');
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
              if($row->learningformat == 'Learning path' || $row->learningformat == 'Instructor-led courses' || $row->learningformat == 'Program') {
                    if(isset($this->reportfilterparams['filter_organization']) && $this->reportfilterparams['filter_organization']>0 ) {
                        $costcenter = " AND bll.costcenterid IN (" .$this->reportfilterparams['filter_organization'] .','. 0 .") AND bll.user_costcenterid = ".$this->reportfilterparams['filter_organization'];
                        $filtercostcenter = $this->reportfilterparams['filter_organization'];
                    }
                    if($this->reportfilterparams['filter_departments']>0) {
                        $dept = " AND bll.departmentid IN (".$this->reportfilterparams['filter_departments'].", 0) AND bll.user_departmentid =".$this->reportfilterparams['filter_departments'] ;
                        $filterdept = $this->reportfilterparams['filter_departments'];                         
                    }
                }else {
                    if ($this->reportfilterparams['filter_organization']>0) {
                        $costcenter = " AND bll.costcenterid IN (" .$this->reportfilterparams['filter_organization'] .','. 0 .") AND bll.user_costcenterid = ".$this->reportfilterparams['filter_organization']; 
                        $filtercostcenter = $this->reportfilterparams['filter_organization'];
                    }
                    if ($this->reportfilterparams['filter_departments'] > 0) {
                        $dept = " AND bll.departmentid IN (".$this->reportfilterparams['filter_departments'].", 0) AND bll.user_departmentid =".$this->reportfilterparams['filter_departments'];
                        $filterdept = $this->reportfilterparams['filter_departments'];
                    }      
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
              if($row->learningformat == 'Learning path' || $row->learningformat == 'Instructor-led courses' || $row->learningformat == 'Program'){
                    $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND bll.user_costcenterid = ".$USER->open_costcenterid;
                    $filtercostcenter = $USER->open_costcenterid;
                    if($this->reportfilterparams['filter_departments']>0) {
                        $dept = " AND bll.departmentid IN (".$this->reportfilterparams['filter_departments'].", 0) AND bll.user_departmentid =".$this->reportfilterparams['filter_departments'] ;
                        $filterdept = $this->reportfilterparams['filter_departments'];
                    }
                }else {
                    $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND bll.user_costcenterid = ".$USER->open_costcenterid;
                    $filtercostcenter = $USER->open_costcenterid;
                    if ($this->reportfilterparams['filter_departments'] > 0) {
                        $dept = " AND bll.departmentid IN (".$this->reportfilterparams['filter_departments'].",0) AND bll.user_departmentid =".$this->reportfilterparams['filter_departments'];
                        $filterdept = $this->reportfilterparams['filter_departments'];
                    }      
                }
             }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                  $filtercostcenter = $USER->open_costcenterid;
                    $filterdept = $USER->open_departmentid;              
                if($row->learningformat == 'Learning path' || $row->learningformat == 'Instructor-led courses' || $row->learningformat == 'Program') {
                    $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .")  AND bll.user_costcenterid = ".$USER->open_costcenterid ." AND bll.user_departmentid =".$USER->open_departmentid ." AND bll.departmentid IN (".$USER->open_departmentid.",0)" ;
                }else {
                    $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .")  AND bll.user_costcenterid = ".$USER->open_costcenterid ." AND bll.user_departmentid = ".$USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid.", 0)" ;
                }         
             }else {
                    $filtercostcenter = $USER->open_costcenterid;
                    $filterdept = $USER->open_departmentid;
                    $filtersubdept = $USER->open_subdepartment;               
                if($row->learningformat == 'Learning path' || $row->learningformat == 'Instructor-led courses' || $row->learningformat == 'Program') {
                    $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .")  AND bll.user_costcenterid = ".$USER->open_costcenterid ." AND bll.user_departmentid =".$USER->open_departmentid ." AND bll.departmentid IN (".$USER->open_departmentid.",0) AND bll.subdepartment IN (" .$USER->open_subdepartment .','. 0 .") AND bll.user_subdepartment =".$USER->open_subdepartment ;
                }else {
                    $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .")  AND bll.user_costcenterid = ".$USER->open_costcenterid ." AND bll.user_departmentid = ".$USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid.", 0) AND bll.subdepartment IN (" .$USER->open_subdepartment .','. 0 .") AND bll.user_subdepartment =".$USER->open_subdepartment ;
                }             
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
              $subdept = " AND bll.subdepartment IN (".$this->reportfilterparams['filter_subdepartments'].",0) AND bll.user_subdepartment =".$this->reportfilterparams['filter_subdepartments'];
              $filtersubdept = $this->reportfilterparams['filter_subdepartments'];
            }  
        }
         if($row->learningformat == 'Online Course') {
            $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'onlinecourses'), IGNORE_MULTIPLE);
            $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);        
        } else if($row->learningformat == 'Exam') {
            $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'learnerexamoverview'), IGNORE_MULTIPLE);
            $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);        
        } else if($row->learningformat == 'Webinar') {
          $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'webinars'), IGNORE_MULTIPLE);
          $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
        } else if($row->learningformat == 'Lab') {
          $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'labs'), IGNORE_MULTIPLE);
          $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);  
        } else if($row->learningformat == 'Assessment') {
          $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'assessments'), IGNORE_MULTIPLE);
          $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);  
        } else if($row->learningformat == 'Learning path') {
          $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'learningpaths'), IGNORE_MULTIPLE);
          $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);  
        } else if($row->learningformat == 'Instructor-led courses') {
          $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'classroom'), IGNORE_MULTIPLE);
          $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);  
        } else if($row->learningformat == 'Program') {
          $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'programs'), IGNORE_MULTIPLE);
          $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);  
        }
         $solutionarea = isset($this->reportfilterparams['filter_solutionarea']) ? implode(',', $this->reportfilterparams['filter_solutionarea']) : 0;
          $technology = isset($this->reportfilterparams['filter_technology']) ? implode(',', $this->reportfilterparams['filter_technology']) : 0;
          $topic = isset($this->reportfilterparams['filter_topic']) ? implode(',', $this->reportfilterparams['filter_topic']) : 0;
          $vendor = isset($this->reportfilterparams['filter_vendor']) ? implode(',', $this->reportfilterparams['filter_vendor']) : 0;
          $level = isset($this->reportfilterparams['filter_level']) ? implode(',', $this->reportfilterparams['filter_level']) : 0;
          $language = isset($this->reportfilterparams['filter_language']) ? implode(',', $this->reportfilterparams['filter_language']) : 0;
          $jobrole = isset($this->reportfilterparams['filter_jobrole']) ? implode(',', $this->reportfilterparams['filter_jobrole']) : 0;

          $tagslist = array($solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
          if (array_sum($tagslist) > 0) {
              $tagslist = implode(',', $tagslist); 
              $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
              if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
                  $filters .= " AND bll.learningformatid IN (".$tagcoursesql.")";
              } else {
                  $filters .= " AND bll.learningformatid IN (0)";
              } 
          }
          if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
              $timefilter = " AND bll.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
          }
          if($row->moduleid == 1) {
              $learning = " AND bll.moduleid IN (1, 7) ";
          } else {
              $learning = " AND bll.moduleid = {$row->moduleid} ";
          } 

        $enrolmentssql = "SELECT COUNT(bll.id) AS enrolments FROM {block_ls_learningformats} AS bll WHERE 1=1 {$learning} {$costcenter} {$dept} {$subdept} {$filters} {$timefilter} ";
        $enrolleddata = $DB->get_field_sql($enrolmentssql);

        $completedsql = "SELECT COUNT(bll.id) AS completed FROM {block_ls_learningformats} AS bll WHERE 1=1 AND bll.completiondate != 0 {$learning} {$costcenter} {$dept} {$subdept} {$filters} {$timefilter} ";
        $completeddata = $DB->get_field_sql($completedsql);

        $completiondeadlinesql = "SELECT COUNT(bll.id) AS completiondeadline FROM {block_ls_learningformats} AS bll WHERE 1=1 AND bll.upcomingdeadline < UNIX_TIMESTAMP() AND bll.completiondate = 0 AND bll.upcomingdeadline != 0 {$learning} {$costcenter} {$dept} {$subdept} {$filters} {$timefilter} ";
        $cddata = $DB->get_field_sql($completiondeadlinesql);

        $upcomingsql = "SELECT COUNT(bll.id) AS upcoming FROM {block_ls_learningformats} AS bll WHERE 1=1 AND bll.upcomingdeadline > UNIX_TIMESTAMP() AND bll.completiondate = 0 AND bll.upcomingdeadline != 0 {$learning} {$costcenter} {$dept} {$subdept} {$filters} {$timefilter} ";
        $ucdata = $DB->get_field_sql($upcomingsql);
        switch($data->column){ 
            case 'learningformat':
              if(!isset($row->learningformat) && isset($data->subquery)){
                $learningformat = $DB->get_field_sql($data->subquery);
              }else{
                  $learningformat = $row->{$data->column};
              }
              $row->{$data->column} = !empty($learningformat) ? $learningformat : '--';
          break;
          case 'completed':
              if(!isset($row->completed) && isset($data->subquery)){
               $completed = $DB->get_field_sql($data->subquery);
            }else{
                  $completed = $completeddata;
                }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $filtercostcenter, 'filter_departments' => $filterdept, 'filter_subdepartments' => $filtersubdept, 'filter_status' => 'completed'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $completed;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $completed,
                array('href' => $allurl));
              }
          break;
          case 'enrolments': 
              if(!isset($row->enrolments) && isset($data->subquery)){
                 $enrolments = $DB->get_field_sql($data->subquery);
            }else{
                  $enrolment = $enrolleddata;
                }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $filtercostcenter, 'filter_departments' => $filterdept, 'filter_subdepartments' => $filtersubdept));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $enrolment;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $enrolment,
                array('href' => $allurl));
              }
          break;
          case 'inprogress': 
              if (!isset($row->inprogress) && isset($data->subquery)) {
                  $inprogress = $DB->get_field_sql($data->subquery);
              } else {
                  $inprogress = ($enrolleddata - $completeddata);
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $filtercostcenter, 'filter_departments' => $filterdept, 'filter_subdepartments' => $filtersubdept, 'filter_status' => 'inprogress'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $inprogress;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $inprogress,
                array('href' => $allurl));
              }
          break;
          case 'completionpercentage':
              if(!isset($row->completionpercentage) && isset($data->subquery)){
                  $completionpercentage = $DB->get_field_sql($data->subquery);
              }else{
                  $completionpercentage = !empty($enrolleddata) ? ROUND(($completeddata / $enrolleddata)*100, 0) : 0;
              }
              $completionprogress = isset($completionpercentage) ? $completionpercentage : 0;
              return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionprogress; progressbar'
                      data-labels = 'inprogress, completed' data-link='' >" . $completionprogress . "</div>";
          break;
          case 'upcomingdeadline':
              if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                $upcomingdeadline = $DB->get_field_sql($data->subquery);
              }else{
                  $upcomingdeadline = $ucdata;
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $filtercostcenter, 'filter_departments' => $filterdept, 'filter_status' => 'upcoming'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = !empty($upcomingdeadline) ? $upcomingdeadline : '--';
              } else{
                  $row->{$data->column} = !empty($upcomingdeadline) ? html_writer::tag('a', $upcomingdeadline,
                array('href' => $allurl))  : '--';
              }
          break;
          case 'completiondeadline':
              if(!isset($row->completiondeadline) && isset($data->subquery)){
                $completiondeadline = $DB->get_field_sql($data->subquery);
              }else{
                  $completiondeadline = $cddata;
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $filtercostcenter, 'filter_departments' => $filterdept, 'filter_subdepartments' => $filtersubdept, 'filter_status' => 'overdue'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = !empty($completiondeadline) ? $completiondeadline : '--';
              } else{
                  $row->{$data->column} = !empty($completiondeadline) ? html_writer::tag('a', $completiondeadline,
                array('href' => $allurl)) : '--';
              }
          break;
      }
      return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }       
}
