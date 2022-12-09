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
 * LearnerScript
 * A Moodle block for creating customizable reports
 * @package blocks
 * @subpackage learnerscript
 * @author: sreekanth<sreekanth@eabyas.in>
 * @date: 2017
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

defined('MOODLE_INTERNAL') || die();
class report_learnercoursesoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public $userid;

    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'filters', 'permissions', 'calcs', 'plot');
        $columns = ['coursename', 'totalactivities', 'completedactivities', 'inprogressactivities', 'grades', 'totaltimespent'];
        $this->columns = ['learnercoursesoverview' => $columns];
       
        
        if (isset($this->role) && $this->role == 'user') {
            $this->parent = true;
        } else {
            $this->parent = false;
        }          
        if ($this->loggedinuserrole != 'user' && $this->loggedinuserrole != 'dh') {
            $this->basicparams = [['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments'], ['name' => 'users']];
        } else if ($this->loggedinuserrole == 'dh') {
            $this->basicparams = array(['name' => 'subdepartments']);
        } else if($this->loggedinuserrole != 'user'){
            $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        }
        $this->filters = array('course', 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->orderable = array('totalactivities', 'completedactivities', 'inprogressactivities', 'coursename', 'totaltimespent');
        $this->defaultcolumn = 'c.id';
    }
    public function init() {
       // if($this->role != 'employee' && !isset($this->params['filter_users'])){
       //      $this->initial_basicparams('users');
       //      $fusers = array_keys($this->filterdata);
       //      $this->params['filter_users'] = array_shift($fusers);
       //  }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $this->courseid = isset($this->params['filter_course']) ? $this->params['filter_course'] : array();
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->params['userid'] = $userid;
    }

    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT c.id)";
    }

    public function select() {
        $this->sql = "SELECT DISTINCT c.id, c.fullname AS coursename";
        parent::select();
    }

    public function from() {
        $this->sql .= " FROM {user_enrolments} ue";
    }

    public function joins() {
        $this->sql .=  " JOIN {enrol} e ON ue.enrolid = e.id 
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 
                          JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 ";
        parent::joins();
    }

    public function where() { 
        global $DB, $USER;
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->sql .= " WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND e.courseid = c.id AND ra.userid = $userid AND c.visible = 1 AND c.open_learningformat !=2 ";
        if (!empty($conditionfinalelements)) {
            $conditions = implode(',', $conditionfinalelements);
            $this->sql .= " AND c.id IN (:conditions)";
            $this->params['conditions'] = $conditions;
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND ra.timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            }
        } 
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
        // if ($this->loggedinuserrole != 'dh') {
        //     if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
        //         $this->sql .= " ";
        //     }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
        //         $this->sql .= " AND c.open_costcenterid = :costcenterid ";
        //         $this->params['costcenterid'] = $USER->open_costcenterid;
        //     } else { 
        //         $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
        //         $this->params['costcenterid'] = $USER->open_costcenterid;
        //         $this->params['departmentid'] = $USER->open_departmentid;
        //     } 
        // } else {
        //     $this->sql .= " AND c.id IN ($this->courseslist) ";
        // }
        parent::where();
    }

    public function search() {
      if (isset($this->search) && $this->search) {
         $fields = array("c.fullname");
          $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
          $fields .= " LIKE '%" . $this->search . "%' ";
          $this->sql .= " AND ($fields) ";
      }
    }

    public function filters() {
        $filtercourses = isset($this->params['filter_course']) ? $this->params['filter_course'] : SITEID;
        $filtermodules = isset($this->params['filter_modules']) ? $this->params['filter_modules'] : 0;
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        if ($filtercourses > SITEID) {
            $filtercourses = $filtercourses;
            $this->sql .= " AND c.id IN ($filtercourses)";
        }
        if (empty($this->params['filter_status']) || $this->params['filter_status'] == 'all') {
            $this->sql .= " ";
        }
        if ($this->params['filter_status'] == 'completed') {
            $this->sql .= " AND c.id IN (SELECT course FROM {course_completions} WHERE userid = $userid
                                        AND timecompleted > 0)";
        }
        if ($this->params['filter_status'] == 'inprogress') {
            $this->sql .= " AND c.id NOT IN (SELECT course FROM {course_completions} WHERE userid = $userid
                                            AND timecompleted > 0)";
        } 
        if (!empty($this->params['filter_organization']) && $this->params['filter_organization'] > 0) {
            $costcenterids = $this->params['filter_organization'];
            $this->sql .= " AND u.open_costcenterid IN ($costcenterids) ";
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
            $departmentids = $this->params['filter_departments'];
            $this->sql .= " AND u.open_departmentid IN ($departmentids) ";
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
     * @param  array $courses Courses
     * @return array $reportarray courses information
     */
    public function get_rows($courses) {
        return $courses;
    }
    
    public function column_queries($columnname, $courseid) {
       global $DB; 
        $filteruserid = isset($this->params['filter_users']) ? $this->params['filter_users'] : $this->userid;
        $filtermoduleid = isset($this->params['filter_modules']) ? $this->params['filter_modules'] : 0;

        $where = " AND %placeholder% = $courseid";
        $concatsql = " ";
        if (!empty($filtermoduleid)) {
            $concatsql = " AND cm.module = $filtermoduleid";
        }
        switch ($columnname) {
            case 'totalactivities' : 
                $identy = 'cm.course';
                $query =  "SELECT COUNT(cm.id) AS totalactivities 
                              FROM {course_modules} AS cm
                             WHERE cm.visible = 1  $concatsql $where ";
            break;
            case 'completedactivities' :
                $identy = 'cm.course';
                $query =  "SELECT COUNT(DISTINCT cmc.coursemoduleid) AS completedactivities 
                               FROM {course_modules_completion} AS cmc
                               JOIN {course_modules} AS cm ON cm.id = cmc.coursemoduleid
                              WHERE cm.visible = 1  AND cmc.userid = $filteruserid AND cmc.completionstate > 0
                                    $concatsql $where ";

            break;
            case 'inprogressactivities' :
                $identy = 'cm.course';
                $query =  "SELECT COUNT(DISTINCT cm.id) AS inprogressactivities 
                               FROM {course_modules} AS cm
                              WHERE  cm.visible = 1 AND cm.id NOT IN (SELECT coursemoduleid
                                                    FROM {course_modules_completion}
                                                    WHERE userid = " . $filteruserid . "  AND completionstate > 0) $concatsql $where ";
            break;
            case 'grades' :
                $identy = 'gi.courseid';
                $modulename = $DB->get_field('modules', 'name', array('id' => $filtermoduleid));
                $gradesql = "SELECT  CONCAT(ROUND(SUM(gg.finalgrade), 2),' / ', ROUND(SUM(gi.grademax), 2)) AS grades 
                               FROM {grade_grades} gg
                               JOIN {grade_items} gi ON gi.id = gg.itemid
                              WHERE gg.userid = $filteruserid  $where ";
                if (!empty($filtermoduleid)) {
                    $gradesql .= " AND gi.itemmodule = '$modulename' AND gi.itemtype != 'course'";
                } else {
                    $gradesql .= " AND gi.itemtype = 'course'";
                }
                $query =  $gradesql;
            break;
            case 'totaltimespent' :
                $identy = 'blc.courseid';
                $query = " SELECT SUM(blc.timespent) AS totaltimespent 
                            FROM {block_ls_coursetimestats} blc 
                            WHERE blc.userid > 2 AND blc.userid = $filteruserid  $where ";
                break;

            default:
            return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
