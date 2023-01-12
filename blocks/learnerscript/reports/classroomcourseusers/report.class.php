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

class report_classroomcourseusers extends reportbase implements report {
  /**
   * [__construct description]
   * @param [type] $report           [description]
   * @param [type] $reportproperties [description]
   */
  public function __construct($report, $reportproperties) {
    parent::__construct($report);
    $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
    $columns = array('firstname','lastname','email','idnumber','phone','course','timetaken','completionpercent','completiondate');
    $columnsarray = array('coursefield'=>['coursefield'],'userfield'=>['userfield'],'classroomcourseusers' => $columns);
    $this->columns = $columnsarray;
    $this->parent = true;
    // $this->basicparams = array(['name' => 'course']);
    $this->orderable = array();
    $this->filters = array('organization','departments','classrooms','user');
    $this->defaultcolumn = 'lcu.id';
    // $this->groupcolumn = 'u.id';
  }

  function init() {
      parent::init();
  }

  function count() {
      $this->sql = "SELECT COUNT(lcu.id) ";
  }

  function select() {
    $this->sql = "SELECT concat(lcu.id,u.id,c.id) as uiiid, u.id as userid, u.firstname,u.lastname, u.phone1 as phone, u.email, u.idnumber, c.fullname as course, c.id as courseid " ;
    parent::select();
  }

  function from() {
    $this->sql .= " FROM {course} c ";
  }

  function joins() {
    $this->sql .=" JOIN {local_classroom_users} lcu JOIN {user} AS u ON lcu.userid = u.id 
                   JOIN {local_classroom} lc ON lc.id = lcu.classroomid";

    parent::joins();
  }

  function where() {
    global $USER, $DB;
    $courseid = $this->params['filter_course'];
    $this->sql .= " where c.id IN (select lcc.courseid from {local_classroom_courses} lcc where lcc.classroomid = lcu.classroomid )  AND u.deleted = 0 ";

      $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context(); //context_system::instance();
      $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path'); 
      if (is_siteadmin()) {
          $this->sql .= "";
      } else  {
          $this->sql .= $costcenterpathconcatsql;
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
        if (!empty($this->params['filter_organization'])  && $this->params['filter_organization'] > 0) {
            $organization = $this->params['filter_organization'];
            $filter_organization[] = " concat('/',lc.open_path,'/') LIKE :organizationparam_{$organization}";
            $this->params["organizationparam_{$organization}"] = '%/'.$organization.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_organization)." ) ";
        }

        if ($this->params['filter_departments'] > 0) {
            $department = $this->params['filter_departments'];
            $filter_department[] = " concat('/',lc.open_path,'/') LIKE :departmentparam_{$department}";
            $this->params["departmentparam_{$department}"] = '%/'.$department.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_department)." ) ";
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $subdepartments = $this->params['filter_subdepartments'];
            $filter_subdepartments[] = " concat('/',lc.open_path,'/') LIKE :subdepartmentsparam_{$subdepartments}";
            $this->params["subdepartmentsparam_{$subdepartments}"] = '%/'.$subdepartments.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_subdepartments)." ) ";
        }
    if(isset($this->params['filter_classrooms']) && $this->params['filter_classrooms'] > 0) {
        $this->sql .= " AND lcu.classroomid = :classroomid ";
        $this->params['classroomid'] = $this->params['filter_classrooms'];
    }
    if (!empty($this->params['filter_user'])) {
        $userid = $this->params['filter_user'];
        $this->sql .= " AND u.id = :userid ";
        $this->params['userid'] = $userid;
    }
    // echo $this->sql;
    // print_r($this->params);exit;
  }

  public function get_rows($courseusers) {
    global $DB;
    $finalelements = array();
    if($courseusers){
      $data = array();
      foreach($courseusers as $courseuser){
        if (!empty($courseuser->courseid)) {
          $sql =  "SELECT count(cm.id)
              FROM {course_completion_criteria} cm
              WHERE cm.course = $courseuser->courseid ";
          $modules = $DB->count_records_sql($sql);

          $sql =  "SELECT count(cm.id)
              FROM {course_completion_crit_compl} cm
              WHERE cm.course = $courseuser->courseid AND userid = $courseuser->userid";
          $completedmodules = $DB->count_records_sql($sql);
          if (!empty($modules))
          $courseuser->completionpercent = round( (($completedmodules / $modules) * 100),2);
          else
          $courseuser->completionpercent = 0;
          $timetaken = $DB->get_record('block_ls_coursetimestats', ['userid'=>$courseuser->userid, 'courseid'=>$courseuser->courseid]);
          $courseuser->timetaken = (!empty($timetaken)) ? $this->secondsToTime($timetaken->timespent):'N/A';
          $timecompleted = $DB->get_field_sql("select timecompleted from {course_completions} where userid = $courseuser->userid AND course = $courseuser->courseid AND timecompleted is not null ");
          $courseuser->completiondate = (!empty($timecompleted)) ? date('M d,Y', $timecompleted):'N/A';

        }
        $data[] = $courseuser;
      }
      return $data;
    }
    return $finalelements;
  }

  function secondsToTime($seconds) {
      $dtF = new \DateTime('@0');
      $dtT = new \DateTime("@$seconds");
      return $dtF->diff($dtT)->format('%h hrs, %i mins and %s secs');
  }
}