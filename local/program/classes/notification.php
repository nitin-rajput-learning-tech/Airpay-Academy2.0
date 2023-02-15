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
 */
 namespace local_program;
 class notification{
 	public $db;
	public $user;
	public function __construct($db=null, $user=null){
		global $DB, $USER;
		$this->db = $db ? $db :$DB;
		$this->user = $user ? $user :$USER;
	}
	public function get_notification_strings($emailtype){
		switch($emailtype){
			case 'program_enrol':
                $strings = "[program_name], [program_organization], [program_stream], [program_creater], [program_enroluserfulname], [program_link], [program_enroluseremail]";   
                break;
            case 'program_unenroll':
                $strings = "[program_name], [program_organization], [program_stream], [program_creater], [program_enroluserfulname], [program_link], [program_enroluseremail]";
                break;
            case 'program_completion':
                $strings = "[program_name], [program_organization], [program_stream], [program_creater], [program_enroluserfulname], [program_link], [program_enroluseremail], [program_completiondate]";
                break;
            case 'program_level_completion':
                $strings = "[program_name], [program_level], [program_enroluserfulname], [program_link], [program_enroluseremail],[program_level_creater],[program_level_completiondate]";   
                break;
            case 'program_course_completion':
                $strings = "[program_name], [program_course], [program_enroluserfulname], [program_link], [program_enroluseremail],[program_level_link],[program_lc_course_link],[program_lc_course_creater],[program_lc_course_completiondate]";   
                break;
            case 'program_session_enrol':
                $strings = "[program_name], [program_level], [program_course], [program_session_name], [program_session_username], [program_session_link], [program_session_useremail], [program_session_trainername], [program_session_startdate], [program_session_enddate]";   
                break;
            case 'program_session_reschedule':
                $strings = "[program_name], [program_level], [program_course], [program_session_name], [program_session_username], [program_session_link], [program_session_useremail], [program_session_trainername], [program_session_startdate], [program_session_enddate]";   
                break;
            case 'program_session_attendance':
                $strings = "[program_name], [program_level], [program_course], [program_session_name], [program_session_username], [program_session_link], [program_session_useremail], [program_session_trainername], [program_session_attendance], [program_session_startdate], [program_session_enddate]";   
                break;
            case 'program_session_reminder':
                $strings = "[program_name], [program_level], [program_course], [program_session_name], [program_session_username], [program_session_link], [program_session_useremail], [program_session_trainername], [program_session_startdate], [program_session_enddate]";   
                break;
            case 'program_session_completion':
                $strings = "[program_name], [program_level], [program_course], [program_session_name], [program_session_username], [program_session_link], [program_session_useremail], [program_session_trainername], [program_session_attendance], [program_session_startdate], [program_session_enddate], [program_session_completiondate]";   
                break;
            case 'program_session_cancel':
                $strings = "[program_name], [program_level], [program_course], [program_session_name], [program_session_username], [program_session_link], [program_session_useremail], [program_session_trainername], [program_session_startdate], [program_session_enddate]";   
                break;
		}
	}
	public function program_notification($emailtype, $touser, $fromuser, $programinstance){

        if($notification = $this->get_existing_notification($programinstance, $emailtype)){
            $this->send_program_notification($programinstance, $touser, $fromuser, $emailtype, $notification);
        }
    }
    public function get_existing_notification($programinstance, $emailtype){
    	$corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        $params = array();
        $notification_typesql = "SELECT lni.* FROM {local_notification_info} AS lni 
            JOIN {local_notification_type} AS lnt ON lnt.id=lni.notificationid
            WHERE concat(',',lni.moduleid,',') LIKE concat('%,',:moduleid,',%') AND lnt.shortname LIKE :emailtype AND lni.active=1 ";
        $params['moduleid'] = $programinstance->id;
        $params['emailtype'] = $emailtype;
        if($costcenterexist){
            $notification_typesql .= " AND concat('/',lni.open_path,'/') LIKE :costcenter";
            $params['costcenter'] = '%'.$programinstance->costcenter.'%';
        }
        $notification = $this->db->get_record_sql($notification_typesql, $params);
        if(empty($notification)){ // sends the default notification for the type.
            $params = array();
            $notification_typesql = "SELECT lni.* FROM {local_notification_info} AS lni 
                JOIN {local_notification_type} AS lnt ON lnt.id=lni.notificationid 
                WHERE (lni.moduleid IS NULL OR lni.moduleid LIKE '0') 
                AND lnt.shortname LIKE :emailtype AND lni.active=1 ";
            $params['emailtype'] = $emailtype;
            if($costcenterexist){

                $notification_typesql .= " AND concat('/',lni.open_path,'/') LIKE :costcenter ";
                $params['costcenter'] = '%'.$programinstance->costcenter.'%';
            }
            $notification = $this->db->get_record_sql($notification_typesql, $params);
        }
        if(empty($notification)){
            return false;
        }else{
            return $notification;
        }
    }

    public function send_program_sessionreminder_notification($programinstance, $touser, $fromuser, $emailtype, $notification){
        global $CFG;
        $datamailobject = new \stdClass();
        $datamailobject->notification_infoid = $notification->id;
        $datamailobject->program_session_startdate = $programinstance->timestart ? \local_costcenter\lib::get_userdate("d/m/Y H:i",$programinstance->timestart) : 'N/A';
        $datamailobject->program_session_enddate = $programinstance->timefinish ? \local_costcenter\lib::get_userdate("d/m/Y H:i",$programinstance->timefinish) : 'N/A';
        $datamailobject->program_name = $programinstance->product_name;
        $datamailobject->program_level = $this->db->get_field('local_program_levels', 'level',array('id' => $programinstance->levelid));

        $courseid = $this->db->get_field('local_program_level_courses', 'courseid', array('id' => $programinstance->bclcid));
        $datamailobject->program_course = $this->db->get_field('course', 'fullname', array('id' => $courseid));
        
        $datamailobject->program_session_name = $programinstance->name;
        $datamailobject->program_session_username = $programinstance->user_name;
        
        $url = new \moodle_url($CFG->wwwroot.'/local/program/view.php?bcid='.$programinstance->programid);
        $datamailobject->program_session_link = \html_writer::link($url, $url);
        $datamailobject->program_session_useremail = $touser->email;
        $trainerdetails_sql = "SELECT u.id, concat(u.firstname,' ',u.lastname) AS trainername 
                FROM {user} AS u 
                JOIN {local_bc_course_sessions} AS lbcs ON lbcs.trainerid=u.id
                WHERE lbcs.id=:sessionid ";
        $trainerdetails = $this->db->get_record_sql($trainerdetails_sql, array('sessionid' => $programinstance->sessionid));
        $datamailobject->program_session_trainername = $trainerdetails->trainername;
     
      
      
        $datamailobject->adminbody = NULL;
        $datamailobject->body = $notification->body;
        $datamailobject->subject = $notification->subject;
        $datamailobject->programid = $programinstance->id;
        $datamailobject->touserid = $touser->id;
        $datamailobject->fromuserid = $fromuser->id;
        $datamailobject->teammemberid = 0;
        if(!empty($notification->adminbody) && !empty($touser->open_supervisorid)){
            $superuser = \core_user::get_user($touser->open_supervisorid);
        }else{
            $superuser = false;
        }
        $this->log_email_notification($touser, $fromuser, $datamailobject);
        if($superuser){
            $datamailobject->body = $notification->adminbody;
            $datamailobject->touserid = $superuser->id;
            $datamailobject->teammemberid = $touser->id;
            $this->log_email_notification($superuser, $fromuser, $datamailobject);
        }
    }


    public function send_program_notification($programinstance, $touser, $fromuser, $emailtype, $notification){
        global $CFG;
    	$datamailobject = new \stdClass();
        $datamailobject->notification_infoid = $notification->id;
        $datamailobject->program_startdate = $programinstance->startdate ? \local_costcenter\lib::get_userdate("d/m/Y H:i",$programinstance->startdate) : 'N/A';
        $datamailobject->program_enddate = $programinstance->enddate ? \local_costcenter\lib::get_userdate("d/m/Y H:i",$programinstance->enddate) : 'N/A';
    	$datamailobject->program_name = $programinstance->name;
    	$datamailobject->program_organization = $this->db->get_field('local_costcenter', 'fullname',  array('id' => $programinstance->costcenter));
    	$datamailobject->program_stream = $this->db->get_field('local_program_stream', 'stream', array('id'=>$programinstance->stream));
        $creatornamesql = "SELECT concat(firstname,' ',lastname) FROM {user} WHERE id=:creatorid ";
    	$datamailobject->program_creater = $this->db->get_field_sql($creatornamesql, array('creatorid' => $programinstance->usercreated));
    	$datamailobject->program_enroluserfulname = fullname($touser);
        $url = new \moodle_url($CFG->wwwroot.'/local/program/view.php?bcid='.$programinstance->id);
    	$datamailobject->program_link = \html_writer::link($url, $url);
    	$datamailobject->program_enroluseremail = $touser->email;
        if($emailtype == 'program_completion'){
            $completiondate = $this->db->get_field('local_program_users', 'completiondate', array('programid'=>$programinstance->id, 'userid'=>$touser->id));
        	$datamailobject->program_completiondate  = $completiondate ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $completiondate) : 'N/A';//for completion
        }
        if($emailtype == 'program_level_completion'){
        	// $datamailobject->program_level_creater	//for level completion
        	// $datamailobject->program_level_completiondate	//for level completion
        }
    	// programcourse completion
        if($emailtype == 'program_course_completion'){
            $courserec_sql = "SELECT c.id, c.fullname, concat(u.firstname,' ',u.lastname) AS creatorname 
                FROM {course} AS c 
                JOIN {user} AS u ON u.id=c.open_coursecreator 
                WHERE c.id=:courseid ";
            $courseobj = $this->db->get_record_sql($courserec_sql, array('courseid' => $programinstance->courseid));
            // $courseobj = $this->db->get_record('course', array('id' => $programinstance->courseid), 'id, fullname, ')
            $datamailobject->program_course = $courseobj->fullname;
        	// $datamailobject->program_level_link //program_course_completion
            $courselink = new \moodle_url('course/view.php', array('id' => $programinstance->courseid));
        	$datamailobject->program_lc_course_link = $courselink;//program_course_completion
            $datamailobject->program_lc_course_creater = $courseobj->creatorname;//program_course_completion
            // $completiondatesql = "SELECT timecompleted FROM {course_completions}";
            $completiondate = $this->db->get_field('course_completions', 'timecompleted', array('course' => $programinstance->courseid, 'userid' => $touser->id));
            $datamailobject->program_lc_course_completiondate = $completiondate ? \local_costcenter\lib::get_userdate("d/m/Y H:i",$completiondate) : 'N/A';//program_course_completion
        }
        $non_coursenamestring = array('program_level_completion', 'program_completion', 'program_unenroll', 'program_enrol', 'program_course_completion');
        if(!in_array($emailtype, $non_coursenamestring)){
            $programcourses_sql = "SELECT c.id,c.fullname FROM {course} AS c 
                JOIN {local_program_level_courses} AS lplc ON lplc.courseid=c.id 
                JOIN {local_program_levels} AS lpl ON lpl.id=lplc.levelid 
                WHERE lpl.programid=:programid AND lpl.id=:levelid ";
            $programscourses = $this->db->get_records_sql_menu($programcourses_sql, array('programid' => $programinstance->id, 'levelid' => $programinstance->levelid));
            $datamailobject->program_course = implode(',', $programscourses);
            $datamailobject->program_level = $this->db->get_field('local_program_levels', 'level',array('id' => $programinstance->levelid));
            $sessiondetails = $this->db->get_record('local_bc_course_sessions', array('id'=>$programinstance->sessionid), 'id, name, timestart, timefinish');
            $datamailobject->program_session_name = $sessiondetails->name;
            $datamailobject->program_session_startdate = $sessiondetails->timestart ? \local_costcenter\lib::get_userdate('d/m/Y H:i',$sessiondetails->timestart) : 'N/A';
            $datamailobject->program_session_enddate = $sessiondetails->timefinish ? \local_costcenter\lib::get_userdate('d/m/Y H:i',$sessiondetails->timefinish) : 'N/A';
            $url = new \moodle_url('local/program/sessions.php?bclcid='.$programinstance->courseid.'&levelid='.$programinstance->levelid.'&bcid='.$programinstance->id);
            $datamailobject->program_session_link = \html_writer::link($url, $url);
            $datamailobject->program_session_username = fullname($touser);
            $datamailobject->program_session_useremail = $touser->email;
            $trainerdetails_sql = "SELECT u.id, concat(u.firstname,' ',u.lastname) AS trainername 
                FROM {user} AS u 
                JOIN {local_bc_course_sessions} AS lbcs ON lbcs.trainerid=u.id
                WHERE lbcs.id=:sessionid ";
            $trainerdetails = $this->db->get_record_sql($trainerdetails_sql, array('sessionid' => $programinstance->sessionid));
            $datamailobject->program_session_trainername = $trainerdetails->trainername;
        }
        $attendancereq = array('program_session_completion', 'program_session_attendance');
        if(in_array($emailtype, $attendancereq)){
            $completion_status = $this->db->get_field('local_bc_session_signups', 'completion_status', array('programid'=>$programinstance->id, 'levelid'=>$programinstance->levelid, 'bclcid'=>$programinstance->courseid, 'sessionid'=>$programinstance->sessionid, 'userid'=>$touser->id));
            if($completion_status == SESSION_PRESENT) {
                $datamailobject->program_session_attendance = 'Present';
            } else {
                $datamailobject->program_session_attendance = 'Absent';
            }
        }
        if($emailtype == 'program_session_completion'){
            $program_completiondate=$this->db->get_field('local_bc_session_signups', 'timemodified', array('programid'=>$programinstance->id, 'levelid'=>$programinstance->levelid, 'bclcid'=>$programinstance->bclcid, 'sessionid'=>$programinstance->sessionid, 'userid'=>$touser->id));
            if(!empty($completion_date)){
                $datamailobject->program_session_completiondate  =  \local_costcenter\lib::get_userdate("d/m/Y H:i",$program_completiondate);
            } else {
                $datamailobject->program_session_completiondate  = 'NA';
            }
        }
        $datamailobject->adminbody = NULL;
        $datamailobject->body = $notification->body;
        $datamailobject->subject = $notification->subject;
        $datamailobject->programid = $programinstance->id;
        $datamailobject->touserid = $touser->id;
        $datamailobject->fromuserid = $fromuser->id;
        $datamailobject->teammemberid = 0;
        if(!empty($notification->adminbody) && !empty($touser->open_supervisorid)){
            $superuser = \core_user::get_user($touser->open_supervisorid);
        }else{
            $superuser = false;
        }
        $this->log_email_notification($touser, $fromuser, $datamailobject);
        if($superuser){
            $datamailobject->body = $notification->adminbody;
            $datamailobject->touserid = $superuser->id;
            $datamailobject->teammemberid = $touser->id;
            $this->log_email_notification($superuser, $fromuser, $datamailobject);
        }
    }
    public function log_email_notification($touser, $fromuser, $datamailobj){
        $dataobject = clone $datamailobj;
        $dataobject->subject = $this->replace_strings($datamailobj, $datamailobj->subject);
        $dataobject->emailbody = $this->replace_strings($datamailobj, $datamailobj->body);
        $dataobject->from_emailid = $fromuser->email;
        $dataobject->from_userid = $fromuser->id;
        $dataobject->to_emailid = $touser->email;
        $dataobject->to_userid = $touser->id;
        $dataobject->ccto = 0;
        $dataobject->sentdate = 0;
        $dataobject->sent_by = $fromuser->id;
        $dataobject->moduleid = $datamailobj->programid;
        
        if($logid = $this->check_pending_mail_exists($touser, $fromuser, $datamailobj)){
            $dataobject->id = $logid;
            $dataobject->timemodified = time();
            $dataobject->usermodified = $this->user->id;
            $logid = $this->db->update_record('local_emaillogs', $dataobject);
        }else{
            $dataobject->timecreated = time();
            $dataobject->usercreated = $this->user->id;
            $this->db->insert_record('local_emaillogs', $dataobject);
        }
    }
    public function check_pending_mail_exists($user, $fromuser, $datamailobj){
        $sql =  " SELECT id FROM {local_emaillogs} WHERE to_userid = :userid AND notification_infoid = :infoid AND from_userid = :fromuserid AND subject = :subject AND status = 0";
        $params['userid'] = $datamailobj->touserid;
        $params['subject'] = $datamailobj->subject;
        $params['fromuserid'] = $datamailobj->fromuserid;
        $params['infoid'] = $datamailobj->notification_infoid;
        if($datamailobj->programid){
            $sql .= " AND moduleid=:programid";
            $params['programid'] = $datamailobj->programid;
        }
        if($datamailobj->teammemberid){
            $sql .= " AND teammemberid=:teammemberid";
            $params['teammemberid'] = $datamailobj->teammemberid;
        }
        return $this->db->get_field_sql($sql ,$params);
    }
    public function replace_strings($dataobject, $data){       
        $strings = $this->db->get_records('local_notification_strings', array('module' => 'program'));        
        if($strings){
            foreach($strings as $string){
                foreach($dataobject as $key => $dataval){
                    $key = '['.$key.']';
                    if("$string->name" == "$key"){
                        $data = str_replace("$string->name", "$dataval", $data);
                    }
                }
            }
        }
        return $data;
    }
}