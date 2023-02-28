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
 * @subpackage local_forum
 */
namespace local_forum;
class notification{
	public $db;
	public $user;
	public function __construct($db=null, $user=null){
		global $DB, $USER;
		$this->db = $db ? $db :$DB;
		$this->user = $user ? $user :$USER;
	}
    public function get_forum_strings($emailtype){
        

    }
    public function forum_notification($emailtype, $touser, $fromuser, $foruminstance){
        if($notification = $this->get_existing_notification($foruminstance, $emailtype)){
            $this->send_forum_notification($foruminstance, $touser, $fromuser, $emailtype, $notification);
        }
        $this->mobile_notifications($emailtype, $foruminstance, $touser, $fromuser);
    }
    public function get_existing_notification($foruminstance, $emailtype){
        $corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        $params = array();
        $notification_typesql = "SELECT lni.* FROM {local_notification_info} AS lni
            JOIN {local_notification_type} AS lnt ON lnt.id=lni.notificationid
            WHERE concat(',',lni.moduleid,',') LIKE concat('%,',:moduleid,',%') AND lnt.shortname LIKE :emailtype AND lni.active=1 ";
        $params['moduleid'] = $foruminstance->id;
        $params['emailtype'] = $emailtype;
        if($costcenterexist){
            $notification_typesql .= " AND concat('/',lni.open_path,'/') LIKE :costcenter";
            $params['costcenter'] = '%'.$foruminstance->costcenter.'%';
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
                $params['costcenter'] = '%'.$foruminstance->costcenter.'%';
            }
            $notification = $this->db->get_record_sql($notification_typesql, $params);
        }
        if(empty($notification)){
            return false;
        }else{
            return $notification;
        }
    }
	public function send_forum_notification($forum, $user, $fromuser, $emailtype, $notification){
        $datamailobj = new \stdclass();
        $datamailobj->forum_title = $forum->fullname;
        $datamailobj->forumid = $forum->id;
        $datamailobj->forum_enrolstartdate = $forum->startdate ? \local_costcenter\lib::get_mail_userdate($user, "d/m/Y", $forum->startdate) : 'N/A';
        $datamailobj->forum_enrolenddate = $forum->enddate ? \local_costcenter\lib::get_mail_userdate($user, "d/m/Y", $forum->enddate) : 'N/A';
        $datamailobj->forum_completiondays = $forum->open_forumcompletiondays ? $forum->open_forumcompletiondays : 'N/A';
        $datamailobj->notification_infoid = $notification->id;

        // $datamailobj->forum_department = $department;
        $datamailobj->forum_department = $forum->open_departmentid ? 
        	$this->db->get_field('local_costcenter', 'fullname', array('id' => $forum->open_departmentid)) : 'N/A' ;
        $datamailobj->forum_categoryname = $this->db->get_field('course_categories', 'name', array('id' => $forum->category));

        $url = new \moodle_url('/mod/quiz/view.php?id='.$forum->id);
        $datamailobj->forum_url = \html_writer::link($url, $url);
        $datamailobj->forum_description = $forum->summary ? $forum->summary : 'N/A' ;
        if($forum->open_coursecreator){
            $datamailobj->course_creator = $this->db->get_field_sql("SELECT concat(firstname,' ',lastname) FROM {user} WHERE id=:creatorid", array('creatorid' => $forum->open_coursecreator));
        }else{
            $datamailobj->course_creator = 'N/A';
        }
        if($emailtype == 'forum_complete'){
        	$datamailobj->forum_completiondate = \local_costcenter\lib::get_mail_userdate($user,"d/m/Y", time());	
        }
        // $includes = new \user_course_details();
        // $courseimage = $includes->course_summary_files($course);
        // $datamailobj->course_image = \html_writer::img($courseimage, $course->fullname,array());
        $datamailobj->enroluser_fullname = $user->firstname.' '.$user->lastname;
	    $datamailobj->enroluser_email = $user->email;
		$datamailobj->forum_reminderdays = $notification->reminderdays;
	    $datamailobj->adminbody = NULL;
	    $datamailobj->body = $notification->body;
	    $datamailobj->subject = $notification->subject;
	    $datamailobj->touserid = $user->id;


	    $fromuser = \core_user::get_support_user();
	    $datamailobj->fromuserid = $fromuser->id;
	    $datamailobj->teammemberid = 0;
	    if(!empty($notification->adminbody) && !empty($user->open_supervisorid)){
	    	$superuser = \core_user::get_user($user->open_supervisorid);
	    	$datamailobj->supervisor_name = fullname($superuser);
	    }else{
	    	$superuser = false;
	    	$datamailobj->supervisor_name = '';
	    }
	 //    if(class_exists('\notifications')){
		//     $notifications_lib = new \notifications();
		//     $notifications_lib->send_email_notification($emailtype, $datamailobj, $user->id, $fromuser->id);
		//     if($superuser){
		//     	$datamailobj->body = null;
		//     	$datamailobj->adminbody = $notification->adminbody;
		//     	$notifications_lib->send_email_notification($emailtype, $datamailobj, $superuser->id, $fromuser->id);
		//     }
		// }else{
			$this->log_email_notification($user, $fromuser, $datamailobj, $emailtype);
			if($superuser){
				$datamailobj->body = $notification->adminbody;
				$datamailobj->touserid = $superuser->id;
				$datamailobj->teammemberid = $user->id;
				$this->log_email_notification($superuser, $fromuser, $datamailobj, $emailtype);
			}
		// }
	}
    public function get_rolename_inforum($foruminstance, $touserid){
        $touser = '';
        $istrainer = $this->db->record_exists('local_course_trainers', array('forumid' => $foruminstance->id, 'trainerid' => $touser->id));
        if(!$istrainer){
            return get_string('employeerolestring', 'local_forum');
        }else{
            return get_string('trainerrolestring', 'local_forum');
        }
    }
    public function log_email_notification($touser, $fromuser, $datamailobj){
        if(!$touser){
            return true;
        }
        $dataobject = clone $datamailobj;
        $dataobject->subject = $this->replace_strings($datamailobj, $datamailobj->subject);
        $emailbody = $this->replace_strings($datamailobj, $datamailobj->body);
        $dataobject->emailbody = $emailbody;
        $dataobject->from_emailid = $fromuser->email;
        $dataobject->from_userid = $fromuser->id;
        $dataobject->to_emailid = $touser->email;
        $dataobject->to_userid = $touser->id;
        $dataobject->ccto = 0;
        $dataobject->sentdate = 0;
        $dataobject->sent_by = $this->user->id;
        $dataobject->moduleid = $datamailobj->forumid;
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
        if($datamailobj->forumid){
            $sql .= " AND moduleid=:forumid";
            $params['forumid'] = $datamailobj->forumid;
        }
        if($datamailobj->teammemberid){
            $sql .= " AND teammemberid=:teammemberid";
            $params['teammemberid'] = $datamailobj->teammemberid;
        }
        return $this->db->get_field_sql($sql ,$params);
    }
    public function replace_strings($dataobject, $data){
        $strings = $this->db->get_records('local_notification_strings', array('module' => 'forum'));
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
    public function mobile_notifications($type, $instance, $userto, $userfrom) {
        global $CFG;
        if ($type == 'forum_enrol') {
            $component = 'local_forum';
            $name = 'forumenrolment';
            $instance->forumurl = '<a href="' . $CFG->wwwroot . '/mod/quiz/view.php?cid=' . $instance->id . '"></a>';
            $messagesubject = get_string('forumenrolmentsub', 'local_forum');
            $message = get_string('forumenrolment', 'local_forum', $instance);
        }
        $plaintext = html_to_text($message);
        $eventdata = new \core\message\message();
        $eventdata->courseid          = SITEID;
        $eventdata->component         = $component;
        $eventdata->name              = $name;
        $eventdata->userfrom          = $userfrom;
        $eventdata->userto            = $userto;
        $eventdata->notification      = 1;
        $eventdata->subject           = $messagesubject;
        $eventdata->fullmessage       = $plaintext;
        $eventdata->fullmessageformat = FORMAT_HTML;
        $eventdata->fullmessagehtml   = $message;
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }
}
