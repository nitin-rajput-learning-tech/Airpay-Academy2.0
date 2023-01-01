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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;

class report_percentagecoursecompletions extends reportbase implements report {
  /**
   * [__construct description]
   * @param [type] $report           [description]
   * @param [type] $reportproperties [description]
   */
  public function __construct($report, $reportproperties) {
    parent::__construct($report);
    $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
    $columns = array('coursename','ten','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninty','cent');
    $columnsarray = array('percentagecoursecompletions' => $columns);
    $this->columns = $columnsarray;
    $this->parent = true;
    // $this->basicparams = array(['name' => 'course']);
    $this->orderable = array();
    $this->filters = array('course');
    $this->defaultcolumn = 'c.id';
    $this->groupcolumn = 'c.id';
  }

  function init() {
      parent::init();
  }

  function count() {
      $this->sql = "SELECT COUNT(distinct c.id) ";
  }

  function select() {
    // $this->sql = " SELECT concat(ue.timecreated, c.id, ue.id, ue.userid) as uniq, ue.id, c.id as courseid, u.id as userid " ;
    $this->sql = " SELECT c.id as courseid, c.fullname, c.shortname " ;
    parent::select();
  }

  function from() {
    $this->sql .= " FROM {course} c ";
  }

  function joins() {
    // $this->sql .=" JOIN {enrol} e ON e.courseid = c.id 
    // JOIN {user_enrolments} ue ON ue.enrolid = e.id
    // JOIN {user} u ON u.id = ue.userid ";

    parent::joins();
  }

  function where() {
    global $USER, $DB;
    $courseid = $this->params['filter_course'];
    $this->sql .= " where c.id > 1  ";

    $systemcontext = context_system::instance();
    // getscheduled report
    if (!is_siteadmin()) {
        $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
        if (!empty($scheduledreport)) {
        $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
        $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
        // $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
        } else {
            $ohs = 1;
        }
    }
    if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
      $this->sql .= " ";
    }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
      $this->sql .= " AND c.open_costcenterid = :costcenterid ";
      $this->params['costcenterid'] = $USER->open_costcenterid;
    }else{
      $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
      $this->params['costcenterid'] = $USER->open_costcenterid;
      $this->params['departmentid'] = $USER->open_departmentid;
    }

    parent::where();
  }

  function search() {
    if (isset($this->search) && $this->search) {
      $fields = array('c.fullname',"CONCAT(u.firstname,' ', u.lastname)",'u.email', 'u.open_employeeid');
      $fields = implode(" LIKE '%$this->search%' OR ", $fields);
      $fields .= " LIKE '%$this->search%' ";
      $this->sql .= " AND ($fields) ";
    }
  }

  function filters() {
    if(isset($this->params['filter_course']) && $this->params['filter_course'] > 0) {
        $this->sql .= " AND c.id = :courseid ";
        $this->params['courseid'] = $this->params['filter_course'];
    }
  }

  public function get_rows($course) {
    global $DB;
    $finalelements = array();
    $sql .= " select ue.id, c.id as courseid, c.fullname, ue.userid FROM {course} c
    JOIN {enrol} e ON e.courseid = c.id 
    JOIN {user_enrolments} ue ON ue.enrolid = e.id
    JOIN {user} u ON u.id = ue.userid
    where c.id = ?  AND u.deleted = 0  group by ue.userid ";
    $courseusers = $DB->get_records_sql($sql, [$this->params['filter_course']]);
    if($courseusers){
      $data = array();
      $ten = $twenty = $thirty = $forty = $fifty = $sixty = $seventy = $eighty = $ninty = $cent = 0;
      foreach($courseusers as $courseuser){
        if (!empty($courseuser->courseid)) {
          $percent = 0;
          $sql =  "SELECT count(cm.id)
              FROM {course_completion_criteria} cm
              WHERE cm.course = $courseuser->courseid ";
          $modules = $DB->count_records_sql($sql);

          $sql =  "SELECT count(cm.id)
              FROM {course_completion_crit_compl} cm
              WHERE cm.course = $courseuser->courseid AND userid = $courseuser->userid";
          $completedmodules = $DB->count_records_sql($sql);

          if (!empty($modules))
          $percent = round( (($completedmodules / $modules) * 100),2);
          else
          $percent = 0;
        
          switch ($percent) {
            case 0:
            
            break;
            case ($percent <= 10 ):
            $ten = $ten + 1;
            break;
            case ($percent > 10 && $percent <= 20):
            $twenty = $twenty + 1;
            break;
            case ($percent > 20 && $percent <= 30):
            $thirty = $thirty + 1;
            break;
            case ($percent > 30 && $percent <= 40):
            $forty = $forty + 1;
            break;
            case ($percent > 40 && $percent <= 50):
            $fifty = $fifty + 1;
            break;
            case ($percent > 50 && $percent <= 60):
            $sixty = $sixty + 1;
            break;
            case ($percent > 60 && $percent <= 70):
            $seventy = $seventy + 1;
            break;
            case ($percent > 70 && $percent <= 80):
            $eighty = $eighty + 1;
            break;
            case ($percent > 80 && $percent <= 90):
            $ninty = $ninty + 1;
            break;
            case ($percent > 90 && $percent <= 100):
            $cent = $cent + 1;
            break;
            default: 0;
            break;

          }
        }        
      }
      $userrecord = new stdclass();
      $userrecord->ten = $ten;
      $userrecord->twenty = $twenty;
      $userrecord->thirty = $thirty;
      $userrecord->forty = $forty;
      $userrecord->fifty = $fifty;
      $userrecord->sixty = $sixty;
      $userrecord->seventy = $seventy;
      $userrecord->eighty = $eighty;
      $userrecord->ninty = $ninty;
      $userrecord->cent = $cent;
      $userrecord->coursename = $courseuser->fullname;
      $data[] = $userrecord;
      return $data;
    }
    return $finalelements;
  }
}