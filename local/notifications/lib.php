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
 * @subpackage local_notifications
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Serve the new notification form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_notifications_output_fragment_new_notification_form($args) {
    global $CFG, $DB, $PAGE;
    $args = (object) $args;
    $context = $args->context;
    $id = $args->id;
    $o = '';
	
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $data = new stdclass();
    if ($id > 0) {
        $data = $DB->get_record('local_notification_info', array('id'=>$id));
        if($args->form_status == 0){
            if ($data->courses)
            $data->course = explode(',',$data->courses);
            $data->body =       array('text'=>$data->body, 'format'=>1);
        }else{
            $data->adminbody =       array('text'=>$data->adminbody, 'format'=>1);
        }
		if(!empty($data->moduleid)){
			$args->moduleid=explode(',',$data->moduleid);
		}
		if (!empty($formdata)) {
			$args->moduleid=$formdata['moduleid'];
		}
		//print_object($formdata);
        $mform = new \local_notifications\forms\notification_form(null, array('form_status' => $args->form_status,'id' => $id,'org'=>$data->costcenterid,'notificationid'=>$args->notificationid,'moduleid'=>$args->moduleid), 'post', '', null, true, $formdata);
        $mform->set_data($data);
    }else{
    $params = array('form_status' => $args->form_status,'id' => $id,'org'=>$formdata['costcenterid'],'notificationid'=>$formdata['notificationid'],'moduleid'=>$formdata['moduleid']);
    $mform = new \local_notifications\forms\notification_form(null, $params, 'post', '', null, true, $formdata);
    }

    if (!empty($args->jsonformdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    
    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    $renderer = $PAGE->get_renderer('local_notifications');
    ob_start();
    $formstatus = array();
    foreach (array_values($mform->formstatus) as $k => $mformstatus) {
        $activeclass = $k == $args->form_status ? 'active' : '';
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass);
    }
    $formstatusview = new \local_notifications\output\form_status($formstatus);
    $o .= $renderer->render($formstatusview);
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
 
    return $o;
}

class notifications {
	/**
	* notification strings
	*
	* @param string $notif_shortname notification identifier
	* @return string notification strings
	*/
    function get_string_identifiers($notif_shortname){
        
        switch($notif_shortname){
            
            case 'course_enrol':
                $strings = "[course_title], [course_enrolstartdate],
                            [course_enrolenddate], [course_department],
                            [course_description], [course_url],
                            [enroluser_fullname], [enroluser_email]";
                break;
            case 'course_complete':
                $strings = "[course_title], [course_enrolstartdate],
                            [course_enrolenddate], [course_department],
                            [course_description], [course_url],
                            [enroluser_fullname], [enroluser_email], [course_completiondate]";
                break;
            case 'course_unenroll':
                $strings = "[course_title], [course_enrolstartdate],
                            [course_enrolenddate], [course_department],
                             [course_description],
                             [enroluser_fullname], [enroluser_email]";
                break;
            case 'course_reminder':
                $strings = "[course_title], [course_enrolstartdate],
                            [course_enrolenddate], [course_department],
                            [course_description],  [course_url],
                            [enroluser_fullname], [enroluser_email], [course_reminderdays]";
                break;
			case 'course_notification':
                $strings = "[course_title], [course_enrolstartdate],
                            [course_enrolenddate], [course_department],
                            [course_description],  [course_url],
                            [enroluser_fullname], [enroluser_email]";
                break;
            case 'classroom_enrol':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],[classroom_link],
                            [classroom_enroluseremail],
                            [classroom_location_fullname],
                            [classroom_Addressclassroomlocation],
                            [classroom_classroomsummarydescription],
                            [classroom_classroom_image]";
                break;
            case 'classroom_enrolwaiting':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_waitinguserfulname],
                            [classroom_waitinguseremail],[classroom_waitinglist_order],
                            [classroom_location_fullname],
                            [classroom_Addressclassroomlocation],
                            [classroom_classroomsummarydescription],
                            [classroom_classroom_image]";
                break;
            case 'classroom_unenroll':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],[classroom_link],
                            [classroom_enroluseremail],
                            [classroom_location_fullname],
                            [classroom_Addressclassroomlocation],
                            [classroom_classroomsummarydescription],
                            [classroom_classroom_image]";
                break;
            
            case 'classroom_invitation':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
                            [classroom_enroluseremail],[classroom_link],
                            [classroom_location_fullname],
                            [classroom_Addressclassroomlocation],
                            [classroom_classroomsummarydescription],
                            [classroom_classroom_image]";
                break;
            
            case 'classroom_hold':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
                            [classroom_enroluseremail],[classroom_link]";
                break;
            
            case 'classroom_cancel':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
                            [classroom_enroluseremail],[classroom_link]";
                break;
            
            case 'classroom_complete':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
                            [classroom_enroluseremail], [classroom_completiondate],[classroom_link]";
                break;
            case 'classroom_nomination':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
                            [classroom_enroluseremail], [classroom_completiondate],[classroom_link]";
                break;
            case 'classroom_reminder':
                $strings = "[classroom_name], [classroom_course], [classroom_startdate],
                            [classroom_enddate], [classroom_creater], [classroom_department], [classroom_sessionsinfo], [classroom_enroluserfulname],
                            [classroom_enroluseremail],[classroom_link],
                            [classroom_location_fullname],
                            [classroom_Addressclassroomlocation],
                            [classroom_classroomsummarydescription],
                            [classroom_classroom_image]";
                break;
            case 'ilt_opting':
                $strings = "[ilt_name], [ilt_course], [ilt_startdate],
                            [ilt_enddate], [ilt_creater], [ilt_department], [ilt_sessionsinfo], [ilt_enroluserfulname],
                            [ilt_enroluseremail],[ilt_link]";
                break;
            case 'ilt_reason':
                $strings = "[ilt_enroluserfulname], [ilt_enroluseremail], [ilt_name], [ilt_startdate],[ilt_enddate],[ilt_reason],[ilt_remark]";
                break;
            case 'ilt_optclassroom_cancel':
                $strings = "[ilt_enroluserfulname], [ilt_enroluseremail], [ilt_name], [ilt_startdate],[ilt_enddate],[ilt_reason],[ilt_remark]";
                break;
            case 'ilt_optrequest_cancel':
                $strings = "[ilt_enroluserfulname], [ilt_enroluseremail], [ilt_name], [ilt_startdate],[ilt_enddate],[ilt_reason],[ilt_remark]";
                break;
            case 'ilt_feedback':
                $strings = "[ilt_name], [ilt_course], [ilt_startdate],
                            [ilt_enddate], [ilt_creater], [ilt_department], [ilt_sessionsinfo], [ilt_enroluserfulname],
                            [ilt_enroluseremail],[ilt_link]";
                break;
            case 'new_course':
                $strings = "[ilt_name], [ilt_course], [ilt_startdate],
                            [ilt_enddate], [ilt_creater], [ilt_department], [ilt_sessionsinfo], [ilt_enroluserfulname],
                            [ilt_enroluseremail], [ilt_completiondate]";
                break;
            case 'new_ilt_added':
                $strings = "[ilt_name], [ilt_course], [ilt_startdate],
                            [ilt_enddate], [ilt_creater], [ilt_department], [ilt_sessionsinfo], [ilt_enroluserfulname],
                            [ilt_enroluseremail], [ilt_completiondate]";
                break;
            case 'learningplan_enrol':
                $strings = "[lep_name], [lep_course], [lep_enroluserfulname],
                            [lep_department], [lep_type],
                            [lep_enroluseremail],[lep_creator],[lep_link]";
                break;
            case 'learningplan_unenrol':
                $strings = "[lep_name], [lep_course], [lep_enroluserfulname],
                            [lep_department], [lep_type],
                            [lep_enroluseremail],[lep_creator],[lep_unenroldate]";
                break;
            case 'learningplan_completion':
                $strings = "[lep_name], [lep_course], [lep_department],
                            [lep_status], [lep_enroluserfulname],
                            [lep_enroluseremail], [lep_completiondate],[lep_creator],[lep_link]";
                break;
            case 'lep_approval_request':
                $strings = "[lep_name], [lep_course], 
                            [lep_status], [lep_enroluserfulname],[lep_type],[lep_department],
                            [lep_enroluseremail],[lep_creator],[lep_link]";
                break;
            case 'lep_rejected':
                $strings = "[lep_name],[lep_course], 
                            [lep_status], [lep_enroluserfulname],[lep_department],[lep_type],
                            [lep_enroluseremail],[lep_creator],[lep_link],[lep_rejectmsg]";
                break;
            case 'lep_approvaled':
                $strings = "[lep_name],[lep_course],[lep_status],[lep_enroluserfulname],[lep_department],[lep_type],
                            [lep_enroluseremail],[lep_creator],[lep_link]";
                break;
            case 'lep_reminder':
                $strings = "[lep_name],[lep_course],[lep_status],[lep_enroluserfulname],[lep_department],[lep_type],
                            [lep_enroluseremail],[lep_creator],[lep_link]";
                break;
            case 'lep_nomination':
                $strings = "[lep_name],[lep_course], 
                            [lep_enroluserfulname],[lep_department],[lep_type],
                            [lep_enroluseremail],[lep_creator],[lep_link]";
                break;
            case 'onlinetest_enrollment':
                $strings = "[test_name],[test_schedule], 
                            [test_enroldate],[test_username],
                            [test_email],[test_url]";
                break;
            case 'onlinetest_unenrollment':
                $strings = "[test_name],[test_schedule], 
                            [test_unenroldate],[test_username],
                            [test_email]";
                break;
            case 'onlinetest_due':
                $strings = "[test_name],[test_schedule], 
                            [test_enroldate],[test_username],
                            [test_email],[test_url]";
                break;
            case 'onlinetest_completed':
                $strings = "[test_name],[test_schedule], 
                            [test_enroldate],[test_username],
                            [test_email],[test_url],[test_completeddate]";
                break; 
            case 'feedback_enrollment':
                $strings = "[feedback_name],[feedback_schedule], 
                            [feedback_enroldate],[feedback_username],
                            [feedback_email],[feedback_url]";
                break;
            case 'feedback_unenrollment':
                $strings = "[feedback_name],[feedback_schedule], 
                            [feedback_unenroldate],[feedback_username],
                            [feedback_email]";
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
            case 'forum_subscription':
                $strings = "[forum_name],
                            [forum_enroldate],[forum_username],
                            [forum_email],[forum_url]";
                break; 
            case 'forum_unsubscription':
                $strings = "[forum_name],
                            [forum_enroldate],[forum_username],
                            [forum_email],[forum_url]";
                break;   
            case 'forum_reply':
                $strings = "[forum_name],
                            [forum_enroldate],[forum_username],
                            [forum_email],[forum_url]";
                break; 
            case 'forum_post':
                $strings = "[forum_name],
                            [forum_enroldate],[forum_username],
                            [forum_email],[forum_url]";
                break;    
         
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
            // case 'program_session_unenroll':
            //     $strings = "[program_name], [program_level], [program_course], [program_session_name], [program_session_username], [program_session_link], [program_session_useremail], [program_session_trainername], [program_session_startdate], [program_session_enddate]";   
            //     break;
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
        return $strings;
    }

    
    
	/**
	* create / update notification template
	*
	* @param string $table
	* @param int $action insert / update value
	* @param object $dataobject object containing notiifcation info
	* @return boolean true / false based on db execution
	*/
	function insert_update_record($table, $action, $dataobject){
        global $DB;
        if($action == 'insert'){
            //$systemcontext = context_system::instance();
            $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
            $str=$dataobject->body;
            $keywords = preg_split("/[\s,]+/", $dataobject->body);
            $keywords2 = preg_split("/[\s,]+/", $keywords[1]);
            $pieces = explode("/", $keywords2[0]);          
            file_save_draft_area_files($pieces[8], $systemcontext->id, 'local', 'notifications',$pieces[8], array('maxfiles' => 5));
            $result = $DB->insert_record("$table", $dataobject);
        } elseif($action == 'update') {
            // $systemcontext = context_system::instance();
            $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
            $str=$dataobject->body;
            $keywords = preg_split("/[\s,]+/", $dataobject->body);
            $keywords2 = preg_split("/[\s,]+/", $keywords[1]);
            $pieces = explode("/", $keywords2[0]);  
            file_save_draft_area_files($pieces[8], $systemcontext->id, 'local', 'notifications',$pieces[8], array('maxfiles' => 5));
             $DB->update_record("$table", $dataobject);
             $result =$dataobject->id;
        }else{
            $result = false;
        }
        return $result;
    }
    
    
	/**
	* inserts notification info into emial log
	*
	* @param string $emailtype type of email
	
	* @param object $dataobj object containing notiifcation info
	* @param int $touserid recepient userid
	* @param int $fromuserid sender userid
	* @param int $batchid classroom id // optional
	* @param int $planid learning plan id // optional
	* @return boolean true / false based on db execution
	*/
	function send_email_notification($emailtype, $dataobj, $touserid, $fromuserid, $batchid = 0, $planid = 0) {
        global $DB, $USER; 
  
		if($touserid){
            $costcenter = $DB->get_field_sql("SELECT open_costcenterid from {user} where id in($touserid)");			
		} else {
		    return false;
		}
	 	$sql = "SELECT ni.*
			FROM {local_notification_info} ni
			JOIN {local_notification_type} nt ON nt.id = ni.notificationid
			WHERE nt.shortname = '$emailtype' and ni.costcenterid = {$costcenter} and ni.active=1";
		// }
        $notfn_data = $DB->get_record_sql($sql);
            
        $touser = $DB->get_record('user', array('id'=>$touserid));
        $fromuser = $DB->get_record('user', array('id'=>$fromuserid));
      
        if($notfn_data){
            $dataobject = new stdclass();
            $dataobject->notification_infoid = $notfn_data->id;
            
            $dataobject->to_userid = $touserid;
            $dataobject->from_userid = $fromuserid;
            
            $subject = $this->replace_strings($dataobj, $notfn_data->subject);
             
            $dataobject->subject = $subject;

            // if($batchid>0){
            //     $dataobj->ilt_department = $DB->get_field('local_costcenter','fullname',array('id'=>$f2fid));
            // }elseif($planid>0){
            //     $dataobj->lep_department = $DB->get_field('local_costcenter','fullname',array('id'=>$costcenter->open_costcenterid));    
            //  }
             
            if($dataobj->body != NULL){
                $emailbody = $this->replace_strings($dataobj, $notfn_data->body);
            }else{
                $emailbody = $this->replace_strings($dataobj, $notfn_data->adminbody);
             }
               
            $dataobject->emailbody = $emailbody;
            
            // if($notfn_data->attachment_filepath){
            //     $dataobject->attachment_filepath = $notfn_data->attachment_filepath;
            // }
            if($notfn_data->enable_cc==1){
                $id = $DB->get_field('user','open_supervisorid',array('userid'=>$touserid));
                if($id){
                    $dataobject->ccto= $id;
                }else{
                  $dataobject->ccto= 0;  
                }
            } else {
                $dataobject->ccto=0;
            }
            
           
            $frommailid = $DB->get_field('user','email',array('id'=>$fromuserid));            
            $sql = "select id, email from {user} where id IN($touserid)";
            $tomailid = $DB->get_records_sql_menu($sql);
            $email = implode(',',$tomailid);
           
            $sentname = $DB->get_field('user','firstname',array('id'=>$USER->id));
            $dataobject->from_emailid = $frommailid;
            $dataobject->to_emailid = $email;
            $dataobject->sentdate = 0;
            $dataobject->sent_by = $USER->id;
            $dataobject->courseid = $dataobj->courseid;
            $dataobject->time_created = time();
            $dataobject->user_created = $USER->id;
            $dataobject->batchid = $batchid;
            $res =  "SELECT * FROM {local_emaillogs} WHERE to_userid = {$touserid} AND notification_infoid = {$notfn_data->id}  AND from_userid = {$fromuserid} AND subject LIKE '{$dataobject->subject}' AND status = 0";

            //added by sarath for error reading databse
            if($dataobj->courseid){
                $res .= " AND courseid={$dataobj->courseid} "; 
            }//ended here by sarath

             $result_update = $DB->get_record_sql($res);
            // print_object($dataobject);
            // print_object($result_update);exit;
            if(empty($result_update)){
                $send=$DB->insert_record('local_emaillogs', $dataobject);
            }else{
                $status = new stdClass();
                $status->id= $result_update->id;
                $status->from_emailid = $frommailid;
                $status->to_emailid = $email;
                $status->sentdate = 0;
                $status->sent_by = $USER->id;
                $status->courseid = $dataobj->courseid;
                $status->time_created = time();
                $status->user_created = $USER->id;
                $status->batchid = $batchid;
                // $status->status=1;
                $send=$DB->update_record('local_emaillogs', $status);
            } 
        }
        return $dataobject->emailbody;
        
    }

    
	
	
	function send_email_notification_ilt_reminder($emailtype, $dataobj, $touserid, $fromuserid,$id){
       
        global $DB, $USER;
         
        $sql = "SELECT ni.*
                FROM {local_notification_info} ni
                JOIN {local_notification_type} nt ON nt.id = ni.notificationid
                WHERE nt.shortname LIKE '{$emailtype}' and ni.id = {$id} AND ni.active=1";
          
        $notfn_data = $DB->get_record_sql($sql);
        
        $touser = $DB->get_record('user', array('id'=>$touserid));
        $fromuser = $DB->get_record('user', array('id'=>$fromuserid));
       
        if($notfn_data){
            $dataobject = new stdclass();
            $dataobject->notification_infoid = $notfn_data->id;
            
            $dataobject->to_userid = $touserid;
            $dataobject->from_userid = $fromuserid;
            
            $subject = $this->replace_strings($dataobj, $notfn_data->subject);
             
            $dataobject->subject = $subject;
            
            $emailbody = $this->replace_strings($dataobj, $notfn_data->body);
          
            $dataobject->body_html = $emailbody;
            
            // if($notfn_data->attachment_filepath){
            //     $dataobject->attachment_filepath = $notfn_data->attachment_filepath;
            // }
            
            $dataobject->usercreated = $USER->id;
            $dataobject->time_created = time();
           
            $frommailid=$DB->get_field('user','email',array('id'=>$fromuserid));
            
            $sql="select id,email from {user} where id IN($touserid)";
            $tomailid=$DB->get_records_sql_menu($sql);
            $email=implode(',',$tomailid);
            if($notfn_data->enable_cc==1){
              $id=$DB->get_field('user','open_supervisorid',array('userid'=>$touserid));
                if($id){
                    $dataobject->ccto= $id;
                }else{
                  $dataobject->ccto= 0;  
                }
               
            }else{
                $dataobject->ccto=0;
            }
            
            $sentname=$DB->get_field('user','firstname',array('id'=>$USER->id));
            $dataobject->from_emailid=$frommailid;
            $dataobject->to_emailid=$email;
            $dataobject->sent_date=0;
            $dataobject->sentby_id=$USER->id;
            $dataobject->sentby_name=$sentname;
            $dataobject->courseid=$dataobj->courseid;
            $dataobject->created_date=time();
            $dataobject->batchid=0;
          
            $DB->insert_record('local_email_logs', $dataobject);
        }
        
        return $dataobject->emailbody;
        
    }

function replace_strings($dataobject, $data){
		global $DB;
        
        $strings = $DB->get_records('local_notification_strings', array());        
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

    function create_custom_email($formdata){
        global $DB, $USER;
        foreach($formdata->enrolledusers as $enroled_users){
            $dataobject = new stdClass();
            $dataobject->batchid = $formdata->ilt;
            $dataobject->from_emailid = $USER->email;
            $dataobject->from_userid = $USER->id;
            $to_emaiid = $DB->get_field('user','email',array('id'=>$enroled_users));
            $dataobject->to_emailid = $to_emaiid;
            $dataobject->to_userid = $enroled_users;
            $supevisor_id = $DB->get_field('user','open_supervisorid',array('userid'=>$enroled_users));
            if(!empty($formdata->enable_cc) && ($supevisor_id)){
                $dataobject->ccto= $supevisor_id;
            }else{
                $dataobject->ccto= 0;  
            }
            $dataobject->subject = $formdata->subject;
            $dataobject->body_html = $formdata->body['text'];
            $dataobject->created_date = time();
            $dataobject->courseid = -1;
            $dataobject->time_created = time();
            $insertdata = $DB->insert_record('local_email_logs', $dataobject);
        }
        return $insertdata;
        
    }
    
    function update_custom_email($formdata){
        global $DB, $USER;
        //updated by rajesh mythri
        foreach($formdata->enrolledusers as $enroled_users){
            $dataobject = new stdClass();
            $dataobject->batchid = $formdata->ilt;
            $dataobject->from_emailid = $USER->email;
            $dataobject->from_userid = $USER->id;
            $to_emaiid = $DB->get_field('user','email',array('id'=>$enroled_users));
            $dataobject->to_emailid = $to_emaiid;
            $dataobject->to_userid = $enroled_users;
            $supevisor_id = $DB->get_field('user','open_supervisorid',array('userid'=>$enroled_users));
            if(!empty($formdata->enable_cc) && ($supevisor_id)){
                $dataobject->ccto= $supevisor_id;
            }else{
                $dataobject->ccto= 0;  
            }
            $dataobject->subject = $formdata->subject;
            $dataobject->body_html = $formdata->body['text'];
            $dataobject->created_date = time();
            $dataobject->courseid = -1;
            $dataobject->time_created = time();
            $updatedata = $DB->update_record('local_email_logs', $dataobject);
        }
        return $updatedata;
    }
    
    function delete_custom_email($id){
        global $DB;        
        $result = $DB->delete_records("local_email_logs", array('id'=>$id));        
        return $result;
    }

}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_notifications_leftmenunode(){
    //$systemcontext = context_system::instance();
    $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
    $notificationsnode = '';
    // if(has_capability('local/notifications:view',$systemcontext) || is_siteadmin()){
    if(is_siteadmin() || has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
        $notificationsnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_notifications', 'class'=>'pull-left user_nav_div notifications'));
            $notifications_url = new moodle_url('/local/notifications/index.php');
            $notifications = html_writer::link($notifications_url, '<i class="fa fa-bell-o"></i><span class="user_navigation_link_text">'.get_string('pluginname','local_notifications').'</span>',array('class'=>'user_navigation_link'));
            $notificationsnode .= $notifications;
        $notificationsnode .= html_writer::end_tag('li');
    }

    return array('19' => $notificationsnode);
}


////////////////////////////////////////////
//////For display on index page//////////
function notification_details($tablelimits, $filtervalues){
        global $DB, $PAGE,$USER,$CFG,$OUTPUT;
        //$systemcontext = context_system::instance();
        $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
        $countsql = "SELECT count(ni.id)
                FROM {local_notification_info} AS ni
                JOIN {local_notification_type} AS nt ON ni.notificationid = nt.id
                JOIN {local_costcenter} AS lc ON ni.costcenterid = lc.id";
        $selectsql = "SELECT ni.id, nt.name, nt.shortname, ni.subject, costcenterid,ni.moduleid, lc.fullname as deptname, ni.active
                FROM {local_notification_info} ni
                JOIN {local_notification_type} nt ON ni.notificationid = nt.id
                JOIN {local_costcenter} lc ON ni.costcenterid = lc.id
            WHERE 1=1 ";
        $queryparam = array();
        if(is_siteadmin()){

        }
        elseif(!is_siteadmin()  && has_capability('local/costcenter:manage_ownorganization', $systemcontext))
        {
            // $costcenter = $DB->get_field_sql("SELECT u.open_costcenterid from {user} u where u.id = $USER->id");
            $costcenter = $USER->open_costcenterid;
            
            $concatsql .= " AND ni.costcenterid= :usercostcenter ";
            $queryparam['usercostcenter'] = $costcenter;
        }
        else {
          print_error('You dont have permissions to view this page.');
              die();
        }

        if(!empty($filtervalues->request)){

            $request = explode(',',$filtervalues->request);
            $requestsarray = array();
            foreach ($request as $key => $value) {
                $requestsarray[] = " ni.moduletype = '$value' ";
            }
            $imploderequests = implode('OR',$requestsarray);
            $concatsql .= " AND ($imploderequests) ";
        }
        $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);

        $concatsql.=" order by ni.id desc";
        $notifications_info = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);

        $list=array();
        $data = array();
        if ($notifications_info) {
            foreach ($notifications_info as $each_notification) { 
                
                /*$row = array();
                $row[] = $each_notification->name;
                $row[] = $each_notification->shortname;
                $row[] = $each_notification->subject;
                $row[] = $DB->get_field('local_costcenter', 'fullname', array('id'=>$each_notification->costcenterid));*/

              $list['notification_id'] = $each_notification->id;
              $list['contextid'] = $systemcontext->id;
               $list['notification_type'] = $each_notification->name;
               $list['code'] = $each_notification->shortname;
               $list['subject'] = $each_notification->subject;
               $list['organization']=$DB->get_field('local_costcenter', 'fullname', array('id'=>$each_notification->costcenterid));
               $data[] = $list;
            }
        }
             //print_object($data);
        return array('count' => $count, 'data' => $data); 
}

function notifications_filter($mform){
    global $DB,$USER;
    //$systemcontext = context_system::instance();
    $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
    // $sql = "SELECT id, name FROM {local_classroom} WHERE id > 1";
    if ((has_capability('local/notifications:view', (new \local_notifications\lib\accesslib())::get_module_context()) || is_siteadmin())) {
        // $requestlist = $DB->get_records_sql_menu("SELECT id, compname FROM {local_request_records} GROUP BY compname");
        // $customrequestlist = array();
        // $trainer_user = ((has_capability('local/classroom:manageclassroom',$systemcontext)||
        //         has_capability('local/program:manageprogram',$systemcontext)||
        //         has_capability('local/certification:managecertification',$systemcontext)) && !is_siteadmin() && !has_capability('local/costcenter:manage_ownorganization',$systemcontext) && !has_capability('local/costcenter:manage_owndepartments',$systemcontext));
        // foreach($requestlist as $key => $value){
        //     if($trainer_user && ($value == 'elearning' || $value == 'learningplan')){
        //         // $value = 'E-Learning';
        //         continue;    
        //     }
        //     $customrequestlist[$value] = get_string($value, 'local_request');
        // }
        // $requestlist = $customrequestlist; 
        $notificationlist = $DB->get_records_sql("SELECT distinct(moduletype) FROM {local_notification_info} ");
        foreach($notificationlist AS $list){
            $customrequestlist[$list->moduletype] = ucfirst($list->moduletype);
        }
        $requestlist = $customrequestlist; 
    }
    $select = $mform->addElement('autocomplete', 'request', '', $requestlist, array('placeholder' => get_string('compname', 'local_request')));
    $mform->setType('request', PARAM_RAW);
    $select->setMultiple(true);
}