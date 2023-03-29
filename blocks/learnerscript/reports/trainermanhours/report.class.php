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

class report_trainermanhours extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('classroomfield'=>['classroomfield'],'userfield'=>['userfield'],'trainermanhours' => array('traininghours', 'userscovered'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments', 'subdepartments', 'level4department','classrooms','trainers');
        $this->orderable = array('classroomname','traininghours', 'userscovered');
        $this->defaultcolumn = 'lc.id';        
    }
    
    function init() {
        parent::init();
    }


    function count() {
        $this->sql = " SELECT COUNT(lc.id) ";
    }

    function select() {
       
        $this->sql  = "SELECT lc.id as classroomid,u.id as userid , lc.name,lc.startdate,CONCAT(u.firstname,' ',u.lastname) as fullname , u.email as email,u.* ";
        parent::select();                
    }
    function from() {
        $this->sql .= " FROM {role_assignments} AS ra ";
    }

    function joins() {
        $this->sql .= " JOIN {user} AS u on u.id=ra.userid 
                        JOIN {local_classroom_trainers} AS lct ON lct.trainerid=u.id
                        JOIN {local_classroom} AS lc ON lc.id = lct.classroomid ";
        parent::joins();
    }

    function where(){
        global $DB;
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'trainer'));
        $this->sql .= " WHERE 1=1 AND ra.roleid=:roleid ";
        $this->params['roleid'] = $roleid;
        $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }
        parent::where();
    }
       
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array('lc.fullname',"CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
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
        if (isset($this->params['filter_trainers']) && $this->params['filter_trainers'] > 0) {
            $userid = $this->params['filter_trainers'];
            $this->sql .= " AND u.id IN ($userid) ";
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

    public function column_queries($column, $userid){
        $where = " AND %placeholder% = $userid";          
      
        switch ($column) {
            case 'traininghours':
                $identy = "cs.trainerid";
                $query = "SELECT SUM(round(cs.duration/60, 2)) 
                            FROM {local_classroom_sessions} cs
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(lc.startdate))
                            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4) 
                            $where ";
                break;
            case 'userscovered':
                $identy = "cs.trainerid";
                $query = "SELECT count(distinct cat.userid) 
                            FROM {local_classroom_attendance} cat
                            JOIN {local_classroom_sessions} cs  ON cat.sessionid = cs.id AND cat.status = 1
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(lc.startdate))
                            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4)
                            $where ";
                break;
           
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }

}
