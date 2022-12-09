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

class report_classroom extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'permissions','orderable','plot');
        $this->columns = ['classroomcolumns'=> ['learner','instructorledcourse','enrolmentdate','coursedate', 'completiondate']];    
        $this->parent = true;
        $this->orderable = array('learner', 'instructorledcourse', 'enrolmentdate', 'coursedate', 'completiondate');
        $this->defaultcolumn = 'bll.id';
        $this->filters = array('classrooms');
        if ($this->loggedinuserrole != 'dh') {
            $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
        }else if($this->loggedinuserrole == 'dh'){
            $this->basicparams = array(['name'=>'subdepartments']);
        }
        $this->excludedroles = array("'employee'");
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
        $this->sql = "SELECT COUNT(DISTINCT bll.id)";
    }
    function select() {
        $this->sql  = "SELECT bll.id, bll.userid, bll.learningformatid as classroomid, bll.username AS learner, bll.name AS instructorledcourse, bll.role_assign_timemodified AS enrolmentdate, lca.sessionid"; 
         parent::select();                
    }
    function from() {
        $this->sql .= " FROM {block_ls_learningformats} as bll
                        JOIN {local_classroom_attendance} as lca ON lca.classroomid = bll.learningformatid AND lca.userid = bll.userid ";
    }
    function joins() {
          parent::joins();
    }
    function where(){
         global $USER, $DB;
         $this->sql .= " WHERE 1 = 1 AND bll.moduleid = 10 AND lca.enrol_status = 0 ";
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
        $systemcontext = context_system::instance();
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
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
                if ($this->params['filter_organization']>0) {
                    $this->sql .= " AND bll.costcenterid IN (" .$this->params['filter_organization'] .", 0) AND bll.user_costcenterid =".$this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND bll.departmentid IN (".$this->params['filter_departments'].", 0)  AND bll.user_departmentid =".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0) AND bll.user_costcenterid =".$USER->open_costcenterid; 
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND bll.departmentid IN (".$this->params['filter_departments'].", 0)  AND bll.user_departmentid =".$this->params['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){ 
                 $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0)  AND bll.user_costcenterid =".$USER->open_costcenterid ." AND bll.user_departmentid = ".$USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid.", 0)" ;
            }else { 
                $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0)  AND bll.user_costcenterid =".$USER->open_costcenterid ." AND bll.user_departmentid = ".$USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid.", 0) AND bll.subdepartment IN (".$USER->open_subdepartment.", 0) AND bll.user_subdepartment =".$USER->open_subdepartment ; 
            } 

            if ($this->params['filter_subdepartments'] > 0) {
                $this->sql .= " AND bll.subdepartment IN (".$this->params['filter_subdepartments'].", 0)  AND bll.user_subdepartment =".$this->params['filter_subdepartments'];
            }
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND bll.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }        
        if (!empty($this->params['filter_classrooms']) && $this->params['filter_classrooms'] > 0) {
            $classroomid = $this->params['filter_classrooms'];
            $this->sql .= " AND bll.learningformatid IN ($classroomid) ";
        }
        if(!empty($this->params['filter_status'])) {
            if($this->params['filter_status'] == 'completed') {
                $this->sql .= " AND bll.completiondate > 0 ";
            } else if ($this->params['filter_status'] == 'inprogress') {
                $this->sql .= " AND bll.completiondate = 0 ";
            }
        }
    }
    public function get_rows($classroom) {
        return $classroom;
    }
}
