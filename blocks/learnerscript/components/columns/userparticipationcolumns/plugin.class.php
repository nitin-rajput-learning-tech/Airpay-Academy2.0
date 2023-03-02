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

class plugin_userparticipationcolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('userparticipationcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('userparticipation');
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
        global $DB;
       
        switch ($data->column) {
            case 'coursesenrolled':
                $sql = "SELECT COUNT(DISTINCT c.id) AS enrolled 
                            FROM {user_enrolments} ue   
                            JOIN {enrol} e ON ue.enrolid = e.id 
                            JOIN {role_assignments} ra ON ra.userid = ue.userid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                            JOIN {context} AS ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 
                            WHERE e.courseid = c.id AND ue.userid = :userid";
                $params = array('userid'=>$row->userid);
                $enrolledcourses = $DB->count_records_sql($sql, $params);
                if($enrolledcourses){
                    $row->{$data->column} = $enrolledcourses;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'coursesinprogress':               
                $sql = "SELECT (COUNT(DISTINCT c.id) - COUNT(DISTINCT cc.id)) AS inprogress 
                          FROM {user_enrolments} ue   
                          JOIN {enrol} e ON ue.enrolid = e.id 
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid 
                          LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0 
                         WHERE e.courseid = c.id AND ue.userid = :userid";
                $params = array('userid'=>$row->userid);
                $coursesinprogress = $DB->count_records_sql($sql, $params);
                if($coursesinprogress){
                    $row->{$data->column} = $coursesinprogress;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;
            case 'coursescompleted':
                $sql = "SELECT COUNT(DISTINCT cc.course) AS completed 
                          FROM {user_enrolments} ue   
                          JOIN {enrol} e ON ue.enrolid = e.id 
                          JOIN {role_assignments} ra ON ra.userid = ue.userid
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                          JOIN {context} AS ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid 
                          JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0 
                          WHERE e.courseid = c.id AND ue.userid = :userid";
                $params = array('userid'=>$row->userid);
                $coursescompleted = $DB->count_records_sql($sql, $params);
                if($coursescompleted){
                    $row->{$data->column} = $coursescompleted;
                }else{
                    $row->{$data->column} = 0;
                }  
                break;
            case 'coursesprogress':
                $sql = "SELECT ROUND((COUNT(distinct cc.course) / COUNT(DISTINCT c.id)) *100, 2) as progress 
                            FROM {user_enrolments} ue   
                            JOIN {enrol} e ON ue.enrolid = e.id 
                            JOIN {role_assignments} ra ON ra.userid = ue.userid
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                            JOIN {context} AS ctx ON ctx.id = ra.contextid
                            JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1 
                            LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid 
                            AND cc.timecompleted > 0 WHERE  e.courseid = c.id AND ue.userid = :userid";
                $params = array('userid'=>$row->userid);
                $coursesprogress = $DB->get_field_sql($sql, $params);
                if($coursesprogress){
                    $row->{$data->column} = $coursesprogress;
                }else{
                    $row->{$data->column} = 0;
                }  
                break;   
            case 'iltenrolled':
                $sql = "SELECT count(lc.id) 
                            FROM {local_classroom} AS lc
                            JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                            WHERE lcu.userid = :userid ";
                $params = array('userid'=>$row->userid);
                $iltenrolled = $DB->count_records_sql($sql, $params);
                if($iltenrolled){
                    $row->{$data->column} = $iltenrolled;
                }else{
                    $row->{$data->column} = 0;
                }     
                break;
            case 'iltinprogress':               
                $sql = "SELECT count(lc.id) 
                            FROM {local_classroom} AS lc
                            JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                            WHERE lcu.userid = :userid AND lcu.completion_status= :status";
                $params = array('userid'=>$row->userid,'status' => 0);
                $iltinprogress = $DB->count_records_sql($sql, $params);
                if($iltinprogress){
                    $row->{$data->column} = $iltinprogress;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'iltcompleted':               
                $sql = "SELECT count(lc.id) 
                            FROM {local_classroom} AS lc
                            JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                            WHERE lcu.userid = :userid AND lcu.completion_status= :status";
                $params = array('userid'=>$row->userid,'status' => 1);
                $iltcompleted = $DB->count_records_sql($sql, $params);
                if($iltcompleted){
                    $row->{$data->column} = $iltcompleted;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'iltprogress':               
                $completedcount = $DB->count_records_sql("select count(cu.id) from {local_classroom_users} cu where cu.userid = ? AND cu.completion_status=?", array($row->userid, 1));
                $enrolledcount = $DB->count_records_sql("select count(cu.id) from {local_classroom_users} cu where cu.userid = ? ", array($row->userid));
              
                $iltprogress = ROUND(($completedcount / $enrolledcount) *100, 2);
                $iltprogress = is_NAN($iltprogress) ? 0 : $iltprogress;
                if($iltprogress){
                    $row->{$data->column} = $iltprogress;
                }else{
                    $row->{$data->column} = 0;
                }
                break;         
            case 'lpenrolled':
                //$completedcount = $DB->count_records_sql("select count(cu.id) from {local_learningplan_user} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.planid=? AND cu.status=?", array($lpid, 1));
                $sql = "SELECT count(lpu.id) 
                            FROM {local_learningplan_user} lpu
                            WHERE lpu.userid = :userid ";                
                $params = array('userid'=>$row->userid);
                $lpenrolled = $DB->count_records_sql($sql, $params);
                if($lpenrolled){
                    $row->{$data->column} = $lpenrolled;
                }else{
                    $row->{$data->column} = 0;
                }     
                break;
            case 'lpinprogress':               
                $sql = "SELECT count(lpu.id) 
                            FROM {local_learningplan_user} lpu
                            WHERE lpu.userid = :userid AND lpu.completiondate IS NULL AND lpu.status IS NULL";
                $params = array('userid'=>$row->userid,'status' => 0);
                $lpinprogress = $DB->count_records_sql($sql, $params);
                if($lpinprogress){
                    $row->{$data->column} = $lpinprogress;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'lpcompleted':               
                $sql = "SELECT count(lpu.id) 
                            FROM {local_learningplan_user} lpu
                            WHERE lpu.userid = :userid AND lpu.completiondate IS NOT NULL AND lpu.status = :status";
                $params = array('userid'=>$row->userid,'status' => 1);
                $lpcompleted = $DB->count_records_sql($sql, $params);
                if($lpcompleted){
                    $row->{$data->column} = $lpcompleted;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'lpprogress':               
                $lpcompletedcount = $DB->count_records_sql("select count(lpu.id) from {local_learningplan_user} lpu WHERE lpu.status=?", array(1));
                $lpenrolledcount = $DB->count_records_sql("select count(lpu.id) from {local_learningplan_user} lpu ");
        
                $lpprogress = ROUND(($lpcompletedcount / $lpenrolledcount) *100, 2);
                $lpprogress = is_NAN($lpprogress) ? 0 : $lpprogress;
                if($lpprogress){
                    $row->{$data->column} = $lpprogress;
                }else{
                    $row->{$data->column} = 0;
                }
                break;                       
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
        
    }

}
