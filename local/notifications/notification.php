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

use local_learningplan\lib\lib as lib;
require_once($CFG->dirroot.'/local/costcenter/lib.php');
require_once($CFG->dirroot.'/local/notifications/lib.php');

/**
 * class for notification trigger
 *
 * @package   local_notifications
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_triger {
    
    /* type of notiifcation*/
    private $type;
    
    /**
  * constructor for the notification trigger
  *
  * @param string $type type of notification
  */
    function __construct($type){
        $this->type = $type;
        $this->costcenterobj = new costcenter();
    }

    public function send_emaillog_notifications(){
        global $DB, $CFG;
        $starttime = strtotime(date('d/m/Y', time()));
        $endtime = $starttime+86399;
        // $sql = "SELECT * 
        //         FROM {local_emaillogs} 
        //         WHERE status = 0 AND timecreated BETWEEN {$starttime} AND {$endtime}";
        // $logs = $DB->get_records_sql($sql);
	        $logs = $DB->get_records('local_emaillogs', array('status' => 0), '', '*', 0, 50);
                
        $supportuser = \core_user::get_support_user();
        foreach($logs as $email_log){
            $record = new stdClass();
            $record->id = $email_log->id;
            $record->from_userid = $email_log->from_userid;
            $record->to_userid = $email_log->to_userid;
            $record->from_emailid = $email_log->from_emailid;
            $record->to_emailid = $email_log->to_emailid;
            $record->ccto = $email_log->ccto;
            $record->batchid = $email_log->batchid;
            $record->courseid = $email_log->courseid;
            $record->subject = $email_log->subject;
            $record->emailbody = $email_log->emailbody;
            // $record->attachment_filepath = $email_log->attachment_filepath;
            $record->status = 1;
            $record->user_created = $email_log->user_created;
            $record->time_created = $email_log->time_created;
            $record->sent_date = time();
            $record->sent_by = $supportuser->id;
            $body = '';
            
            $touser = $DB->get_record('user', array('id'=>$record->to_userid, 'deleted' => 0));
            if(empty($touser)){// check for not sending emails to deleted users
                continue;
            }
            $from_user = $DB->get_record('user', array('id'=>$record->from_userid));
           
            $get_notification_infoid = $DB->get_field('local_notification_info','notificationid',array('id'=>$email_log->notification_infoid));
            $get_local_notification_type = $DB->get_record('local_notification_type',array('id'=>$get_notification_infoid));
            $data = 'local_';
            $message = new \core\message\message();
            $message->component = $data.$get_local_notification_type->pluginname;
            $message->name = $get_local_notification_type->shortname;
            $message->userfrom = $from_user;
            $message->userto = $touser;
            $message->subject = $record->subject;
            $message->fullmessage = $record->emailbody;
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml = $record->emailbody;
            $message->smallmessage =  $record->subject;;
            $message->notification = '1';
            $message->courseid = 1;

            // $notif_info = $DB->get_record('local_notification_info',array('id'=>$email_log->notification_infoid), 'id,notificationid, moduletype, attach_certificate');
            
            // $plugin_exists = core_component::get_plugin_directory('tool', 'certificate');
            // if($plugin_exists){
            //     $notif_type = $DB->get_field('local_notification_type','shortname',array('id'=>$notif_info->notificationid));

            //     $completions_mails = array('course_complete', 'classroom_complete', 'program_completion', 'learningplan_completion', 'onlinetest_completed');   
            
            //     if(in_array($notif_type, $completions_mails) && ($notif_info->attach_certificate == 1)){
            //         switch ($notif_type) {
            //             case 'course_complete':
            //                 $certid = $DB->get_field('course', 'open_certificateid', array('id'=>$email_log->moduleid));
            //                 break;
            //             case 'classroom_complete':
            //                 $certid = $DB->get_field('local_classroom', 'certificateid', array('id'=>$email_log->moduleid));
            //                 break;
            //             case 'learningplan_completion':
            //                 $certid = $DB->get_field('local_learningplan', 'certificateid', array('id'=>$email_log->moduleid));
            //                 break;
            //             case 'onlinetest_completed':
            //                 $certid = $DB->get_field('local_onlinetests', 'certificateid', array('id'=>$email_log->moduleid));
            //                 break;
            //             case 'program_completion':
            //                 $certid = $DB->get_field('local_program', 'certificateid', array('id'=>$email_log->moduleid));
            //                 break;
            //             default:
            //                 $certid = null;
            //                 break;
            //         }
            //     }
                // if($certid){                
                //     $moduleinfo = new \stdClass();
                //     $moduleinfo->moduletype = explode('_',$notif_type)[0];
                //     $moduleinfo->moduleid = $email_log->moduleid;

                //     $template = $DB->get_record('local_certificate', array('id' => $certid), '*', MUST_EXIST);
                //     $template = new \tool_certificate\template($template);
                //     $template->generate_pdf(false, $touser->id, false, $moduleinfo, true);

                //     $filename = 'Biz_user'.$touser->id.'_'.$moduleinfo->moduletype.''.$email_log->moduleid.'.pdf';
                //     $filepath = '/user_certificates/'.$filename;

                //     $usercontext = context_user::instance($touser->id);
                //     $file = new stdClass;
                //     $file->contextid = $usercontext->id;
                //     $file->component = 'user';
                //     $file->filearea  = 'private';
                //     $file->itemid    = 0;
                //     $file->filepath  = '/user_certificates/';
                //     $file->filename  = 'Biz_user'.$touser->id.'_'.$notif_type.''.$email_log->moduleid.'.pdf';
                //     $file->source    = 'test';
                     
                //     $fs = get_file_storage();
                //     $content = file_get_contents($CFG->dataroot."/user_certificates/$filename", FILE_USE_INCLUDE_PATH);
                //     if(file_exists($CFG->dataroot."/user_certificates/$filename")){
                //         $pathnamehash = $fs->get_pathname_hash($file->contextid, $file->component, $file->filearea, $file->itemid, $file->filepath, $file->filename);
                //         $existingfile = $DB->get_record('files', array('pathnamehash' => $pathnamehash));
                //         if($existingfile){
                //             $exfile = $fs->get_file_instance($existingfile);
                //             if (!$exfile->is_directory()) {
                //                 $exfile->delete();
                //             }
                //         }
                //     }
                //     $file = $fs->create_file_from_string($file, $content);
                //     $message->attachment = $file;
                //     $message->attachname = $filename;

                //     $messageid = message_send($message);
                //     $file->delete();
                // }else{
                    // $messageid = message_send($message);
                // }
            // }else{
                $messageid = message_send($message);
            // }

            $DB->update_record('local_emaillogs',$record);

        }

    }    
}  