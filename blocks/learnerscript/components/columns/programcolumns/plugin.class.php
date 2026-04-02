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

class plugin_programcolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('programcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('program');
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

    // Data -> Plugin configuration data.
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
         global $DB, $USER;
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
                if ($this->reportfilterparams['filter_organization']>0) {
                    $costcenter = " AND bll.costcenterid IN (" .$this->reportfilterparams['filter_organization'] .','. 0 .")";
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND bll.departmentid = ".$this->reportfilterparams['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .")";
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND bll.departmentid = ".$this->reportfilterparams['filter_departments'];
                }
            } else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
               $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND bll.departmentid = ". $USER->open_departmentid ;
            } else {
                $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND bll.departmentid = ". $USER->open_departmentid. " AND bll.subdepartment = ". $USER->open_subdepartment ;
            }

            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
                $subdept = " AND bll.subdepartmentid = ".$this->reportfilterparams['filter_subdepartments'];
            } 
        }         
        switch ($data->column) {
            case 'enrolmentdate':
                if(!isset($row->enrolmentdate) && isset($data->subquery)){
                    $enrolmentdate = $DB->get_field_sql($data->subquery);
                }else{
                    $enrolmentdate = $row->{$data->column};
                }
                $row->{$data->column} = !empty($enrolmentdate) ? strftime('%d-%m-%Y', $enrolmentdate) : '--';
                break;
            case 'progress':
                if(!isset($row->progress) && isset($data->subquery)){
                    $progress = $DB->get_field_sql($data->subquery);
                }else{
                    if(!empty($row->completiondate)){
                        $completed = 100;
                        $enrolled = 100;
                    } else {
                        $enrolled = $DB->count_records('local_program_levels',  array('programid' => $row->programid));
                        $completed = $DB->count_records('local_bc_level_completions',  array('programid' => $row->programid, 'completion_status'=>1, 'userid'=>$row->userid));
                    }
                }
                $completionprogress = ROUND(($completed/$enrolled)*100,0);
                return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionprogress; progressbar'
                    data-labels = 'inprogress, completed' data-link='' >" . $completionprogress . "</div>";
                break;
            case 'completiondate':
                if(!isset($row->completiondate) && isset($data->subquery)){
                    $completiondate = $DB->get_field_sql($data->subquery);
                }else{
                    $completiondate = $row->{$data->column}; 
                }
                $row->{$data->column} = !empty($completiondate) ? strftime('%d-%m-%Y', $completiondate) : '--';
                break;
            case 'upcomingdeadline':
                if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                    $upcomingdeadline = $DB->get_field_sql($data->subquery);
                }else{
                   $upcomingdeadline = $DB->get_field_sql("SELECT bll.upcomingdeadline AS upcomingdeadline
                        FROM {block_ls_learningformats} AS bll
                        WHERE bll.upcomingdeadline > UNIX_TIMESTAMP() AND bll.id = {$row->id} AND bll.userid = $row->userid AND bll.completiondate = 0 AND bll.moduleid = 9 AND bll.learningformatid = {$row->programid} {$costcenter} {$dept} {$subdept} ");
                }
                $row->{$data->column} = !empty($upcomingdeadline) ? strftime('%d-%m-%Y', $upcomingdeadline) : '--';
                break;
            case 'overduedeadline':
                if(!isset($row->overduedeadline) && isset($data->subquery)){
                    $overduedeadline = $DB->get_field_sql($data->subquery);
                }else{
                    $overduedeadline = $DB->get_field_sql("SELECT bll.upcomingdeadline AS overduedeadline
                        FROM {block_ls_learningformats} AS bll
                        WHERE bll.upcomingdeadline < UNIX_TIMESTAMP() AND bll.id = {$row->id} AND bll.userid = $row->userid AND bll.completiondate = 0 AND bll.moduleid = 9 AND bll.upcomingdeadline !=0 AND bll.learningformatid = {$row->programid} {$costcenter} {$dept} {$subdept} "); 
                }
                $row->{$data->column} = !empty($overduedeadline) ? strftime('%d-%m-%Y', $overduedeadline) : '--';
                break;                                
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

}

