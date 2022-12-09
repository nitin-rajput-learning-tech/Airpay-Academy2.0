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
 * @author: sreekanth
 * @date: 2017
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

class report_quizzes extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->columns = array('quizfield' => ['quizfield'] , 'quizzes' => array(
            'avggrade', 'grademax', 'gradepass', 'notattemptedusers', 'inprogressusers',
            'completedusers', 'noofcompletegradedfirstattempts','totalattempts',
            'totalnoofcompletegradedattempts', 'avggradeoffirstattempts',
            'avggradeofallattempts', 'avggradeofhighestgradedattempts', 'totaltimespent', 'numviews'));
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $this->courselevel = true;
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
        $this->filters = array('course', 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->orderable = array('avggrade', 'grademax', 'gradepass', 'notattemptedusers', 'inprogressusers','completedusers', 'noofcompletegradedfirstattempts','totalattempts',
            'totalnoofcompletegradedattempts', 'avggradeoffirstattempts',
            'avggradeofallattempts', 'avggradeofhighestgradedattempts', 'totaltimespent', 'name', 'course');
        $this->searchable = array('main.name', 'c.fullname', 'c.shortname');
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = array("'employee'");
    }
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT main.id)";
    }
    public function select() {
        $this->sql = "SELECT DISTINCT main.id, main.name AS name, c.id AS course, cm.id AS activityid ";
        parent::select();
    }
    public function from() {
        $this->sql .= " FROM {quiz} as main
                        JOIN {course_modules} as cm ON cm.instance = main.id
                        JOIN {modules} m ON cm.module = m.id AND m.name = 'quiz'
                        JOIN {course} c ON c.id = cm.course ";
    }
    public function joins() { 
        parent::joins();
    }
    public function where() { 
        $this->sql .= " WHERE c.visible = 1 AND cm.visible = 1 AND c.id <> :siteid AND m.name = 'quiz' ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND main.course IN ($this->rolewisecourses) ";
            } 
        }
         $this->params['siteid'] = SITEID; 
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
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->params['filter_departments'],$this->params['filter_subdepartments']);
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
        
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND cm.added BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
    }
    
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    public function get_rows($quizs = array()) {
        return $quizs;
    }
    public function column_queries($columnname, $quizid, $courseid = null) {
        global $DB, $USER;
        $employeeroleid = $DB->get_field('role', 'id', array('shortname' => 'employee'));

        if($courseid){
            $learnersql  = (new querylib)->get_learners('', $courseid);
        }else{
            $learnersql  = (new querylib)->get_learners('', '%courseid%');
        }

        $where = " AND %placeholder% = $quizid"; 
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
            $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid . " AND u.open_departmentid = " . $USER->open_departmentid . " AND u.open_subdepartment = " .$USER->open_subdepartment;
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
        $filtercourseids = isset($this->params['filter_course']) ? $this->params['filter_course'] : SITEID;

        switch ($columnname) {
            case 'grademax':
                $identy = 'q.id';
                $query = "SELECT ROUND(q.grade, 2) AS grademax 
                        FROM {quiz} q
                        WHERE 1 = 1 $where ";
            break;
            case 'gradepass':
                $identy = 'cm.instance';
                $query = "SELECT ROUND(gi.gradepass, 2) AS gradepass 
                        FROM {quiz} q
                        JOIN {course_modules} as cm ON cm.instance = q.id
                        JOIN {modules} m ON cm.module = m.id
                        JOIN {course} c ON c.id = cm.course
                        JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemmodule = 'quiz' AND gi.iteminstance = q.id  
                        WHERE m.name = 'quiz' AND cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1 $where ";
            break;
            case 'avggrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT ROUND(AVG(g.finalgrade), 2) AS avggrade 
                        FROM {grade_grades} g 
                        JOIN {grade_items} gi ON gi.id = g.itemid
                        WHERE g.finalgrade IS NOT NULL  
                        AND gi.itemmodule = 'quiz' $where ";
            break;
            case 'noofcompletegradedfirstattempts':
                $identy = 'quiza.quiz';
                $courseid = 'q.course';
                $query = "SELECT COUNT(*) AS noofcompletegradedfirstattempts 
                        FROM {quiz_attempts} quiza
                        JOIN  {quiz} q ON quiza.quiz = q.id 
                        JOIN {user} u ON u.id = quiza.userid
                        WHERE quiza.quiz = q.id AND quiza.preview = 0 
                        AND quiza.state = 'finished' AND (quiza.state = 'finished' AND NOT EXISTS ( SELECT 1 FROM {quiz_attempts} qa2 WHERE qa2.quiz = quiza.quiz AND qa2.userid = quiza.userid AND qa2.state = 'finished' AND qa2.attempt < quiza.attempt)) AND quiza.userid IN ($learnersql) AND quiza.sumgrades IS NOT NULL $where $concatsql $departmentsql ";
            break;
            case 'totalnoofcompletegradedattempts':
                $identy = 'quiza.quiz';
                $courseid = 'q.course';
                $query = "SELECT COUNT(*) AS totalnoofcompletegradedattempts 
                              FROM {quiz_attempts} quiza 
                              JOIN  {quiz} q ON quiza.quiz = q.id 
                              JOIN {user} u ON u.id = quiza.userid
                              WHERE quiza.quiz = q.id AND quiza.preview = 0 AND quiza.state = 'finished' AND quiza.userid IN ($learnersql) AND quiza.sumgrades IS NOT NULL $where $concatsql $departmentsql ";
            break;
            case 'avggradeofhighestgradedattempts':
                $identy = 'quiza.quiz';
                $courseid = 'q.course';
                $query = "SELECT CONCAT(IF(q.sumgrades > 0, 
                        ROUND(IF(AVG(quiza.sumgrades) > 0, AVG(quiza.sumgrades) * 100 / q.sumgrades, 0), 2), 0), '%') AS avggradeofhighestgradedattempts 
                        FROM {quiz_attempts} quiza
                        JOIN  {quiz} q ON quiza.quiz = q.id 
                        JOIN {user} u ON u.id = quiza.userid
                        WHERE quiza.quiz = q.id AND quiza.userid IN ($learnersql) AND quiza.preview = 0 AND quiza.state = 'finished' AND (quiza.state = 'finished' AND NOT EXISTS ( SELECT 1 FROM {quiz_attempts} qa2
                                WHERE qa2.quiz = quiza.quiz AND qa2.userid = quiza.userid AND qa2.state = 'finished'
                                AND ( COALESCE(qa2.sumgrades, 0) > COALESCE(quiza.sumgrades, 0) OR (COALESCE(qa2.sumgrades, 0) = COALESCE(quiza.sumgrades, 0) AND qa2.attempt < quiza.attempt) ))) AND quiza.sumgrades IS NOT NULL $where $concatsql $departmentsql ";
            break;
            case 'avggradeoffirstattempts':
                $identy = 'quiza.quiz';
                $courseid = 'q.course';
                $query = "SELECT CONCAT(IF(q.sumgrades > 0, 
                        ROUND(IF(AVG(quiza.sumgrades) > 0, AVG(quiza.sumgrades) * 100 / q.sumgrades, 0), 2), 0), '%') AS avggradeoffirstattempts 
                        FROM {quiz_attempts} quiza
                        JOIN  {quiz} q ON quiza.quiz = q.id 
                        JOIN {user} u ON u.id = quiza.userid
                        WHERE quiza.quiz = q.id
                              AND quiza.preview = 0 AND quiza.userid IN ($learnersql) AND quiza.state = 'finished' AND (quiza.state = 'finished' AND NOT EXISTS ( SELECT 1 FROM {quiz_attempts} qa2 
                                  WHERE qa2.quiz = quiza.quiz AND qa2.userid = quiza.userid AND qa2.state = 'finished' AND qa2.attempt < quiza.attempt)) AND quiza.sumgrades IS NOT NULL $where $concatsql $departmentsql ";
            break;
            case 'avggradeofallattempts':
                $identy = 'quiza.quiz';
                $courseid = 'q.course';
                $query = "SELECT CONCAT(IF(q.sumgrades > 0, 
                        ROUND(IF(AVG(quiza.sumgrades) > 0, AVG(quiza.sumgrades) * 100 / q.sumgrades, 0), 2), 0), '%') AS avggradeofallattempts 
                              FROM {quiz_attempts} quiza
                              JOIN  {quiz} q ON quiza.quiz = q.id 
                              JOIN {user} u ON u.id = quiza.userid
                             WHERE quiza.quiz = q.id AND quiza.userid IN ($learnersql) AND quiza.preview = 0 AND quiza.state = 'finished' 
                               AND quiza.sumgrades IS NOT NULL $where $concatsql $departmentsql ";
            break;
            case 'inprogressusers':
                $identy = 'qat.quiz';
                $courseid = 'q.course';
                $query = "SELECT COUNT(DISTINCT qat.userid) AS inprogressusers 
                              FROM {quiz_attempts} qat
                              JOIN {quiz} q ON qat.quiz = q.id 
                              JOIN {user} u ON qat.userid = u.id
                             WHERE qat.state = 'inprogress' AND qat.quiz = q.id AND u.deleted = 0 
                               AND u.confirmed = 1 AND qat.userid IN ($learnersql) AND u.suspended = 0 $where $concatsql $departmentsql ";
            break;
            case 'completedusers':
                $identy = 'cmo.instance';
                $courseid = 'cmo.course';
                $query = "SELECT COUNT(DISTINCT cmc.userid) AS completedusers 
                            FROM {course_modules_completion} AS cmc
                            JOIN {course_modules} as cmo ON cmo.id = cmc.coursemoduleid
                            JOIN {modules} m ON m.id = cmo.module AND m.name= 'quiz'
                            JOIN {context} con ON con.instanceid = cmo.course
                            JOIN {role_assignments} ra ON ra.contextid = con.id
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee' 
                            JOIN {user} u ON u.id = cmc.userid
                           WHERE ra.userid = cmc.userid AND cmc.completionstate > 0
                             AND cmo.visible = 1 AND cmc.userid != 2 AND cmc.userid IN ($learnersql) $where $concatsql $departmentsql ";
            break; 
            case 'totalattempts':
                $identy = 'qat.quiz';
                $courseid = 'q.course';
                $query = "SELECT COUNT(DISTINCT qat.userid) AS totalattempts 
                              FROM {quiz_attempts} qat
                              JOIN {quiz} q ON qat.quiz = q.id 
                              JOIN {user} u ON qat.userid = u.id
                             WHERE qat.state = 'finished' AND qat.quiz = q.id AND u.deleted = 0 
                               AND u.confirmed = 1 AND qat.userid IN ($learnersql) AND u.suspended = 0 $where $concatsql $departmentsql ";
            break;
            case 'notattemptedusers':
                $identy = 'q.id';
                $courseid = 'cm.course';
                $query = "SELECT COUNT(DISTINCT u.id) AS notattemptedusers 
                            FROM {user} u
                            JOIN {user_enrolments} ue on ue.userid = u.id AND ue.status = 0
                            JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                            JOIN {role_assignments} ra ON ra.userid = ue.userid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                            JOIN {context} con ON con.id = ra.contextid AND con.contextlevel = 50
                            JOIN {course} c ON c.id = con.instanceid 
                            JOIN {course_modules} as cm ON cm.course = c.id
                            JOIN {modules} m ON m.id = cm.module AND m.name= 'quiz'
                            JOIN {quiz} q ON cm.instance = q.id 
                           WHERE  u.id NOT IN ( SELECT qat.userid
                                                  FROM {quiz_attempts} qat
                                                  JOIN {quiz} q1 ON qat.quiz = q1.id 
                                                 WHERE q1.id = q.id $where)
                             AND u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0 AND ra.userid IN ($learnersql) $where $concatsql $departmentsql ";
            break;
            case 'totaltimespent':
                $identy = 'cm.instance';
                $courseid = 'mt.courseid';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent 
                            FROM {block_ls_modtimestats} mt 
                            JOIN {course_modules} cm ON cm.id = mt.activityid 
                            JOIN {modules} m ON m.id = cm.module 
                            JOIN {user} u ON u.id = mt.userid AND u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0
                            WHERE m.name = 'quiz' AND mt.userid IN ($learnersql) $where $concatsql $departmentsql ";
            break; 
            case 'numviews':
                $identy = 'cm.instance';
                $courseid = 'lsl.courseid';
                if($this->reporttype == 'table'){
                    $query = "  SELECT * FROM ((SELECT COUNT(DISTINCT lsl.userid) as distinctusers 
                                  FROM {logstore_standard_log} lsl 
                                  JOIN {user} u ON u.id = lsl.userid 
                                  JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                  JOIN {quiz} q ON q.id = cm.instance 
                                  JOIN {modules} m ON m.id = cm.module
                                 WHERE lsl.crud = 'r' AND lsl.contextlevel = 70  AND lsl.anonymous = 0 AND u.id IN ($learnersql)
                                   AND lsl.userid > 2  AND u.confirmed = 1 AND u.deleted = 0  AND lsl.anonymous = 0 AND m.name = 'quiz'
                                   $where $concatsql $departmentsql ) 
                                  AS c1,
                                   (SELECT COUNT('X') as numviews 
                                      FROM {logstore_standard_log} lsl 
                                      JOIN {user} u ON u.id = lsl.userid
                                     JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                     JOIN {quiz} q ON q.id = cm.instance 
                                     JOIN {modules} m ON m.id = cm.module
                                     WHERE  lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.userid > 2 AND u.id IN ($learnersql) AND lsl.anonymous = 0 AND u.confirmed = 1 AND u.deleted = 0 AND m.name = 'quiz' $where $concatsql $departmentsql ) AS c2)";
                }else{
                    $query = "  SELECT COUNT('X') as numviews 
                                  FROM {logstore_standard_log} lsl 
                                 JOIN {user} u ON u.id = lsl.userid
                                 JOIN {course_modules} cm ON lsl.contextinstanceid = cm.id
                                 JOIN {quiz} q ON q.id = cm.instance 
                                 JOIN {modules} m ON m.id = cm.module
                                 WHERE  lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.userid > 2 AND u.id IN ($learnersql) AND lsl.anonymous = 0 AND u.confirmed = 1 AND u.deleted = 0 AND m.name = 'quiz' $where $concatsql $departmentsql ";
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
