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

/** LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @subpackage learnerscript
 * @author: Revanth Kumar Grandhi
 * @date: 2021
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

class report_examoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->columns = array('examoverviewcolumns' => array('learner', 'course', 'enrolments', 'inprogress', 'completed', 'upcomingdeadline', 'overduedeadline','upcomingexpiry','upcomingendoflife'));
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $this->courselevel = true;
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->orderable = array('learner', 'course', 'enrolments', 'inprogress', 'completed', 'upcomingdeadline', 'overduedeadline','upcomingexpiry','upcomingendoflife');
        $this->searchable = array('');
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = array("'employee'");
    }
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id)";
    }
    public function select() {
        $this->sql = " SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS learner, c.fullname AS course, COUNT(ue.userid) AS enrolments, (count(ue.userid) - COUNT(cc.timecompleted)) AS inprogress, COUNT(cc.timecompleted) AS completed ";
        parent::select();
    }
    public function from() {
        $this->sql .= " FROM {user} AS u
                        JOIN {user_enrolments} AS ue ON ue.userid = u.id
                        JOIN {enrol} AS e ON e.id = ue.enrolid
                        JOIN {course} AS c ON c.id = e.courseid 
                        JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat AND clf.name = 'Exam'
                        JOIN {role_assignments} AS ra ON ra.userid = ue.userid
                        JOIN {role} AS r ON r.id = ra.roleid AND r.shortname = 'employee'
                        JOIN {context} ct ON ct.id = ra.contextid and ct.instanceid = c.id
                        LEFT JOIN {course_completions} AS cc ON cc.userid = ue.userid AND cc.course = c.id ";
    }
    public function joins() { 
        parent::joins();
    }
    public function where() { 
        $this->sql .= "  WHERE 1 = 1 AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND ct.contextlevel = 50 ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            } 
        } 
        parent::where();
    }
    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('name');
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    public function filters() { 
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
                    $this->sql .= " AND c.open_costcenterid = " . $this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND c.open_departmentid = ". $this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                $this->sql .= " AND c.open_costcenterid = " . $USER->open_costcenterid;             
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND c.open_departmentid = ". $this->params['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
               $this->sql .= " AND c.open_costcenterid = " . $USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ; 
            } else { 
                
                $this->sql .= " AND c.open_costcenterid = " . $USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ." AND c.open_subdepartment = ". $USER->open_subdepartment ;              
            } 
        }
        if (isset($this->params['filter_status'])) {
            if($this->params['filter_status'] == 'completed') {
                $this->sql .= " AND cc.timecompleted IS NOT NULL ";
            } else if($this->params['filter_status'] == 'inprogress'){
                $this->sql .= " AND cc.timecompleted IS NULL ";
            } else if($this->params['filter_status'] == 'overdue') {
                $this->sql .= " AND cc.timecompleted IS NULL AND ue.completiondate !=0 AND ue.completiondate < UNIX_TIMESTAMP() ";
            } else if($this->params['filter_status'] == 'upcoming') {
                $this->sql .= " AND cc.timecompleted IS NULL AND ue.completiondate != 0 AND ue.completiondate > UNIX_TIMESTAMP() ";
            } else if($this->params['filter_status'] == 'upcomingexpiry') {
                $this->sql .= " AND cff.shortname = 'Valid for (months)' AND DATE_ADD(FROM_UNIXTIME(cfd.timemodified) , interval cfd.charvalue month) BETWEEN CURDATE() 
                        AND (CURDATE() + 90) AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";

            } else if($this->params['filter_status'] == 'upcomingendoflife') {
                $this->sql .= "  AND cff.shortname = 'EOL' AND FROM_UNIXTIME(cfd.intvalue) BETWEEN CURDATE() AND (CURDATE() + 90)
                          AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";
            }
        }
    }
    
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    public function get_rows($learning = array()) {
        return $learning;
    }
    public function column_queries($columnname, $courseid, $courses = null) {

    }
}
