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
 * @author: Arun Kumar <arun@eabyas.in>
 * @date: 2017
 */

use block_learnerscript\local\reportbase;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;

class report_myscorm extends reportbase {

    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->columns = array('myscormcolumns' => array('course', 'scormname', 'attempt' , 'activitystate', 'finalgrade','firstaccess', 'lastaccess', 'totaltimespent', 'numviews'));
        $this->parent = true;
        if (isset($this->role) && $this->role == 'user') {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        if (is_siteadmin() || $this->loggedinuserrole != 'user') {
            $this->basicparams = [['name' => 'users']];
        }
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->courselevel = false;
        $this->filters = array('course', 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->orderable = array('course','scormname','activitystate','attempt','finalgrade','totaltimespent','numviews');
        $this->defaultcolumn = 's.id';
	}
  function init() {
       /* if($this->role != 'user' && !isset($this->params['filter_users'])){
            $this->initial_basicparams('users');
            $fusers = array_keys($this->filterdata);
            $this->params['filter_users'] = array_shift($fusers);
        }*/
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
          $basicparams = array_column($this->basicparams, 'name');
          foreach ($basicparams as $basicparam) {
            if (empty($this->params['filter_' . $basicparam])) {
              return false;
            }
          }
        }
  }
  function count() {
    $this->sql .= "SELECT COUNT(DISTINCT s.id) ";
  }

  function select() {
        $userid = isset($this->params['userid']) ? $this->params['userid'] : 0;
        $this->sql = "SELECT DISTINCT s.id, c.id AS courseid, ra.userid AS userid, cm.id as cmid,
                                           m.id as moduleid, st.scormid as scormid, c.fullname AS course, s.name AS scormname"; 

        parent::select();

  }

  function from() {
    $this->sql .= " FROM {role_assignments} AS ra";
  }

  function joins() {
    $this->sql .= " JOIN {role} as r on r.id=ra.roleid AND r.shortname='employee'
                   JOIN {context} AS ctx ON ctx.id = ra.contextid
                   JOIN {course} as c ON c.id = ctx.instanceid
                   JOIN {enrol} e ON e.courseid = c.id AND e.status = 0 
                   JOIN {user_enrolments} ue ON ue.userid = ra.userid AND ue.enrolid = e.id AND ue.status = 0 
                   JOIN {scorm} AS s ON s.course = c.id
                   JOIN {course_modules} AS cm ON cm.instance = s.id
                   JOIN {modules} AS m ON m.id = cm.module
              LEFT JOIN {scorm_scoes_track} AS st ON st.scormid = s.id
                   JOIN {scorm_scoes} ss ON ss.scorm = s.id";

    parent::joins();
  }

    function where() {
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $coursesql  = (new querylib)->get_learners('','s.course');
        $this->sql .=" WHERE ra.userid = $userid  AND cm.visible = 1 AND
                                  cm.deletioninprogress = 0 AND c.visible = 1 AND m.name = 'scorm' ";
        
        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            }
        }
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("c.fullname", "s.name");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->courseid = isset($this->params['filter_course']) ? $this->params['filter_course'] : array();
        $this->params['userid'] = $userid;

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
        
        if($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND ra.timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
        if (!empty($this->courseid) && $this->courseid != '_qf__force_multiselect_submission') {
          $courseid = $this->courseid;
          $this->sql .= " AND c.id = $courseid";
        }
    }
	public function get_rows($elements) {
		return $elements;
	}
    public function column_queries($columnname, $scormid) {
        $where = " AND %placeholder% = $scormid";
        $userid = isset($this->params['userid']) ? $this->params['userid'] : 0;
        switch ($columnname) {
            case 'attempt':
                $identy = 'scormid';
                $query = "SELECT attempt AS attempt  
                            FROM {scorm_scoes_track} WHERE 1 = 1 $where 
                             AND userid = $userid ORDER BY id DESC LIMIT 0,1  ";
                break;
            case 'activitystate':
                $identy = 'scormid';
                $query = "SELECT value AS activitystate FROM {scorm_scoes_track} 
                           WHERE element = 'cmi.core.lesson_status'
                             AND userid = $userid $where ORDER BY id DESC LIMIT 0,1  ";
            break;
            case 'finalgrade':
                $identy = 'gi.iteminstance';
                $query = "SELECT ROUND(gg.finalgrade, 2) AS finalgrade 
                            FROM {grade_grades} gg JOIN {grade_items} gi ON gi.id = gg.itemid 
                            WHERE gi.itemmodule = 'scorm' AND gg.userid = $userid $where";
            break;
            case 'firstaccess':
                $identy = 'scormid';
                $query = "SELECT value AS firstaccess FROM {scorm_scoes_track} 
                           WHERE element = 'x.start.time' 
                             AND userid = $userid $where ORDER BY attempt ASC LIMIT 0, 1 ";
            break;
            case 'totaltimespent':
                $identy = 'cm.instance';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent 
                            FROM {block_ls_modtimestats} as mt 
                            JOIN {course_modules} cm ON cm.id = mt.activityid 
                            JOIN {modules} m ON m.id = cm.module 
                            WHERE m.name = 'scorm' AND mt.userid = $userid $where";
            break;
            case 'numviews':
                $identy = 'cm.instance';
                $query = "SELECT COUNT(lsl.id) AS numviews 
                              FROM {logstore_standard_log} lsl 
                              JOIN {course_modules} cm ON cm.id = lsl.contextinstanceid  
                              JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                              JOIN {user} u ON u.id = lsl.userid AND u.confirmed = 1 AND u.deleted = 0
                             WHERE lsl.crud = 'r' AND lsl.contextlevel = 70 AND lsl.anonymous = 0 
                               AND lsl.userid = $userid $where ";
            break;
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }

}
