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
namespace local_learningplan;
class notification {
	public $db;
	public $user;
	public function __construct($db=null, $user=null){
		global $DB, $USER;
		$this->db = $db ? $db :$DB;
		$this->user = $user ? $user :$USER;
	}
	public function get_notification_strings($emailtype){
		switch($emailtype){
			case 'learningplan_enrol':
                $strings = "[lep_name], [lep_course], [lep_enroluserfulname],
                            [lep_type],
                            [lep_enroluseremail],[lep_creator],[lep_link]";
            break;
            case 'learningplan_completion':
                $strings = "[lep_name], [lep_course],
                            [lep_status], [lep_enroluserfulname],
                            [lep_enroluseremail], [lep_completiondate],[lep_creator],[lep_link]";
            break;
		}
	}
	public function learningplan_notification($emailtype, $touser, $fromuser, $learningplaninstance){
        if($notification = $this->get_existing_notification($learningplaninstance, $emailtype)){
            $this->send_learningplan_notification($learningplaninstance, $touser, $fromuser, $emailtype, $notification);
        }
    }
    public function get_existing_notification($learningplaninstance, $emailtype){
    	$corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        $params = array();
        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$learningplaninstance->open_path);
        $notification_typesql = "SELECT lni.* FROM {local_notification_info} AS lni 
            JOIN {local_notification_type} AS lnt ON lnt.id=lni.notificationid
            WHERE concat(',',lni.moduleid,',') LIKE concat('%',:moduleid,'%') AND lnt.shortname LIKE :emailtype AND lni.active=1 ";
        $params['moduleid'] = $learningplaninstance->id;
        $params['emailtype'] = $emailtype;
        if($costcenterexist){
            $notification_typesql .= " AND concat('/',lni.open_path,'/') LIKE :costcenter ";
            $params['costcenter'] = $org;
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
                $notification_typesql .= " AND concat('/',lni.open_path,'/') LIKE  concat('%',:costcenter,'%') ";
                $params['costcenter'] = $org;
            }
            $notification = $this->db->get_record_sql($notification_typesql, $params);
        }
        if(empty($notification)){
            return false;
        }else{
            return $notification;
        }
    }
    public function send_learningplan_notification($learningplaninstance, $touser, $fromuser, $emailtype, $notification){
    	$courses_sql = "SELECT lcc.id, c.fullname as coursename
                        FROM {local_learningplan_courses} lcc
                        JOIN {local_learningplan} lc ON lc.id = lcc.planid 
                        JOIN {course} c ON c.id = lcc.courseid
                        WHERE lc.id = :planid";
    	$courses = $this->db->get_records_sql_menu($courses_sql, array('planid' => $learningplaninstance->id));
		$coursenames = implode(' , ',$courses).'.';
    	$datamailobject = new \stdClass();
        $datamailobject->notification_infoid = $notification->id;
    	$datamailobject->lep_name = $learningplaninstance->name;
    	$datamailobject->lep_course = $coursenames;
    	$datamailobject->lep_enroluserfulname = fullname($touser);
    	$corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        if($costcenterexist && !empty($learningplaninstance->department)){
    		$datamailobject->lep_department = $this->db->get_field('local_costcenter', 'fullname', array('id' => $learningplaninstance->department));
    	}
    	if($learningplaninstance->learning_type == 1){
            $plantype = 'Core Courses';
        }elseif($learningplaninstance->learning_type == 2){
            $plantype = 'Elective Courses';
        }else{
            $plantype = 'N/A';
        }
        if($emailtype == "learningplan_unenrol"){
            $datamailobject->lep_unenroldate = \local_costcenter\lib::get_userdate("d/m/Y H:i");
        }
    	$datamailobject->lep_type = $plantype;
    	$datamailobject->lep_enroluseremail = $touser->email;
    	$datamailobject->lep_creator = $this->db->get_field_sql("SELECT concat(firstname,' ',lastname) FROM {user} WHERE id =:creatorid" ,array('creatorid' => $learningplaninstance->usercreated));
    	$url = new \moodle_url($CFG->wwwroot.'/local/learningplan/plan_view.php?id='.$learningplaninstance->id);
        $datamailobject->lep_link = '<a href='.$url.'>'.$url.'</a>';
        if($emailtype == 'learningplan_completion'){
            $completiondate = $this->db->get_field('local_learningplan_user', 'completiondate', array('userid' => $touser->id, 'status' => 1));
	    	$datamailobject->lep_completiondate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$completiondate);
	    	$datamailobject->lep_status = $completiondate ? get_string('learningplancompleted', 'local_learningplan') : get_string('learningplanpending', 'local_learningplan');
	    }
	    $datamailobject->adminbody = NULL;
        $datamailobject->body = $notification->body;
        $datamailobject->subject = $notification->subject;
        $datamailobject->learningplanid = $learningplaninstance->id;
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
        $dataobject->moduleid = $datamailobj->learningplanid;

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
    public function replace_strings($dataobject, $data){       
        $strings = $this->db->get_records('local_notification_strings', array('module' => 'learningplan'));
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
    public function check_pending_mail_exists($user, $fromuser, $datamailobj){
        $sql =  " SELECT id FROM {local_emaillogs} WHERE to_userid = :userid AND notification_infoid = :infoid AND from_userid = :fromuserid AND subject = :subject AND status = 0 ";
        $params['userid'] = $datamailobj->touserid;
        $params['fromuserid'] = $datamailobj->fromuserid;
        $params['infoid'] = $datamailobj->notification_infoid;
        $params['subject'] = $datamailobj->subject;
        if($datamailobj->learningplanid){
            $sql .= " AND moduleid=:learningplanid";
            $params['learningplanid'] = $datamailobj->learningplanid;
        }
        if($datamailobj->teammemberid){
            $sql .= " AND teammemberid=:teammemberid";
            $params['teammemberid'] = $datamailobj->teammemberid;
        }
        return $this->db->get_field_sql($sql ,$params);
    }
}
