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
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;
use block_learnerscript\local\reportbase;

class plugin_programanalysis extends pluginbase {

    public function init() {
        $this->fullname = get_string('programanalysis', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array();
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
        global $DB, $CFG;
        $context = context_system::instance();
        $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'programs'), IGNORE_MULTIPLE);
        $learneroverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);

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
                if ($this->reportfilterparams['filter_organization']>0) {
                    $costcenter = $this->reportfilterparams['filter_organization'];
                    $filtercostcenter = " AND lp.costcenter = ".$this->reportfilterparams['filter_organization'];
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = $this->reportfilterparams['filter_departments'];
                    $filterdept = " AND lp.department = ".$this->reportfilterparams['filter_departments'];
                }      
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                $costcenter = $USER->open_costcenterid;
                $filtercostcenter = " AND lp.costcenter = ".$USER->open_costcenterid;
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = $this->reportfilterparams['filter_departments'];
                    $filterdept = " AND lp.department = ".$this->reportfilterparams['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                 $costcenter = $USER->open_costcenterid;
                $filtercostcenter = " AND lp.costcenter = ".$USER->open_costcenterid;
                $dept = $USER->open_departmentid;
                $filterdept = " AND lp.department = ".$USER->open_departmentid;
            } else {
                $costcenter = $USER->open_costcenterid;
                $filtercostcenter = " AND lp.costcenter = ".$USER->open_costcenterid;
                $dept = $USER->open_departmentid;
                $filterdept = " AND lp.department = ".$USER->open_departmentid;
                $subdept = $USER->open_subdepartment;
                $filtersubdept = " AND lp.subdepartment = ".$USER->open_subdepartment;
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
                $subdept = $this->reportfilterparams['filter_subdepartments'];
                $filtersubdept = " AND lp.subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
            } 
        } 

        $enrol = $DB->get_field_sql("SELECT COUNT(lpu.id)
            FROM {local_program_users} as lpu
            WHERE lpu.programid = $row->id ");
        $completed = $DB->get_field_sql("SELECT COUNT(lpu.id)
            FROM {local_program_users} lpu
            WHERE 1 AND lpu.completion_status != 0 AND lpu.completiondate != 0 AND lpu.programid = $row->id ");
        switch ($data->column) {
            case 'enrolments':
                if(!isset($row->enrolments) && isset($data->subquery)){
                    $enrolments = $DB->get_field_sql($data->subquery);
                }else{
                    $enrolments = $row->{$data->column};
                }
             $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept,'filter_subdepartments' => $subdept, 'filter_programs' => $row->id ));
                  $row->{$data->column} = html_writer::tag('a', $enrolments,
                  array('href' => $allurl));
                break;
            case 'inprogress':
                if(!isset($row->inprogress) && isset($data->subquery)){
                    $inprogress = $DB->get_field_sql($data->subquery);
                }else{
                    $inprogress = ($enrol-$completed);
                }
             $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept,'filter_subdepartments' => $subdept, 'filter_programs' => $row->id, 'filter_status' => 'inprogress' ));
                  $row->{$data->column} = html_writer::tag('a', $inprogress,
                  array('href' => $allurl));
                break;
            case 'completed':
                if(!isset($row->completed) && isset($data->subquery)){
                    $completed = $DB->get_field_sql($data->subquery);
                }else{
                    $completed = $completed; 
                }
             $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept,'filter_subdepartments' => $subdept, 'filter_programs' => $row->id, 'filter_status' => 'completed' ));
                  $row->{$data->column} = html_writer::tag('a', $completed,
                  array('href' => $allurl));
                break;
            case 'upcomingdeadline':
                if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                    $upcomingdeadline = $DB->get_field_sql($data->subquery);
                }else{
                    $upcomingdeadline = $DB->get_field_sql("SELECT COUNT(lpu.programdeadline) AS upcomingdeadline
                        FROM {local_program_users} AS lpu
                        JOIN {local_program} AS lp ON lp.id = lpu.programid
                        WHERE lpu.programdeadline > UNIX_TIMESTAMP() AND lpu.programid = {$row->id} AND lpu.completiondate = 0 AND lpu.completion_status = 0 AND lpu.programdeadline != 0 ");
                }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept,'filter_subdepartments' => $subdept, 'filter_programs' => $row->id, 'filter_status' => 'upcoming' ));
                  $row->{$data->column} = html_writer::tag('a', $upcomingdeadline,
                  array('href' => $allurl));
                break;
            case 'overduedeadline':
                if(!isset($row->overduedeadline) && isset($data->subquery)){
                    $overduedeadline = $DB->get_field_sql($data->subquery);
                }else{
                    $overduedeadline = $DB->get_field_sql("SELECT COUNT(lpu.programdeadline) AS upcomingdeadline
                        FROM {local_program_users} AS lpu
                        JOIN {local_program} AS lp ON lp.id = lpu.programid
                        WHERE lpu.programdeadline < UNIX_TIMESTAMP() AND lpu.programid = {$row->id} AND lpu.completiondate = 0 AND lpu.completion_status = 0 AND lpu.programdeadline != 0 ");
                }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $costcenter, 'filter_departments' => $dept,'filter_subdepartments' => $subdept, 'filter_programs' => $row->id, 'filter_status' => 'overdue' ));
                  $row->{$data->column} = html_writer::tag('a', $overduedeadline,
                  array('href' => $allurl));
                break;
        }

        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
}
