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

class report_learnerexamoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->columns = array('learnerexamcolumns' => array('learner', 'enrolments', 'inprogress', 'completed', 'upcomingdeadline', 'overduedeadline','upcomingexpiry','upcomingendoflife'));
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $this->courselevel = true;
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->orderable = array('learner', 'enrolments', 'inprogress', 'completed', 'upcomingdeadline', 'overduedeadline','upcomingexpiry','upcomingendoflife');
        $this->filters = array('contentprovider', 'learningtype',  'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');        
        $this->searchable = array('');
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = array("'employee'");
    }
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id)";
    }
    public function select() {
        $this->sql = " SELECT DISTINCT u.id, CONCAT(u.firstname, ' ', u.lastname) AS learner, COUNT(le.userid) AS enrolments, (SELECT COUNT(le1.examid) FROM {block_ls_exams} le1 
                                WHERE le1.completiondate = 0 AND le1.userid = u.id) AS inprogress, 
                                (SELECT COUNT(le1.examid) FROM {block_ls_exams} le1 
                                WHERE le1.completiondate != 0 AND le1.userid = u.id) AS completed ";
        parent::select();
    }
    public function from() {
        $this->sql .= " FROM {user} AS u 
                        JOIN {block_ls_exams} le ON le.userid = u.id ";
    }
    public function joins() { 
        parent::joins();
    }
    public function where() { 
        $this->sql .= "  WHERE 1 = 1 ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND le.examid IN ($this->rolewisecourses) ";
            } 
        } 
        parent::where();
    }
    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("le.username");
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
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
                if ($this->params['filter_organization']>0) {
                    $this->sql .= " AND le.costcenterid IN (".$this->params['filter_organization'].", 0) AND le.user_costcenterid=".$this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND le.departmentid IN (".$this->params['filter_departments'].", 0) AND le.user_departmentid=".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                $this->sql .= " AND le.costcenterid IN (".$USER->open_costcenterid.", 0) AND le.user_costcenterid=".$USER->open_costcenterid;
                if ($this->params['filter_departments'] > 0) {
                   $this->sql .= " AND le.departmentid IN (".$this->params['filter_departments'].", 0) AND le.user_departmentid=".$this->params['filter_departments'];

                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
                $this->sql .= " AND le.costcenterid IN (".$USER->open_costcenterid.", 0) AND le.user_costcenterid=".$USER->open_costcenterid;
                if ($this->params['filter_departments'] > 0) {
                   $this->sql .= " AND le.departmentid IN (".$this->params['filter_departments'].", 0) AND le.user_departmentid=".$this->params['filter_departments'];

                }
            } else { 
                $this->sql .= " AND le.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND le.user_costcenterid = ".$USER->open_costcenterid ." AND le.departmentid = ".$USER->open_departmentid ." AND le.user_departmentid IN (". $USER->open_departmentid .", 0)" ;               
            }
            if ($this->params['filter_subdepartments'] > 0) {
                $this->sql .= " AND le.subdepartment IN (".$this->params['filter_subdepartments'].", 0) AND le.user_subdepartment=".$this->params['filter_subdepartments'];

            }
        }
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $this->sql .= " AND le.open_contentvendor IN ($contentproviderids) ";
        }
        $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0;        
        $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        $tagslist = array($learningtype, $solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
        if (array_sum($tagslist) > 0) {
            $tagslist = implode(',', $tagslist); 
            $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
            if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
                $this->sql .= " AND le.examid IN (".$tagcoursesql.")";
            } else {
                $this->sql .= " AND le.examid IN (0)";
            } 
        }        
        if (isset($this->params['filter_status'])) {
            if($this->params['filter_status'] == 'completed') {
                $this->sql .= " AND le.completiondate IS NOT NULL ";
            } else if($this->params['filter_status'] == 'inprogress'){
                $this->sql .= " AND le.completiondate IS NULL ";
            } else if($this->params['filter_status'] == 'overdue') {
                $this->sql .= " le.deadline < UNIX_TIMESTAMP() AND le.completiondate = 0 AND le.deadline != 0 ";
            } else if($this->params['filter_status'] == 'upcoming') {
                $this->sql .= " le.deadline > UNIX_TIMESTAMP() AND le.completiondate = 0 AND le.deadline != 0 ";
            } else if($this->params['filter_status'] == 'upcomingexpiry') {
                $this->sql .= " le.upcomingexpiry != 0 ";

            } else if($this->params['filter_status'] == 'upcomingendoflife') {
                $this->sql .= " le.upcomingeol != 0 ";
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
