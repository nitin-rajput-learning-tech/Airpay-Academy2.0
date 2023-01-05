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
        $this->columns = ['classroomfield'=>['classroomfield'],'classroomsoverviewcolumns' => ['classroomname','enrollmentscount','completionscount']];
        $this->filters = array('organization','departments', 'subdepartments', 'classrooms','classroomstatus');
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
        $this->sql = "SELECT lc.id as classroomid,lc.name as classroomname";
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
      $this->sql .= " WHERE 1=1 ";
      $systemcontext = context_system::instance();
      // getscheduled report
      //   if (!is_siteadmin()) {
      //       $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
      //       if (!empty($scheduledreport)) {
      //       $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
      //       $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
      //       $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
      //       } else {
      //           $ohs = $dh = 1;
      //       }
      //   }
      // if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
      //       $this->sql .= "";
      //   }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
      //       $this->sql .= " AND lc.costcenter = :costcenterid ";
      //       $this->params['costcenterid'] = $USER->open_costcenterid;
      //   }else if(has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
      //       $this->sql .= " AND lc.costcenter = :costcenterid AND lc.department = :departmentid";
      //       $this->params['costcenterid'] = $USER->open_costcenterid;
      //   }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
      //       $this->sql .= " AND lc.costcenter = :costcenterid AND lc.department = :departmentid";
      //       $this->params['costcenterid'] = $USER->open_costcenterid;
      //       $this->params['departmentid'] = $USER->open_departmentid;
      //   }else{
      //       $this->sql .= " AND lc.costcenter = :costcenterid AND lc.department = :departmentid AND lc.subdepartment = :subdepartmentid";
      //       $this->params['costcenterid'] = $USER->open_costcenterid;
      //       $this->params['departmentid'] = $USER->open_departmentid;
      //       $this->params['subdepartmentid'] = $USER->open_subdepartment;
      //   }

      $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.open_path'); 

      if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
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
        if (!empty($this->params['filter_organization'])  && $this->params['filter_organization'] > 0) {
            $this->sql .= " AND lc.costcenter = :orgid ";
            $this->params['orgid']= $this->params['filter_organization'];
        }

        if ($this->params['filter_departments'] > 0) {
            $this->sql .= " AND lc.department = :deptid ";
            $this->params['deptid'] = $this->params['filter_departments'];
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $this->sql .= " AND lc.subdepartment = :subdeptid ";
            $this->params['subdeptid'] = $this->params['filter_subdepartments'];
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
            $sql = "SELECT COUNT(lcu.id)
                    FROM {local_classroom_users} as lcu
                    JOIN {user} as u ON u.id = lcu.userid 
                    WHERE lcu.classroomid = :classroomid 
                    AND u.deleted =:deleted AND u.suspended =:suspended ";

            $completionscount = ' AND lcu.completion_status = :status ';
            
            foreach ($classroominfo as $classroom) {
                $classroom->enrollmentscount = $DB->count_records_sql($sql, array('classroomid' => $classroom->classroomid,'deleted' => 0, 'suspended' => 0));

                $classroom->completionscount = $DB->count_records_sql($sql.$completionscount, array('classroomid' => $classroom->classroomid,'deleted' => 0,'suspended' => 0,'status' => 1));

                $data[] = $classroom;
            }
        }
        return $data;
    }
}
