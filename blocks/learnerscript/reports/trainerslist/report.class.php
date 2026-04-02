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

class report_trainerslist extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('trainerslist' => array('trainername','email', 'totaltrainings','completedtrainings','upcomingtrainings','userscovered'));
        $this->components = array('columns', 'filters', 'permissions');
        $this->filters = array('trainers');
       // $this->orderable = array('trainername','email', 'totaltrainings','completedtrainings','upcomingtrainings','userscovered');
        $this->defaultcolumn = 'u.id';
    }
    
    function init() {
        parent::init();
    }

    function count() {
        $this->sql = " SELECT COUNT(ra.id) ";
    }

    function select() {
       
        $this->sql  = "SELECT u.id, CONCAT(u.firstname,' ',u.lastname) as trainername , u.email as email,u.open_employeeid ";
        parent::select();                
    }
    function from() {
        $this->sql .= " FROM {role_assignments} AS ra ";
    }

    function joins() {
        $this->sql .= " JOIN {user} AS u on u.id=ra.userid ";
        parent::joins();
    }

    function where(){
        global $DB,$USER;
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'trainer'));
        $this->sql .= " WHERE 1=1 AND ra.roleid=:roleid ";
        $this->params['roleid'] = $roleid;
        $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $usercostcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path',$org);
            $costcenterpathconcatsql  = $costcenterpathconcatsql  . $usercostcenterpathconcatsql  ; 
            $this->sql .= $costcenterpathconcatsql;
        }
        parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 

    function filters(){

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
}
