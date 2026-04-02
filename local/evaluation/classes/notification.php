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
 * @subpackage local_evaluation
 */

 namespace local_evaluation;
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
			case 'feedback_enrollment':
                $strings = "[feedback_name],[feedback_schedule], 
                            [feedback_enroldate],[feedback_username],
                            [feedback_email],[feedback_url]";
                break;
            case 'feedback_due':
                $strings = "[feedback_name],[feedback_schedule], 
                            [feedback_enroldate],[feedback_username],
                            [feedback_email],[feedback_url]";
                break;
			case 'feedback_completed':
                $strings = "[feedback_name],[feedback_schedule], 
                            [feedback_enroldate],[feedback_username],
                            [feedback_email],[feedback_url],[feedback_completeddate]";
                break;
		}
	}
	public function evaluation_notification($emailtype, $touser, $fromuser, $evaluationinstance){
        if($notification = $this->get_existing_notification($evaluationinstance, $emailtype)){
            $this->send_evaluation_notification($evaluationinstance, $touser, $fromuser, $emailtype, $notification);
        }
    }
    public function get_existing_notification($evaluationinstance, $emailtype){
    	$corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        $params = array();
        $notification_typesql = "SELECT lni.* FROM {local_notification_info} AS lni 
            JOIN {local_notification_type} AS lnt ON lnt.id=lni.notificationid
            WHERE concat(',',lni.moduleid,',') LIKE concat('%,',:moduleid,',%') AND lnt.shortname LIKE :emailtype AND lni.active=1 ";
        $params['moduleid'] = $evaluationinstance->id;
        $params['emailtype'] = $emailtype;
        if($costcenterexist){
            $costcenterid=explode('/',$evaluationinstance->open_path)[1];   
            $notification_typesql .= " AND lni.open_path LIKE '%$costcenterid'";
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
                $costcenterid=explode('/',$evaluationinstance->open_path)[1];   
                $notification_typesql .= " AND lni.open_path LIKE '%$costcenterid'";
            }
            $notification = $this->db->get_record_sql($notification_typesql, $params);
        }
        if(empty($notification)){
            return false;
        }else{
            return $notification;
        }
    }
    public function send_evaluation_notification($evaluationinstance, $touser, $fromuser, $emailtype, $notification){
        $datamailobject = new \stdClass();
    	$datamailobject->notification_infoid = $notification->id;
    	$datamailobject->feedback_name = $evaluationinstance->name;
    	if($evaluationinstance->timeopen && $evaluationinstance->timeclose){
            $datamailobject->feedback_schedule = \local_costcenter\lib::get_userdate("d/m/Y H:i",$evaluationinstance->timeopen).' To '. \local_costcenter\lib::get_userdate("d/m/Y H:i",$evaluationinstance->timeclose);
        }else{
            $datamailobject->feedback_schedule = get_string('openevaluation', 'local_evaluation');
        }
        $enroldate = $this->db->get_field('local_evaluation_users', 'timecreated', array('userid' => $touser->id, 'evaluationid' => $evaluationinstance->id));
    	$datamailobject->feedback_enroldate = \local_costcenter\lib::get_userdate("d/m/Y H:i", $enroldate);

        if($emailtype == "feedback_unenrollment"){
            $datamailobject->feedback_unenroldate = \local_costcenter\lib::get_userdate("d/m/Y H:i");
        }

    	$datamailobject->feedback_username = fullname($touser);
    	$datamailobject->feedback_email = $touser->email;
    	$url = new \moodle_url($CFG->wwwroot.'/local/evaluation/show_entries.php?id='.$evaluationinstance->id.'&userid='.$touser->id);
        $datamailobject->feedback_url = \html_writer::link($url, $url);
    	if($emailtype){
			$completedon = $this->db->get_field('local_evaluation_completed', 'timemodified',  array('evaluation'=>$data->id, 'userid'=>$touser->id));
            if ($completedon) {
                $completedon = \local_costcenter\lib::get_userdate("d/m/Y H:i", $completedon);
            } else {
                $completedon = 'N/A';
            }
    		$datamailobject->feedback_completeddate = $completedon;
    	}
    	$datamailobject->adminbody = NULL;
        $datamailobject->body = $notification->body;
        $datamailobject->subject = $notification->subject;
        $datamailobject->evaluationid = $evaluationinstance->id;
        $datamailobject->touserid = $touser->id;
        $datamailobject->fromuserid = $fromuser->id;
        $datamailobject->teammemberid = 0;
        if(!empty($notification->adminbody) && !empty($touser->open_supervisorid)){
            $superuser = \core_user::get_user($touser->open_supervisorid);
        }else{
            $superuser = false;
        }
        if($touser->suspended == 0 && $touser->deleted == 0){
        $this->log_email_notification($touser, $fromuser, $datamailobject);
    }
        if($superuser && $superuser->suspended == 0 && $superuser->deleted == 0){
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
        $dataobject->moduleid = $datamailobj->evaluationid;
        
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
        if($datamailobj->evaluationid){
            $sql .= " AND moduleid=:evaluationid";
            $params['evaluationid'] = $datamailobj->evaluationid;
        }
        if($datamailobj->teammemberid){
            $sql .= " AND teammemberid=:teammemberid";
            $params['teammemberid'] = $datamailobj->teammemberid;
        }
        return $this->db->get_field_sql($sql ,$params);
    }
    public function replace_strings($dataobject, $data){       
        $strings = $this->db->get_records('local_notification_strings', array('module' => 'feedback'));        
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
