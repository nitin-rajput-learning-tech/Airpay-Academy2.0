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
 * @author: sreekanth<sreekanth@eabyas.in>
 * @date: 2017
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

class report_scorm extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $columns = ['noofattempts', 'highestgrade','avggrade','lowestgrade','noofcompletions','totaltimespent', 'numviews'];
        $this->columns = ['scormfield'=> ['scormfield'], 'scorm' => $columns];
        $this->courselevel = true;
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
        $this->filters = array('course', 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->parent = true;
        $this->orderable = array('noofattempts', 'highestgrade','avggrade','lowestgrade','noofcompletions','totaltimespent','name', 'course');
        $this->searchable = array('main.name', 'c.fullname');
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = array("'employee'");

    }

    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT main.id)";
    }

    public function select() {
        $this->sql = "SELECT DISTINCT main.id, main.name, main.course, cm.id AS activityid, cm.visible as status";
        parent::select();
    }

    public function from() {
        $this->sql .= " FROM {scorm} as main 
                        JOIN {course_modules} as cm ON cm.instance = main.id
                        JOIN {modules} m ON cm.module = m.id
                        JOIN {course} c ON c.id = cm.course ";
    }

    public function joins() { 
        parent::joins();
    }

    public function where() { 
        $this->sql .= " WHERE c.visible = 1 AND c.id <> :siteid AND m.name = 'scorm' AND cm.visible = 1 ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND cm.course IN ($this->rolewisecourses) ";
            }
        }
        $this->params['siteid'] = SITEID; 
        $systemcontext = context_system::instance();
        parent::where();
    }

    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $this->searchable);
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
            if ($this->loggedinuserrole != 'dh') {
                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
                    $coursesql  = (new querylib)->getcourseslist($this->params['filter_organization'], $this->params['filter_departments'],$this->params['filter_subdepartments']);
                }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->params['filter_departments'],$this->params['filter_subdepartments']);
                }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid,$this->params['filter_subdepartments']); 
                } else { 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid, $USER->open_subdepartment); 
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
        if (!empty($this->params['filter_course']) && $this->params['filter_course'] <> SITEID && !$this->scheduling) {
            $courseids = $this->params['filter_course'];
            $this->sql .= " AND main.course IN ($courseids) ";
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND cm.added BETWEEN $this->ls_startdate AND $this->ls_enddate ";
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

    public function groupby() {
        $this->sql .= " GROUP BY main.id";
    }
    
    /**
     * @param  array $activites Activites
     * @return array $reportarray Activities information
     */
    public function get_rows($scormsdata = array()) {
        return $scormsdata;
    }

    public function column_queries($columnname, $scormid, $courseid = null) {
        global $DB, $USER;
        if($courseid){
            $learnersql  = (new querylib)->get_learners('', $courseid);
        }else{
            $learnersql  = (new querylib)->get_learners('', '%courseid%');
        }
        $where = " AND %placeholder% = $scormid";

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
            $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid . " AND u.open_departmentid = " . $USER->open_departmentid ." AND u.open_subdepartment = " .$USER->open_subdepartment;
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
            case 'noofattempts' :
                $identy = 'sst.scormid';
                $courseid = 's.course';
                $query = "SELECT COUNT(sst.id) AS noofattempts 
                        FROM {scorm_scoes_track} sst 
                        JOIN {scorm} s ON s.id = sst.scormid 
                        WHERE sst.element = 'x.start.time' AND sst.userid > 2 
                        AND sst.userid IN ($learnersql) $where ";
                break;
            case 'noofcompletions' :
                $identy = 'cm.instance';
                $courseid = 'cm.course';
                $query = "SELECT COUNT(DISTINCT cmc.id) AS noofcompletions 
                            FROM {course_modules_completion} cmc 
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                            JOIN {modules} m ON m.id = cm.module
                            WHERE cmc.coursemoduleid = cm.id AND cmc.userid > 2 AND cmc.completionstate > 0 
                                AND cmc.userid IN ($learnersql) AND m.name = 'scorm' $where ";
                break;
            case 'highestgrade' :
                $identy = 'gi.iteminstance';
                $query = "SELECT ROUND(MAX(gg.finalgrade),2) AS highestgrade 
                            FROM {grade_grades} gg 
                            JOIN {grade_items} gi ON gi.id = gg.itemid 
                            WHERE gi.itemmodule = 'scorm' $where ";
                break;  
            case 'avggrade' :
                $identy = 'gi.iteminstance';
                $query = "SELECT ROUND(AVG(gg.finalgrade),2) AS avggrade 
                            FROM {grade_grades} gg 
                            JOIN {grade_items} gi ON gi.id = gg.itemid 
                            WHERE gi.itemmodule = 'scorm' $where ";
                break;

           case 'lowestgrade' :
                $identy = 'gi.iteminstance';
                $query = "SELECT ROUND(MIN(gg.finalgrade),2) AS lowestgrade 
                            FROM {grade_grades} gg 
                            JOIN {grade_items} gi ON gi.id = gg.itemid 
                            WHERE gi.itemmodule = 'scorm' $where ";
                break;
            case 'totaltimespent' :
                $identy = 'cm.instance';
                $courseid = 'mt.courseid';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent 
                            FROM {block_ls_modtimestats} as mt 
                            JOIN {course_modules} cm ON cm.id = mt.activityid
                            JOIN {modules} m ON m.id = cm.module
                            WHERE m.name = 'scorm' AND mt.userid IN ($learnersql) $where ";
                break;
            case 'numviews':
                $identy = 'cm.instance';
                $courseid = 'lsl.courseid';
                if($this->reporttype == 'table'){
                    $query = "  SELECT * FROM ((SELECT COUNT(DISTINCT lsl.userid) as distinctusers 
                                  FROM {logstore_standard_log} lsl 
                                  JOIN {user} u ON u.id = lsl.userid 
                                  JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                  JOIN {scorm} q ON q.id = cm.instance 
                                  JOIN {modules} m ON m.id = cm.module
                                 WHERE lsl.crud = 'r' AND lsl.contextlevel = 70  AND lsl.anonymous = 0 AND u.id IN ($learnersql)
                                   AND lsl.userid > 2  AND u.confirmed = 1 AND u.deleted = 0  AND lsl.anonymous = 0 AND m.name = 'scorm'
                                   $where $departmentsql ) 
                                  AS c1,
                                   (SELECT COUNT('X') as numviews 
                                      FROM {logstore_standard_log} lsl 
                                      JOIN {user} u ON u.id = lsl.userid
                                     JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                     JOIN {scorm} q ON q.id = cm.instance 
                                     JOIN {modules} m ON m.id = cm.module
                                     WHERE  lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.userid > 2 AND u.id IN ($learnersql) AND lsl.anonymous = 0 AND u.confirmed = 1 AND u.deleted = 0 AND m.name = 'scorm' $where $departmentsql ) AS c2)";
                }else{
                    $query = "  SELECT COUNT('X') as numviews 
                                  FROM {logstore_standard_log} lsl 
                                 JOIN {user} u ON u.id = lsl.userid
                                 JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                 JOIN {scorm} q ON q.id = cm.instance 
                                 JOIN {modules} m ON m.id = cm.module
                                 WHERE  lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.userid > 2 AND u.id IN ($learnersql) AND lsl.anonymous = 0 AND u.confirmed = 1 AND u.deleted = 0 AND m.name = 'scorm' $where $departmentsql ";
                } 
            break;   
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        $query = str_replace('%courseid%', $courseid, $query);
        return $query;
    }
}
