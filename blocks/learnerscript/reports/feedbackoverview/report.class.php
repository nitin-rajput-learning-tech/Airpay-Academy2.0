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

class report_feedbackoverview extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
      
        $this->parent = true;
        $this->columns = ['feedbackfield'=>['feedbackfield'],'feedbackoverviewcolumns' => ['enrolmentscount','completionscount']];
        $this->components = array('columns', 'filters', 'permissions','orderable','plot');
        $this->filters = array('organization', 'departments', 'subdepartments', 'feedbacks');
        $this->orderable = array('feedbackname','enrolmentscount','completionscount');
        $this->defaultcolumn = 'le.id';
        $this->reportid = $report->reportid;
        $this->scheduleflag = ($report->scheduling) ? true : false;
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(le.id)";
    }
    function select() {
        $this->sql = "SELECT le.id as feedbackid, le.name as feedbackname,
                            (SELECT COUNT(eu.id) 
                                FROM {local_evaluation_users} as eu
                                JOIN {user} as u ON u.id = eu.userid AND u.deleted = 0 AND u.suspended = 0
                                WHERE eu.evaluationid = le.id) as enrolmentscount,
                            (SELECT COUNT(ec.id) 
                                FROM {local_evaluation_completed} as ec
                                JOIN {user} as u ON u.id = ec.userid AND u.deleted = 0 AND u.suspended = 0
                                WHERE ec.evaluation = le.id) as completionscount ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_evaluations} le";
    }
    function joins() {
          parent::joins();
    }
    function where(){
        global $USER, $DB;
        $this->sql .=  " WHERE le.instance = 0 AND le.deleted  = 0 ";
        if (!is_siteadmin()  && $this->scheduleflag ) {  
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid' => $this->reportid, 'sendinguserid' => $USER->id], IGNORE_MULTIPLE);
        }
        $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='le.open_path', null, 'lowerandsamepath');
 
        if (is_siteadmin()) {
           $this->sql .= "";
        } else  if (!empty($scheduledreport) &&  $this->scheduleflag ) {                      
             $usercostcenterpath =  $DB->get_record_sql("SELECT cc.path FROM {role_assignments} AS ra
                JOIN {context} AS c ON c.id = ra.contextid AND c.contextlevel = 40
                JOIN {course_categories} AS cc ON cc.id = c.instanceid
                JOIN {role} AS r ON r.id = ra.roleid
                WHERE ra.userid =:userid",array('userid' => $USER->id));   

            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='le.open_path',  $usercostcenterpath, 'lowerandsamepath');
        }
       
        $this->sql .= $costcenterpathconcatsql;       
        parent::where();
    }
    function search() {  
       if (isset($this->search) && $this->search) {
            $fields = array('le.name');
            $fields = implode(" LIKE '%$this->search%' OR ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters() {   
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(le.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(le.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }
        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(le.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'/%';
        }

        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(le.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'/%';
        }

        if (!empty($this->params['filter_feedbacks'])) {
             $this->sql .= " AND le.id = :feedbackid ";
             $this->params['feedbackid']= $this->params['filter_feedbacks'];
        }

    }   
    public function get_rows($feedbacks = array()) {
        return $feedbacks;
    }
}
