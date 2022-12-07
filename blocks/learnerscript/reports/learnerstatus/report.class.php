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

class report_learnerstatus extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'ordering', 'permissions', 'filters', 'plot');
        $this->parent = true;
        $this->columns = array('learnerstatus' => array('email','type','learner', 'course', 'progress', 'completed', 'upcomingdeadline',  'overduedeadline'));
        $this->orderable = array('email','type','learner', 'course', 'progress', 'completed', 'upcomingdeadline',  'overduedeadline'); 
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->filters = array('contentprovider', 'learningtype', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->excludedroles = array("'employee'");
        $this->searchable = array('bll.username');        
    }
    function count() {
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
            if ($this->params['filter_organization']>0) {
                $costcenter = " AND bll.user_costcenterid = " .$this->params['filter_organization'] ." AND bll.costcenterid IN (".$this->params['filter_organization'] . ",0)"; 
                $costcenterlp = " AND bll.user_costcenterid = " .$this->params['filter_organization'] ." AND bll.costcenterid IN (".$this->params['filter_organization'] .", 0)";   
                $costcenterclass = " AND bll.user_costcenterid = " .$this->params['filter_organization']  ." AND bll.costcenterid IN (".$this->params['filter_organization'] .", 0)";
                $costcenterp = " AND bll.user_costcenterid = " .$this->params['filter_organization'] ." AND bll.costcenterid IN (".$this->params['filter_organization'] .", 0)";
            }
            if ($this->params['filter_departments'] > 0) {
                $dept = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                $deptlp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                $deptclass = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                $deptp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";                
            }
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
                $costcenter = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid . ",0)";
                $costcenterlp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";   
                $costcenterclass = " AND bll.user_costcenterid = " .$USER->open_costcenterid  ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                $costcenterp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                if ($this->params['filter_departments'] > 0) {
                    $dept = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptlp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                    $deptclass = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                }
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
                $costcenter = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid . ",0)";
                $costcenterlp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";   
                $costcenterclass = " AND bll.user_costcenterid = " .$USER->open_costcenterid  ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                $costcenterp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                if ($this->params['filter_departments'] > 0) {
                    $dept = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptlp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                    $deptclass = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                }
        }else{
            $costcenter = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid . ",0) AND bll.user_departmentid = ". $USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid .",0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .",0)";
            $costcenterlp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid  IN (".$USER->open_costcenterid .", 0) AND bll.user_departmentid = ". $USER->open_departmentid." AND bll.departmentid IN (". $USER->open_departmentid .", 0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .", 0)";
            $costcenterclass = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0) AND bll.user_departmentid = ". $USER->open_departmentid." AND bll.departmentid IN (". $USER->open_departmentid .", 0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .", 0)";
            $costcenterp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0) AND bll.user_departmentid = ". $USER->open_departmentid." AND bll.departmentid IN (". $USER->open_departmentid .", 0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .", 0)";
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $dept = " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)" ;
            $deptlp = " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)";
            $deptclass = " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)" ;
            $deptp = " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)";
        }
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $contentprovideridsf= " AND bll.open_contentvendor IN ($contentproviderids) ";
        } 

        $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0; 
        $certification = isset($this->params['filter_certification']) ? implode(',', $this->params['filter_certification']) : 0;
        $certificationlevel = isset($this->params['filter_certificationlevel']) ? implode(',', $this->params['filter_certificationlevel']) : 0;
        $exam = isset($this->params['filter_exam']) ? implode(',', $this->params['filter_exam']) : 0;
        $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        $tagslist = array($learningtype, $certification, $certificationlevel, $exam, $solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
        if (array_sum($tagslist) > 0) {
            $tagslist = implode(',', $tagslist); 
            $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
            if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
                $learningtypef = " AND bll.learningformatid IN (".$tagcoursesql.")";
            } else {
                $learningtypef = " AND bll.learningformatid IN (0)";
            } 
        }        
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $timefilter = " AND bll.role_assign_timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate ";
            // $timefilterlp = " AND llp.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
            // $timefilterclass = " AND lc.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate "; 
            // $timefilterp = " AND lp.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";           
        }
        if (isset($this->search) && $this->search) {
            $fields = array("bll.username");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $searchfilter = " AND ($fields) ";
        }        
        $this->sql  = " SELECT COUNT(DISTINCT bll.id) AS enrolments
            FROM {block_ls_learningformats} bll
            WHERE 1 = 1   {$costcenter} {$dept} {$timefilter} {$searchfilter} ";
    }

    function select() {
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
            if ($this->params['filter_organization']>0) {
                $costcenter = " AND bll.user_costcenterid = " .$this->params['filter_organization'] ." AND bll.costcenterid IN (".$this->params['filter_organization'] . ",0)"; 
                $costcenterlp = " AND bll.user_costcenterid = " .$this->params['filter_organization'] ." AND llp.costcenter IN (".$this->params['filter_organization'] .", 0)";   
                $costcenterclass = " AND bll.user_costcenterid = " .$this->params['filter_organization']  ." AND bll.costcenterid IN (".$this->params['filter_organization'] .", 0)";
                $costcenterp = " AND bll.user_costcenterid = " .$this->params['filter_organization'] ." AND bll.costcenterid IN (".$this->params['filter_organization'] .", 0)";
            }
            if ($this->params['filter_departments'] > 0) {
                $dept = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                $deptlp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                $deptclass = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                $deptp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";                
            }
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
                $costcenter = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid . ",0)";
                $costcenterlp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";   
                $costcenterclass = " AND bll.user_costcenterid = " .$USER->open_costcenterid  ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                $costcenterp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                if ($this->params['filter_departments'] > 0) {
                    $dept = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptlp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                    $deptclass = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                }
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
                $costcenter = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid . ",0)";
                $costcenterlp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";   
                $costcenterclass = " AND bll.user_costcenterid = " .$USER->open_costcenterid  ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                $costcenterp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0)";
                if ($this->params['filter_departments'] > 0) {
                    $dept = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptlp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                    $deptclass = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)" ;
                    $deptp = " AND bll.user_departmentid = ".$this->params['filter_departments']." AND bll.departmentid IN (". $this->params['filter_departments'] .", 0)";
                }
        }else{
            $costcenter = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid . ",0) AND bll.user_departmentid = ". $USER->open_departmentid ." AND bll.departmentid IN (". $USER->open_departmentid .",0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .",0)";
            $costcenterlp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid  IN (".$USER->open_costcenterid .", 0) AND bll.user_departmentid = ". $USER->open_departmentid." AND bll.departmentid IN (". $USER->open_departmentid .", 0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .", 0)";
            $costcenterclass = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0) AND bll.user_departmentid = ". $USER->open_departmentid." AND bll.departmentid IN (". $USER->open_departmentid .", 0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .", 0)";
            $costcenterp = " AND bll.user_costcenterid = " .$USER->open_costcenterid ." AND bll.costcenterid IN (".$USER->open_costcenterid .", 0) AND bll.user_departmentid = ". $USER->open_departmentid." AND bll.departmentid IN (". $USER->open_departmentid .", 0) AND bll.user_subdepartment = ".$USER->open_subdepartment ." AND bll.subdepartment IN (" .$USER->open_subdepartment .", 0)";
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $dept .= " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)" ;
            $deptlp = " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)";
            $deptclass = " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)" ;
            $deptp = " AND bll.user_subdepartment = ".$this->params['filter_subdepartments']." AND bll.subdepartment IN (". $this->params['filter_subdepartments'] .", 0)";
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
             $timefilter = " AND bll.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
            // $timefilterlp = " AND llp.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
            // $timefilterclass = " AND lc.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate "; 
            // $timefilterp = " AND lp.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";           
        }        
        if (isset($this->search) && $this->search) {
            $fields = array("bll.username");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $searchfilter = " AND ($fields) ";
        }
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $contentprovideridsf = " AND bll.open_contentvendor IN ($contentproviderids) ";
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
                $learningtypef = " AND bll.learningformatid IN (".$tagcoursesql.")";
            } else {
                $learningtypef = " AND bll.learningformatid IN (0)";
            } 
        }          
        $this->sql = " SELECT bll.id AS totalcount, bll.username AS learner, (SELECT u.email FROM {user} u WHERE u.id = bll.userid) AS email, bll.name AS course, bll.learningformatid AS courseid, bll.moduletype AS type, bll.userid, bll.moduleid as moduleid 
                FROM {block_ls_learningformats} AS bll
                WHERE 1 = 1  {$costcenter} {$dept} {$timefilter} {$searchfilter} ";
      parent::select();
    }
    
    function from() {
        $this->sql .= " ";
    }

    function joins() {
      parent::joins();
    }

    function where() { 
        global $DB, $USER;
        $this->sql .= " ";
        parent::where();
    }

    function search() {
        // if (isset($this->search) && $this->search) {
        //     $fields = array("lcc.fullname");
        //     $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
        //     $fields .= " LIKE '%" . $this->search . "%' ";
        //     $this->sql .= " AND ($fields) ";
        // }
    }

    function filters() {
        global $DB, $USER;
    }
    public function get_rows($users) {
        return $users;
    }
    public function column_queries($column, $userid){

    }

}
