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

use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_classroom_completions extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'filters', 'permissions');
        $this->columns = array('classroomfield'=>['classroomfield'],
                                'userfield'=>['userfield'],
                                'classroomcompletionscolumns'=>['attendedsessions','totalsessions','usercompletionstatus','usercompletiondate']);
        $this->filters = array('organization','departments', 'subdepartments', 'level4department', 'user','classrooms','completionstatus');
        $this->defaultcolumn = 'lcu.id';
    }


    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(lcu.id) ";
    }

    function select() {
        $this->sql = " SELECT lcu.id as userenrolid , lc.id AS classroomid, lcu.userid, u.*,
                        (SELECT COUNT(lcs.id) 
                        FROM {local_classroom_sessions} lcs 
                        WHERE lcs.classroomid = lc.id) AS totalsessions,
                        (SELECT COUNT(lca.id) 
                        FROM {local_classroom_attendance} lca 
                        WHERE lca.classroomid = lc.id AND
                         lca.userid = u.id AND lca.status = 1) AS attendedsessions,
                        CASE
                            WHEN lcu.completion_status > 0 THEN 'Completed'
                            ELSE 'Not Completed'
                        END AS usercompletionstatus,
                        lcu.completiondate AS usercompletiondate,CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.username, u.firstname, u.lastname, u.email, lc.open_path as class_open_path  " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }

    function joins() {
        $this->sql .=" JOIN {local_classroom_users} lcu ON lc.id = lcu.classroomid
                        JOIN {user} u ON u.id = lcu.userid ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;

        $systemcontext = context_system::instance();
        $this->sql .= " WHERE lc.status <> 0 "; //Not considering the new classrooms.
        $this->sql .= (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('lc.name',"CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {

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
         /*  if ($this->params['filter_level5department'] > 0) {
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
        } */
        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND lcu.completiondate > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;
        // }
        // if($this->ls_enddate > 0){
            $this->sql .= " AND lcu.completiondate < :report_enddate ";
            $this->params['report_enddate'] = $this->ls_enddate;
        }

        if (!empty($this->params['filter_classrooms']) && $this->params['filter_classrooms'] > 0) {
            $this->sql .= " AND lc.id = :classroomid ";
            $this->params['classroomid'] = $this->params['filter_classrooms'];
        }

        if (!empty($this->params['filter_user']) && $this->params['filter_user'] > 0) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $this->params['filter_user'];
        }

        if ($this->params['filter_completionstatus'] > -1){
            $this->sql .= " AND lcu.completion_status = :usercomplstatus ";
            $this->params['usercomplstatus'] = $this->params['filter_completionstatus'];
        }

    }

    /**
     * [get_rows description]
     * @param  array  $classroomcompletion [description]
     * @return [type] [description]
     **/
    public function get_rows($classroomcompletion = array()) {
        return $classroomcompletion;
    }
}
