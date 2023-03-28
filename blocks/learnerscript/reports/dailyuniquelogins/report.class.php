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

class report_dailyuniquelogins extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('dailyuniquelogins' => array('costcentername','usercount','monthname','year'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization');
        $this->orderable = array('costcentername','usercount','monthname','year');
        $this->groupcolumn = 'YEAR(FROM_UNIXTIME(lsl.timecreated)), MONTH(FROM_UNIXTIME(lsl.timecreated)),SUBSTRING(u.open_path,2,1) ';
        $this->sqlorder['column'] = 'YEAR(FROM_UNIXTIME(lsl.timecreated)), MONTH(FROM_UNIXTIME(lsl.timecreated))';       
       
    }
    
    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT count(DISTINCT(concat(YEAR(FROM_UNIXTIME(lsl.timecreated)), MONTH(FROM_UNIXTIME(lsl.timecreated))))) ";
    }

    function select() {      
        $this->sql  = "SELECT lsl.userid,lc.fullname as costcentername, COUNT(DISTINCT(lsl.userid)) as usercount, YEAR(FROM_UNIXTIME(lsl.timecreated)) AS year, MONTH(FROM_UNIXTIME(lsl.timecreated)) as month, MONTHNAME(FROM_UNIXTIME(lsl.timecreated)) AS monthname ";//, concat(u.firstname,' ', u.lastname) AS employeename, u.email
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {logstore_standard_log} as lsl ";
    }

    function joins() {
        $this->sql .= " JOIN {user} u ON u.id = lsl.userid 
                        JOIN {local_costcenter} lc ON concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.parentid = 0 ";
        parent::joins();
    }

    function where(){
       
        $this->sql .= " WHERE lsl.action LIKE '%loggedin%' and lsl.userid > 2 ";
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else {
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
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array('l.month','l.year');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 

    function filters(){  

        if (!empty($this->params['filter_organization'])  && $this->params['filter_organization'] > 0) {
            $organization = $this->params['filter_organization'];
            $filter_organization[] = " concat('/',u.open_path,'/') LIKE :organizationparam_{$organization}";
            $this->params["organizationparam_{$organization}"] = '%/' . $organization . '/%';
            $this->sql .= " AND ( " . implode(' OR ', $filter_organization) . " ) ";
        }

        if ($this->params['filter_departments'] > 0) {
            $department = $this->params['filter_departments'];
            $filter_department[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $this->params["departmentparam_{$department}"] = '%/' . $department . '/%';
            $this->sql .= " AND ( " . implode(' OR ', $filter_department) . " ) ";
        }

        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND lsl.timecreated > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;

            $this->sql .= " AND lsl.timecreated < :report_enddate ";
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
       /*  global $DB;
        $loginsdata = array();
        if($data){  
             
            foreach ($data as $rec) {
                $orgid = ($rec->open_path) ? explode('/',$rec->open_path)[1] : null;
                $rec->costcentername = $DB->get_field_sql('SELECT c.fullname as costcentername FROM {local_costcenter} c WHERE c.depth = 1 AND c.id = :orgid',array('orgid' => $orgid));
                $loginsdata[] = $rec; 
            }
        }     
        return $loginsdata; */
    }
}


