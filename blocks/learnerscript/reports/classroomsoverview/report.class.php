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

use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_classroomsoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->components = array('columns','filters', 'permissions', 'plot', 'orderable');
        $this->columns = ['classroomfield'=>['classroomfield'],'classroomsoverviewcolumns' => ['classroomname','classroomstatus','enrollmentscount','completionscount','trainerhrs']];
        $this->filters = array('organization','departments', 'subdepartments', 'level4department', 'classrooms','classroomstatus');
        $this->orderable = array('classroomname','enrollmentscount','completionscount');
        $this->defaultcolumn = 'lc.id';
    }
    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(lc.id) ";
    }
     function select() {
        $this->sql = "SELECT lc.id as classroomid,lc.name as classroomname, lc.open_path AS class_open_path,lc.status as classroomstatus,lc.startdate,lc.enddate";
      parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }
    function joins() {
          parent::joins();
    }
     function where() {
         global $USER, $DB;
      $this->sql .= " WHERE lc.status <> 0 "; //Not considering the new classrooms.
      $systemcontext = context_system::instance();
      $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.open_path'); 

      if (is_siteadmin()) {
          $this->sql .= "";
      } else  {
          $this->sql .= $costcenterpathconcatsql;
      }
       parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("lc.name");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters() {
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(lc.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(lc.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(lc.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(lc.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }      

        if ($this->params['filter_classrooms'] > 0) {
            $this->sql .= " AND lc.id = :classroomid ";
            $this->params['classroomid'] = $this->params['filter_classrooms'];
        }

        if ($this->params['filter_classroomstatus'] > -1) {
            $this->sql .= " AND lc.status = :status ";
            $this->params['status'] = $this->params['filter_classroomstatus'];
        }
    }
    public function get_rows($classroominfo = array()) {
        global $DB;
        $data = array();
        if($classroominfo){
            $costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
            $sql = "SELECT COUNT(lcu.id)
                    FROM {local_classroom_users} as lcu
                    JOIN {user} as u ON u.id = lcu.userid 
                    WHERE lcu.classroomid = :classroomid 
                    AND u.deleted =:deleted AND u.suspended =:suspended {$costcenterpathconcatsql} ";

            $completionscount = ' AND lcu.completion_status = :status ';
            $enrolsql = '';
            if($this->ls_startdate > 0 && $this->ls_enddate > 0){
                $enrolsql .= " AND lcu.timecreated > :ls_startdate ";
                $completionscount .= " AND lcu.completiondate > :ls_startdate ";
            // }
            // if($this->ls_enddate > 0){
                $enrolsql .= " AND lcu.timecreated < :ls_enddate ";
                $completionscount .= " AND lcu.completiondate < :ls_enddate ";
            }

            foreach ($classroominfo as $classroom) {
                $classroom->enrollmentscount = $DB->count_records_sql($sql.$enrolsql, array('classroomid' => $classroom->classroomid,'deleted' => 0, 'suspended' => 0, 'ls_startdate' => $this->ls_startdate, 'ls_enddate' => $this->ls_enddate));

                $classroom->completionscount = $DB->count_records_sql($sql.$completionscount, array('classroomid' => $classroom->classroomid,'deleted' => 0,'suspended' => 0,'status' => 1, 'ls_startdate' => $this->ls_startdate, 'ls_enddate' => $this->ls_enddate));
                if ($classroom->classroomstatus == 0) {
                    $classroom->classroomstatus = 'New';
                } else if ($classroom->classroomstatus == 1) {
                    $classroom->classroomstatus = 'Active';
                } else if ($classroom->classroomstatus == 2) {
                    $classroom->classroomstatus = 'Hold';
                } else if ($classroom->classroomstatus == 3) {
                    $classroom->classroomstatus = 'Cancel';
                } else if ($classroom->classroomstatus == 4) {
                    $classroom->classroomstatus = 'Completed';
                }

                $classroom->trainerhrs = $DB->get_field_sql("SELECT SUM(round(cs.duration/60, 2)) 
                            FROM {local_classroom_sessions} cs
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME($classroom->startdate))
                            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME($classroom->startdate)) AND (c.status = 1 OR c.status = 4) 
                            AND classroomid = :classroomid", array('classroomid' => $classroom->classroomid));
                $data[] = $classroom;
            }
        }
        return $data;
    }
}
