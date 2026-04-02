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
 * @subpackage local_notifications
 */
// this has been depricated and will be removed in later versions of bizlms....
// this has been added as a task in "local_notifications"

// require_once(dirname(__FILE__) . '/../../config.php');
// global $DB, $CFG, $USER, $PAGE, $OUTPUT;
// $PAGE->set_url('/local/notifications/emaillogs.php');
// $PAGE->set_context(context_system::instance());
// $PAGE->set_pagelayout('admin');
// $PAGE->set_title(get_string('pluginname', 'local_notifications'));
// $PAGE->navbar->add(get_string('pluginname', 'local_notifications'));
// echo $OUTPUT->header();

// $sql = "SELECT * 
// 		FROM {local_emaillogs} 
// 		WHERE status = 0 AND FROM_UNIXTIME(timecreated,'%Y-%m-%d') = CURDATE()";

// $logs = $DB->get_records_sql($sql);

// foreach($logs as $email_log){
// 		$record = new stdClass();
// 		$record->id = $email_log->id;
// 		$record->from_userid = $email_log->from_userid;
// 		$record->to_userid = $email_log->to_userid;
// 		$record->from_emailid = $email_log->from_emailid;
// 		$record->to_emailid = $email_log->to_emailid;
// 		$record->ccto = $email_log->ccto;
// 		$record->batchid = $email_log->batchid;
// 		$record->courseid = $email_log->courseid;
// 		$record->subject = $email_log->subject;
// 		$record->emailbody = $email_log->emailbody;
// 		$record->attachment_filepath = $email_log->attachment_filepath;
// 		$record->status = 1;
// 		$record->user_created = $email_log->user_created;
// 		$record->time_created = $email_log->time_created;
// 		$record->sent_date = time();
// 		$record->sent_by = \core_user::get_support_user()->id;
// 		$body = '';
// 		$DB->update_record('local_emaillogs',  $record);
// 		$touser = $DB->get_record('user', array('id'=>$record->to_userid));
// 		// $from_user = $DB->get_record('user', array('id'=>$record->from_userid));
// 		$from_user = core_user::get_support_user();;

// 		$get_notification_infoid = $DB->get_field('local_notification_info','notificationid',array('id'=>$email_log->notification_infoid));
// 		$get_local_notification_type = $DB->get_field('local_notification_type','shortname',array('id'=>$get_notification_infoid));
	
// 		if($get_local_notification_type=='certification_complete'){
// 		$cert = $DB->record_exists('local_certification_users', array('userid' => $record->to_userid, 'certificationid' => $record->batchid,'completion_status'=>1));
// 			if($cert){
// 				$tempdir = make_temp_directory('certificate/attachment');
// 			    if (!$tempdir) {
// 			        return false;
// 			    }

// 		    	// Now, get the PDF.
// 		       // Create new customcert issue record if one does not already exist.
// 			      $img = new local_certification\certification();
// 		        if (!$DB->record_exists('local_certification_issues', array('userid' => $record->to_userid, 'certificationid' => $record->batchid))) {
// 		            $customcertissue = new stdClass();
// 		            $customcertissue->certificationid = $record->batchid;
// 		            $customcertissue->userid = $record->to_userid;
		          
// 		            $customcertissue->code =$img->generate_code();
// 		            $customcertissue->timecreated = time();
// 		            // Insert the record into the database.
// 		            $DB->insert_record('local_certification_issues', $customcertissue);
// 		        }
		      
// 		        $templateid = $DB->get_record_sql("SELECT id,name,templateid  FROM {local_certification} WHERE id = $record->batchid");
// 		        $template = $DB->get_record('local_certification_templts', array('id' => $templateid->templateid), '*', MUST_EXIST);
// 		        $template = new \local_certification\template($template);
// 		        $filecontents =$template->generate_pdf(false, $record->to_userid, true);
		        
// 		        // Set the name of the file we are going to send.
// 		        $filename = $templateid->name;
// 		        $filename = \core_text::entities_to_utf8($filename);
// 		        $filename = \local_costcenter\lib::strip_tags_custom($filename);
// 		        $filename = rtrim($filename, '.');
// 		        $filename = str_replace('&', '_', $filename) . '.pdf';
		    
// 		        // Create the file we will be sending.
// 		        $tempfile = $tempdir . '/' . md5(microtime() . $record->to_userid) . '.pdf';
// 		        file_put_contents($tempfile, $filecontents);
// 		      	email_to_user($touser, fullname($from_user), $record->subject, $body, $record->emailbody, $tempfile, $filename);
// 		    }else{
// 		     email_to_user($touser, fullname($from_user), $record->subject, $body, $record->emailbody);
// 			}

// 	}else{
// 		     email_to_user($touser, fullname($from_user), $record->subject, $body, $record->emailbody);
// 	}

// }
// echo $OUTPUT->footer();

