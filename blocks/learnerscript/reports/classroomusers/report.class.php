<?php
/*
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

class report_classroomusers extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('classroomusers' => array('employeename', 'totalilts', 'totalsessions','attendedsessions','totalhours'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');      
        $this->orderable = array('employeename', 'totalilts', 'totalsessions','attendedsessions','totalhours');
        $this->filters = array('user');        
        $this->defaultcolumn = 'u.id';
        
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(u.id)";
    }
    function select() {
        $this->sql = "SELECT u.id, concat(u.firstname,' ',u.lastname) AS employeename, 
        (SELECT count(cu.id) FROM {local_classroom_users} AS cu WHERE cu.userid = u.id) AS totalilts, 
        (SELECT count(cs.id) FROM {local_classroom_sessions} AS cs JOIN {local_classroom_users} AS cu ON cu.classroomid = cs.classroomid WHERE cu.userid= u.id) AS totalsessions, 
        (SELECT concat(FLOOR(sum(cs.timefinish - cs.timestart)/3600),' Hours') FROM {local_classroom_sessions} AS cs JOIN {local_classroom_attendance} AS ca ON ca.sessionid = cs.id WHERE ca.userid= u.id AND ca.status = 1) AS totalhours,
        (SELECT count(ca.id) FROM {local_classroom_sessions} AS cs JOIN {local_classroom_attendance} AS ca ON ca.sessionid = cs.id WHERE ca.userid= u.id AND ca.status = 1 ) AS attendedsessions ";
        
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {user} u ";
    }

    function joins() {
        parent::joins();
    }

    function where(){
       
        global $USER, $DB;
        $this->sql .= " WHERE u.id > :id AND u.deleted = :deleted AND u.suspended = :suspended ";
        $this->params['id'] = 2;
        $this->params['deleted'] = 0;
        $this->params['suspended'] = 0;      
        $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');

        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }
        
        parent::where();

    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)", "u.email");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        } 
    } 

    function filters(){    
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'%';
        }

        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'%';
        }
        
        if (!empty($this->params['filter_user'])) {
            $userid = $this->params['filter_user'];
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $userid;
        }      
    }
    
    /**
     * [get_rows description]
     * @param  array  $trainermandays [description]
     * @return [type]        [description]
     **/
    public function get_rows($data = array()) {      
        return $data;
    }

}
