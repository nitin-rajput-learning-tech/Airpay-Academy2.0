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
 * @author: Revanth Kumar
 * @date: 2021
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

class report_examenrolments extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->columns = array('examenrolmentscolumns' => array('learner', 'course', 'enrolmentdate', 'progress', 'dateofcompletion', 'completiondeadline','upcomingdeadline', 'overduedeadline', 'upcomingexpiry','upcomingendoflife'));
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $this->courselevel = true;
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
        $this->filters = array('coursevendors');
        $this->orderable = array('learner', 'course', 'enrolmentdate', 'progress', 'dateofcompletion', 'completiondeadline', 'upcomingdeadline', 'overduedeadline', 'upcomingexpiry','upcomingendoflife');
        $this->defaultcolumn = 'ue.id';
        $this->excludedroles = array("'employee'");
    }
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT ue.id)";
    }
    public function select() {
        $this->sql = "SELECT DISTINCT ue.id, CONCAT(u.firstname, ' ', u.lastname) AS learner, 
        ue.timecreated AS enrolmentdate, c.fullname AS course, cc.timecompleted AS dateofcompletion, ue.completiondate AS completiondeadline, c.id AS courseid, ue.userid AS userid ";
        parent::select();
    }
    public function from() {
        $this->sql .= " FROM {user_enrolments} ue
                         JOIN {enrol} e ON e.id = ue.enrolid 
                         JOIN {role_assignments} ra ON ra.userid = ue.userid
                         JOIN {context} ct ON ct.id = ra.contextid
                         JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
                         JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
                         JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid 
                         JOIN {local_courses_venderslist} lcc1 ON lcc1.id = c.open_vendor
                         JOIN {local_courses_learningformat} clf ON clf.id = c.open_learningformat AND clf.name = 'Exam' 
                         LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid 
                         LEFT JOIN {customfield_data} cfd ON c.id = cfd.instanceid
                         LEFT JOIN {customfield_field} cff ON cff.id = cfd.fieldid  ";
    }
    public function joins() { 
        parent::joins();
    }
    public function where() { 
        $this->sql .= " WHERE 1 = 1 AND ct.contextlevel = 50 ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            } 
        } 
        parent::where();
    }
    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
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
        $subquerysql = " ";
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
                if ($this->params['filter_organization']>0) {
                    $this->sql .= " AND c.open_costcenterid IN (" .$this->params['filter_organization'] .", 0) AND u.open_costcenterid = " .$this->params['filter_organization']; 
                    $subquerysql .= " AND c.open_costcenterid = " .$this->params['filter_organization']; 
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND c.open_departmentid IN (".$this->params['filter_departments'].", 0) AND u.open_departmentid=".$this->params['filter_departments'];
                    $subquerysql .= " AND c.open_departmentid = ".$this->params['filter_departments']; 

                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                $this->sql .= " AND c.open_costcenterid IN (" .$USER->open_costcenterid .", 0) AND u.open_costcenterid = " .$USER->open_costcenterid; 
                $subquerysql .= " AND c.open_costcenterid = " .$USER->open_costcenterid; 
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND c.open_departmentid IN (".$this->params['filter_departments'].", 0) AND u.open_departmentid=".$this->params['filter_departments'];
                    $subquerysql .= " AND c.open_departmentid = ".$this->params['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
                $this->sql .= " AND c.open_costcenterid IN (".$USER->open_costcenterid.", 0) AND u.open_costcenterid =".$USER->open_costcenterid ." AND u.open_departmentid = ".$USER->open_departmentid ." AND c.open_departmentid IN (". $USER->open_departmentid.", 0)" ;
                $subquerysql .= " AND c.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ; 
            } else { 
                $this->sql .= " AND c.open_costcenterid IN (".$USER->open_costcenterid.", 0) AND u.open_costcenterid =".$USER->open_costcenterid ." AND u.open_departmentid = ".$USER->open_departmentid ." AND c.open_departmentid IN (". $USER->open_departmentid.", 0) AND c.open_subdepartment IN (".$USER->open_subdepartment.",0) AND u.open_subdepartment =".$USER->open_subdepartment ;
            } 

            if ($this->params['filter_subdepartments'] > 0) {
                $this->sql .= " AND c.open_subdepartment IN (".$this->params['filter_subdepartments'].", 0) AND u.open_subdepartment=".$this->params['filter_subdepartments'];
                    $subquerysql .= " AND c.open_subdepartment = ".$this->params['filter_subdepartments']; 

            }
        } 
        if (!empty($this->params['filter_coursevendors']) && $this->params['filter_coursevendors'] > 0) { 
            $contentproviderids = $this->params['filter_coursevendors']; 
            $this->sql .= " AND c.open_vendor IN ($contentproviderids) ";
            $subquerysql .= " AND c.open_vendor IN ($contentproviderids) ";
        }

        if (isset($this->params['filter_status'])) {
            if ($this->params['filter_status'] == 'inprogress') {
                $this->sql .= " AND cc.timecompleted IS NULL ";    
            } else if ($this->params['filter_status'] == 'completed') {
                $this->sql .= " AND cc.timecompleted IS NOT NULL ";
            } else if($this->params['filter_status'] == 'upcoming') {
                $this->sql .= " AND cc.timecompleted IS NULL AND ue.completiondate != 0 AND ue.completiondate > UNIX_TIMESTAMP() ";
            } else if($this->params['filter_status'] == 'overdue') {
                $this->sql .= " AND cc.timecompleted IS NULL AND ue.completiondate != 0 AND ue.completiondate < UNIX_TIMESTAMP() ";
            } else if($this->params['filter_status'] == 'upcomingexpiry') {
                $this->sql .= " AND cff.name = 'Valid for (months)' AND cfd.charvalue != '' AND DATE_ADD(FROM_UNIXTIME(cfd.timemodified) , interval cfd.charvalue month) BETWEEN CURDATE() 
                        AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";

            } else if($this->params['filter_status'] == 'upcomingendoflife') {
                $this->sql .= "  AND cff.name = 'EOL' AND cfd.intvalue !=0  AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND cc.timecompleted IS NOT NULL ";
            }
        }       
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND c.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        } 
    }
    
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    public function get_rows($courses = array()) {
        return $courses;
    }
    public function column_queries($columnname, $courseid, $courses = null) {
        global $DB, $USER;     
        $where = " AND %placeholder% = $courseid"; 
        $systemcontext = context_system::instance();
    }
}
