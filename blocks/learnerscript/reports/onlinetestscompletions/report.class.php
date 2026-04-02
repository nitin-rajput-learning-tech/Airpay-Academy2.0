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

class report_onlinetestscompletions extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        // 'userfield' =>['userfield'],
        $this->columns = ['onlinetestfield'=>['onlinetestfield'],'userfield'=>['userfield'],'onlinetestscompletionscolumns' => ['onlinetestname','achievedgrade','completionstatus','completiondate']];
        $this->components = array('columns', 'filters', 'permissions');
        $this->filters = array('organization','departments', 'subdepartments', 'onlinetests','users','completionstatus');
        $this->orderable = array('onlinetestname');
        $this->defaultcolumn = 'lou.id';
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(lou.id)";
    }
    function select() {
        $this->sql = "SELECT lou.id,u.id as userid,lo.id as onlinetestid, lo.quizid,lo.name as onlinetestname,
                      lou.status as completionstatus, lou.timemodified as completiondate ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_onlinetests} lo ";
    }
    function joins() {
         $this->sql .= " JOIN {local_onlinetest_users} as lou ON lou.onlinetestid = lo.id
                         JOIN {user} as u ON u.id = lou.userid AND u.suspended = 0 ";
          parent::joins();
    }
    function where(){
        global $USER, $DB;
         $this->sql .=  " WHERE u.deleted = 0 ";
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
            $this->sql .= "";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
            $this->sql .= " AND lo.costcenterid = :costcenterid ";
            $this->params['costcenterid']= $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
            $this->sql .= " AND lo.costcenterid = :costcenterid  AND lo.departmentid = :departmentid";
            $this->params['costcenterid']= $USER->open_costcenterid;
            $this->params['departmentid']= $USER->open_departmentid;
        }else{
            $this->sql .= " AND lo.costcenterid = :costcenterid  AND lo.departmentid = :departmentid AND lo.subdepartment = :subdepartmentid";
            $this->params['costcenterid']= $USER->open_costcenterid;
            $this->params['departmentid']= $USER->open_departmentid;
            $this->params['subdepartmentid']= $USER->open_subdepartment;
        }

         parent::where();
    }
 function search() {
         if (isset($this->search) && $this->search) {
            $fields = array('lo.name',"CONCAT(u.firstname, ' ', u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters() {       
        if (isset($this->params['filter_organization']) && !empty($this->params['filter_organization'])) {
            $this->sql .= " AND lo.costcenterid = :orgid ";;
            $this->params['orgid']= $this->params['filter_organization'];
        }

        if ($this->params['filter_departments'] > 0) {
           $this->sql .= " AND lo.departmentid = :deptid ";
           $this->params['deptid']= $this->params['filter_departments'];
        }
        if ($this->params['filter_subdepartments'] > 0) {
           $this->sql .= " AND lo.subdepartment = :subdeptid ";
           $this->params['subdeptid']= $this->params['filter_subdepartments'];
        }
        if (!empty($this->params['filter_onlinetests'])) {
                $this->sql .= " AND lo.id = :testid ";
                $this->params['testid']= $this->params['filter_onlinetests'];

        }
        if (!empty($this->params['filter_user'])) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid']= $this->params['filter_users'];
        }   
        if ($this->params['filter_completionstatus'] > -1) {
            $this->sql .= " AND lou.status = :status ";
            $this->params['status']= $this->params['filter_completionstatus'];
        }
    }
    public function get_rows($onlinetests) {
        return $onlinetests;
    }
}
