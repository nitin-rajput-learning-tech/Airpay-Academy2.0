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

class report_learnercertificationsoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('learnercertificationscolumns' => array('learner','enrolments','inprogress','completed', 'upcomingexpiry', 'upcomingendoflife', 'upcomingdeadline','overduedeadline' ));
        $this->components = array('columns', 'filters', 'permissions', 'orderable','plot');
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->filters = array('certificates', 'contentprovider', 'learningtype',  'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');        
        $this->orderable = array('learner','enrolments','inprogress','completed', 'upcomingexpiry', 'upcomingendoflife','upcomingdeadline','overduedeadline', 'status');
        $this->defaultcolumn = 'u.id';
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = " SELECT COUNT(DISTINCT u.id) ";
    }
    function select() {
        $this->sql = " SELECT DISTINCT u.id, CONCAT(u.firstname, ' ', u.lastname) AS learner, COUNT(DISTINCT lcu.certificationid) AS enrolments ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {user} AS u
                        JOIN {local_certification_users} AS lcu on u.id = lcu.userid
                        JOIN {local_certification} AS lc ON lc.id = lcu.certificationid 
                        LEFT JOIN {local_certification_courses} AS lcc ON lcc.certificationid = lc.id ";
    }
    function joins() { 
        parent::joins();
    }
    function where() { 
        global $USER, $DB;
        $this->sql .= " WHERE 1 = 1 AND u.deleted = 0 AND u.confirmed = 1 ";
        parent::where();
    }
    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)");
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters() {    
        global $DB, $CFG,$OUTPUT, $USER;
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
                    $this->sql .= " AND lc.costcenter IN (".$this->params['filter_organization'].", 0) AND u.open_costcenterid =". $this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND lc.department IN (".$this->params['filter_departments'].",-1) AND u.open_departmentid=".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {
                $this->sql .= " AND lc.costcenter IN (" .$USER->open_costcenterid .", 0) AND u.open_costcenterid =". $USER->open_costcenterid;           
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND lc.department IN (".$this->params['filter_departments'].",-1) AND u.open_departmentid=".$this->params['filter_departments'];
                }
            } else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {
                $this->sql .= " AND lc.costcenter IN (" .$USER->open_costcenterid . ", 0) AND lc.department IN (".$USER->open_departmentid.",-1) AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid;
            }else {
                $this->sql .= " AND lc.costcenter IN (" .$USER->open_costcenterid . ", 0) AND lc.department IN (".$USER->open_departmentid.",-1) AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid ." AND lc.subdepartment IN (" .$USER->open_subdepartment .", 0) AND u.open_subdepartment =" .$USER->open_subdepartment;
            }

            if ($this->params['filter_subdepartments'] > 0) {
                $this->sql .= " AND lc.subdepartment IN (".$this->params['filter_subdepartments'].",-1) AND u.open_subdepartment=".$this->params['filter_subdepartments'];
            }

        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND lc.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
        if (!empty($this->params['filter_certificates']) && $this->params['filter_certificates'] > 0) {
            $this->sql .= " AND lc.id = ".$this->params['filter_certificates']." ";
        }
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $this->sql .= " AND c.open_contentvendor IN ($contentproviderids) ";
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
                $this->sql .= " AND lcc.courseid IN (".$tagcoursesql.")";
            } else {
                $this->sql .= " AND lcc.courseid IN (0)";
            } 
        }         
    } 
        
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    function get_rows($courses = array()) {
        return $courses;
    }
}
