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

class report_learningpaths extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'permissions','orderable','plot');
        $this->columns = ['learningpathfield'=>['learningpathfield'], 'learningpathscolumns'=> ['learner','learningplan','enrolmentdate','progress', 'completiondeadline','completiondate', 'upcomingdeadline', 'overduedeadline']];    
        $this->parent = true;
        // $this->filters = array('learningpath');
        $this->orderable = array('learner','learningplan','enrolmentdate','progress', 'completiondeadline','completiondate', 'upcomingdeadline', 'overduedeadline');
        $this->defaultcolumn = 'bll.id';
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->filters = array('learningpath', 'contentprovider', 'learningtype',  'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');        
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
        $this->sql  = "SELECT bll.id, bll.userid, bll.learningformatid as planid, bll.username AS learner, bll.name AS learningplan , bll.role_assign_timemodified AS enrolmentdate, bll.completiondate AS completiondate ";
         parent::select();                
    }
    function from() {
        $this->sql .= " FROM {block_ls_learningformats} AS bll";
    }
    function joins() {
          parent::joins();
    }
    function where(){
         global $USER, $DB;
         $this->sql .= " WHERE bll.moduleid = 8";
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
                    $this->sql .= " AND bll.costcenterid IN (" .$this->params['filter_organization'].", 0) AND bll.user_costcenterid=".$this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND bll.departmentid IN (".$this->params['filter_departments'].", 0) AND bll.user_departmentid=".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid.", 0) AND bll.user_costcenterid=".$USER->open_costcenterid;
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND bll.departmentid IN (".$this->params['filter_departments'].", 0) AND bll.user_departmentid=".$this->params['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0)  AND bll.user_costcenterid=".$USER->open_costcenterid." AND bll.user_departmentid = ". $USER->open_departmentid ." AND bll.departmentid IN (".$USER->open_departmentid.", 0)" ;
            } else {
                $this->sql .= " AND bll.costcenterid IN (" .$USER->open_costcenterid .", 0)  AND bll.user_costcenterid=".$USER->open_costcenterid." AND bll.user_departmentid = ". $USER->open_departmentid ." AND bll.departmentid IN (".$USER->open_departmentid.", 0) AND bll.subdepartment IN (" .$USER->open_subdepartment .",0) AND bll.user_departmentid =".$USER->open_subdepartment ;
            }
            if ($this->params['filter_subdepartments'] > 0) {
                $this->sql .= " AND bll.subdepartment IN (".$this->params['filter_subdepartments'].", 0) AND bll.user_subdepartment=".$this->params['filter_subdepartments'];
            } 
        }        
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND bll.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
        if ($this->params['filter_learningpath'] > 0) {
            $lplan = $this->params['filter_learningpath'];
            $this->sql .= " AND bll.learningformatid = $lplan ";
        }
        if(isset($this->params['filter_user'])) {
            $this->sql .= " AND bll.userid = ". $this->params['filter_user'];
        }
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $this->sql .= " AND bll.open_contentvendor IN ($contentproviderids) ";
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
                $this->sql .= " AND bll.learningformatid IN (".$tagcoursesql.")";
            } else {
                $this->sql .= " AND bll.learningformatid IN (0)";
            } 
        }        
        if(!empty($this->params['filter_status'])) {
            if($this->params['filter_status'] == 'completed') {
                $this->sql .= " AND bll.completiondate > 0 ";
            } else if ($this->params['filter_status'] == 'inprogress') {
                $this->sql .= " AND bll.completiondate = 0 ";
            } else if ($this->params['filter_status'] == 'upcoming') {
                $this->sql .= " AND bll.upcomingdeadline > UNIX_TIMESTAMP() AND bll.completiondate = 0 AND bll.upcomingdeadline != 0 ";
            } else if ($this->params['filter_status'] == 'overdue') {
                $this->sql .= " AND bll.upcomingdeadline < UNIX_TIMESTAMP() AND bll.completiondate = 0 AND bll.upcomingdeadline != 0 ";
            }
        }
    }
    public function get_rows($learningpaths) {
        return $learningpaths;
    }
}
