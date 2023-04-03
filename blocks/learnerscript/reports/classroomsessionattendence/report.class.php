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

class report_classroomsessionattendence extends reportbase implements report {
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
                                'classroomsessionattendencecolumns'=>['sessionname','trainer','attendendencestatus','timestart','timefinish']);
        $this->filters = array('user','trainers','classrooms');
        $this->basicparams = array(['name' => 'classrooms']);
        $this->defaultcolumn = 'rowNumber';
    }


    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(lcs.id) ";
    }

    function select() {
        $this->sql = " SELECT (@cnt := @cnt + 1) AS rowNumber,CONCAT(u.firstname,' ',u.lastname) AS fullname, u.*,lcs.id , lc.id AS classroomid, lcs.id AS sessionid,lcu.userid,lcs.name as sessionname,lcs.trainerid as trainer,lca.status as attendendencestatus,FROM_UNIXTIME(lcs.timestart, '%d-%m-%Y %h:%i:%s %p') as timestart,FROM_UNIXTIME(lcs.timefinish, '%d-%m-%Y %h:%i:%s %p') as timefinish
                        " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }


    function joins() {
        $this->sql .=" JOIN {local_classroom_users} lcu ON  lcu.classroomid = lc.id 
                        JOIN  {user} u ON u.id = lcu.userid 
                        JOIN {local_classroom_sessions} lcs on lcs.classroomid = lcu.classroomid AND lcu.classroomid = lc.id
                        JOIN {local_classroom_attendance} lca on lca.classroomid = lc.id AND lca.sessionid = lcs.id AND lca.userid = lcu.userid 
                        CROSS JOIN (SELECT @cnt := 0) AS dummy";

        parent::joins();
    }

    function where() {
        $this->sql .= " WHERE 1=1 AND u.deleted = 0 AND u.suspended = 0 and u.confirmed = 1  ";
        $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }

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
        if (!empty($this->params['filter_classrooms']) && $this->params['filter_classrooms'] > 0) {
            $this->sql .= " AND lc.id = :classroomid ";
            $this->params['classroomid'] = $this->params['filter_classrooms'];
        } else {
            if(is_siteadmin()){
                $this->sql .= " AND lc.id = 0 ";        
            }else{
                $this->sql .= "";
            }
        }

        if (!empty($this->params['filter_user']) && $this->params['filter_user'] > 0) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $this->params['filter_user'];
        }

        if (!empty($this->params['filter_trainers']) && $this->params['filter_trainers'] > 0) {
            $this->sql .= " AND lcs.trainerid = :userid ";
            $this->params['userid'] = $this->params['filter_trainers'];
        }

        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND lc.startdate > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;

            $this->sql .= " AND lc.enddate < :report_enddate ";
            $this->params['report_enddate'] = $this->ls_enddate;
        } 
        
    }

    /**
     * [get_rows description]
     * @param  array  $sessionattendance [description]
     * @return [type] [description]
     **/
    public function get_rows($sessionattendance = array()) {
       
        return $sessionattendance;
    }
}
