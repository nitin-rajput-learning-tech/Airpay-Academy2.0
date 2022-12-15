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

class report_programcompletions extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns','permissions', 'calcs', 'plot','orderable');
        $this->columns =  ['progarmfield' => ['programfield'],'userfield'=>['userfield'],'programcompletioncolumns'=>['programname','completionstatus','completiondate','lastaccess']];
        $this->parent = true;
        $this->filters = array('organization','departments', 'subdepartments', 'programs','user','completionstatus');
        $this->orderable = array('programname','lastaccess');
        $this->defaultcolumn = 'lpu.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(lpu.id)";
    }
    function select() {
        $this->sql = "SELECT lpu.id,lp.id as programid,u.id as userid,lp.name as programname,
                            lpu.completion_status AS completionstatus,
                            lpu.completiondate as completiondate, u.lastaccess";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_program} lp ";
    }
    function joins() {
         $this->sql .= " JOIN {local_program_stream} ps ON ps.id = lp.stream
                         JOIN {local_program_users} lpu ON lp.id = lpu.programid
                         JOIN {user} u ON u.id = lpu.userid";
          parent::joins();
    }
    function where(){
         global $USER, $DB;
        $this->sql .= " WHERE u.deleted = 0 AND u.suspended = 0 ";
        $this->params['siteid'] = SITEID;
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
           
   function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('lp.name',"CONCAT(u.firstname, ' ', u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters() { 

        if ($this->params['filter_organization'] > 0) {
            $this->sql .= " AND lp.costcenter = :orgid ";;
            $this->params['orgid']= $this->params['filter_organization'];
        }

        if ($this->params['filter_departments'] > 0) {
           $this->sql .= " AND lp.department = :deptid ";
           $this->params['deptid']= $this->params['filter_departments'];
        }

        if ($this->params['filter_subdepartments'] > 0) {
           $this->sql .= " AND lp.subdepartment = :subdeptid ";
           $this->params['subdeptid']= $this->params['filter_subdepartments'];
        }
        
        if (isset($this->params['filter_programs'])) {
            if(!empty($this->params['filter_programs'])){
                $this->sql .= " AND lp.id  = :prid ";
                $this->params['prid']= $this->params['filter_programs'];

            }
        }
        
        if(!empty($this->params['filter_user'])){
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid']= $this->params['filter_user'];

        }

        if ((isset($this->params['filter_completionstatus'])) && ($this->params['filter_completionstatus'] != -1)) {
           $this->sql .= " AND lpu.completion_status = :status ";
           $this->params['status']= $this->params['filter_completionstatus'];

        }
         //echo $this->sql;
        // print_object($this->params);
    }    
    public function get_rows($programs) {
        //print_object($programs);
        return $programs;
    }
}
