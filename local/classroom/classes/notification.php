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
 * @subpackage local_classroom
 */
namespace local_classroom;
class notification{
	public $db;
	public $user;
	public function __construct($db=null, $user=null){
		global $DB, $USER;
		$this->db = $db ? $db :$DB;
		$this->user = $user ? $user :$USER;
	}
    public function get_classroom_strings($emailtype){
        // switch($emailtype){
        //     case 'classroom_enrol':done
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],[classroom_link],
        //                     [classroom_enroluseremail],
        //                     [classroom_location_fullname],
        //                     [classroom_Addressclassroomlocation],
        //                     [classroom_classroomsummarydescription],
        //                     [classroom_classroom_image]";
        //         break;

        //     case 'classroom_unenroll':
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],[classroom_link],
        //                     [classroom_enroluseremail],
        //                     [classroom_location_fullname],
        //                     [classroom_Addressclassroomlocation],
        //                     [classroom_classroomsummarydescription],
        //                     [classroom_classroom_image]";
        //         break;

        //     case 'classroom_invitation':
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
        //                     [classroom_enroluseremail],[classroom_link],
        //                     [classroom_location_fullname],
        //                     [classroom_Addressclassroomlocation],
        //                     [classroom_classroomsummarydescription],
        //                     [classroom_classroom_image]";
        //         break;

        //     case 'classroom_hold': done
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
        //                     [classroom_enroluseremail],[classroom_link]";
        //         break;

        //     case 'classroom_cancel':done
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
        //                     [classroom_enroluseremail],[classroom_link]";
        //         break;

        //     case 'classroom_complete':done
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
        //                     [classroom_enroluseremail], [classroom_completiondate],[classroom_link]";
        //         break;
        //     case 'classroom_nomination':
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
        //                     [classroom_enroluseremail], [classroom_completiondate],[classroom_link]";
        //         break;
        //     case 'classroom_reminder':
        //         $strings = "[classroom_name], [classroom_course], [classroom_startdate],
        //                     [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
        //                     [classroom_enroluseremail],[classroom_link],
        //                     [classroom_location_fullname],
        //                     [classroom_Addressclassroomlocation],
        //                     [classroom_classroomsummarydescription],
        //                     [classroom_classroom_image]";
        //         break;
        // }


    }
    public function classroom_notification($emailtype, $touser, $fromuser, $classroominstance,$waitinglistid=0){
        if($notification = $this->get_existing_notification($classroominstance, $emailtype)){
            $this->send_classroom_notification($classroominstance, $touser, $fromuser, $emailtype, $notification,$waitinglistid);
        }
        $this->mobile_notifications($emailtype, $classroominstance, $touser, $fromuser);
    }
    public function get_existing_notification($classroominstance, $emailtype){
        $corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        $params = array();
        $notification_typesql = "SELECT lni.* FROM {local_notification_info} AS lni
            JOIN {local_notification_type} AS lnt ON lnt.id=lni.notificationid
            WHERE concat(',',lni.moduleid,',') LIKE concat('%,',:moduleid,',%') AND lnt.shortname LIKE :emailtype AND lni.active=1 ";
        $params['moduleid'] = $classroominstance->id;
        $params['emailtype'] = $emailtype;
        if($costcenterexist){
            $notification_typesql .= " AND concat('/',lni.open_path,'/') LIKE :costcenter";
            $params['costcenter'] = '%'.$classroominstance->costcenter.'%';
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
                $params['costcenter'] = '%'.$classroominstance->costcenter.'%';
            }
            $notification = $this->db->get_record_sql($notification_typesql, $params);
        }
        if(empty($notification)){
            return false;
        }else{
            return $notification;
        }
    }
	public function send_classroom_notification($classroominstance, $touser, $fromuser, $emailtype, $notification,$waitinglistid=0){
        global $PAGE,$CFG;
        $classroomcourses_sql = "SELECT lcc.courseid, c.fullname
            FROM {local_classroom_courses} lcc
            JOIN {course} c ON lcc.courseid = c.id
            WHERE lcc.classroomid = {$classroominstance->id} ";
        $creater = \core_user::get_user($classroominstance->usercreated);
        $classroomcourses =  $this->db->get_records_sql_menu($classroomcourses_sql);
        $datamailobject = new \stdClass();
        $datamailobject->classroom_name = $classroominstance->name ? $classroominstance->name : 'N/A';
        $datamailobject->classroom_course = $classroomcourses ? implode(', ', $classroomcourses).'.' : 'N/A';
        $datamailobject->classroom_startdate = $classroominstance->startdate ? \local_costcenter\lib::get_userdate("d/m/Y H:i",$classroominstance->startdate) :'N/A';
        $datamailobject->classroom_enddate = $classroominstance->enddate ? \local_costcenter\lib::get_userdate("d/m/Y H:i",$classroominstance->enddate) :'N/A';
        $datamailobject->classroom_creater = $creater ? fullname($creater) :'N/A';
        $datamailobject->notification_infoid = $notification->id;
        $touserid = '';

        $corecomponent = new \core_component();
        $costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        if($costcenterexist && !empty($classroominstance->department)){
            $classroom_department = $this->db->get_field('local_costcenter', 'fullname', array('id' => $classroominstance->department));
            $datamailobject->classroom_department=$classroom_department ? $classroom_department :'N/A';
        }else{
            $datamailobject->classroom_department ='N/A';
        }
        $renderer = $PAGE->get_renderer('local_classroom');

        $classroomsessionsinfo=$renderer->view_classroom_sessions($classroominstance->id);
        $datamailobject->classroom_sessionsinfo = $classroomsessionsinfo ? $classroomsessionsinfo :'N/A';


        $classroomasignedrolename=$this->get_rolename_inclassroom($classroominstance, $touserid);
        $datamailobject->classroom_asigned_rolename = $classroomasignedrolename ? $classroomasignedrolename :'N/A';


        $datamailobject->classroom_enroluserfulname = $touser ? fullname($touser) :'N/A';
        $datamailobject->classroom_enroluseremail = $touser->email ? $touser->email : 'N/A';
        $url = new \moodle_url($CFG->wwwroot.'/local/classroom/view.php?cid='.$classroominstance->id);
        $datamailobject->classroom_link = '<a href='.$url.'>'.$url.'</a>';
        $additionrequirements = array('classroom_reminder', 'classroom_invitation',
            'classroom_unenroll', 'classroom_enrol','classroom_enrolwaiting');
        if(in_array($emailtype, $additionrequirements)){
            $institutes = $this->db->get_record_sql("SELECT li.fullname, li.address
                        FROM {local_classroom} lc
                        JOIN {local_location_institutes} li ON lc.instituteid = li.id
                        WHERE lc.id = {$classroominstance->id}");
            $datamailobject->classroom_location_fullname = $institutes->fullname ? $institutes->fullname : 'N/A';
            $datamailobject->classroom_Addressclassroomlocation = $institutes->address ? $institutes->address : 'N/A';
            $datamailobject->classroom_classroomsummarydescription = $classroominstance->description ? $classroominstance->description : 'N/A';
            $classroominclude = new \local_classroom\includes();
            $classesimg = $classroominclude->get_classroom_summary_file($classroominstance);
            $datamailobject->classroom_classroom_image = \html_writer::img($classesimg, $classroominstance->name,array());
        }
        if($emailtype =='classroom_enrolwaiting'){
            $datamailobject->classroom_waitinguserfulname = $touser ? fullname($touser) :'N/A';
            $datamailobject->classroom_waitinguseremail = $touser->email ? $touser->email : 'N/A';
            $waitingorder=$this->db->get_field('local_classroom_waitlist','sortorder',array('id'=>$waitinglistid));
            $datamailobject->classroom_waitinglist_order = $waitingorder ? $waitingorder : 'N/A';
        }
        $completiondatestring = array('classroom_nomination', 'classroom_complete');
        if(in_array($emailtype, $completiondatestring)){
            $completiondate = $this->db->get_field('local_classroom_users', 'completiondate', array('userid' => $touser->id, 'classroomid' => $classroominstance->id));
            $datamailobject->classroom_completiondate = $completiondate ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $completiondate) : 'N/A';
        }
        $datamailobject->body = $notification->body;
        $datamailobject->subject = $notification->subject;
        $datamailobject->classroomid = $classroominstance->id;
        $datamailobject->touserid = $touser->id;
        $datamailobject->fromuserid = $fromuser->id;
        $datamailobject->teammemberid = 0;
        // $fromuser = \core_user::get_support_user();
        if(!empty($notification->adminbody) && !empty($touser->open_supervisorid)){
            $superuser = \core_user::get_user($touser->open_supervisorid);
        }else{
            $superuser = false;
        }
        // if(class_exists('\notifications')){
        //     $notifications_lib = new \notifications();
        //     $notifications_lib->send_email_notification($emailtype, $datamailobj, $touser->id, $fromuser->id);
        //     if($superuser){
        //         $datamailobj->body = null;
        //         $datamailobj->adminbody = $notification->adminbody;
        //         $notifications_lib->send_email_notification($emailtype, $datamailobj, $superuser->id, $fromuser->id);
        //     }
        // }else{
            // print_r($datamailobject);die;
            $this->log_email_notification($touser, $fromuser, $datamailobject);
            if($superuser){
                $datamailobject->body = $notification->adminbody;
                $datamailobject->touserid = $superuser->id;
                $datamailobject->teammemberid = $touser->id;
                $this->log_email_notification($superuser, $fromuser, $datamailobject);
            }
        // }
	}
    public function get_rolename_inclassroom($classroominstance, $touserid){
        $touser = '';
        $istrainer = $this->db->record_exists('local_classroom_trainers', array('classroomid' => $classroominstance->id, 'trainerid' => $touser->id));
        if(!$istrainer){
            return get_string('employeerolestring', 'local_classroom');
        }else{
            return get_string('trainerrolestring', 'local_classroom');
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
        $dataobject->moduleid = $datamailobj->classroomid;

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
        if($datamailobj->classroomid){
            $sql .= " AND moduleid=:classroomid";
            $params['classroomid'] = $datamailobj->classroomid;
        }
        if($datamailobj->teammemberid){
            $sql .= " AND teammemberid=:teammemberid";
            $params['teammemberid'] = $datamailobj->teammemberid;
        }
        return $this->db->get_field_sql($sql ,$params);
    }
    public function replace_strings($dataobject, $data){
        $strings = $this->db->get_records('local_notification_strings', array('module' => 'classroom'));
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
        if ($type == 'classroom_enrol') {
            $component = 'local_classroom';
            $name = 'classroomenrolment';
            $instance->classroomurl = '<a href="' . $CFG->wwwroot . '/local/classroom/view.php?cid=' . $instance->id . '"></a>';
            $messagesubject = get_string('classroomenrolmentsub', 'local_classroom');
            $message = get_string('classroomenrolment', 'local_classroom', $instance);
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
