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

/** LearnerScript
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: sreekanth
 * @date: 2017
 */
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;

class report_resources extends reportbase implements report {

    private $resourcenames;

    private $resourceslist;

    private $aliases = [];
	/**
	 * [__construct description]
	 * @param [type] $report           [description]
	 * @param [type] $reportproperties [description]
	 */
	public function __construct($report, $reportproperties = false) {
		parent::__construct($report, $reportproperties);
		$this->parent = true;
		$this->components = array('columns', 'filters', 'permissions', 'plot');
		$resourcescolumns = array('activity','totaltimespent','numviews');
		$this->columns = ['activityfield' => ['activityfield'] ,'resourcescolumns' => $resourcescolumns];
		$this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
        $this->filters = array('course', 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->courselevel = true;
		$this->orderable = array('course','activity','totaltimespent');
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = array("'student'");
	}
    function init() {
        global $DB;
        $modules = $DB->get_fieldset_select('modules',  'name', '');
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $resourcearchetype = plugin_supports('mod', $modulename, FEATURE_MOD_ARCHETYPE);
            if($resourcearchetype){
                $this->aliases[] = $modulename;
                $resources[] = "'$modulename'";
                $fields1[] = "COALESCE($modulename.name,'')";
            }
        }
        $this->resourcenames = implode(',', $fields1);
        $this->resourceslist = implode(',', $resources);
        $this->params['siteid'] = SITEID;
        $this->params['target'] = 'course_module';
        $this->params['contextlevel'] = CONTEXT_MODULE;
        $this->params['action'] = 'viewed';
    }
    function count() {
        $this->sql = "SELECT COUNT(main.id) ";
    }

    function select() {
        $this->sql = "SELECT main.id, c.id AS course, 
                        c.fullname AS courseid, m.name AS moduletype, m.id AS module, 
                        CONCAT($this->resourcenames) AS activity, main.visible as status";
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course_modules} AS main";
    }

    function joins() {
        $this->sql .= " JOIN {modules} AS m ON m.id = main.module
                       JOIN {course} AS c ON c.id = main.course ";
        foreach ($this->aliases as $alias) {
            $this->sql .= " LEFT JOIN {".$alias."} AS $alias ON $alias.id = main.instance AND m.name = '$alias'";
        }
        parent::joins();
    }

    function where() { 
        $this->sql .= " WHERE c.visible = 1 AND c.id <> :siteid AND main.deletioninprogress = 0 AND m.name IN ($this->resourceslist) AND main.visible = 1 ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND main.course IN ($this->rolewisecourses) ";
            }
        } 
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND main.added BETWEEN $this->ls_startdate AND $this->ls_enddate ";
            $this->params['ls_startdate'] = $this->ls_startdate;
            $this->params['ls_enddate'] = $this->ls_enddate;
        }
        parent::where();
    }

    function search() {
        global $DB;
        $modules = $DB->get_fieldset_select('modules',  'name', '');
        $this->aliases1 = [];
        foreach ($modules as $modulename) {
            $resourcearchetype = plugin_supports('mod', $modulename, FEATURE_MOD_ARCHETYPE);
            if($resourcearchetype){
                $this->aliases1[] = $modulename;
                $resources[] = "'$modulename'";
                $fields2[] = "COALESCE($modulename.name,'')";
            }
        }
        if (isset($this->search) && $this->search) {
            $fields2[] = array_push($fields2,"c.fullname");
            $fields = implode(" LIKE '%$this->search%' OR ", $fields2);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() { 
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
            if ($this->loggedinuserrole != 'dh') {
                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
                    $coursesql  = (new querylib)->getcourseslist($this->params['filter_organization'], $this->params['filter_departments'],$this->params['filter_subdepartments']);
                }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->params['filter_departments'],$this->params['filter_subdepartments']);
                }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->params['filter_departments'],$this->params['filter_subdepartments']);
                } else { 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid,$USER->subdepartment); 
                } 
                if (!empty($coursesql)) { 
                    $this->sql .= " AND c.id IN (".$coursesql.")";
                } else {
                    $this->sql .= " AND c.id IN (0)";
                }
            } else {
                $this->sql .= " AND c.id IN ($this->courseslist)";
            } 
        } else {
            $coursesql  = (new querylib)->getcourseslist($this->params['filter_organization'], $this->params['filter_departments'],$this->params['filter_subdepartments']); 
            if (!empty($coursesql)) { 
                $this->sql .= " AND c.id IN (".$coursesql.")";
            } else {
                $this->sql .= " AND c.id IN (0)";
            }
        }
        if (!empty($this->params['filter_course']) && $this->params['filter_course'] <> SITEID  && !$this->scheduling) {
            $courseids = $this->params['filter_course'];
            $this->sql .= " AND c.id IN ($courseids) ";
        } 
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $this->sql .= " AND c.open_contentvendor IN ($contentproviderids) ";
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
                $this->sql .= " AND c.id IN (".$tagcoursesql.")";
            } else {
                $this->sql .= " AND c.id IN (0)";
            } 
        }
    }
	/**
	 * [get_rows description]
	 * @param  [type] $elements [description]
	 * @return [type]           [description]
	 */
	function get_rows($elements) {
		global $CFG, $OUTPUT, $DB;

		return $elements;
	}
	public function column_queries($columnname, $coursemoduleid, $courses = null) {
        global $DB, $USER;
        if($courses){
            $learnersql  = (new querylib)->get_learners('', $courses);
        }else{
            $learnersql  = (new querylib)->get_learners('', '%courses%');
        }
        $where = " AND %placeholder% = $coursemoduleid";
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
        $concatsql = " ";
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
            $concatsql .= " ";
        } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {
            $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {
             $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid . " AND u.open_departmentid = " . $USER->open_departmentid;
        } else {
            $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid . " AND u.open_departmentid = " . $USER->open_departmentid ." AND u.open_subdepartment = ". $USER->open_subdepartment;
        } 
        $departmentsql = " "; 
        if (!empty($this->params['filter_subdepartments'])  && $this->params['filter_subdepartments'] > 0) {
            $departmentsql .= " AND u.open_subdepartment = " . $this->params['filter_subdepartments'];
        } 
        if (!empty($this->params['filter_departments'])  && $this->params['filter_departments'] > 0) {
            $departmentsql .= " AND u.open_departmentid = " . $this->params['filter_departments'];
        } 
        if (!empty($this->params['filter_organization'])) {
            $costcenterids = $this->params['filter_organization'];
            $departmentsql .= " AND u.open_costcenterid IN ($costcenterids) ";
        }
        switch ($columnname) {
            case 'totaltimespent':
                $identy = 'cm.id';
                $courses = 'mt.courseid';
                $query =  "SELECT SUM(mt.timespent) 
                             FROM {block_ls_modtimestats} mt 
                             JOIN {course_modules} cm ON cm.id = mt.activityid 
                             WHERE 1 = 1 AND mt.userid IN ($learnersql) 
                            $where ";
            break;
            case 'numviews':
                $identy = 'lsl.contextinstanceid';
                $courses = 'lsl.courseid';
                if($this->reporttype == 'table'){
                    $query = "  SELECT * FROM ((SELECT COUNT(DISTINCT lsl.userid) as distinctusers 
                                  FROM {logstore_standard_log} lsl 
                                  JOIN {user} u ON u.id = lsl.userid 
                                  JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                 WHERE lsl.crud = 'r' AND lsl.contextlevel = 70  AND lsl.anonymous = 0 AND u.id IN ($learnersql)
                                   AND lsl.userid > 2  AND u.confirmed = 1 AND u.deleted = 0  AND lsl.anonymous = 0 
                                   $where $departmentsql ) 
                                  AS c1,
                                   (SELECT COUNT('X') as numviews 
                                      FROM {logstore_standard_log} lsl 
                                      JOIN {user} u ON u.id = lsl.userid
                                     JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                     WHERE  lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.userid > 2 AND u.id IN ($learnersql) AND lsl.anonymous = 0 AND u.confirmed = 1 AND u.deleted = 0  $where $departmentsql ) AS c2)";
                }else{
                    $query = "  SELECT COUNT('X') as numviews 
                                  FROM {logstore_standard_log} lsl 
                                  JOIN {user} u ON u.id = lsl.userid
                                 JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                 WHERE  lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.userid > 2 AND u.id IN ($learnersql) AND lsl.anonymous = 0 AND u.confirmed = 1 AND u.deleted = 0  $where $departmentsql ";
                }

            break;
            default:
                return false;
            break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        $query = str_replace('%courses%', $courses, $query);
        return $query;
    }
}
