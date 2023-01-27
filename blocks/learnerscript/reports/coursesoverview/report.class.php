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
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_coursesoverview extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
        $columns = array('coursefield'=>['coursefield'], 'coursesoverviewcolumns' => ['noofenrollments', 'noofcompletions']);   
        $this->columns = $columns;
        $this->filters = array('organization','departments', 'subdepartments', 'level4department', 'level5department', 'course');
        $this->orderable = array('coursename', 'noofenrollments', 'noofcompletions');
        $this->defaultcolumn = 'c.id';
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(c.id) ";
    }

    function select() {

        $this->sql = "SELECT c.id courseid, c.fullname as coursename, c.open_path as course_open_path " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course} c ";
    }

    function joins() {
        $this->sql .=" JOIN {course_categories} cat ON cat.id = c.category ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;
        $this->sql .= " WHERE c.id <> :siteid ";
        // $this->sql .= " WHERE c.id <> :siteid AND 
        //         CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";

        $this->params['siteid'] = SITEID;

        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');

        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }

        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("c.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }
        if ($this->params['filter_level5department'] > 0) {
            $l5dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level5department'], 'path');
            $this->sql .= " AND concat(c.open_path,'/') like :l5dept ";
            $this->params['l5dept'] = $l5dept.'/%';
        }


        if(isset($this->params['filter_course']) && $this->params['filter_course'] > 0) {
            $this->sql .= " AND c.id = :courseid ";
            $this->params['courseid'] = $this->params['filter_course'];
        }
        // echo $this->sql;

    }

    public function get_rows($courses) {
        global $DB;
        $data = array();
        if($courses){
            $costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
            $enrolsql = "SELECT COUNT(ra.id)
                        FROM {role_assignments} ra
                        JOIN {context} cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                        JOIN {role} r ON r.id = ra.roleid
                        JOIN {user} u ON ra.userid = u.id
                        WHERE u.deleted = 0
                            AND u.suspended = 0 AND r.shortname = 'employee'
                            AND cxt.instanceid = :courseid {$costcenterpathconcatsql} ";
            $completedsql = "SELECT COUNT(ra.id)
                        FROM {role_assignments} ra
                        JOIN {context} AS cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                        JOIN {role} r ON r.id = ra.roleid
                        JOIN {user} u ON ra.userid = u.id
                        JOIN {course_completions} cc ON cxt.instanceid = cc.course
                            AND cc.userid = u.id AND cc.timecompleted IS NOT NULL
                        WHERE u.deleted = 0
                            AND u.suspended = 0 AND r.shortname = 'employee'
                            AND cxt.instanceid = :courseid {$costcenterpathconcatsql} ";

            foreach ($courses as $course) {
                $course->noofenrollments = $DB->count_records_sql($enrolsql, array('courseid' => $course->courseid));

                $course->noofcompletions = $DB->count_records_sql($completedsql, array('courseid' => $course->courseid));

                $data[] = $course;
            }
        }
        return $data;
    }
}
