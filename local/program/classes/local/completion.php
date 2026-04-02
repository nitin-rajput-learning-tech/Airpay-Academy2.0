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
 * @package Bizlms 
 * @subpackage local_program 
 */

namespace local_program\local;

defined('MOODLE_INTERNAL') || die();

class completion{
    public function program_course_completion($programid, $levelid, $courseid, $userid){
        global $DB, $USER;
        $categorycontext =  (new \local_program\lib\accesslib())::get_module_context($programid);
        $completion_record = $DB->get_record('local_bc_level_completions', array('programid' => $programid, 'levelid' => $levelid, 'userid' => $userid));
        if(empty($completion_record)){

            $bclevelcmptl = new stdClass();
            $bclevelcmptl->programid = $programid;
            $bclevelcmptl->type = 0;
            $bclevelcmptl->levelid = $levelid;
            $bclevelcmptl->userid = $userid;
            $bclevelcmptl->bclcids = $courseid;
            $completionstatus = $this->levelcompletion_status($levelid, $userid, $courseid);
            $bclevelcmptl->completion_status = $completionstatus;
            if($completionstatus){
                $bclevelcmptl->completiondate = time();
            }
            $bclevelcmptl->usercreated = $USER->id;
            $bclevelcmptl->timecreated = time();
            $bclevelcmptl->id = $DB->insert_record('local_bc_level_completions',
                        $bclevelcmptl);
        }else{
            $completion_record->bclcids .= $courseid;
            $completionstatus = $this->levelcompletion_status($levelid, $userid, $completion_record->bclcids);
            if($completionstatus){
                $bclevelcmptl->completiondate = time();
            }
            $bclevelcmptl->usermodified = $USER->id;
            $bclevelcmptl->timemodified = time();
            $DB->update_record('local_bc_level_completions', $completion_record);
        }
        if($bclevelcmptl->completiondate){
            $type = 'program_level_completion';
            $emaillogs = new \local_program\notification();
            $touser = \core_user::get_user($userdata->userid);
            $programinstance = $DB->get_record('local_program', array('id' => $programid));
            $email_logs = $emaillogs->program_notification($type, $touser, $USER, $programinstance);
                
            $bcuser = $DB->get_record('local_program_users',
                array('programid' => $programid,
                    'userid' => $userid, 'completion_status' => 0));
            if (!empty($bcuser)) {
                $bclevels = $DB->get_records_menu('local_program_levels',
                    array('programid' => $programid), 'id',
                    'id, id AS level');
                $bcusercmptllevelids = $bcuser->levelids;
                if (empty($bcusercmptllevelids)) {
                    $bcuser->levelids = $levelid;
                    $levelids = array($levelid);
                } else {
                    $levelids = explode(',', $bcusercmptllevelids);

                    array_unique($levelids);
                    $bcuser->levelids = implode(',', array_filter($levelids));
                }
                $bclevelcompletionstatus = array_diff($bclevels, $levelids);
                if (empty($bclevelcompletionstatus)) {
                    $bcuser->completion_status = 1;
                    $bcuser->completiondate = time();
                }
                $DB->update_record('local_program_users', $bcuser);
                //program completions $bcuser->completion_status=1
                if($bcuser->completion_status == 1){
                  $type = 'program_completion';
                  $params = array('context' => $categorycontext,
                    'objectid' => $programid,
                    'courseid' => 1,
                    'userid' => $userid,
                    'relateduserid' => $userid);
                  $event = \local_program\event\program_user_completed::create($params);
                  $event->add_record_snapshot('local_program', $programid);
                  $event->trigger();
                  $emaillogs = new \local_program\notification();
                  $touser = \core_user::get_user($userid);
                  $programinstance = $DB->get_record('local_program', array('id' => $programid));
                  $email_logs = $emaillogs->program_notification($type, $touser, $USER, $programinstance);
                }
            }
        }
    }
    public function get_completion_program_mapping($courseid, $userid){
        global $DB;
        $sql = "SELECT lpu.id, lplc.levelid, lpu.programid FROM {local_program_users} AS lpu 
            JOIN {local_program_level_courses} AS lplc ON lplc.programid = lpu.programid 
            WHERE lplc.courseid = :courseid AND lpu.userid = :userid ";
        $mappinginfo = $DB->get_records_sql($sql, array('courseid' => $courseid, 'userid' => $userid));
        foreach($mappinginfo AS $info){
            $this->program_course_completion($info->programid, $info->levelid, $courseid, $userid);
        }
    }
    public function levelcompletion_status($levelid, $userid, $courseids = null){
        global $DB;
        $coursecount = $DB->count_records_sql("SELECT count(id) FROM {local_program_level_courses} WHERE levelid= :levelid ", array('levelid' => $levelid));
        if(is_null($courseids)){
            $courseids = $DB->get_field('local_bc_level_completions', 'bclcids', array('levelid' => $levelid, 'userid' => $userid));
        }
        $completedcourses = explode(',', $courseids);
        if(count($completedcourses) == $coursecount){
            return 1;
        }
        return 0;
    }
    public function program_course_completion_task_bk(){
        global $DB;
        $sql = "SELECT concat(lpu.id,'_',lplc.id,'_',lplc.levelid) AS id, lpu.userid, lplc.courseid, lplc.levelid, lplc.programid
            FROM {local_program_users} AS lpu 
            JOIN {local_program_level_courses} AS lplc ON lplc.programid = lpu.programid
            JOIN {course_completions} AS cc ON cc.course = lplc.courseid AND cc.userid = lpu.userid
            WHERE lpu.completion_status <> 1 AND cc.timecompleted IS NOT NULL ";
        $user_coursemapping = $DB->get_records_sql($sql);
        foreach($user_coursemapping AS $coursemapping){
            $levelcoursecompleted = $DB->record_exists_sql("SELECT lblc.id FROM {local_bc_level_completions} AS lblc WHERE lblc.userid = :userid AND concat(',',lblc.bclcids,',') LIKE '%,{$coursemapping->courseid},%' AND lblc.levelid = :levelid ",  array('userid' => $coursemapping->userid ,'levelid' => $coursemapping->levelid));
            if(!$levelcoursecompleted){
                $this->program_course_completion($coursemapping->programid, $coursemapping->levelid, $coursemapping->courseid, $coursemapping->userid);
            }
        }
    }
    public function program_course_completion_task(){
        global $DB;
        $programs = $DB->get_records('local_program', array());
        foreach($programs AS $program){
            $this->program_level_completions($program->id);
        }
    }
    /**
     * [program_level_completions description]
     * @method program_level_completions
     * @param  [integer]                $programid [Required]
     * @param  [integer]                $userid [optional]
     * @return [Boolean]                [status of the task]
     */
    public function program_level_completions($programid,$userid = 0){
        global $DB, $USER, $CFG;
        require_once($CFG->libdir.'/completionlib.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_role.php');

        $categorycontext =  (new \local_program\lib\accesslib())::get_module_context($programid);

        //getting enrolled user for the program or single
        $programuserparam = array();
        $programuserssql = "SELECT pu.*
                                FROM {user} AS u
                                JOIN {local_program_users} AS pu ON pu.userid = u.id
                                WHERE u.id > 2 AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND pu.programid = :programid AND pu.completion_status = 0 ";
        $programuserparam['programid'] = $programid;
        if($userid){
            $programuserssql .= " AND pu.userid = :userid";
            $programuserparam['userid'] = $userid;
        }                        
        $programusers = $DB->get_records_sql($programuserssql,$programuserparam);

        //getting all sections under the certificate
        $levels = $DB->get_records('local_program_levels',array('programid' => $programid));
        foreach ($levels as $key => $level) {
            //each section completion ccriteria
            $this->program_course_completions($programid, $level, $programusers);
        }
        
        //program completion data
        $program_completiondata = $DB->get_record('local_bc_completion_criteria', array('programid' => $programid));
        
        $totallevelssql = "SELECT count(id) as total
                                        FROM {local_program_levels}
                                        WHERE programid = $programid ";
        if(!empty($program_completiondata)&&($program_completiondata->leveltracking=="OR" || $program_completiondata->leveltracking=="AND") && !($program_completiondata->levelids == null || $program_completiondata->leveltracking == 'ALL')){
            $totallevelssql .=" AND id in ($program_completiondata->levelids)";
        }
        $totallevels = $DB->count_records_sql($totallevelssql);
        
        if(!empty($totallevels)) {
            foreach($programusers as $programuser){
                if(!empty($program_completiondata)){
                    $existstatus = $programuser->completion_status; 
                    $programuser->completion_status = 0;                
                    $completed_levels_sql="SELECT count(id) as total FROM {local_bc_level_completions} where programid={$programid} and userid={$programuser->userid} and completion_status=1 ";
                     
                    if(($program_completiondata->leveltracking == 'OR' || $program_completiondata->leveltracking == 'AND') && !($program_completiondata->levelids == null || $program_completiondata->leveltracking == 'ALL')){
                        $completed_levels_sql.=" AND levelid in ($program_completiondata->levelids)";
                    }
                    $completed_levels = $DB->count_records_sql($completed_levels_sql);
                    
                    if ($completed_levels == $totallevels && ($program_completiondata->leveltracking == "AND" || $program_completiondata->leveltracking == "ALL")) {
                       
                        $programuser->completion_status = 1;
                    }
                    if (($completed_levels <= $totallevels && $completed_levels!=0 && $program_completiondata->leveltracking=="OR")) {
                       
                        $programuser->completion_status = 1;
                    }
                    if($existstatus == $programuser->completion_status || $existstatus == 1){
                    continue;
                } 
                $programuser->usermodified = $USER->id;
                $programuser->timemodified = time();
                if($programuser->completion_status == 1){
                    $programuser->completiondate = time();
                }
                $DB->update_record('local_program_users', $programuser);
                if($programuser->completion_status == 1){
                    $type = 'program_completion';
                    $params = array('context' => $categorycontext,
                        'objectid' => $programid,
                        'courseid' => 1,
                        'userid' => $programuser->userid,
                        'relateduserid' => $programuser->userid);
                    $event = \local_program\event\program_user_completed::create($params);
                    $event->add_record_snapshot('local_program', $programid);
                    $event->trigger();
                    $emaillogs = new \local_program\notification();
                    $touser = \core_user::get_user($programuser->userid);
                    $fromuser = \core_user::get_support_user();
                    $programinstance = $DB->get_record('local_program', array('id' => $programid));
                    $email_logs = $emaillogs->program_notification($type, $touser, $fromuser, $programinstance);
                }
                }
            }
        }
        return true;
    }
    public function program_course_completions($programid, $level, $programusers){
        global $DB, $USER;
        $level_completiondata = $DB->get_record('local_bcl_cmplt_criteria', array('programid' => $programid,'levelid' => $level->id));
        if(!empty($level_completiondata)){

            $programcoursessql = "SELECT c.*
                                  FROM {course} AS c
                                  JOIN {local_program_level_courses} AS lplc ON lplc.courseid = c.id
                                 WHERE lplc.programid = {$programid} AND lplc.levelid = {$level->id}";

            if(($level_completiondata->coursetracking == "OR" || $level_completiondata->coursetracking=="AND") && !($level_completiondata->coursetracking == 'ALL' || $level_completiondata->courseids == null)){
                $programcoursessql.=" AND lplc.courseid in ($level_completiondata->courseids)";
            }
            $level_courses = $DB->get_records_sql($programcoursessql);
            //each level courses

            if(!empty($level_courses)) {
                foreach($programusers as $programuser){
                    $usercousrecompletionstatus =array();
                    $completedcourses = [];
                    foreach($level_courses as $levelcourse) {
                        $params = array(
                            'userid'    => $programuser->userid,
                            'course'    => $levelcourse->id
                        );

                        $ccompletion = new \completion_completion($params);
                        
                        $ccompletionis_complete =  $ccompletion->is_complete();
                        if ($ccompletionis_complete) {
                            $usercousrecompletionstatus[]= true;
                            $completedcourses[] = $levelcourse->id;
                        }
                    }
                    //level completion status for each user
                    $leveluser = new \stdClass();
                    if(($level_completiondata->coursetracking=="ALL" || $level_completiondata->coursetracking=="AND") && count($usercousrecompletionstatus) == count($level_courses)){
                        $leveluser->completiondate = time();
                        $leveluser->completion_status = 1;
                    }else if($level_completiondata->coursetracking=="OR" && count($usercousrecompletionstatus) > 0){
                        $leveluser->completiondate = time();
                        $leveluser->completion_status = 1;
                    // }else if($level_completiondata->coursetracking=="AND" && count($usercousrecompletionstatus) == count($level_courses)){
                    //     $leveluser->completiondate = time();
                    //     $leveluser->completion_status = 1;
                    }else{
                        $leveluser->completiondate = 0;
                        $leveluser->completion_status = 0;
                    }

                    $leveluser->userid =  $programuser->userid;
                    $leveluser->levelid =  $level->id;
                    $leveluser->programid =  $programid;
                    $leveluser->bclcids = implode(',', array_filter($completedcourses));
                    if($existdata = $DB->get_record('local_bc_level_completions', array('programid' => $programid, 'levelid' => $level->id,'userid' => $leveluser->userid))){
                        if($leveluser->completion_status == 1){
                            $existprogramuser = $DB->get_record('local_program_users', array('programid' => $programid, 'userid' => $programuser->userid), 'id, levelids');
                            $levelids = explode(',', $existprogramuser->levelids);
                            if(!in_array($leveluser->levelid, $levelids)){
                                $levelids[] = $leveluser->levelid;
                                $existprogramuser->levelids = implode(',', array_filter($levelids));
                            }
                            $existprogramuser->timemodified = time();
                            $DB->update_record('local_program_users',  $existprogramuser);
                        }
                        if($existdata->completion_status == 1 || $existdata->completion_status === $leveluser->completion_status){
                            $leveluser->id = $existdata->id;
                            $DB->update_record('local_bc_level_completions',  $leveluser);
                            continue;
                        }
                        $leveluser->id = $existdata->id;
                        $leveluser->usermodified = $USER->id;
                        $leveluser->timemodified = time();
                        $DB->update_record('local_bc_level_completions', $leveluser);
                    }else{
                        $leveluser->usercreated = $USER->id;
                        $leveluser->timecreated = time();
                        $insertid = $DB->insert_record('local_bc_level_completions', $leveluser);
                    }
                    if($leveluser->completion_status == 1){
                        $existprogramuser = $DB->get_record('local_program_users', array('programid' => $programid, 'userid' => $programuser->userid), 'id, levelids');
                        $levelids = explode(',', $existprogramuser->levelids);
                        if(!in_array($leveluser->levelid, $levelids)){
                            $levelids[] = $leveluser->levelid;
                            $existprogramuser->levelids = implode(',', array_filter($levelids));
                        }
                        $existprogramuser->timemodified = time();
                        $DB->update_record('local_program_users',  $existprogramuser);
                        $type = 'program_level_completion';
                        $emaillogs = new \local_program\notification();
                        $touser = \core_user::get_user($programuser->userid);
                        $fromuser = \core_user::get_support_user();
                        $programinstance = $DB->get_record('local_program', array('id' => $programid));
                        $programinstance->levelid = $level->id;
                        $email_logs = $emaillogs->program_notification($type, $touser, $fromuser, $programinstance);
                    }
                }
            }
        }
    }
}