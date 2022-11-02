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
 * @author: Arun Kumar M
 * @date: 2017
 */
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;

defined('MOODLE_INTERNAL') || die();
class report_courses extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report, $reportproperties);
        $columns = ['enrolments', 'completed', 'activities', 'progress', 'avggrade',
                    'highgrade', 'lowgrade', 'badges', 'totaltimespent', 'numviews'];
        $this->columns = ['coursefield' => ['coursefield'] ,
                          'coursescolumns' => $columns];

        $coursecolumns = $DB->get_columns('course');
        $usercolumns = $DB->get_columns('user');
        $this->conditions = ['courses' => array_keys($coursecolumns),
                             'user' => array_keys($usercolumns)];

        $this->components = array('columns', 'conditions', 'ordering', 'filters','permissions', 'plot');
        $this->filters = array(/*'coursecategories',*/ /*'courses'*/);
        $this->parent = true;
        $this->orderable = array('enrolments', 'completed', 'activities', 'avggrade','progress',
                                'highgrade', 'lowgrade', 'badges', 'totaltimespent', 'fullname');

        $this->searchable = array('main.fullname', 'cat.name');
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = array("'student'");
    }

    public function init() {
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        } 
        $this->categoriesid = isset($this->params['filter_coursecategories']) ? $this->params['filter_coursecategories'] : 0; 
        
    }
    public function count() {
       $this->sql = "SELECT count(main.id)";

    }

    public function select() {
      $this->sql = "SELECT main.id, main.*, main.id AS course ";
      parent::select();
    }

    public function from() {
      $this->sql .= " FROM {course} as main JOIN {course_categories} as cat ON main.category = cat.id";
    }
    public function joins() {
      parent::joins();
    }

    public function where() {
	global $DB;
       $this->sql .= " WHERE main.visible = 1 AND main.id <> :siteid ";
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
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND main.id IN ($this->rolewisecourses) ";
            } 
        }
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
        if (!empty($this->params['filter_courses'])) {
            $courseids = $this->params['filter_courses'];
            $this->sql .= " AND main.id IN ($courseids) ";
        }
        if (!empty($this->params['filter_coursecategories'])) {
            $categoryids = $this->params['filter_coursecategories'];
            $this->sql .= " AND main.category IN ($categoryids) ";
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND main.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return array(array(), 0);
            }
            $this->sql .= " AND main.id IN ( $conditions )";
        }
    }


    public function groupby() {
        $this->sql .= " GROUP BY main.id";
    }

    public function get_rows($courses) {
        return $courses;
    }

    public function column_queries($columnname, $courseid, $courses = null) { 
        if($courses){
            $learnersql  = (new querylib)->get_learners('', $courses);
        }else{
            $learnersql  = (new querylib)->get_learners('', '%courseid%');
        }
        $where = " AND %placeholder% = $courseid";
        switch ($columnname) {
            case 'progress':
                $identy = 'ct.instanceid';
                $query = "SELECT ROUND((c1.completed / c2.enrolled) * 100, 2) AS progress FROM ((SELECT COUNT(DISTINCT cc.userid) AS completed 
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND ue.status = 0
                                     JOIN {role_assignments} ra ON ra.userid = ue.userid
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                                     JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0
                                     JOIN {course_completions} as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid
                                    where 1 = 1 AND ra.userid IN ($learnersql) AND cc.course = e.courseid $where ) 
                          AS c1,
                       (SELECT COUNT(DISTINCT ue.userid) AS enrolled 
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND ue.status = 0
                                     JOIN {role_assignments} ra ON ra.userid = ue.userid
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                                     JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0
                                    where 1 = 1 AND ra.userid IN ($learnersql) $where ) AS c2 )";
                break;
            case 'activities':
                $identy = 'course';
                $query  = "SELECT COUNT(id) as activities  FROM {course_modules} where 1 = 1 AND visible = 1 $where ";
            break;
            case 'enrolments':
                $identy = 'ct.instanceid';
                $query  = "SELECT COUNT(DISTINCT ue.userid) AS enrolled 
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND ue.status = 0
                                     JOIN {role_assignments} ra ON ra.userid = ue.userid
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                                     JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0
                                    where 1 = 1 AND ra.userid IN ($learnersql) $where ";
            break;
            case 'completed':
                $identy = 'ct.instanceid';
                $query ="SELECT COUNT(DISTINCT cc.userid) AS completed 
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND ue.status = 0
                                     JOIN {role_assignments} ra ON ra.userid = ue.userid
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'student'
                                     JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0
                                     JOIN {course_completions} as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid
                                    where 1 = 1 AND ra.userid IN ($learnersql) AND cc.course = e.courseid $where "; 
            break;
            case 'highgrade':
                $identy = 'gi.courseid';
                $query = "SELECT  ROUND(MAX(finalgrade),2) as highgrade 
                          FROM {grade_grades} g  
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id 
                         WHERE g.finalgrade IS NOT NULL AND g.userid IN ($learnersql) $where ";
            break;
            case 'lowgrade':
                $identy = 'gi.courseid';
                $query = "SELECT  ROUND(MIN(finalgrade),2) as lowgrade 
                          FROM {grade_grades} g  
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id 
                         WHERE g.finalgrade IS NOT NULL AND g.userid IN ($learnersql) $where ";
            break;
            case 'avggrade':
                $identy = 'gi.courseid';
                $query = "SELECT  ROUND(AVG(finalgrade),2) as avggrade 
                          FROM {grade_grades} g 
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id 
                         WHERE g.finalgrade IS NOT NULL AND g.userid IN ($learnersql) $where ";
            break;
            case 'badges':
                $identy = 'b.courseid';
                $query = "SELECT COUNT(b.id) AS badges  FROM {badge} b WHERE b.status != 0  AND b.status != 2 $where ";
            break;
            case 'totaltimespent':
                $identy = 'bt.courseid';
                $query = "SELECT SUM(bt.timespent) AS totaltimespent  from {block_ls_coursetimestats} AS bt 
                           WHERE 1 = 1 AND bt.userid IN ($learnersql) $where ";
            break;
            case 'numviews':
            $identy = 'lsl.courseid';
            if($this->reporttype == 'table'){
                $query = "SELECT c2.numviews,c1.distinctusers FROM ((SELECT COUNT(DISTINCT lsl.userid) as distinctusers 
                          FROM {logstore_standard_log} lsl 
                          JOIN {user} u ON u.id = lsl.userid 
                         WHERE lsl.crud = 'r' AND lsl.anonymous = 0
                           AND lsl.userid > 2 AND lsl.userid IN ($learnersql) AND u.confirmed = 1 AND u.deleted = 0 $where ) 
                          AS c1,
                       (SELECT COUNT('X') as numviews 
                          FROM {logstore_standard_log} lsl 
                          JOIN {user} u ON u.id = lsl.userid
                         WHERE  lsl.crud = 'r'
                           AND lsl.anonymous = 0 AND lsl.userid > 2
                           AND u.confirmed = 1 AND lsl.userid IN ($learnersql) AND u.deleted = 0 $where ) AS c2 )";
            }else{
                $query = "SELECT COUNT('X') as numviews 
                          FROM {logstore_standard_log} lsl 
                          JOIN {user} u ON u.id = lsl.userid
                         WHERE  lsl.crud = 'r'
                           AND lsl.anonymous = 0 AND lsl.userid > 2
                           AND u.confirmed = 1 AND lsl.userid IN ($learnersql) AND u.deleted = 0 $where ";
            }
            break;

            default:
            return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        $query = str_replace('%courseid%', $identy, $query);
        return $query;
    }
}