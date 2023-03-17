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
use core_completion\progress;

class report_coursesoverview extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
        $columns = array('coursefield'=>['coursefield'], 'coursesoverviewcolumns' => ['noofenrollments', 'noofcompletions','noofinprogress','percentofcompletions','notstarted','quizpassed','quizfail','traineduserpercent']);
        $this->columns = $columns;
        $this->filters = array('organization','departments', 'subdepartments', 'level4department', 'level5department', 'course');
        $this->orderable = array('coursename', 'noofenrollments', 'noofcompletions','noofinprogress','percentofcompletions');
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
        $this->sql .=" JOIN {local_custom_category} cat ON cat.id = c.open_categoryid ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;
        $this->sql .= " WHERE c.id <> :siteid  AND c.open_coursetype = :type";
        // $this->sql .= " WHERE c.id <> :siteid AND 
        //         CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";

        $this->params['siteid'] = SITEID;
        $this->params['type'] = 0;
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

        // print_r($this->params);
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

            $noofcompletions = "SELECT count(id) FROM {course_completions} WHERE course =:courseid and timecompleted IS NOT NULL";

            $inprogress = "SELECT count(ul.id) FROM {user_lastaccess} AS ul
                WHERE ul.courseid =:courseid AND ul.userid !=2
                AND ul.userid NOT IN(SELECT cc.userid FROM {course_completions} AS cc
                    WHERE cc.course = ul.courseid AND cc.userid = ul.userid AND cc.timecompleted IS NOT NULL)";
            $inprogress .= " AND ul.userid IN (SELECT ra.userid
                        FROM mdl_role_assignments ra
                        JOIN mdl_context cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                        JOIN mdl_role r ON r.id = ra.roleid
                        JOIN mdl_user u ON ra.userid = u.id
                        WHERE u.deleted = 0
                            AND u.suspended = 0 AND r.shortname = 'employee'
                            AND cxt.instanceid = ul.courseid)";

            $notstarted = "AND ra.userid NOT IN (SELECT ul.userid FROM {user_lastaccess} AS ul WHERE ul.courseid = cxt.instanceid)";

            $quizpassgrade = "SELECT id, gradepass, iteminstance FROM {grade_items} WHERE itemmodule = 'quiz' AND courseid =:courseid";

            $countquizparticipents = "SELECT count(id) FROM {quiz_grades} WHERE quiz =:quizeid";

            $countpassed = " AND grade >=:passgrade";

            $countfail = " AND grade <:passgrade";

            foreach ($courses as $course) {

                $course->noofenrollments = $DB->count_records_sql($enrolsql, array('courseid' => $course->courseid));
                $course->noofinprogress = $DB->count_records_sql($inprogress, array('courseid' => $course->courseid));
                $course->noofcompletions = $DB->count_records_sql($noofcompletions, array('courseid' => $course->courseid));
                $course->notstarted = $DB->count_records_sql($enrolsql.$notstarted, array('courseid' => $course->courseid));
                $percentofcompletions = round(($course->noofcompletions/$course->noofenrollments)*100);
                $percentofcompletion = is_NAN($percentofcompletions) ? 0 : $percentofcompletions;
                $course->percentofcompletions = '<div class="progress">
                    <div class="progress-bar text-center" role="progressbar" aria-valuenow="'.$percentofcompletion.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$percentofcompletion.'%">
                        <span class="progress_percentage ml-2">'.$percentofcompletion.'%</span>
                    </div>
                </div>';
                $passgrade = $DB->get_record_sql($quizpassgrade,array('courseid' => $course->courseid));

                $totalattemptsuser = $DB->count_records_sql($countquizparticipents, array('quizeid' => $passgrade->iteminstance));

                $userpassed = $DB->count_records_sql($countquizparticipents.$countpassed, array('passgrade' => $passgrade->gradepass, 'quizeid' => $passgrade->iteminstance));
                $course->quizpassed = $userpassed;

                $userfail = $DB->count_records_sql($countquizparticipents.$countfail, array('passgrade' => $passgrade->gradepass, 'quizeid' => $passgrade->iteminstance));
                $course->quizfail = $userfail;

                $traineduser = round(($userpassed/$course->noofenrollments)*100);
                $traineduserpercent = is_NAN($traineduser) ? 0 : $traineduser;
                $course->traineduserpercent = '<div class="progress">
                    <div class="progress-bar text-center" role="progressbar" aria-valuenow="'.$traineduserpercent.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$traineduserpercent.'%">
                        <span class="progress_percentage ml-2">'.$traineduserpercent.'%</span>
                    </div>
                </div>';
                $data[] = $course;
            }
        }
        return $data;
    }
}
