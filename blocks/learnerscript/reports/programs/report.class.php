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
use block_learnerscript\local\querylib;
use block_learnerscript\report;

class report_programs extends reportbase implements report {

	/**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns','permissions','filters');
        $this->columns = ['programfield'=>['programfield'],'programcolumns' => ['learner','program', 'enrolmentdate', 'progress', 'completiondate', 'upcomingdeadline', 'overduedeadline']];
        $this->filters = ['programs'];
        $this->orderable = array('learner','program', 'enrolmentdate', 'progress', 'completiondate', 'upcomingdeadline', 'overduedeadline');
        $this->defaultcolumn = 'bll.id';
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->searchable = array('bll.name');
    }
    function init() {
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }        
        parent::init();
    }
    function count() {
        $this->sql = " SELECT count(DISTINCT bll.id)";
    }
    function select() {
        $this->sql = "SELECT bll.id, bll.userid, bll.learningformatid as programid, bll.username as learner, bll.name as program, bll.role_assign_timemodified as enrolmentdate, bll.completiondate, bll.upcomingdeadline as completiondeadline ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {block_ls_learningformats} as bll";
    }
    function joins() {
         $this->sql .= " ";
          parent::joins();
    }
    function where(){
        global $USER, $DB;
         $this->sql .=  " WHERE 1=1 AND bll.moduleid = 9";
           parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $this->searchable);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters(){
        global $DB, $USER;
        $context = context_system::instance();
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

        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){ 
                if ($this->params['filter_organization']>0) {
                    $this->sql .= " AND bll.costcenterid IN (" .$this->params['filter_organization'] .", 0) AND bll.user_costcenterid = ".$this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND bll.departmentid IN (".$this->params['filter_departments'].", 0) AND bll.user_departmentid =".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0)  AND bll.user_costcenterid = ".$USER->open_costcenterid;
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND bll.departmentid IN (".$this->params['filter_departments'].", 0) AND bll.user_departmentid =".$this->params['filter_departments'];
                }
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                 $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0)  AND bll.user_costcenterid = ".$USER->open_costcenterid ." AND bll.user_departmentid = ".$USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid.", 0)" ;
            } else {
                $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0)  AND bll.user_costcenterid = ".$USER->open_costcenterid ." AND bll.user_departmentid = ".$USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid.", 0) AND bll.subdepartment IN (" .$USER->open_subdepartment .", 0) AND bll.user_subdepartment = ".$USER->open_subdepartment ;
            }
            if ($this->params['filter_subdepartments'] > 0) {
                $this->sql .= " AND bll.subdepartment IN (".$this->params['filter_subdepartments'].", 0) AND bll.user_subdepartment =".$this->params['filter_subdepartments'];
            } 
        }
        if (!empty($this->params['filter_programs'])) {
            $this->sql .= " AND bll.learningformatid =". $this->params['filter_programs'] ;
        }
        if(isset($this->params['filter_status'])){
            if($this->params['filter_status'] == 'inprogress') {
                $this->sql .= " AND bll.completiondate = 0 ";
            } else if($this->params['filter_status'] == 'completed') {
                $this->sql .= " AND bll.completiondate > 0 ";
            } else if($this->params['filter_status'] == 'upcoming') {
                $this->sql .= " AND bll.upcomingdeadline > UNIX_TIMESTAMP() AND  bll.completiondate = 0 AND bll.upcomingdeadline != 0  ";
            } else if($this->params['filter_status'] == 'overdue') {
                $this->sql .= " AND bll.upcomingdeadline < UNIX_TIMESTAMP() AND  bll.completiondate = 0 AND bll.upcomingdeadline != 0  ";       
            }
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND bll.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        } 
    }    
    function get_rows($programs){
        return $programs;
    }
 }
