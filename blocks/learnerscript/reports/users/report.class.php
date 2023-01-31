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

class report_users extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'ordering', 'permissions', 'filters', 'plot');
        $this->parent = true;
        $this->columns = array('userfield' => array('userfield'), 'usercolumns' => array('enrolled', 'inprogress',
            'completed', 'grade', 'badges', 'progress', 'status', 'upcomingdeadline', 'overduedeadline'));
        $this->orderable = array('fullname', 'email', 'enrolled'/*, 'inprogress', 'completed','grade','progress',
                            'badges', 'upcomingdeadline', 'overduedeadline'*/); 
        // if ($this->loggedinuserrole != 'dh') {
        //     $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        // }
        $this->filters = array('organization', 'departments','subdepartments', 'level4department', 'level5department', 'geostate', 'geodistrict', 'geosubdistrict', 'geovillage', 'users'/*, 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole', 'country'*/);
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = array("'employee'");

    }
    function count() {
      $this->sql  = " SELECT count(DISTINCT u.id) ";
    }

    function select() {
      $this->sql = " SELECT DISTINCT u.id , u.id AS userid, CONCAT(u.firstname,' ',u.lastname) AS fullname, u.*";
      parent::select();
    }
    
    function from() {
      $this->sql .= " FROM {user} as u";
    }

    function joins() {
      parent::joins();
    }

    function where() { 
        global $DB, $USER;
        $this->sql .= " WHERE u.confirmed = 1 AND u.deleted = 0 AND u.id > 2 ";


      $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
      if (is_siteadmin()) {
          $this->sql .= "";
      } else  {
          $this->sql .= $costcenterpathconcatsql;
      }

        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return array(array(), 0);
            }
            $this->sql .= " AND u.id IN ( $conditions )";
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
        global $DB; 
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments'] > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }

        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }
        if ($this->params['filter_level5department'] > 0) {
            $l5dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level5department'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l5dept ";
            $this->params['l5dept'] = $l5dept.'/%';
        }
        if ($this->params['filter_geostate'] > 0) {
            $this->sql .= " AND u.open_states = :filter_geostate ";
        }
        if ($this->params['filter_geodistrict'] > 0) {
            $this->sql .= " AND u.open_district = :filter_geodistrict ";
        }
        if ($this->params['filter_geosubdistrict'] > 0) {
            $this->sql .= " AND u.open_subdistrict = :filter_geosubdistrict ";
        }
        if ($this->params['filter_geovillage'] > 0) {
            $this->sql .= " AND u.open_village = :filter_geovillage ";
        }
        if (isset($this->params['filter_users'])
            && $this->params['filter_users'] >0
            && $this->params['filter_users'] != '_qf__force_multiselect_submission') {
            $userid = $this->params['filter_users'];
            $this->sql .= " AND u.id IN ($userid) ";
        }
        if (!empty($this->params['filter_country'])) {
            $countryval = isset($this->params['filter_country']) ? implode(',', $this->params['filter_country']) : 0; 
            $this->sql .= ' AND u.country IN ("' . implode('", "', $this->params['filter_country']) . '") ';
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
          $this->sql .= " AND u.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate";
        } 
    }
    public function get_rows($users) {
        return $users;
    }
    public function column_queries($column, $userid){
        $where = " AND %placeholder% = $userid";
        // if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
        //     if ($this->rolewisecourses != '') {
        //         $coursefilter = " AND c.id IN ($this->rolewisecourses) ";
        //     }
        // }else{
          $coursefilter = "";
        // }
        $contentprovider = '';
        // if (!empty($this->params['filter_contentprovider']) && $this->params['filter_contentprovider'] > 0) {
        //     $contentproviderids = $this->params['filter_contentprovider'];
        //     $contentprovider .= " AND c.open_contentvendor IN ($contentproviderids) ";
        // }

        // $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0;
        // $certification = isset($this->params['filter_certification']) ? implode(',', $this->params['filter_certification']) : 0;
        // $certificationlevel = isset($this->params['filter_certificationlevel']) ? implode(',', $this->params['filter_certificationlevel']) : 0;
        // $exam = isset($this->params['filter_exam']) ? implode(',', $this->params['filter_exam']) : 0;
        // $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        // $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        // $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        // $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        // $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        // $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        // $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        // $tagslist = array($learningtype, $certification, $certificationlevel, $exam, $solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole);
        // if (array_sum($tagslist) > 0) {
        //     $tagslist = implode(',', $tagslist);
        //     $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
        //     if (!empty($tagcoursesql) && $tagcoursesql > 0) {
        //         $contentprovider .= " AND c.id IN (".$tagcoursesql.")";
        //     } else {
        //         $contentprovider .= " AND c.id IN (0)";
        //     }
        // }
        
        switch ($column) {
            case 'enrolled':
                $identy = "ue.userid";
                $query = "SELECT COUNT(DISTINCT c.id) AS enrolled 
                          FROM {user_enrolments} ue   
                          JOIN {enrol} e ON ue.enrolid = e.id 
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 
                          WHERE e.courseid = c.id $where $coursefilter $contentprovider";
                break;
            case 'inprogress':
                $identy = "ue.userid";
                $query = "SELECT (COUNT(DISTINCT c.id) - COUNT(DISTINCT cc.id)) AS inprogress 
                          FROM {user_enrolments} ue   
                          JOIN {enrol} e ON ue.enrolid = e.id 
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 
                     LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0 
                         WHERE e.courseid = c.id $where $coursefilter $contentprovider";
                break;
            case 'completed':
                $identy = "cc.userid";
                $query = "SELECT COUNT(DISTINCT cc.course) AS completed 
                          FROM {user_enrolments} ue   
                          JOIN {enrol} e ON ue.enrolid = e.id 
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 
                          JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0 WHERE e.courseid = c.id $where $coursefilter $contentprovider";
                break;
            case 'progress':
                $identy = "ra.userid";
                $query = "SELECT ROUND((COUNT(distinct cc.course) / COUNT(DISTINCT c.id)) *100, 2) as progress 
                            FROM {user_enrolments} ue   
                            JOIN {enrol} e ON ue.enrolid = e.id 
                            JOIN {role_assignments} ra ON ra.userid = ue.userid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                            JOIN {context} AS ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 
                       LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid 
                             AND cc.timecompleted > 0 WHERE  e.courseid = c.id $where $coursefilter $contentprovider";
                break;
            case 'badges':
                $identy = "bi.userid";
                $query = "SELECT COUNT(bi.id) AS badges FROM {badge_issued} as bi 
                          JOIN {badge} as b ON b.id = bi.badgeid 
                         WHERE  bi.visible = 1 AND b.status != 0
                          AND b.status != 2 AND b.status != 4   
                           $where ";
                break;
            case 'grade':
                 $identy = "gg.userid";
                 $query = "SELECT CONCAT(ROUND(sum(gg.finalgrade), 2),' / ', ROUND(sum(gi.grademax), 2)) AS grade 
                           FROM {grade_grades} AS gg
                           JOIN {grade_items} AS gi ON gi.id = gg.itemid
                           JOIN {course_completions} AS cc ON cc.course = gi.courseid
                           JOIN {course} AS c ON cc.course = c.id AND c.visible=1 
                          WHERE gi.itemtype = 'course' AND cc.course = gi.courseid
                            AND cc.timecompleted IS NOT NULL 
                            AND gg.userid = cc.userid
                             $where $coursefilter $contentprovider";
                break;
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }

}
