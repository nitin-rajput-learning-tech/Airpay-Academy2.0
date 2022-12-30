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

class plugin_graphlearningcolumns extends pluginbase {
    public function init() {
        $this->fullname = get_string('graphlearning', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('graphlearning');
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
                if($data->column == 'learningpathenrollments' || $data->column == 'learningpathcompletions') {
                    if(isset($this->reportfilterparams['filter_organization'])) {
                        $costcenter = " AND llp.costcenter =".$this->reportfilterparams['filter_organization']; 
                    }
                    if($this->reportfilterparams['filter_departments']>0) {
                        $dept = " AND llp.department =".$this->reportfilterparams['filter_departments']; 
                    }
                } else if($data->column == 'instructorledcoursescompletions' || $data->column == 'instructorledcoursesenrollments') {
                    if(isset($this->reportfilterparams['filter_organization'])) {
                        $costcenter = " AND lc.costcenter =".$this->reportfilterparams['filter_organization'];
                    }
                    if($this->reportfilterparams['filter_departments'] > 0) {
                        $dept = " AND lc.department =".$this->reportfilterparams['filter_departments']; 
                    }
                } else {
                    if ($this->reportfilterparams['filter_organization']) {
                        $costcenter = " AND c.open_costcenterid = " .$this->reportfilterparams['filter_organization'];
                    }
                    if ($this->reportfilterparams['filter_departments'] > 0) {
                        $dept = " AND c.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                    }      
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                if($data->column == 'learningpathenrollments' || $data->column == 'learningpathcompletions') {
                    $costcenter = " AND llp.costcenter = " .$USER->open_costcenterid;
                    if($this->reportfilterparams['filter_departments']>0) {
                        $dept = " AND llp.department =".$this->reportfilterparams['filter_departments']; 
                    }
                } else if($data->column == 'instructorledcoursescompletions' || $data->column == 'instructorledcoursesenrollments') {
                    $costcenter = " AND lc.costcenter = " .$USER->open_costcenterid;
                    if($this->reportfilterparams['filter_departments'] > 0) {
                        $dept = " AND lc.department =".$this->reportfilterparams['filter_departments']; 
                    }
                } else {
                    $costcenter = " AND c.open_costcenterid = " .$USER->open_costcenterid; 
                    if ($this->reportfilterparams['filter_departments'] > 0) {
                        $dept = " AND c.open_departmentid = ".$this->reportfilterparams['filter_departments'];
                    }      
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                if($data->column == 'learningpathenrollments' || $data->column == 'learningpathcompletions') {
                    $costcenter = " AND llp.costcenter = " .$USER->open_costcenterid ." AND llp.department =".$USER->open_departmentid ;
                } else if($data->column == 'instructorledcoursescompletions' || $data->column == 'instructorledcoursesenrollments') {
                    $costcenter = "  AND lc.costcenter = " .$USER->open_costcenterid ." AND lc.department =".$USER->open_departmentid  ;
                } else {
                    $costcenter = " AND c.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid ;
                } 
            } else {
                if($data->column == 'learningpathenrollments' || $data->column == 'learningpathcompletions') {
                    $costcenter = " AND llp.costcenter = " .$USER->open_costcenterid ." AND llp.department =".$USER->open_departmentid." AND llp.subdepartment =".$USER->open_subdepartment ;
                } else if($data->column == 'instructorledcoursescompletions' || $data->column == 'instructorledcoursesenrollments') {
                    $costcenter = "  AND lc.costcenter = " .$USER->open_costcenterid ." AND lc.department =".$USER->open_departmentid." AND lc.subdepartment =".$USER->open_subdepartment  ;
                } else {
                    $costcenter = " AND c.open_costcenterid = " .$USER->open_costcenterid . " AND c.open_departmentid = ". $USER->open_departmentid. " AND c.open_subdepartment = ". $USER->open_subdepartment;
                }             
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
                $subdept = " AND c.open_subdepartment = ".$this->reportfilterparams['filter_subdepartments'];
            } 
        }
        if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
            $timefilter = " AND c.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
        }        
        $enrolmentssql = " SELECT COUNT(ue.userid) AS enrolments
                            FROM {user_enrolments} AS ue
                            join {enrol} AS e ON e.id = ue.enrolid 
                            join {course} AS c ON c.id = e.courseid AND c.visible=1
                            join {role_assignments} AS ra ON ra.userid = ue.userid
                            join {role} AS r ON r.id = ra.roleid AND r.shortname='employee'
                            JOIN {context} AS ct ON ct.id = ra.contextid AND ct.instanceid = c.id
                            WHERE 1  {$costcenter} {$dept} {$subdept} {$timefilter} AND DATE_FORMAT(from_unixtime(ue.timecreated), '%M') = '{$row->month}' ";
        $completionssql = "SELECT COUNT(cc.timecompleted) 
                            FROM {user_enrolments} AS ue
                            join {enrol} AS e ON e.id = ue.enrolid 
                            join {course} AS c ON c.id = e.courseid AND c.visible=1
                            join {role_assignments} AS ra ON ra.userid = ue.userid
                            join {role} AS r ON r.id = ra.roleid AND r.shortname='employee'
                            JOIN {context} AS ct ON ct.id = ra.contextid AND ct.instanceid = c.id
                            LEFT JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = ue.userid
                            WHERE 1 {$costcenter} {$dept} {$subdept} {$timefilter} AND DATE_FORMAT(from_unixtime(ue.timecreated), '%M') = '{$row->month}' ";
        switch ($data->column) {
            case 'month': 
                if(!isset($row->month) && isset($data->subquery)){
                    $month = $DB->get_field_sql($data->subquery);
                }else{
                    $month = $row->{$data->column};
                }
                $row->{$data->column} = !empty($month) ? $month : '--';
            break;
            case 'onlinecoursesenrolments': 
                if(!isset($row->onlinecoursesenrolments) && isset($data->subquery)){
                    $onlinecoursesenrolments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $enrolmentssql;
                    $sql .= " AND c.open_learningformat = 1 ";
                    $onlinecoursesenrolments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($onlinecoursesenrolments) ? $onlinecoursesenrolments : '--';
            break;
            case 'examenrolments': 
                if(!isset($row->examenrolments) && isset($data->subquery)){
                    $examenrolments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $enrolmentssql;
                    $sql .= " AND c.open_learningformat = 2  ";
                    $examenrolments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($examenrolments) ? $examenrolments : '--';
            break;
            case 'assessmentenrollments': 
                if(!isset($row->assessmentenrollments) && isset($data->subquery)){
                    $assessmentenrollments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $enrolmentssql;
                    $sql .= " AND c.open_learningformat = 3  ";
                    $assessmentenrollments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($assessmentenrollments) ? $assessmentenrollments : '--';
            break;
            case 'labenrollments': 
                if(!isset($row->labenrollments) && isset($data->subquery)){
                    $labenrollments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $enrolmentssql;
                    $sql .= " AND c.open_learningformat = 4  ";
                    $labenrollments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($labenrollments) ? $labenrollments : '--';
            break;
            case 'virtualcourseenrolments': 
                if(!isset($row->virtualcourseenrolments) && isset($data->subquery)){
                    $virtualcourseenrolments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $enrolmentssql;
                    $sql .= " AND c.open_learningformat = 5  ";
                    $virtualcourseenrolments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($virtualcourseenrolments) ? $virtualcourseenrolments : '--';
            break;
            case 'examvoucherenrolments': 
                if(!isset($row->examvoucherenrolments) && isset($data->subquery)){
                    $examvoucherenrolments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $enrolmentssql;
                    $sql .= " AND c.open_learningformat = 6  ";
                    $examvoucherenrolments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($examvoucherenrolments) ? $examvoucherenrolments : '--';
            break;
            case 'webinarenrollments': 
                if(!isset($row->webinarenrollments) && isset($data->subquery)){
                    $webinarenrollments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $enrolmentssql;
                    $sql .= " AND c.open_learningformat = 7  ";
                    $webinarenrollments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($webinarenrollments) ? $webinarenrollments : '--';
            break;
            case 'learningpathenrollments': 
                if(!isset($row->learningpathenrollments) && isset($data->subquery)){
                    $learningpathenrollments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = "SELECT count(llpu.timecreated) AS enrolments  
                           FROM {local_learningplan_user} AS llpu
                           JOIN {local_learningplan} AS llp ON llp.id = llpu.planid
                           JOIN {user} AS u ON u.id = llpu.userid  
                           WHERE 1=1 {$costcenter} {$dept} {$subdept} AND DATE_FORMAT(from_unixtime(llpu.timecreated), '%M') = '{$row->month}' ";
                    if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
                        $sql .= " AND llp.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
                    }
                    $learningpathenrollments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($learningpathenrollments) ? $learningpathenrollments : '--';
            break;
            case 'instructorledcoursesenrollments': 
                if(!isset($row->instructorledcoursesenrollments) && isset($data->subquery)){
                    $instructorledcoursesenrollments = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = "SELECT  COUNT(lcu.timecreated) AS enrolments 
                          FROM {local_classroom_users} as lcu
                          JOIN {local_classroom} as lc on lc.id = lcu.classroomid
                          JOIN {local_classroom_attendance} as lca on lca.classroomid = lc.id AND lca.userid = lcu.userid
                          JOIN {user} as u on u.id = lcu.userid  
                          WHERE 1=1 {$costcenter} {$dept} {$subdept} AND DATE_FORMAT(from_unixtime(lcu.timecreated), '%M') = '{$row->month}' ";
                    if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
                        $sql .= " AND lc.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
                    }                    
                    $instructorledcoursesenrollments = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($instructorledcoursesenrollments) ? $instructorledcoursesenrollments : '--';
            break; 
            case 'onlinecoursescompletions': 
                if(!isset($row->onlinecoursescompletions) && isset($data->subquery)){
                    $onlinecoursescompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $completionssql;
                    $sql .= " AND c.open_learningformat = 1  ";
                    $onlinecoursescompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($onlinecoursescompletions) ? $onlinecoursescompletions : '--';
            break;
            case 'examcompletions': 
                if(!isset($row->examcompletions) && isset($data->subquery)){
                    $examcompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $completionssql;
                    $sql .= " AND c.open_learningformat = 2  ";
                    $examcompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($examcompletions) ? $examcompletions : '--';
            break;
            case 'assessmentcompletions': 
                if(!isset($row->assessmentcompletions) && isset($data->subquery)){
                    $assessmentcompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $completionssql;
                    $sql .= " AND c.open_learningformat = 3  ";
                    $assessmentcompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($assessmentcompletions) ? $assessmentcompletions : '--';
            break;
            case 'labcompletions': 
                if(!isset($row->labcompletions) && isset($data->subquery)){
                    $labcompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $completionssql;
                    $sql .= " AND c.open_learningformat = 4  ";
                    $labcompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($labcompletions) ? $labcompletions : '--';
            break;
            case 'virtualcoursecompletions': 
                if(!isset($row->virtualcoursecompletions) && isset($data->subquery)){
                    $virtualcoursecompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $completionssql;
                    $sql .= " AND c.open_learningformat = 5  ";
                    $virtualcoursecompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($virtualcoursecompletions) ? $virtualcoursecompletions : '--';
            break;
            case 'examvouchercompletions': 
                if(!isset($row->examvouchercompletions) && isset($data->subquery)){
                    $examvouchercompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $completionssql;
                    $sql .= " AND c.open_learningformat = 6  ";
                    $examvouchercompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($examvouchercompletions) ? $examvouchercompletions : '--';
            break;
            case 'webinarcompletions': 
                if(!isset($row->webinarcompletions) && isset($data->subquery)){
                    $webinarcompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = $completionssql;
                    $sql .= " AND c.open_learningformat = 7  ";
                    $webinarcompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($webinarcompletions) ? $webinarcompletions : '--';
            break;
            case 'learningpathcompletions': 
                if(!isset($row->learningpathcompletions) && isset($data->subquery)){
                    $learningpathcompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = "SELECT COUNT(llpu.completiondate) AS completiondate  
                           FROM {local_learningplan_user} AS llpu
                           JOIN {local_learningplan} AS llp ON llp.id = llpu.planid
                           JOIN {user} AS u ON u.id = llpu.userid  
                           WHERE 1=1 {$costcenter} {$dept} {$subdept} AND DATE_FORMAT(from_unixtime(llpu.completiondate), '%M') = '{$row->month}' ";
                    if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
                        $sql .= " AND llp.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
                    }                    
                    $learningpathcompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($learningpathcompletions) ? $learningpathcompletions : '--';
            break;
            case 'instructorledcoursescompletions': 
                if(!isset($row->instructorledcoursescompletions) && isset($data->subquery)){
                    $instructorledcoursescompletions = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = "SELECT count(lcu.completiondate) AS completiondate 
                          FROM {local_classroom_users} as lcu
                          JOIN {local_classroom} as lc on lc.id = lcu.classroomid
                          JOIN {local_classroom_attendance} as lca on lca.classroomid = lc.id AND lca.userid = lcu.userid
                          JOIN {user} as u on u.id = lcu.userid  
                          WHERE 1=1 {$costcenter} {$dept} {$subdept} AND lca.status = 1 AND lca.enrol_status = 0 AND DATE_FORMAT(from_unixtime(lcu.completiondate), '%M') = '{$row->month}' ";
                    if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
                        $sql .= " AND lc.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
                    }                    
                    $instructorledcoursescompletions = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($instructorledcoursescompletions) ? $instructorledcoursescompletions : '--';
            break;                                    
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
