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
 * @package block_learnerscript
 */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;

class plugin_learningpathscolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('learningpathscolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('learningpaths');
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
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                 $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND bll.departmentid = ". $USER->open_departmentid ;
            } else {
                $costcenter = " AND bll.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND bll.departmentid = ". $USER->open_departmentid. " AND bll.subdepartment = " .$USER->open_subdepartment ;
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
                $subdept = " AND bll.subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
            } 
        }        
         $learning = " AND bll.learningformatid = {$row->planid}";
            switch ($data->column) {
            case 'enrolmentdate':
                if(!isset($row->enrolmentdate) && isset($data->subquery)){
                    $enrolmentdate = $DB->get_field_sql($data->subquery);
                }else{
                    $enrolmentdate = $row->{$data->column};
                }
                $row->{$data->column} = !empty($enrolmentdate) ? strftime('%d-%m-%Y', $enrolmentdate) : '--';
                break;
            case 'completiondate':
                if(!isset($row->dateofcompletion) && isset($data->subquery)){
                    $dateofcompletion = $DB->get_field_sql($data->subquery);
                }else{
                    $dateofcompletion = $row->{$data->column};
                }
                $row->{$data->column} = !empty($dateofcompletion) ? strftime('%d-%m-%Y', $dateofcompletion) : '--';
                break;
            case 'completiondeadline': 
                if(!isset($row->completiondeadline) && isset($data->subquery)){
                    $completiondeadline = $DB->get_field_sql($data->subquery);
                }else{
                    $completiondeadline = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completiondeadline) ? strftime('%d-%m-%Y', $completiondeadline) : '--';
                break;
            case 'upcomingdeadline': 
                if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                    $upcomingdeadline = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = " SELECT bll.upcomingdeadline AS upcomingdeadline
                            FROM {block_ls_learningformats} AS bll
                            WHERE bll.upcomingdeadline > UNIX_TIMESTAMP() AND bll.id = $row->id AND bll.userid = {$row->userid} AND bll.completiondate = 0 AND bll.moduleid = 8 {$learning} {$costcenter} {$dept} {$subdept} ";
                    $upcomingdata = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($upcomingdata) ? strftime('%d-%m-%Y', $upcomingdata) : '--';
                break;
            case 'overduedeadline': 
                if(!isset($row->overduedeadline) && isset($data->subquery)){
                    $overduedeadline = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = " SELECT bll.upcomingdeadline AS overduedeadline
                            FROM {block_ls_learningformats} AS bll
                            WHERE bll.upcomingdeadline < UNIX_TIMESTAMP() AND bll.id = $row->id AND bll.userid = {$row->userid} AND bll.upcomingdeadline != 0 AND bll.completiondate = 0 AND bll.moduleid = 8 {$learning} {$costcenter} {$dept} {$subdept}";
                    $overdue = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($overdue) ? strftime('%d-%m-%Y', $overdue) : '--';
                break;                                
            case 'progress':
                if(!isset($row->progress) && isset($data->subquery)){
                    $completionprogress = $DB->get_field_sql($data->subquery);
                }else{
                    if(!empty($row->completiondate)){
                        $completed = 100;
                        $enrolled = 100;
                    } else {
                        $sql = " SELECT GROUP_CONCAT(bll.learningformatid) AS courses 
                            FROM {block_ls_learningformats} AS bll   
                            WHERE 1=1 AND bll.userid = $row->userid AND bll.moduleid = 8 {$learning}";
                        $records = $DB->get_field_sql($sql);
                        $courses = explode(',',$records);
                        $enrolled = count($courses);
                        $i = $completed = 0;
                        foreach($courses as $course) {
                            if($course>0) {
                                $courserecords = $DB->get_record_sql("SELECT * FROM {course} WHERE id = $course");
                                $percent = progress::get_course_progress_percentage($courserecords, $row->userid);
                                if (!is_null($percent)) {
                                    $percent = floor($percent);
                                    if($percent == 100){
                                        $completed = ++$i;
                                    }
                                }
                            }
                        }
                    }
                }
                $completionprogress = ROUND(($completed/$enrolled)*100,0);
                return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionprogress; progressbar'
                        data-labels = 'inprogress, completed' data-link='' >" . $completionprogress . "</div>";
                break;
            }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
