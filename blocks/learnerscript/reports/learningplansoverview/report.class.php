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

class report_learningplansoverview extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'permissions','orderable','plot');
        $this->columns = ['learningpathfield'=>['learningpathfield'], 'learningplansoverviewcolumns'=> ['optionalcourses','mandatorycourses','enrolledcount',
         'completedcount']];    
        $this->parent = true;
        $this->filters = array('organization','departments', 'subdepartments', 'level4department', 'level5department', 'learningpath');
        $this->orderable = array('learningpath_name','enrolledcount','completedcount');
        $this->defaultcolumn = 'lp.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(lp.id)";
    }
    function select() {

        $this->sql  = "SELECT lp.id as learningpathid,lp.name as learningplanname,lp.open_path as lp_open_path ";
         parent::select();                
    }
    function from() {
        $this->sql .= " FROM {local_learningplan} lp ";
    }
    function joins() {
          parent::joins();
    }
    function where(){
         global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
        $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();

        $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path'); 

        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }

         parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array('lp.name');
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
       
    } 
    function filters(){
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }
        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }
        if ($this->params['filter_level5department'] > 0) {
            $l5dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level5department'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :l5dept ";
            $this->params['l5dept'] = $l5dept.'/%';
        }
        if ($this->params['filter_learningpath'] > 0) {
            $lplan = $this->params['filter_learningpath'];
            $this->sql .= " AND lp.id = $lplan ";
        }
        // print_r($this->params);
        // echo $this->sql;exit;
    }
    public function get_rows($learningpaths) {
        global $DB;
        $data = array();
        if($learningpaths){
            $costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
            $sql = "SELECT count(llu.id)
                    FROM {local_learningplan_user} as llu
                    JOIN {user} u ON u.id = llu.userid AND u.deleted = 0 AND u.suspended = 0
                    WHERE llu.planid = :planid {$costcenterpathconcatsql} ";

            $completionscount = ' AND llu.status = :status ';
            $enrolsql = '';
            if($this->ls_startdate > 0 && $this->ls_enddate > 0){
                $enrolsql .= " AND llu.timecreated > :ls_startdate ";
                $completedsql .= " AND llu.completiondate > :ls_startdate ";
            // }
            // if($this->ls_enddate > 0){
                $enrolsql .= " AND llu.timecreated < :ls_enddate ";
                $completionscount .= " AND llu.completiondate < :ls_enddate ";
            }
            foreach ($learningpaths as $learningpath) {
                $learningpath->enrolledcount = $DB->count_records_sql($sql, array('planid' => $learningpath->learningpathid,'deleted' => 0, 'suspended' => 0, 'ls_startdate' => $this->ls_startdate, 'ls_enddate' => $this->ls_enddate));

                $learningpath->completedcount = $DB->count_records_sql($sql.$completionscount, array('planid' => $learningpath->learningpathid,'deleted' => 0,'suspended' => 0,'status' => 1, 'ls_startdate' => $this->ls_startdate, 'ls_enddate' => $this->ls_enddate));

                $data[] = $learningpath;
            }
        }
        return $data;
    }
}
