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

class report_trainingsoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('trainingsoverview' => array('classroomname','classroomstartdate','classroomenddate','session', 'sessionstartdate','sessionenddate','sessiontime','type','room','status','attendedusers','trainer'));
        $this->components = array('columns', 'filters', 'permissions');
        $this->filters = array('organization','departments', 'subdepartments', 'classrooms','trainers');
        $this->orderable = array('classroomname','classroomstartdate','classroomenddate','session', 'sessionstartdate','sessionenddate','sessiontime','type','room','status','attendedusers','trainer');
        $this->defaultcolumn = 'cs.id';
    }
    
    function init() {
        parent::init();
    }

    function count() {
        $this->sql = " SELECT COUNT(cs.id) ";
    }

    function select() {
       
        $this->sql  = "SELECT cs.id,c.name as classroomname, cs.name as session, cr.name as room, CONCAT(u.firstname,' ',u.lastname) as trainer,
                        DATE(FROM_UNIXTIME(c.startdate)) as classroomstartdate, DATE(FROM_UNIXTIME(c.enddate)) as classroomenddate,
                        DATE(FROM_UNIXTIME(cs.timestart)) as sessionstartdate, DATE(FROM_UNIXTIME(cs.timefinish)) as sessionenddate, cs.*";
      
        parent::select();                
    }
    function from() {
        $this->sql .= " FROM {local_classroom_sessions} AS cs ";
    }

    function joins() {
        $this->sql .= " JOIN {local_classroom} AS c ON cs.classroomid = c.id 
                        LEFT JOIN {user} AS u ON u.id = cs.trainerid 
                        LEFT JOIN {local_location_room} AS cr ON cr.id = cs.roomid ";
        parent::joins();
    }

    function where(){
        global $DB;    
        $time=time();    
        $this->sql .= " WHERE 1=1 ";        
        $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }
        parent::where();
    }
   
    function search(){
    } 
    
    function filters(){

        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'%';
        }
        if ($this->params['filter_departments'] > 0) {
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
     
        if ($this->params['filter_classrooms'] > 0) {
            $this->sql .= " AND lc.id = :classroomid ";
            $this->params['classroomid'] = $this->params['filter_classrooms'];
        }

        if (isset($this->params['filter_trainers']) && $this->params['filter_trainers'] > 0) {
            $userid = $this->params['filter_trainers'];
            $this->sql .= " AND u.id IN ($userid) ";
        }
        
        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND c.startdate > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;

            $this->sql .= " AND c.enddate < :report_enddate ";
            $this->params['report_enddate'] = $this->ls_enddate;
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
   // ('classroomname','session', 'pastdate','futuredate','time','type','room','status','attendedusers','trainer'));  

}
