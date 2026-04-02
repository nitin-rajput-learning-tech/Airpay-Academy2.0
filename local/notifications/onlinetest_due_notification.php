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
// This has been added as a task in "local_onlinetest"

// require_once(dirname(__FILE__) . '/../../config.php');
// global $DB, $CFG, $USER, $PAGE, $OUTPUT;
// require_login();
// require_once($CFG->dirroot.'/local/notifications/lib.php');
// $PAGE->set_url('/local/notifications/onlinetest_due_notification.php');
// $PAGE->set_context(context_system::instance());
// $PAGE->set_pagelayout('admin');
// $PAGE->set_title(get_string('onlinetest_due', 'local_notifications'));
// $PAGE->navbar->add(get_string('onlinetest_due', 'local_notifications'));
// echo $OUTPUT->header(); 
// $sql = "SELECT lu.id as user_id,l.*,lu.userid 
// 		FROM {local_onlinetest_users} lu
// 		JOIN {local_onlinetests} l ON l.id = lu.onlinetestid
// 		WHERE lu.status !=1";// AND FROM_UNIXTIME(l.timeclose,'%Y-%m-%d') = DATE_ADD(CURDATE(),INTERVAL 1  DAY)
// $records = $DB->get_records_sql($sql);
// foreach ($records as $record) {
// 	$type = 'onlinetest_due';
//     $dataobj = $record->id;
// 	$costcenter = $DB->get_record_sql("select open_costcenterid from {user} where id = $record->userid");
//  	$get_notification_type = $DB->get_record('local_notification_type',array('shortname'=>$type));
//  	//$get_notifications = $DB->get_records('local_notification_info',array('notificationid'=>$get_notification_type->id,'active'=>1,'costcenterid'=>$costcenter->open_costcenterid));	
//  	$get_notification = $DB->get_record_sql("SELECT id,body,adminbody, reminderdays FROM {local_notification_info} WHERE notificationid = $get_notification_type->id and active=1 and costcenterid=$costcenter->open_costcenterid and FIND_IN_SET($dataobj,moduleid)");
//     if(!$get_notification){
//         $get_notification = $DB->get_record_sql("SELECT id,body,adminbody, reminderdays FROM {local_notification_info} WHERE notificationid = $get_notification_type->id and active=1 and costcenterid=$costcenter->open_costcenterid and moduleid IS NULL");
//     }
// 	if($get_notification){	
//         $remainder_days = $get_notification->reminderdays;
// 		if($DB->record_exists('local_emaillogs',array('to_userid' => $record->userid, 'notification_infoid' => $get_notification->id,'from_userid'=>$USER->id))){

// 	    }else{
// 			$singleuser = $record->userid ;
// 			$data=$DB->get_record('local_onlinetests',array('id'=>$record->id));
// 			$dataobj = new stdclass();
// 			$dataobj->test_name = $data->name;
// 			if($data->timeopen && $data->timeclose){
// 				$dataobj->test_schedule = 'Scheduled From:'. \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->timeopen).' Scheduled To: '. \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->timeclose);
// 			}else{
// 				$dataobj->test_schedule = 'Open Test';
// 			}
// 			$dataobj->test_enroldate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->timemodified);
// 			$adduser = $DB->get_record('user',array('id'=>$singleuser));
// 			$dataobj->test_username = $adduser->firstname.' '.$adduser->lastname;
// 			$dataobj->test_email = $adduser->email;
// 			$cm = get_coursemodule_from_instance("quiz", $data->quizid, 0, false, MUST_EXIST);
// 	        $url = new moodle_url($CFG->wwwroot.'/mod/quiz/view.php?id='.$cm->id);
// 			$dataobj->test_url = '<a href='.$url.'>'.$url.'</a>';
// 			$dataobj->adminbody = NULL;
// 	        $dataobj->body = $get_notification->body;
//             $dataobj->moduletype="onlinetests";
//             $dataobj->moduleid=$data->id;
// 			$touserid = $singleuser;
// 			$fromuserid = $USER->id;
// 			$notifications_lib = new notifications();
// 			$emailtype = $type;
// 			$days_ago = strtotime('-'.$remainder_days.' days', $data->timeopen);
//             $currdate = \local_costcenter\lib::get_userdate("d/m/Y H:i",time());
//             $date = explode('-', $currdate);
//             $starttime = mktime(0,0,0,$date[1],$date[0],$date[2]);
//             if($starttime < $days_ago && $days_ago < $data->timeopen && $data->timeopen >  time()){               
// 				$notifications_lib->send_email_notification($emailtype, $dataobj, $touserid, $fromuserid);
// 			}
// 		}
// 	}
// }
// echo $OUTPUT->footer();