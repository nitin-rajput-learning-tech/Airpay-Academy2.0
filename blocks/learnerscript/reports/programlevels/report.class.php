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

class report_programlevels extends reportbase implements report {
  /**
   * [__construct description]
   * @param [type] $report           [description]
   * @param [type] $reportproperties [description]
   */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = false;
        $this->components = array('columns','filters', 'permissions');
        $columns = array('fullname','lastaccess');
        $columnsarray = array('userfield'=>['userfield'], 'programlevels' => $columns);
        $this->columns = $columnsarray;
        $this->basicparams = array(['name' => 'organization'],['name' => 'departments'], ['name'=>'subdepartments'], ['name' => 'programs']);
        $this->filters = array('cohort');
        $this->orderable = array('fullname','lastaccess');        
        $this->defaultcolumn = 'u.id';
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id) ";
    }

    function select() {
        $this->sql = "SELECT DISTINCT u.id, u.id as userid, CONCAT(u.firstname, ' ', u.lastname) AS fullname, lp.id AS programid, u.lastaccess" ;
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {user} u  ";
    }

    function joins() {
        $this->sql .=" JOIN {local_program_users} lpu ON lpu.userid = u.id 
                    JOIN {local_program} lp ON lp.id = lpu.programid 
                    LEFT JOIN {cohort_members} cm ON cm.userid = u.id";
        parent::joins();
    }

    function where() {
        global $USER, $DB;
        $this->sql .= " WHERE 1 = 1 AND u.deleted = 0 
                        AND u.suspended = 0 ";
        $systemcontext = context_system::instance();
    // getscheduled report
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = 1;
            }
        }
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
            $this->sql .= " AND u.open_costcenterid = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
            $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
        }else{
            $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid AND u.open_subdepartment =:subdepartment";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
            $this->params['subdepartment'] = $USER->open_subdepartment;
        }
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname,' ', u.lastname)",'u.email', 'u.open_employeeid');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        if (!empty($this->params['filter_organization']) && $this->params['filter_organization'] > 0) {
            $orgids = $this->params['filter_organization'];
            $this->sql .= " AND u.open_costcenterid = :orgid ";
            $this->params['orgid'] = $orgids;
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
            $deptids = $this->params['filter_departments'];
            $this->sql .= " AND u.open_departmentid = :deptids ";
            $this->params['deptids'] = $deptids;
        }
        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
            $subdeptids = $this->params['filter_subdepartments'];
            $this->sql .= " AND u.open_subdepartment = :subdeptid ";
            $this->params['subdeptid'] = $subdeptids;
        }
        if(isset($this->params['filter_programs']) && $this->params['filter_programs'] > 0) {
            $programid = $this->params['filter_programs'];
            $this->sql .= " AND lp.id = $programid ";
        }
        if(isset($this->params['filter_cohort']) && $this->params['filter_cohort'] > 0) {
            $cohortid = $this->params['filter_cohort'];
            $this->sql .= " AND cm.cohortid = $cohortid ";
        }
    }

    public function get_rows($programusers) {
        global $DB;
        if(!empty($programusers)){
            $data = array();
            foreach($programusers as $programuser){

                $report = new stdClass();
                $report->fullname = $programuser->fullname;
                $report->lastaccess = $programuser->lastaccess;
                $report->userid = $programuser->userid;
                $levels = $DB->get_records_sql("SELECT lpl.id, lpl.level 
                                FROM {local_program_levels} lpl
                                WHERE 1 = 1 AND lpl.programid = :programid", ['programid' => $programuser->programid]);
                $i = 0;
                foreach($levels as $level){
                    $usercompletionsql = "SELECT lblc.completion_status 
                                    FROM {local_bc_level_completions} lblc 
                                    WHERE 1 = 1 AND lblc.programid = :programid AND lblc.userid = :userid 
                                    AND lblc.levelid = :levelid";
                    $usercompletion = $DB->get_field_sql($usercompletionsql, ['programid' => $programuser->programid, 'userid' => $programuser->userid, 'levelid' => $level->id]);
                    $levelkey ="level_$i";
                    if (!$DB->record_exists_sql($usercompletionsql, ['programid' => $programuser->programid, 'userid' => $programuser->userid, 'levelid' => $level->id])) {
                        $report->{$levelkey} = '--';                        
                    } else {
                       if(!empty($usercompletion)){
                            $report->{$levelkey} = '<span class="label label-success">Completed</span>';
                        } else {
                            $report->{$levelkey} = '<span class="label label-warning">Not completed</span>';
                        } 
                    }
                    $i++;
                }
                $data[] = $report;
            }
            return $data;
        }
        return $finalelements;
    }
}
