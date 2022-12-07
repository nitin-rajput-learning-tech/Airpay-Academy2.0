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
/** LearnerScript Reports
  * A Moodle block for creating customizable reports
  * @package blocks
  * @subpackage learnerscript
 * @author: Revanth Kumar
 * @date: 2021
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;
use block_learnerscript\local\reportbase;

class plugin_graphexamenrolmentscolumns extends pluginbase {
    public function init() {
        $this->fullname = get_string('graphexamenrolments', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('graphexamenrolments');
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG,$OUTPUT, $USER;
        $context = context_system::instance();
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = $dhs = 1;
            }
        }
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){ 
                if ($this->reportfilterparams['filter_organization']) {
                    $costcenter = " AND c.open_costcenterid = " .$this->reportfilterparams['filter_organization']; 
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND c.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                $costcenter = " AND c.open_costcenterid = " .$USER->open_costcenterid; 
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND c.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
               $costcenter = " AND c.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ;
            } else { 
                $costcenter = " AND c.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid. " AND c.open_subdepartment = ".$USER->open_subdepartment ; 
            } 
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
                $subdept = " AND c.open_subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
            }
        }  
        if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
            $timefilter = " AND c.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
        }
        switch ($data->column) {
            case 'month': 
                if(!isset($row->month) && isset($data->subquery)){
                    $month = $DB->get_field_sql($data->subquery);
                }else{
                    $month = date("F", mktime(null, null, null, $row->month));
                }
                $row->{$data->column} = !empty($month) ? $month : '--';
            break;
            case 'enrolments': 
                if(!isset($row->enrolments) && isset($data->subquery)){
                    $enrolments = $DB->get_field_sql($data->subquery);
                }else{
                    $enrolments = $row->{$data->column};
                }
                $row->{$data->column} = !empty($enrolments) ? $enrolments : '--';
            break;
            case 'completed': 
                if(!isset($row->completed) && isset($data->subquery)){
                    $completed = $DB->get_field_sql($data->subquery);
                }else{
                    $completed = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completed) ? $completed : '--';
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
