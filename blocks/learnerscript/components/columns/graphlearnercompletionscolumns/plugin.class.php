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

class plugin_graphlearnercompletionscolumns extends pluginbase {
    public function init() {
        $this->fullname = get_string('graphlearnercompletions', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('graphlearnercompletions');
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
        $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'examenrolments'), IGNORE_MULTIPLE);
        $courseoverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            } else {
                $ohs = 1;
            }
        }
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){
                $parentstatus = " AND lcc.parentid = 0 ";
                if ($this->reportfilterparams['filter_organization']) {
                    $costcenter = " AND u.open_costcenterid = " .$this->reportfilterparams['filter_organization']; 
                }

                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND u.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                }
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs){
                    $parentstatus = " AND lcc.parentid = 0 ";
                    $costcenter = " AND u.open_costcenterid = " .$USER->open_costcenterid; 
                    if ($this->params['filter_departments'] > 0) {
                        $dept = " AND u.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                    }
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $context) && $ohs){
                $parentstatus = " AND lcc.parentid > 0 ";
                $costcenter = " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ; 
            }else{
                $parentstatus = " AND lcc.parentid > 0 ";
                $costcenter = " AND u.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid. " AND u.open_subdepartment = ".$USER->open_subdepartment ; 
            }
            if ($this->params['filter_subdepartments'] > 0) {
                $subdept = " AND u.open_subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
            }
            if ($this->ls_startdate >= 0 && $this->ls_enddate) {
                $timefilter = " AND c.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
            }
        }
        switch ($data->column) {
            case 'month': 
                if(!isset($row->month) && isset($data->subquery)){
                    $month = $DB->get_field_sql($data->subquery);
                }else{
                    $month = $row->{$data->column};
                }
                $row->{$data->column} = !empty($month) ? $month : '--';
            break;
            case 'completions': 
                if(!isset($row->completions) && isset($data->subquery)){
                    $completions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = "SELECT COUNT(cc.timecompleted) AS 'completed'
                            FROM {local_costcenter} AS lcc
                            JOIN {user} AS u ON u.open_costcenterid = lcc.id OR u.open_departmentid = lcc.id
                            JOIN {user_enrolments} AS ue ON ue.userid = u.id
                            JOIN {enrol} AS e ON e.id = ue.enrolid 
                            JOIN {course} AS c ON c.id = e.courseid AND c.visible=1
                            JOIN {role_assignments} ra ON ra.userid = ue.userid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                            JOIN {context} AS ctx ON ctx.id = ra.contextid
                            LEFT JOIN {course_completions} AS cc on cc.course = c.id AND cc.userid = ue.userid 
                            WHERE c.id = ctx.instanceid AND u.confirmed=1 AND u.deleted=0 AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND DATE_FORMAT(from_unixtime(cc.timecompleted), '%M') = '{$row->month}' {$parentstatus} {$costcenter} {$dept} {$subdept} {$timefilter} ";
                    $completions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($completions) ? $completions : '--';
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}

 
