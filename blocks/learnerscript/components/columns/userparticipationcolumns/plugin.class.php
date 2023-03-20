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
                $enrolledcourses = $this->course_enrolled($row->userid);
                if($enrolledcourses){
                    $row->{$data->column} = $enrolledcourses;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'coursesinprogress':  
                $coursesinprogress =  $this->course_inprogress($row->userid);
                if($coursesinprogress){
                    $row->{$data->column} = $coursesinprogress;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;
            case 'coursescompleted':
                $coursescompleted =  $this->course_completed($row->userid);
                if($coursescompleted){
                    $row->{$data->column} = $coursescompleted;
                }else{
                    $row->{$data->column} = 0;
                }  
                break;
            case 'coursesprogress':
                $enrolledcount = $this->course_enrolled($row->userid);
                $completedcount = $this->course_completed($row->userid);
                $coursesprogress = intval(($completedcount / $enrolledcount) * 100);              
               
                $row->{$data->column} ='<div class="progress">
                        <div class="progress-bar text-center" role="progressbar" aria-valuenow="'.$coursesprogress.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$coursesprogress.'%">
                            <span class="progress_percentage ml-2">'.$coursesprogress.'% Complete</span>
                        </div>
                    </div>';
               
                break;   
            case 'iltenrolled':             
                $iltenrolled = $this->ilt_enrolled($row->userid);
                if($iltenrolled){
                    $row->{$data->column} = $iltenrolled;
                }else{
                    $row->{$data->column} = 0;
                }     
                break;
            case 'iltinprogress':               
                $iltinprogress = $this->ilt_inprogress($row->userid);
                if($iltinprogress){
                    $row->{$data->column} = $iltinprogress;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'iltcompleted':               
                $iltcompleted = $this->ilt_completed($row->userid);
                if($iltcompleted){
                    $row->{$data->column} = $iltcompleted;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'iltprogress':                               
                /* $completedcount = $DB->count_records_sql("select count(cu.id) from {local_classroom_users} cu where cu.userid = ? AND cu.completion_status=?", array($row->userid, 1));
                $enrolledcount = $DB->count_records_sql("select count(cu.id) from {local_classroom_users} cu where cu.userid = ? ", array($row->userid));
               */
                $iltenrolledcount = $this->ilt_enrolled($row->userid);
                $iltcompletedcount = $this->ilt_completed($row->userid);
                $iltprogress = intval(($iltcompletedcount / $iltenrolledcount) * 100);
                //$iltprogress = is_NAN($iltprogress) ? 0 : $iltprogress;
                $row->{$data->column} ='<div class="progress">
                            <div class="progress-bar text-center" role="progressbar" aria-valuenow="'.$iltprogress.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$iltprogress.'%">
                                <span class="progress_percentage ml-2">'.$iltprogress.'% Complete</span>
                            </div>
                        </div>';
               
                break;         
            case 'lpenrolled':
                $lpenrolled = $this->lp_enrolled($row->userid);
                if($lpenrolled){
                    $row->{$data->column} = $lpenrolled;
                }else{
                    $row->{$data->column} = 0;
                }     
                break;
            case 'lpinprogress':               
                $lpinprogress = $this->lp_inprogress($row->userid);
                if($lpinprogress){
                    $row->{$data->column} = $lpinprogress;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'lpcompleted':               
                $lpcompleted = $this->lp_completed($row->userid);
                if($lpcompleted){
                    $row->{$data->column} = $lpcompleted;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'lpprogress':               
                /* $lpcompletedcount = $DB->count_records_sql("select count(lpu.id) from {local_learningplan_user} lpu WHERE lpu.status=?", array(1));
                $lpenrolledcount = $DB->count_records_sql("select count(lpu.id) from {local_learningplan_user} lpu "); */
                $lpenrolledcount = $this->lp_enrolled($row->userid);
                $lpcompletedcount = $this->lp_completed($row->userid);
                $lpprogress = intval(($lpcompletedcount / $lpenrolledcount) * 100);               
                $row->{$data->column} ='<div class="progress">
                        <div class="progress-bar text-center" role="progressbar" aria-valuenow="'.$lpprogress.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$lpprogress.'%">
                            <span class="progress_percentage ml-2">'.$lpprogress.'% Complete</span>
                        </div>
                    </div>';
             
                break; 
           
            case 'programenrolled':               
                $programenrolled = $this->program_enrolled($row->userid);
                if($programenrolled){
                    $row->{$data->column} = $programenrolled;
                }else{
                    $row->{$data->column} = 0;
                }
                break; 
            case 'programinprogress':               
                $programinprogress = $this->program_inprogress($row->userid);
                if($programinprogress){
                    $row->{$data->column} = $programinprogress;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;  
            case 'programcompleted':               
                $programcompleted = $this->program_completed($row->userid);
                if($programcompleted){
                    $row->{$data->column} = $programcompleted;
                }else{
                    $row->{$data->column} = 0;
                }
                break;
            case 'programprogress': 
                $programenrolled = $this->program_enrolled($row->userid);              
                $programcompleted = $this->program_completed($row->userid);
                $programprogress = intval(($programcompleted / $programenrolled) * 100);               
                $row->{$data->column} ='<div class="progress">
                    <div class="progress-bar text-center" role="progressbar" aria-valuenow="'.$programprogress.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$programprogress.'%">
                        <span class="progress_percentage ml-2">'.$programprogress.'% Complete</span>
                    </div>
                </div>';
            
                break;   
            default:
                $row->{$data->column} = isset($row->{$data->column}) ? $row->{$data->column} : $row->{$data->column};
            break;                      
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

    public function course_enrolled($userid){
        global $DB;
        $sql = "SELECT COUNT(DISTINCT c.id) AS enrolled 
                    FROM {user_enrolments} ue   
                    JOIN {enrol} e ON ue.enrolid = e.id 
                    JOIN {role_assignments} ra ON ra.userid = ue.userid
                    JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                    JOIN {context} AS ctx ON ctx.id = ra.contextid
                    JOIN {course} c ON c.id = ctx.instanceid 
                    WHERE e.courseid = c.id AND ue.userid = :userid";
        $params = array('userid'=>$userid);
        $enrolledcourses = $DB->count_records_sql($sql, $params);
        return $enrolledcourses;
    }

    public function course_inprogress($userid){
        global $DB;
        $sql = "SELECT (COUNT(DISTINCT c.id) - COUNT(DISTINCT cc.id)) AS inprogress 
                    FROM {user_enrolments} ue   
                    JOIN {enrol} e ON ue.enrolid = e.id 
                    JOIN {role_assignments} ra ON ra.userid = ue.userid
                    JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                    JOIN {context} AS ctx ON ctx.id = ra.contextid
                    JOIN {course} c ON c.id = ctx.instanceid 
                    LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0 
                WHERE e.courseid = c.id AND ue.userid = :userid";
        $params = array('userid'=>$userid);
        $inprogresscount = $DB->count_records_sql($sql, $params);        
        return $inprogresscount;
    }

    public function course_completed($userid){
        global $DB;
        $sql = "SELECT COUNT(DISTINCT cc.course) AS completed 
                    FROM {user_enrolments} ue   
                    JOIN {enrol} e ON ue.enrolid = e.id 
                    JOIN {role_assignments} ra ON ra.userid = ue.userid
                    JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                    JOIN {context} AS ctx ON ctx.id = ra.contextid
                    JOIN {course} c ON c.id = ctx.instanceid 
                    JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid AND cc.timecompleted > 0 
                    WHERE e.courseid = c.id AND ue.userid = :userid";
        $params = array('userid'=>$userid);
        $coursescompleted = $DB->count_records_sql($sql, $params);      
        return $coursescompleted;
    }

    public function course_progress($userid){
        global $DB;
        $sql = "SELECT ROUND((COUNT(distinct cc.course) / COUNT(DISTINCT c.id)) *100, 2) as progress 
                    FROM {user_enrolments} ue   
                    JOIN {enrol} e ON ue.enrolid = e.id 
                    JOIN {role_assignments} ra ON ra.userid = ue.userid
                    JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                    JOIN {context} AS ctx ON ctx.id = ra.contextid
                    JOIN {course} c ON c.id = ctx.instanceid
                    LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ue.userid 
                    AND cc.timecompleted > 0 WHERE  e.courseid = c.id AND ue.userid = :userid";
        $params = array('userid'=>$userid);
        $coursesprogress = $DB->get_field_sql($sql, $params);
        return $coursesprogress;
    }

    public function ilt_enrolled($userid){
        global $DB;
        $sql = "SELECT count(lc.id) 
                    FROM {local_classroom} AS lc
                    JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                    WHERE lcu.userid = :userid ";
        $params = array('userid'=>$userid);
        $iltenrolled = $DB->count_records_sql($sql, $params);
        return $iltenrolled ;
    }

    public function ilt_inprogress($userid){
        global $DB;
        $sql = "SELECT count(lc.id) 
                    FROM {local_classroom} AS lc
                    JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                    WHERE lcu.userid = :userid AND lcu.completion_status= :status";
        $params = array('userid'=>$userid,'status' => 0);
        $iltinprogress = $DB->count_records_sql($sql, $params);
        return $iltinprogress ;
    }

    public function ilt_completed($userid){
        global $DB;
        $sql = "SELECT count(lpu.id) 
                    FROM {local_learningplan_user} lpu
                    WHERE lpu.userid = :userid AND lpu.completiondate IS NOT NULL AND lpu.status = :status";
        $params = array('userid'=>$userid,'status' => 1);
        $lpcompleted = $DB->count_records_sql($sql, $params);
        return $lpcompleted ;
    }

    public function lp_enrolled($userid){
        global $DB;
        $sql = "SELECT count(lpu.id) 
                    FROM {local_learningplan_user} lpu
                    WHERE lpu.userid = :userid ";                
        $params = array('userid'=>$userid);
        $lpenrolled = $DB->count_records_sql($sql, $params);
        return $lpenrolled;
    }

    public function lp_inprogress($userid){
        global $DB;
        $sql = "SELECT count(lpu.id) 
                    FROM {local_learningplan_user} lpu
                    WHERE lpu.userid = :userid AND lpu.completiondate IS NULL AND lpu.status IS NULL";
        $params = array('userid'=>$userid,'status' => 0);
        $lpinprogress = $DB->count_records_sql($sql, $params);
        return $lpinprogress;
    }

    public function lp_completed($userid){
        global $DB;
        $sql = "SELECT count(lpu.id) 
                    FROM {local_learningplan_user} lpu
                    WHERE lpu.userid = :userid AND lpu.completiondate IS NOT NULL AND lpu.status = :status";
        $params = array('userid'=>$userid,'status' => 1);
        $lpcompleted = $DB->count_records_sql($sql, $params);
        return $lpcompleted;
    }

    public function program_enrolled($userid){
        global $DB;
        $sql = "SELECT COUNT(pg.id)
                    FROM {local_program} as pg
                    JOIN {local_program_users} AS pgu ON pg.id = pgu.programid
                    WHERE pgu.userid = :userid ";//AND bc.visible=1 
        $params = array('userid'=>$userid,'status' => 1);
        $programenrolled = $DB->count_records_sql($sql, $params);
        return $programenrolled;
    }

    public function program_inprogress($userid){
        global $DB;
        $sql = "SELECT count(pg.id) 
                    FROM {local_program} AS pg
                    JOIN {local_program_users} AS pgu ON pg.id = pgu.programid
                    WHERE pgu.userid = :userid AND pgu.programid NOT IN (SELECT programid
                    FROM {local_program_users} WHERE completion_status = :status AND completiondate > 0
                    AND userid = {$userid} ) ";//AND pg.visible=1 
        $params = array('userid'=>$userid,'status' => 1);
        $programinprogress = $DB->count_records_sql($sql, $params);
        return $programinprogress;
    }

    public function program_completed($userid){
        global $DB;
        $sql = "SELECT COUNT(pg.id)
                    FROM {local_program} as pg
                    JOIN {local_program_users} AS pgu ON pg.id = pgu.programid
                    WHERE pgu.completion_status = :status AND pgu.completiondate > 0 
                    AND pgu.userid = :userid "; //AND pg.visible=1 
        $params = array('userid'=>$userid,'status' => 1);
        $programcompleted = $DB->count_records_sql($sql, $params);
        return $programcompleted;
    }

}
