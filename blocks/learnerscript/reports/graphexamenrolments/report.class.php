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
/** LearnerScript Reports
  * A Moodle block for creating customizable reports
  * @package blocks
  * @subpackage learnerscript
 * @author: Revanth Kumar
 * @date: 2021
  */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_graphexamenrolments extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'permissions','orderable','plot');
        $this->columns = ['graphexamenrolmentscolumns'=> ['month', 'enrolments', 'completed']];    
        $this->parent = true;
        $this->orderable = array('id', 'enrolments');
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->defaultcolumn = 'm.id';
        $this->orderable = array('month', 'enrolments', 'completed');
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
        $this->sql = "SELECT COUNT(DISTINCT m.id)";
    }
    function select() {
        $this->sql  = "SELECT DISTINCT m.id, m.id AS month "; 
        parent::select();                
    }
    function from() {
        $this->sql .= "FROM (
                        SELECT 1 AS
                        id
                        UNION SELECT 2 AS
                        id
                        UNION SELECT 3 AS
                        id
                        UNION SELECT 4 AS
                        id
                        UNION SELECT 5 AS
                        id
                        UNION SELECT 6 AS
                        id
                        UNION SELECT 7 AS
                        id
                        UNION SELECT 8 AS
                        id
                        UNION SELECT 9 AS
                        id
                        UNION SELECT 10 AS
                        id
                        UNION SELECT 11 AS
                        id
                        UNION SELECT 12 AS
                        id
                        ) AS m ";
    }
    function joins() {
          parent::joins();
    }
    function where(){
        global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
         parent::where();
    }   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array('m.id');
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
       
    } 
    function filters(){
        global $DB, $USER;
    }
    public function get_rows($learningpaths) {
        return $learningpaths;
    } 
    public function column_queries($columnname, $vendorid) { 
        $where = " AND %placeholder% = $vendorid";
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
        $filtersql = " ";
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
                if ($this->params['filter_organization']) {
                    $filtersql .= " AND c.open_costcenterid = " .$this->params['filter_organization']; 
                }
                if ($this->params['filter_departments'] > 0) {
                    $filtersql .= " AND c.open_departmentid = ".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                $filtersql .= " AND c.open_costcenterid = " .$USER->open_costcenterid; 
                if ($this->params['filter_departments'] > 0) {
                    $filtersql .= " AND c.open_departmentid = ".$this->params['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
               $filtersql .= " AND c.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ; 
            } else { 
                $filtersql .= " AND c.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ." AND c.open_subdepartment = " .$USER->open_subdepartment; 
            } 

            if ($this->params['filter_subdepartments'] > 0) {
                 $filtersql .= " AND c.open_subdepartment = ".$this->params['filter_subdepartments'];
            }
        }

         
        switch ($columnname) {
            case 'enrolments': 
            $identy = "DATE_FORMAT(from_unixtime(ue.timecreated), '%m')";
            $query = " SELECT COUNT(DISTINCT ue.id) AS enrolments 
                        FROM {user_enrolments} ue
                         JOIN {enrol} e ON e.id = ue.enrolid 
                         JOIN {role_assignments} ra ON ra.userid = ue.userid
                         JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                         JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
                         JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
                         JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid 
                         WHERE 1 = 1 AND c.open_learningformat = 2 $where $filtersql ";
            break;
            case 'completed': 
            $identy = "DATE_FORMAT(from_unixtime(ue.timecreated), '%m')";
            $query = " SELECT COUNT(DISTINCT ue.id) AS completed 
                        FROM {user_enrolments} ue
                         JOIN {enrol} e ON e.id = ue.enrolid 
                         JOIN {role_assignments} ra ON ra.userid = ue.userid
                         JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                         JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
                         JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
                         JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid 
                         JOIN {course_completions} as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid 
                         WHERE 1 = 1 AND c.open_learningformat = 2 $where $filtersql ";
            break;

        } 
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
