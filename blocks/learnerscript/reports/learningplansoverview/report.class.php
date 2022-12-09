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

class report_learningplansoverview extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'permissions','orderable','plot');
        $this->columns = ['learningpathfield'=>['learningpathfield'], 'learningplansoverviewcolumns'=> ['optionalcourses','mandatorycourses','enrolledcount',
         'completedcount']];    
        $this->parent = true;
        $this->filters = array('organization','departments', 'subdepartments', 'learningpath');
        $this->orderable = array('learningpath_name','enrolledcount','completedcount');
        $this->defaultcolumn = 'lp.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(lp.id)";
    }
    function select() {
        $this->sql  = "SELECT lp.id as learningpathid,lp.name as learningplanname,
                    (SELECT count(llu.id) 
                        FROM {local_learningplan_user} as llu 
                        JOIN {user} u ON u.id = llu.userid AND u.deleted = 0 AND u.suspended = 0
                        WHERE llu.planid = lp.id) as enrolledcount,
                    (SELECT count(llu.id) 
                        FROM {local_learningplan_user} as llu 
                        JOIN {user} u ON u.id = llu.userid AND u.deleted = 0 AND u.suspended = 0
                        WHERE llu.planid = lp.id AND llu.status = 1) as completedcount ";
         parent::select();                
    }
    function from() {
        $this->sql .= " FROM {local_learningplan} lp ";
    }
    function joins() {
          parent::joins();
    }
    function where(){
         global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
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
            $this->sql .= "";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
            $this->sql .= " AND lp.costcenter = :costcenterid ";
            $this->params['costcenterid']= $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
            $this->sql .= " AND lp.costcenter = :costcenterid AND lp.department = :departmentid";
           $this->params['costcenterid']= $USER->open_costcenterid;
           $this->params['departmentid']= $USER->open_departmentid;
        }else{
           $this->sql .= " AND lp.costcenter = :costcenterid AND lp.department = :departmentid AND lp.subdepartment = :subdepartment";
           $this->params['costcenterid']= $USER->open_costcenterid;
           $this->params['departmentid']= $USER->open_departmentid;
           $this->params['subdepartment']= $USER->open_subdepartment;
        }
         parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array('lp.name');
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
       
    } 
    function filters(){
         if ($this->params['filter_organization'] > 0) {
            $orgid = $this->params['filter_organization'];
            $this->sql .= " AND lp.costcenter = :orgid ";
            $this->params['orgid'] = $orgid;
        }
        if ($this->params['filter_departments'] > 0) {
            $this->sql .= " AND lp.department = :deptid ";
            $this->params['deptid'] = $this->params['filter_departments'];
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $this->sql .= " AND lp.subdepartment = :subdeptid ";
            $this->params['subdeptid'] = $this->params['filter_subdepartments'];
        }
        if ($this->params['filter_learningpath'] > 0) {
            $lplan = $this->params['filter_learningpath'];
            $this->sql .= " AND lp.id = $lplan ";
        }
    }
    public function get_rows($learningpaths) {
        return $learningpaths;
    }
}
