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

class report_feedbackcompletions extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = ['feedbackfield'=>['feedbackfield'],'userfield'=>['userfield'],'feedbackcompletionscolumns' => ['completionstatus','completiondate']];
        $this->components = array('columns', 'filters', 'permissions','orderable');
        $this->filters = array('organization','departments', 'subdepartments', 'feedbacks');
        $this->orderable = array('feedbackname');
        $this->defaultcolumn = 'le.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(eu.id)";
    }
    function select() {
        $this->sql  = "SELECT eu.id,le.id as feedbackid,u.id as userid,le.name as feedbackname,
                       ec.timemodified AS completiondate,eu.status as completionstatus";
         parent::select();                
    }
    function from() {
        $this->sql .= " FROM {local_evaluations} le ";
    }
    function joins() {
        $this->sql .= "JOIN {local_evaluation_users} eu ON eu.evaluationid = le.id
                      LEFT JOIN {local_evaluation_completed} ec ON ec.evaluation = le.id AND ec.userid = eu.userid
                    JOIN {user} u ON eu.userid = u.id AND u.deleted = 0 AND u.suspended = 0";
          parent::joins();
    }
    function where(){
       global $USER, $DB;
        $this->sql .= "  WHERE le.instance = 0 AND le.deleted  = 0 ";
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
            $this->sql.= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
           $this->sql .= " AND le.costcenterid = :costcenterid ";
           $this->params['costcenterid']= $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
           $this->sql .= " AND le.costcenterid = :costcenterid AND le.departmentid = :departmentid ";
           $this->params['costcenterid']= $USER->open_costcenterid;
           $this->params['departmentid']= $USER->open_departmentid;
        }else{
           $this->sql .= " AND le.costcenterid = :costcenterid AND le.departmentid = :departmentid AND le.subdepartment = :subdepartmentid";
           $this->params['costcenterid']= $USER->open_costcenterid;
           $this->params['departmentid']= $USER->open_departmentid;
           $this->params['subdepartmentid']= $USER->open_subdepartement;
        }
         parent::where();
    }
     function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('le.name',"CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }  
    function filters() {  
        if (isset($this->params['filter_organization']) && !empty($this->params['filter_organization'])) {
            $this->sql .= " AND le.costcenterid = :orgid ";;
            $this->params['orgid']= $this->params['filter_organization'];
        }

        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
           $this->sql .= " AND le.departmentid = :deptid ";
           $this->params['deptid']= $this->params['filter_departments'];
        }

        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
           $this->sql .= " AND le.subdepartment = :subdeptid ";
           $this->params['subdeptid']= $this->params['filter_subdepartments'];
        }

        if (!empty($this->params['filter_feedbacks'])) {
             $this->sql .= " AND le.id = :feedbackid ";
             $this->params['feedbackid']= $this->params['filter_feedbacks'];
        }

        if (!empty($this->params['filter_user'])) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid']= $this->params['filter_user'];

        }
    }    
    public function get_rows($feedbacks = array()) {
        return $feedbacks;
    }
}
