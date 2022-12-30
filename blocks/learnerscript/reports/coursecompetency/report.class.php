<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License AS published by
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
 * @author: <jahnavi@eabyas.in>
 * @date: 2020
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls as ls;
use block_learnerscript\report;

class report_coursecompetency extends reportbase implements report {
    /**
     * [__construct description]
     * @param [type] $report           [description]
     * @param [type] $reportproperties [description]
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->courselevel = true;
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $columns = ['competency', 'framework', 'activity', 'completedusers'];
        $this->columns = ['coursecompetency' => $columns];
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
        $this->filters = array('course', 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->orderable = array('competency', 'completedusers');
        $this->defaultcolumn = 'com.id';
        $this->excludedroles = array("'student'");
    }
    function init() {
        global $DB;
        /*if(!isset($this->params['filter_courses'])){
            $this->initial_basicparams('courses');
            $coursefilter = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($coursefilter);
        }*/
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams AS $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
    }

    function count() {
        $this->sql = "SELECT COUNT(DISTINCT com.id) ";
    }

    function select() {
        $courseid = $this->params['filter_course'];
        $this->sql = "SELECT DISTINCT com.id, com.shortname AS competency, comf.shortname AS framework, ccom.courseid, COUNT(ucom.id) AS completedusers ";
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {competency} com ";
    }

    function joins() {
        $this->sql .= " JOIN {competency_framework} comf ON comf.id = com.competencyframeworkid 
                        JOIN {competency_coursecomp} ccom ON ccom.competencyid = com.id 
                        JOIN {course} c ON c.id = ccom.courseid
                        LEFT JOIN {competency_usercompcourse} ucom ON ucom.competencyid = com.id AND ucom.courseid = ccom.courseid AND ucom.proficiency IS NOT NULL 
";
        parent::joins();
    }

    function where() {
        global $DB, $USER;
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : array();
        $this->sql .= " WHERE 1 = 1 ";
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        // $systemcontext = context_system::instance();
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
                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)){ 
                    $coursesql  = (new querylib)->getcourseslist($this->params['filter_organization'], $this->params['filter_departments'],$this->params['filter_subdepartments']);
                }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $categorycontext) && $ohs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->params['filter_departments'],$this->params['filter_subdepartments']);
                }else if(has_capability('local/costcenter:manage_owndepartments', $categorycontext) && $dhs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid,$this->params['filter_subdepartments']); 
                } else { 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid,$USER->open_subdepartment); 
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

        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND ccom.courseid IN ($this->rolewisecourses) ";
            }
        }
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)", "u.email");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        
        if ($this->params['filter_course'] > SITEID) {
            $courseid = $this->params['filter_course'];
            $this->sql .= " AND ccom.courseid IN ($courseid)";
        }
        if (!empty($this->params['filter_organization'])) {
            $costcenterids = $this->params['filter_organization'];
            $this->sql .= " AND c.open_costcenterid IN ($costcenterids) ";
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) { 
            $departmentids = $this->params['filter_departments'];
            $this->sql .= " AND c.open_departmentid IN ($departmentids) ";
        } 
        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) { 
            $subdepartmentids = $this->params['filter_subdepartments'];
            $this->sql .= " AND c.open_subdepartment IN ($subdepartmentids) ";
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
        
        // if ($this->ls_startdate > 0 && $this->ls_enddate) {
        //     $this->sql .= " AND ra.timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        // }
    }
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    public function get_rows($users = array()) {
        return $users;
    }
    // public function column_queries($columnname, $assignid, $courseid = null) {
    //     $where = " AND %placeholder% = $assignid";
    //     $filtercourseid = $this->params['filter_courses'];

    //     switch ($columnname) {
             
                      
    //         default:
    //             return false;
    //             break;
    //     }
    //     $query = str_replace('%placeholder%', $identy, $query);
    //     return $query;
    // }
}
