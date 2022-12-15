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

class report_classroomcompletions extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->columns = ['classroomfield'=>['classroomfield'], 'userfield'=>['userfield'],'classroomcompletions'=>['totalsessions','attendedsessions','usercompletionstatus','usercompletiondate']];
        $this->parent = true;
        $this->filters = array('organization','departments', 'subdepartments', 'classrooms','completionstatus','users');
        $this->orderable = array( );
        $this->defaultcolumn = 'lcu.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(lcu.id)";
    }
    function select() {
        $this->sql = " SELECT lcu.id,lc.id AS classroomid,lcu.userid,
                        (SELECT COUNT(lcs.id) 
                        FROM {local_classroom_sessions} lcs 
                        WHERE lcs.classroomid = lc.id) AS totalsessions,
                        (SELECT COUNT(lca.id) 
                        FROM {local_classroom_attendance} lca 
                        WHERE lca.classroomid = lc.id AND
                         lca.userid = u.id AND lca.status = 1) AS attendedsessions,
                        CASE
                            WHEN lcu.completion_status > 0
                            THEN 'Completed'
                            ELSE 'Not Completed'
                        END AS usercompletionstatus,
                        lcu.completiondate AS usercompletiondate  ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }
    function joins() {
         $this->sql .= "  JOIN {local_classroom_users} as lcu ON lc.id = lcu.classroomid
                          JOIN {user} as u ON u.id = lcu.userid ";
          parent::joins();
    }
    function where(){
         global $USER, $DB;
         $this->sql .= " WHERE u.deleted = 0 AND u.suspended = 0 ";
         $systemcontext = \context_system::instance();
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
            $this->sql .= " AND lc.costcenter = :costcenterid ";
            $this->params['costcenterid']= $USER->open_costcenterid;

        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
            $this->sql .= " AND lc.costcenter =:costcenterid 
                        AND lc.department = :departmentid";
            $this->params['costcenterid']= $USER->open_costcenterid;
            $this->params['departmentid']= $USER->open_departmentid;

        }else{
            $this->sql .= " AND lc.costcenter =:costcenterid 
                        AND lc.department = :departmentid AND lc.subdepartment = :subdepartmentid";
            $this->params['costcenterid']= $USER->open_costcenterid;
            $this->params['departmentid']= $USER->open_departmentid;
             $this->params['subdepartmentid']= $USER->open_subdepartment;
        }

         parent::where();
    }
           
   function search() {
        if(isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)", 'lc.name', 'u.open_employeeid');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }  
    } 
    function filters() { 

        if (!empty($this->params['filter_organization']) && $this->params['filter_organization'] > 0) {
            $this->sql .= " AND lc.costcenter = :orgid ";
            $this->params['orgid'] = $this->params['filter_organization'];
        }

        
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
            $this->sql .= " AND lc.department = :deptid ";
            $this->params['deptid'] = $this->params['filter_departments'];

        }

        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
            $this->sql .= " AND lc.subdepartment = :subdeptid ";
            $this->params['subdeptid'] = $this->params['filter_subdepartments'];

        }

        if (!empty($this->params['filter_classrooms']) && $this->params['filter_classrooms'] > 0) {
            $this->sql .= " AND lc.id = :classroomid ";
            $this->params['classroomid'] = $this->params['filter_classrooms'];
        }

        if (!empty($this->params['filter_user']) && $this->params['filter_user'] > 0) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $this->params['filter_user'];
        }

        if ($this->params['filter_completionstatus'] > -1){
            $this->sql .= " AND lcu.completion_status = :usercomplstatus ";
            $this->params['usercomplstatus'] = $this->params['filter_completionstatus'];
        }

    }

    public function get_rows($classroomcompletion) {
        return $classroomcompletion;
    }
}
