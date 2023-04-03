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
        $this->filters = array('organization','departments', 'subdepartments','level4department', 'programs','user','completionstatus');
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
        $this->sql = "SELECT (@cnt := @cnt + 1) AS rowNumber,lpu.id,lp.id as programid,u.id as userid,u.*,lp.name as programname,
                            lpu.completion_status AS completionstatus,CONCAT(u.firstname,' ',u.lastname) as fullname, 
                            lpu.completiondate as completiondate, u.lastaccess";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_program} lp ";
    }
    function joins() {
         $this->sql .= " JOIN {local_program_users} lpu ON lp.id = lpu.programid
                         JOIN {user} u ON u.id = lpu.userid 
                         CROSS JOIN (SELECT @cnt := 0) AS dummy ";//JOIN {local_custom_category} cat ON cat.id = lp.open_categoryid
          parent::joins();
    }
    function where(){
        global $USER, $DB;
        $this->sql .= " WHERE u.deleted = 0 AND u.suspended = 0 ";
        $this->params['siteid'] = SITEID;
       
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
        $costcenterpathconcatsql = (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'lp.open_path');
        if (is_siteadmin() ) {
          $this->sql .= "";
        } else {
          $this->sql .= $costcenterpathconcatsql;
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
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments'] > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
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
       
    }    
    public function get_rows($programs) {
        return $programs;
    }
}
