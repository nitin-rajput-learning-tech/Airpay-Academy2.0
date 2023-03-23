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
use block_learnerscript\local\querylib;
defined('MOODLE_INTERNAL') || die();
class report_courseparticipation extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report, $reportproperties);
        $this->columns = ['courseparticipationcolumns' => ['enrollments','completions']];
        $this->components = array('columns', 'filters','permissions');
        $this->filters = array('organization', 'departments','subdepartments', 'level4department', 'level5department','course');
        $this->parent = true;
        $this->defaultcolumn = 'u.id';
        $this->enablestatistics = true;
    }

    function init() {
        parent::init();
    }

    function concatsql(){

        $searchsql="";

        if (isset($this->search) && $this->search) {

            $fields = array('c.id');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $searchsql .= " AND ($fields) ";

        }

        $filtersql=$searchsql;

        $filteronesql="";

        $filtertwosql="";

        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $filtersql .= " AND concat(u.open_path,'/') like '%$orgpath/%'";
        }
        if ($this->params['filter_departments'] > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $filtersql .= " AND concat(u.open_path,'/') like '%$l2dept/%' ";
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $filtersql .= " AND concat(u.open_path,'/') like '%$l3dept/%' ";
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
             $filtersql .= " AND concat(u.open_path,'/') like '%$l4dept/%' ";
        }
        if ($this->params['filter_level5department'] > 0) {
            $l5dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level5department'], 'path');
            $filtersql .= " AND concat(u.open_path,'/') like '%$l5dept/%' ";
        }
        if (!empty($this->params['filter_course'])) {

            $courseid = $this->params['filter_course'];

            $filtersql .= " AND e.courseid = $courseid ";
        }

        if($this->ls_startdate > 0 && $this->ls_enddate > 0){

            $ls_startdate=$this->ls_startdate;

            $ls_enddate=$this->ls_enddate;

            $filteronesql=$filtersql;

            $filteronesql.= " AND ra.timemodified > $ls_enddate ";

            $filteronesql .= " AND ra.timemodified < $ls_enddate ";

            $filtertwosql=$filtersql;

            $filtertwosql .= " AND cc.timecompleted > $ls_enddate ";

            $filtertwosql .= " AND cc.timecompleted < $ls_enddate ";

        }else{

            $filteronesql=$filtersql;

            $filtertwosql=$filtersql;

        }

        return compact('filteronesql','filtertwosql');

    }

    function count() {


        $concatsql=$this->concatsql();

        $filteronesql=$concatsql['filteronesql'];


        $this->sql = "SELECT COUNT(DISTINCT(SELECT COUNT(DISTINCT(ue.id))
                        FROM {user_enrolments} ue
                        JOIN {user} u ON ue.userid = u.id
                        JOIN {enrol} e ON e.id = ue.enrolid AND (e.enrol = 'manual' OR e.enrol = 'self')
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} cxt ON cxt.id = ra.contextid
                        JOIN {role} r ON r.id = ra.roleid
                        JOIN {course} c ON c.id = cxt.instanceid
                        WHERE u.deleted = 0 AND c.id = e.courseid AND u.confirmed = 1
                        AND u.suspended = 0 AND r.shortname = 'employee' $filteronesql)) ";
    }

    function select() {


        $concatsql=$this->concatsql();

        $filteronesql=$concatsql['filteronesql'];

        $filtertwosql=$concatsql['filtertwosql'];

        $this->sql = "SELECT DISTINCT(SELECT COUNT(DISTINCT(ue.id))
                        FROM {user_enrolments} ue
                        JOIN {user} u ON ue.userid = u.id
                        JOIN {enrol} e ON e.id = ue.enrolid AND (e.enrol = 'manual' OR e.enrol = 'self')
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} cxt ON cxt.id = ra.contextid
                        JOIN {role} r ON r.id = ra.roleid
                        JOIN {course} c ON c.id = cxt.instanceid
                        WHERE u.deleted = 0 AND c.id = e.courseid AND u.confirmed = 1
                        AND u.suspended = 0 AND r.shortname = 'employee' $filteronesql) AS 'Enrollments',
                        (SELECT COUNT(DISTINCT(ue.id))
                        FROM {user_enrolments} ue
                        JOIN {user} u ON ue.userid = u.id
                        JOIN {enrol} e ON e.id = ue.enrolid AND (e.enrol = 'manual' OR e.enrol = 'self')
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} cxt ON cxt.id = ra.contextid
                        JOIN {role} r ON r.id = ra.roleid
                        JOIN {course_completions} cc ON e.courseid = cc.course
                        JOIN {course} c ON c.id = cc.course
                        AND cc.userid = u.id AND cc.timecompleted IS NOT NULL
                        WHERE  u.deleted = 0
                        AND u.suspended = 0 AND r.shortname = 'employee' AND ra.userid = u.id
                        AND c.id = e.courseid $filtertwosql) AS 'Completions'";


        parent::select();
    }

    function from() {
        $this->sql .= " FROM {user} u ";
    }

    function joins() {

        $this->sql .= "";

        parent::joins();
    }

    function where() {
        global $USER, $DB;

        $this->sql .= " ";

        parent::where();
    }


    function search() {
        if (isset($this->search) && $this->search) {
            $this->sql .= "  ";
        }
    }

    function filters() {

        $this->sql .= "";
    }

    public function get_rows($courseusers) {
        return $courseusers;
    }
}
