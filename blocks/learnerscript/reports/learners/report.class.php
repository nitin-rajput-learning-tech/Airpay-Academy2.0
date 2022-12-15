<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */

use block_learnerscript\local\reportbase;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;

class report_learners extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'ordering', 'permissions', 'filters', 'plot');
        $this->parent = true;
        $this->columns = array('learnerscolumns' => array('orgdept', 'enrolments', 'inprogress', 'completed', 'completionpercentage'));
        $this->orderable = array('orgdept', 'enrolments', 'inprogress', 'completed', 'completionpercentage'); 
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->defaultcolumn = 'lcc.id';
        $this->excludedroles = array("'employee'");
    }
    function count() {
        $this->sql  = " SELECT count(DISTINCT lcc.id) ";
    }

    function select() {
        $this->sql = " SELECT lcc.id, lcc.fullname AS orgdept, COUNT(ue.id) AS enrolments, COUNT(cc.timecompleted) AS completed, (COUNT(ue.id) - COUNT(cc.timecompleted)) AS inprogress, ROUND((COUNT(cc.timecompleted) / COUNT(ue.id))*100, 0) AS completionpercentage ";
      parent::select();
    }
    
    function from() {
        $this->sql .= " FROM {local_costcenter} AS lcc
                      JOIN {user} AS u ON u.open_costcenterid = lcc.id OR u.open_departmentid = lcc.id
                      JOIN {user_enrolments} AS ue ON ue.userid = u.id
                      JOIN {enrol} AS e ON e.id = ue.enrolid 
                      JOIN {course} AS c ON c.id = e.courseid AND c.visible=1
                      JOIN {role_assignments} ra ON ra.userid = ue.userid
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                      JOIN {context} AS ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 
                      LEFT JOIN {course_completions} AS cc on cc.course = c.id AND cc.userid = ue.userid ";
    }

    function joins() {
      parent::joins();
    }

    function where() { 
        global $DB, $USER;
        $this->sql .= " WHERE c.id = ctx.instanceid AND u.confirmed = 1 AND u.deleted=0 AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("lcc.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        global $DB, $USER;
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
            $this->sql .= " AND lcc.parentid = 0 ";
            if ($this->params['filter_organization']>0) {
                $this->sql .= " AND u.open_costcenterid = " .$this->params['filter_organization']; 
            }

            if ($this->params['filter_departments'] > 0) {
                $this->sql .= " AND u.open_departmentid = ".$this->params['filter_departments'];
            }
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
                $this->sql .= " AND lcc.parentid = 0 ";
                $this->sql .= " AND u.open_costcenterid = " .$USER->open_costcenterid; 
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND u.open_departmentid = ".$this->params['filter_departments'];
                }
        }else if(has_capability('local/costcenter:manage_owndepartements', $systemcontext) && $dhs){
            $this->sql .= " AND lcc.parentid > 0 ";
            $this->sql .= " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid;
        }else{
            $this->sql .= " AND lcc.parentid > 0 ";
            $this->sql .= " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid ." AND u.open_subdepartment = ".$USER->open_subdepartment; 
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $this->sql .= " AND u.open_subdepartment = ".$this->params['filter_subdepartments'];
        }
        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return array(array(), 0);
            }
            $this->sql .= " AND u.id IN ( $conditions )";
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND c.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }      
    }
    public function get_rows($users) {
        return $users;
    }
    public function column_queries($column, $userid){

    }

}
