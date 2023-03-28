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

class report_programsoverview extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'filters', 'permissions','orderable','plot');
        $this->columns = ['progarmfield' => ['programfield'],'programsoverviewcolumns'=>['programname', 'stream', 'levelscount', 'coursescount', 'enrollmentscount','completionscount']];
        $this->parent = true;
        $this->filters = array('organization','departments', 'subdepartments','level4department','programs');
        $this->orderable = array('programname','enrollmentscount','completionscount');
        $this->defaultcolumn = 'lp.id';
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(lp.id)";
    }
    function select() {
        $this->sql = "SELECT lp.id as programid, lp.name as programname,cat.fullname as stream,
                            (SELECT COUNT(pl.id)
                            FROM {local_program_levels} pl 
                            WHERE pl.programid = lp.id) AS levelscount,
                            (SELECT COUNT(plc.id)
                            FROM {local_program_level_courses} plc 
                            WHERE plc.programid = lp.id) AS coursescount, 
                            (SELECT COUNT(plu.id)
                            FROM {local_program_users} plu
                            JOIN {user} u ON u.id = plu.userid AND u.suspended = 0 AND u.deleted = 0
                            WHERE plu.programid = lp.id) AS enrollmentscount,
                            (SELECT COUNT(plu.id)
                            FROM {local_program_users} plu
                            JOIN {user} u ON u.id = plu.userid AND u.suspended = 0 AND u.deleted = 0
                            WHERE plu.programid = lp.id AND plu.completion_status = 1) AS completionscount  ";
      parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_program} lp";
    }
    function joins() {
        $this->sql .= "  LEFT JOIN {local_custom_category} cat ON cat.id = lp.open_categoryid ";
       //  $this->sql .= " JOIN {local_program_stream} ps ON ps.id = lp.stream";
          parent::joins();
    }
    function where(){
         global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
        $this->params['siteid'] = SITEID;
        $systemcontext = \context_system::instance();
        // getscheduled report
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = $dh = 1;
            }
        }
        $costcenterpathconcatsql = (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }
         parent::where();
    }
    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('lp.name');
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }   
    function filters() {
      
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(lp.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments'] > 0) {
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
        if ($this->params['filter_programs'] > 0) {
           $this->sql .= " AND lp.id = :program ";
           $this->params['program']= $this->params['filter_programs'];
        }
    }
    public function get_rows($programs) {
        return $programs;
    }
}
