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
class report_courseparticipation extends reportbase {
    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'ordering', 'permissions', 'filters', 'plot');
        $this->parent = true;
        $this->columns = ['courseparticipationcolumns' => ['enrolled','inprogress','completed','progress']];
        $this->filters = array('organization', 'departments','subdepartments', 'level4department', 'level5department','course');
        $this->orderable = array('enrolled');
        $this->enablestatistics = true;
        $this->defaultcolumn = 'u.id';
    }

    function count() {
      $this->sql  = " SELECT COUNT(DISTINCT (SELECT COUNT(DISTINCT c.id) AS enrolled
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON ue.enrolid = e.id
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                          WHERE e.courseid = c.id AND  ue.userid=u.id)) ";
    }

    function select() {
       $this->sql = " SELECT DISTINCT u.id ";
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
     

    }
    public function get_rows($users) {

        global $DB;

        if(empty($users)){

            return array();

        }

        $data=array();


         $input = json_decode(json_encode ($users ) , true);


        $result = array_keys(array_flip(array_column($input, 'id')));

        foreach($this->columns['courseparticipationcolumns'] as $key=>$column){

            if($column == 'progress' || $column == 'inprogress'){

                continue;
            }


            $sqlparams=$this->courseparticipation_column_queries($column,$result);


            if(!empty($sqlparams)){


                $data[$column] = $data[$column]+$DB->get_field_sql($sqlparams['sqlquery'],$sqlparams['sqlparams']);
            }


        }

        if(!empty($data)){

            $data['inprogress'] = ($data['completed'] > 0 && $data['enrolled'] > 0) ? $data['enrolled']-$data['completed'] : 0 ;

            $data['progress'] = ($data['completed'] > 0 && $data['enrolled'] > 0) ? round(($data['completed']/$data['enrolled'])*100) : 0 ;

            $data['progress'] =$data['progress'].'%';

            return array((object)$data);
        }else{
            return $data;
        }


    }

    public function courseparticipation_column_queries($column, $userids){

        global $DB;

        if(empty($userids)){

            return array();

        }

        $filteruserids = $userids;

        list($filteruseridssql, $filteruseridsparams) = $DB->get_in_or_equal($filteruserids, SQL_PARAMS_QM, 'userids', true, false);
        $where= " AND  %placeholder% $filteruseridssql";

        $coursefilter = "";

        if (isset($this->params['filter_course'])
            && $this->params['filter_course'] >0
            && $this->params['filter_course'] != '_qf__force_multiselect_submission') {
            $courseid = $this->params['filter_course'];
            $coursefilter.= " AND c.id IN ($courseid) ";
        }


        if ($this->ls_startdate >= 0 && $this->ls_enddate) {

            if ($column == 'enrolled' || $column == 'enrolled') {

              $coursefilter.= " AND ra.timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate";

            }elseif ($column == 'completed' || $column == 'progress') {

                $coursefilter.= " AND cc.timecompleted BETWEEN $this->ls_startdate AND $this->ls_enddate";
            }

        }

        switch ($column) {
            case 'enrolled':
                $identy = "ue.userid";
                $query = "SELECT COUNT(DISTINCT c.id) AS enrolled
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON ue.enrolid = e.id
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                          WHERE e.courseid = c.id $where $coursefilter ";
                break;
            case 'inprogress':
                $identy = "ue.userid";
                $query = "SELECT (COUNT(DISTINCT c.id) - COUNT(DISTINCT cc.id)) AS inprogress
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON ue.enrolid = e.id
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                     LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0
                         WHERE e.courseid = c.id $where $coursefilter ";
                break;
            case 'completed':
                $identy = "cc.userid";
                $query = "SELECT COUNT(DISTINCT cc.course) AS completed
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON ue.enrolid = e.id
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                          JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0 WHERE e.courseid = c.id $where $coursefilter ";
                break;


            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return array('sqlquery'=>$query,'sqlparams'=>$filteruseridsparams);
    }
}
