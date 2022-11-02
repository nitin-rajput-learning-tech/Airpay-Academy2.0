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

class report_learningplancomletions extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER, $DB;
        parent::__construct($report);
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot','orderable');
        $this->columns = ['learningpathfield'=>['learningpathfield'], 'userfield'=>['userfield'],'learningplancompletionscolumns'=>['learningpathname','completionstatus','completiondate']];
        $this->parent = true;
        $this->filters = array('organization','departments','learningpath','completionstatus');
        $this->orderable = array('learningpathname');
        $this->defaultcolumn = 'llu.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(llu.id)";
    }
    function select() {
        $this->sql = "SELECT llu.id,lp.id as learningpathid,u.id as userid,lp.name as learningpathname,
                        llu.status AS completionstatus,
                        llu.completiondate as completiondate ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_learningplan} lp  ";
    }
    function joins() {
         $this->sql .= "  JOIN {local_learningplan_user} as llu ON lp.id = llu.planid
                          JOIN {user} as u ON u.id = llu.userid ";
          parent::joins();
    }
    function where(){
         global $USER, $DB;
         $this->sql .= " WHERE u.deleted = 0 AND u.suspended = 0 AND lp.visible = 1";
         $systemcontext = \context_system::instance();
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
            $this->sql .= " AND lp.costcenter = :costcenterid ";
            $this->params['costcenterid']= $USER->open_costcenterid;

        }else{
            $this->sql .= " AND lp.costcenter =:costcenterid 
                        AND lp.department = :departmentid";
            $this->params['costcenterid']= $USER->open_costcenterid;
            $this->params['departmentid']= $USER->open_departmentid;
        }

         parent::where();
    }
           
   function search() {
        if(isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)", 'lp.name', 'u.open_employeeid');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }  
    } 
    function filters() {   
        
        if (isset($this->params['filter_organization']) && !empty($this->params['filter_organization'])) {
            $this->sql .= " AND lp.costcenter = :orgid ";;
            $this->params['orgid']= $this->params['filter_organization'];
        }

        if (isset($this->params['filter_departments']) && $this->params['filter_departments'] >= 0 && $this->params['filter_departments'] != '' ) {
           $this->sql .= " AND lp.department = :deptid ";
           $this->params['deptid']= $this->params['filter_departments'];
        }

        if (!empty($this->params['filter_learningpath'])) {
            $lplan = $this->params['filter_learningpath'];
            $this->sql .= " AND lp.id = $lplan ";

        }
        if (!empty($this->params['filter_user'])) {
            $userid = $this->params['filter_user'];
            $this->sql .= " AND u.id = $userid ";
        }
       if ((isset($this->params['filter_completionstatus'])) && ($this->params['filter_completionstatus'] != -1)) {
            $lpid = $this->params['filter_completionstatus'];
            if($lpid == 1){
                $this->sql .= " AND llu.status = $lpid";
            }else{
                $this->sql .= " AND llu.status IS NULL";
            }
        }
        // echo $this->sql;
        // print_object($this->params);exit;
   }
    public function get_rows($learningpath) {
        return $learningpath;
    }
}
