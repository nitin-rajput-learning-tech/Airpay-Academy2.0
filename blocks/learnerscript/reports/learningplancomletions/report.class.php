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

class report_learningplancomletions extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot','orderable');
        $this->columns = ['learningpathfield'=>['learningpathfield'], 'userfield'=>['userfield'],'learningplancompletionscolumns'=>['learningpathname','completionstatus','completiondate','totalcourse','totalcoursecompleted','inprogresscourse']];
        $this->parent = true;
        $this->filters = array('organization','departments', 'subdepartments', 'level4department', 'learningpath','user','completionstatus');
        $this->orderable = array('learningpathname');
        $this->defaultcolumn = 'llu.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(llu.id)";
    }
    function select() {
        $this->sql = "SELECT llu.id AS learningplanuserid,lp.id as learningpathid,u.id as userid, u.*,lp.name as learningpathname,
                        llu.status AS completionstatus,CONCAT(u.firstname, ' ', u.lastname) AS fullname,username,firstname,lastname,email,
                        llu.completiondate as completiondate, lp.open_path as lp_open_path ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_learningplan} lp  ";
    }
    function joins() {
         $this->sql .= "  JOIN {local_learningplan_user} as llu ON lp.id = llu.planid
                          JOIN {user} as u ON u.id = llu.userid ";
          parent::joins();
    }
    function where(){
         global $USER, $DB;
         $this->sql .= " WHERE u.deleted = 0 AND u.suspended = 0 ";
         $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();

        $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path', null, 'lowerandsamepath');
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
           
   function search() {
        if(isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)", 'lp.name', 'u.open_employeeid');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
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

        if (!empty($this->params['filter_learningpath'])) {
            $lplan = $this->params['filter_learningpath'];
            $this->sql .= " AND lp.id = $lplan ";

        }
        if (!empty($this->params['filter_user'])) {
            $this->sql .= " AND u.id = :filter_user ";
        }
        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND llu.completiondate > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;

            $this->sql .= " AND llu.completiondate < :report_enddate ";
            $this->params['report_enddate'] = $this->ls_enddate;
        }
       if ((isset($this->params['filter_completionstatus'])) && ($this->params['filter_completionstatus'] != -1)) {
            $lpid = $this->params['filter_completionstatus'];
            if($lpid == 1){
                $this->sql .= " AND llu.status = $lpid";
            }else{
                $this->sql .= " AND llu.status IS NULL";
            }
        }
        // echo $this->sql;
        // print_object($this->params);exit;
   }
    public function get_rows($learningpath) {
        return $learningpath;
    }
}
