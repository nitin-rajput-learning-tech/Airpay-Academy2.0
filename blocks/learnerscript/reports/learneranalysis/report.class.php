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
 * @author: Jahnavi
 * @date: 2021
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

class report_learneranalysis extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->columns = array('learneranalysis' => array('learner', 'learningformat', 'learningpath'));
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $this->courselevel = true;
        if ($this->loggedinuserrole != 'dh') {
            $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
        }else if ($this->loggedinuserrole == 'dh') {
            $this->basicparams = array(['name' => 'subdepartments']); 
        }
        $this->excludedroles = array("'employee'");
        $this->filters = array('course', 'contentprovider', 'learningtype',  'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');        
        $this->orderable = array('learner', 'learningformat', 'learningpath');
        $this->searchable = array('');
        $this->defaultcolumn = 'u.id';
    }
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id)";
    }
    public function select() {
        $this->sql = "SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS learner ";
        parent::select();
    }
    public function from() {
        $this->sql .= " FROM {user} u ";
    }
    public function joins() { 
        parent::joins();
    }
    public function where() { 
        $this->sql .= " WHERE 1 = 1 AND u.confirmed = 1 AND u.deleted = 0 ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            } 
        } 
        parent::where();
    }
    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('u.firstname');
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
                    $this->sql .= " AND u.open_costcenterid = " .$this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND u.open_departmentid = ".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                $this->sql .= " AND u.open_costcenterid = " .$USER->open_costcenterid;
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND u.open_departmentid = ".$this->params['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
               $this->sql .= " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid ;
            } else {                 
                $this->sql .= " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid ." AND u.open_subdepartment = ".$USER->open_subdepartment;
            } 
        }
        // if (!empty($this->params['filter_onlinecourses']) && $this->params['filter_onlinecourses'] > 0) {
        //     $onlinecourseid = $this->params['filter_onlinecourses'];
        //     $this->sql .= " AND c.id IN ($onlinecourseid) ";
        //     $subqueryfilter .= " AND c.id IN ($onlinecourseid) ";
        // }
        // if (!empty($this->params['filter_contentprovider'])) {
        //     $contentproviderids = $this->params['filter_contentprovider']; 
        //     $this->sql .= " AND c.open_contentvendor IN ($contentproviderids) ";
        // }
        // $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0;
        // $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        // $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        // $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        // $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        // $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        // $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        // $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        // $tagslist = array($learningtype, $solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
        // if (array_sum($tagslist) > 0) {
        //     $tagslist = implode(',', $tagslist); 
        //     $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
        //     if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
        //         $this->sql .= " AND c.id IN (".$tagcoursesql.")";
        //     } else {
        //         $this->sql .= " AND c.id IN (0)";
        //     } 
        // }                                

        // if (isset($this->params['filter_status'])) {
        //     if($this->params['filter_status'] == 'completed') {
        //         $this->sql .= " AND cc.timecompleted IS NOT NULL ";
        //     } else if($this->params['filter_status'] == 'inprogress'){
        //         $this->sql .= " AND cc.timecompleted IS NULL ";
        //     } else if($this->params['filter_status'] == 'overdue') {
        //         $this->sql .= " AND ue.completiondate !=0 AND ue.completiondate < UNIX_TIMESTAMP() AND ue.id NOT IN (SELECT DISTINCT ue.id 
        //             FROM {user_enrolments} ue
        //             JOIN {enrol} e ON e.id = ue.enrolid 
        //             JOIN {role_assignments} ra ON ra.userid = ue.userid
        //             JOIN {context} ct ON ct.id = ra.contextid
        //             JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
        //             JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
        //             JOIN {course_completions} as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid 
        //             JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid 
        //             JOIN {local_courses_learningformat} clf ON clf.id = c.open_learningformat AND clf.name = 'Online Course'
        //             WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') $subqueryfilter ) ";               
        //     } else if($this->params['filter_status'] == 'upcoming') {
        //         $this->sql .= " AND ue.completiondate !=0 AND ue.completiondate > UNIX_TIMESTAMP() AND ue.id NOT IN (SELECT DISTINCT ue.id 
        //             FROM {user_enrolments} ue
        //             JOIN {enrol} e ON e.id = ue.enrolid 
        //             JOIN {role_assignments} ra ON ra.userid = ue.userid
        //             JOIN {context} ct ON ct.id = ra.contextid
        //             JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
        //             JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
        //             JOIN {course_completions} as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid 
        //             JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid 
        //             JOIN {local_courses_learningformat} clf ON clf.id = c.open_learningformat AND clf.name = 'Online Course'
        //             WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') $subqueryfilter ) ";
        //     }

        // } 
        // if ($this->ls_startdate >= 0 && $this->ls_enddate) {
        //     $this->sql .= " AND c.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        // }        
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
        
    }
}
