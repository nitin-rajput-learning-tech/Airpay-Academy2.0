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
 * @subpackage local_request
 */
namespace local_request;
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
			case 'request_add':
                $strings = "[req_component], [req_componentname], 
                            [req_userfulname], [req_useremail]";
                break;
            case 'request_approve':
                $strings = "[req_component], [req_componentname], 
                            [req_userfulname], [req_useremail]";
                break; 
            case 'request_deny':
                $strings = "[req_component], [req_componentname], 
                            [req_userfulname], [req_useremail]";
                break;
		}
	}
	public function request_notification($emailtype, $requestcomponent, $touser, $fromuser, $requesteduser){
		if($notification = $this->get_existing_notification($emailtype, $touser)){
            $this->send_request_notification($requestcomponent, $touser, $fromuser, $emailtype, $notification, $requesteduser);
        }
	}
	public function get_existing_notification($emailtype, $touser){
		$corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        $params = array();
        $notification_typesql = "SELECT lni.* FROM {local_notification_info} AS lni 
            JOIN {local_notification_type} AS lnt ON lnt.id=lni.notificationid 
            WHERE lnt.shortname LIKE :emailtype AND lni.active=1 ";
        $params['emailtype'] = $emailtype;
        if($costcenterexist){
            $notification_typesql .= " AND lni.costcenterid=:costcenterid";
            $params['costcenterid'] = $touser->open_costcenterid;
        }
        $notification = $this->db->get_record_sql($notification_typesql, $params);
        if(empty($notification)){
            return false;
        }else{
            return $notification;
        }
	}
	public function send_request_notification($requestcomponent, $touser, $fromuser, $emailtype, $notification, $requesteduser){
		$datamailobject = new \stdClass();
		if($requestcomponent->compname == 'elearning'){
            $comp_name = $this->db->get_field('course','fullname',array('id'=>$requestcomponent->componentid));
        }else{
        	$comp_name = $this->db->get_field('local_'.$requestcomponent->compname,'name',array('id'=>$requestcomponent->componentid));
        }
		// elseif($requestcomponent->compname == 'learningplan'){
  //           $comp_name = $DB->get_field('local_learningplan','name',array('id'=>$data->componentid));
  //       }elseif($requestcomponent->compname == 'classroom'){
  //           $comp_name = $DB->get_field('local_classroom','name',array('id'=>$data->componentid));
  //       }elseif($requestcomponent->compname == 'program'){
  //           $comp_name = $DB->get_field('local_program','name',array('id'=>$data->componentid));
  //       }elseif($requestcomponent->compname == 'elearning'){
  //           $comp_name = $DB->get_field('course','fullname',array('id'=>$data->componentid));
  //       }
		$datamailobject->req_component = $requestcomponent->compname;
		$datamailobject->req_componentname = $comp_name;
		// $datamailobject->req_userfulname = fullname($touser);
		// $datamailobject->req_useremail = $touser->email;

        $datamailobject->req_userfulname = fullname($requesteduser);
        $datamailobject->req_useremail = $requesteduser->email;
		$datamailobject->body = $notification->body;
        $datamailobject->subject = $notification->subject;
        $datamailobject->componentid = $requestcomponent->id;
        $datamailobject->touserid = $touser->id;
        $datamailobject->fromuserid = $fromuser->id;
        $datamailobject->notification_infoid = $notification->id;
        $datamailobject->teammemberid = 0;
        if(!empty($notification->adminbody) && !empty($touser->open_supervisorid) && $emailtype != 'request_add'){
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
        $dataobject->moduleid = $datamailobj->componentid;
        
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
	public function check_pending_mail_exists($touser, $fromuser, $datamailobj){
		$sql =  " SELECT id FROM {local_emaillogs} WHERE to_userid = :userid AND notification_infoid = :infoid AND from_userid = :fromuserid AND subject = :subject AND status = 0 ";
        $params['userid'] = $datamailobj->touserid;
        $params['fromuserid'] = $datamailobj->fromuserid;
        $params['infoid'] = $datamailobj->notification_infoid;
        $params['subject'] = $datamailobj->subject;
        if($datamailobj->componentid){
            $sql .= " AND moduleid=:componentid";
            $params['componentid'] = $datamailobj->componentid;
        }
        if($datamailobj->teammemberid){
            $sql .= " AND teammemberid=:teammemberid";
            $params['teammemberid'] = $datamailobj->teammemberid;
        }
        return $this->db->get_field_sql($sql ,$params);
	}
	 public function replace_strings($dataobject, $data){       
        $strings = $this->db->get_records('local_notification_strings', array('module' => 'request'));
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