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

class report_coursegradeactivities extends reportbase implements report {
  /**
   * [__construct description]
   * @param [type] $report           [description]
   * @param [type] $reportproperties [description]
   */
  public function __construct($report, $reportproperties) {
    parent::__construct($report);
    $this->components = array('columns','filters', 'permissions');
    $columns = array('completionstatus','completiondate');
    $columnsarray = array('coursefield'=>['coursefield'],'userfield'=>['userfield'],'coursegradeactivities' => $columns);
    $this->columns = $columnsarray;
    $this->filters = array('organization','departments', 'subdepartments', 'course');
    $this->defaultcolumn = 'c.id';
  }

  function init() {
      parent::init();
  }

  function count() {
      $this->sql = "SELECT COUNT(u.id) ";
  }

  function select() {
    $this->sql = "SELECT u.id as userid, c.id as courseid, cmp.timecompleted " ;

    parent::select();
  }

  function from() {
    $this->sql .= " FROM {course} c ";
  }

  function joins() {
    $this->sql .=" JOIN {course_categories} AS cc ON cc.id = c.category
                  JOIN {enrol} e ON e.courseid = c.id  
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {context} AS cxt ON cxt.instanceid = c.id and cxt.contextlevel = 50
                  JOIN {role_assignments} AS ra ON ra.contextid = cxt.id AND ra.userid = ue.userid
                  JOIN {role} AS r ON r.id = ra.roleid
                  JOIN {user} AS u ON u.id = ra.userid
                  LEFT JOIN {course_completions} AS cmp ON cmp.course = c.id AND cmp.userid = u.id ";

    parent::joins();
  }

  function where() {
    global $USER, $DB;
    $courseid = $this->params['filter_course'];
    $this->sql .= " WHERE r.shortname = 'employee' AND u.deleted = 0 
                    AND u.suspended = 0 AND c.id = $courseid 
                    AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";

    $systemcontext = context_system::instance();
    // getscheduled report
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
    if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
      $this->sql .= " ";
    }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
      $this->sql .= " AND c.open_costcenterid = :costcenterid ";
      $this->params['costcenterid'] = $USER->open_costcenterid;
    }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
      $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
      $this->params['costcenterid'] = $USER->open_costcenterid;
      $this->params['departmentid'] = $USER->open_departmentid;
    }else{
      $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid AND open_subdepartment = :subdepartmentid";
      $this->params['costcenterid'] = $USER->open_costcenterid;
      $this->params['departmentid'] = $USER->open_departmentid;
      $this->params['subdepartmentid'] = $USER->open_subdepartment;
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

  public function get_rows($courseusers) {
    global $DB;
    $finalelements = array();
    if($courseusers){
      $data = array();
      foreach($courseusers as $courseuser){
        // $report = new stdClass();
        
        // $report->coursename = $courseuser->coursename;
        // $report->shortname = $courseuser->shortname;
        // $report->category = $courseuser->category;
        // $report->employeename = $courseuser->employeename;
        // $report->employeeid = $courseuser->employeeid;
        // $report->email = $courseuser->email;
        // if(!empty($courseuser->supervisor)){
        //   $report->supervisor =  $courseuser->supervisor;
        // }else{
        //   $report->supervisor =  'N/A';
        // }
        // $report->userdepartment = $courseuser->userdepartment;
        // if(!empty($courseuser->designation)){
        //   $report->designation =  $courseuser->designation;
        // }else{
        //   $report->designation =  'N/A';
        // }
        if($courseuser->timecompleted){
          $courseuser->completionstatus = get_string('completed','block_learnerscript');
          $courseuser->completiondate = date('d-M-Y',$courseuser->timecompleted);
        }else{
          $courseuser->completionstatus = get_string('not_completed','block_learnerscript');
          $courseuser->completiondate = 'NA';
        }
        
        $sql =  "SELECT cm.*,m.name as itemname
              FROM {course_modules} cm
              JOIN {modules} m ON cm.module = m.id
              WHERE cm.deletioninprogress = 0 AND cm.visible=1 AND cm.course = $courseuser->courseid ";
        $criteria = $DB->get_records_sql($sql);

        if($criteria){
          foreach($criteria as $key=>$class){
            $sqllist = "SELECT gi.id,gi.itemname as name,gi.grademax
                        FROM  {grade_items} gi
                        WHERE gi.courseid= $courseuser->courseid AND gi.itemtype = 'mod'
                        AND gi.iteminstance = '$class->instance' AND gi.itemmodule = '$class->itemname' ";
            $data_list = $DB->get_record_sql($sqllist);
      
            if($data_list){
              $activity1=$class->itemname;
              $classid ="classid_$data_list->id";
              $activity_grades= $this->get_activitygrades($courseuser->userid,$courseuser->courseid,$class->instance,$activity1,$data_list->id);
              if($activity_grades =='NA'){
                  $courseuser->$classid = "N/A";
              }else if(!empty($activity_grades) && $activity_grades !='NA'){
                $courseuser->$classid = round($activity_grades,2);
              }else{
                $courseuser->$classid = "Not Yet Graded";
              }
      
              $grade ="gradeclassid_$data_list->id";
              if($data_list->grademax){
                $courseuser->$grade = round($data_list->grademax,2);
              }else{
                $courseuser->$grade = "N/A";
              }
            }
          }
        }
        $data[] = $courseuser;
      }
      return $data;
    }
    return $finalelements;
  }

  function get_activitygrades($userid,$courseid,$moduleid,$activity1,$itemid){
    global $CFG,$DB;

    $checkgrade = $DB->get_field('grade_items','id',array('itemtype'=>'mod','itemmodule'=>$activity1));

    if($checkgrade) {
      $sql="SELECT gg.id,ROUND(gg.finalgrade,2) AS finalgrade
        FROM {grade_grades} as gg
        WHERE gg.userid={$userid} AND gg.itemid =  $itemid ";

      $result = $DB->get_record_sql($sql);

      return $result->finalgrade;
    }else{
      return 'NA';
     }
   }

}
