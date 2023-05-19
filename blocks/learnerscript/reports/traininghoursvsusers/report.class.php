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

class report_traininghoursvsusers extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('traininghoursvsusers' => array('monthyear', 'totaltrainings', 'month','year','traininghours', 'trainingdays', 'userscovered'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        //$this->filters = array('organization','departments', 'subdepartments');
        $this->sqlorder['column'] = 'year';
        $this->sqlorder['dir'] = 'desc';
        $this->orderable = array('monthyear','totaltrainings', 'month','year','traininghours', 'userscovered');
        $this->defaultcolumn = 'lc.startdate';
        
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT( distinct MONTH(FROM_UNIXTIME(lc.startdate)))";
    }
    function select() {
        /*
         (SELECT count(id)           
            FROM {local_classroom} c       
            WHERE YEAR(FROM_UNIXTIME(c.startdate)) = YEAR(FROM_UNIXTIME(lc.startdate))
            AND MONTH(FROM_UNIXTIME(c.startdate)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4) )  as totaltrainings,
         (SELECT SUM(DATEDIFF(DATE(FROM_UNIXTIME(c.enddate)), DATE(FROM_UNIXTIME(c.startdate))))
        FROM {local_classroom} c 
        WHERE YEAR(FROM_UNIXTIME(c.startdate)) = YEAR(FROM_UNIXTIME(c.startdate))
        AND MONTH(FROM_UNIXTIME(c.startdate)) = MONTH(FROM_UNIXTIME(c.startdate)) AND (c.status = 1 OR c.status = 4) )  as  trainingdays,   
         (SELECT SUM(round(cs.duration/60, 2)) 
        FROM {local_classroom_sessions} cs
        JOIN {local_classroom} c ON cs.classroomid = c.id
        WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(lc.startdate))
        AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4)) as traininghours,   
    (SELECT count(distinct cat.userid) 
        FROM {local_classroom_attendance} cat
        JOIN {local_classroom_sessions} cs  ON cat.sessionid = cs.id AND cat.status = 1
        JOIN {local_classroom} c ON cs.classroomid = c.id
        WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(lc.startdate))
        AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4) )  as userscovered */

        $this->sql  = "SELECT distinct concat(MONTH(FROM_UNIXTIME(lc.startdate)), '/', YEAR(FROM_UNIXTIME(lc.startdate))) as monthyear, FROM_UNIXTIME(lc.startdate, '%M') AS month,
                    YEAR(FROM_UNIXTIME(lc.startdate)) AS year "; 
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }
    function joins() {
        parent::joins();
    }
    function where(){
        global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
        $this->sql .= " AND (lc.status = 1 OR lc.status = 4) ";      
    
        $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.open_path', null, 'lowerandsamepath');

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
            $fields = array("MONTH(FROM_UNIXTIME(lc.startdate))","YEAR(FROM_UNIXTIME(lc.startdate))","concat(MONTH(FROM_UNIXTIME(lc.startdate)), '/', YEAR(FROM_UNIXTIME(lc.startdate)))","FROM_UNIXTIME(lc.startdate, '%M')","YEAR(FROM_UNIXTIME(lc.startdate))");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";           
        }
    } 

    function filters(){    
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(lc.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments'] > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(lc.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(lc.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
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
