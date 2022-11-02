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
// has been added as a task in "local_program".

// require_once(dirname(__FILE__) . '/../../config.php');
// require_once($CFG->dirroot . '/local/notifications/notification.php');
// require_once($CFG->dirroot.'/local/includes.php');
// // require_once($CFG->dirroot . '/local/notifications/renderer.php');
// global $DB, $CFG, $USER, $PAGE, $OUTPUT;
// require_login();
// $PAGE->set_url('/local/notifications/session_remainders.php');
// $PAGE->set_context(context_system::instance());
// $PAGE->set_pagelayout('admin');
// $PAGE->set_title(get_string('pluginname', 'local_notifications'));
// $PAGE->navbar->add(get_string('pluginname', 'local_notifications'));

// echo $OUTPUT->header();
// $get_notification_type = $DB->get_record('local_notification_type',array('shortname'=>'program_session_reminder'));
// $currtime = time();
// $sql = "SELECT ss.id, ss.sessionid, ss.userid, ss.programid FROM {local_bc_session_signups} as ss JOIN {local_bc_course_sessions} as s ON s.id=ss.sessionid WHERE ss.completion_status != 1";
// $sessions = $DB->get_records_sql($sql);
// foreach ($sessions as $session) {
// 	// echo "hiii";
// 	$singleuser=new stdClass();
// 	$singleuser->id= $session->userid;
// 	$dataobj = $session->programid;
// 	// $moduleid = $session->programid;
// 	$costcenter = $DB->get_record_sql("select open_costcenterid from {user} where id = $singleuser->id");
// 	$costcenter_trainer = $DB->get_record_sql("select open_costcenterid from {user} where id = $USER->id");
// 	// echo "rizwana";
// 	// print_object($costcenter);
// 	// print_object($costcenter->open_costcenterid);
// 	$get_notifications_emp = $DB->get_record_sql("SELECT id,body,adminbody, reminderdays FROM {local_notification_info} WHERE notificationid = $get_notification_type->id and active=1 and costcenterid=$costcenter->open_costcenterid and FIND_IN_SET($dataobj,moduleid)");
// 	// echo "SELECT id,body,adminbody, reminderdays FROM {local_notification_info} WHERE notificationid = $get_notification_type->id and active=1 and costcenterid=$costcenter->open_costcenterid and FIND_IN_SET($dataobj,moduleid)";
// 	// print_object($get_notifications_emp);
// 	if(!$get_notifications_emp){
// 		// echo ""
// 		$get_notifications_emp = $DB->get_record_sql("SELECT id,body,adminbody, reminderdays FROM {local_notification_info} WHERE notificationid = $get_notification_type->id and active=1 and costcenterid=$costcenter->open_costcenterid and moduleid IS NULL");
// 	}
// 	// print_object($get_notifications_emp);
// 	if($get_notifications_emp){
// 		// echo "rizwana";
// 		$remainder_days = $get_notifications_emp->reminderdays;
// 	    $from = $DB->get_record('user', array('id'=>$USER->id));
// 	    $data=$DB->get_record('local_bc_course_sessions',array('id'=>$session->sessionid));
// 	    $program=$DB->get_record('local_program',array('id'=>$data->programid));
// 		$dataobj = new stdclass();
						
// 	    $creater = $DB->get_record('user',array('id'=>$data->usercreated));
// 	    $dataobj->program_name = $program->name;
// 	    $level = $DB->get_field('local_program_levels',  'level',  array('id'=>$data->levelid));
// 	    $dataobj->program_level = $level;
// 	    $courseid = $DB->get_field('local_program_level_courses',  'courseid',  array('id'=>$data->bclcid));
// 	    $course = $DB->get_field('course',  'fullname',  array('id'=>$courseid));
// 	    $dataobj->program_course = $course;
// 	    $dataobj->program_startdate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$program->startdate);
// 	    $dataobj->program_enddate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$program->enddate);
// 	    // $dataobj->program_creater = $creater->firstname.' '.$creater->lastname;
// 	    $dataobj->program_session_name = $data->name;
// 	    $adduser = $DB->get_record('user',array('id'=>$singleuser->id));
// 	    if($string == NULL){
// 	        $dataobj->program_session_username = $adduser->firstname.' '.$adduser->lastname;
// 	    }elseif($string == 'trainer'){
// 	        $dataobj->program_session_username = $adduser->firstname.' '.$adduser->lastname.' '.'is enrolled as Trainer';
// 	    }
// 	    $url = new moodle_url($CFG->wwwroot.'/local/program/sessions.php?bclcid='.$data->bclcid.'&levelid='.$data->levelid.'&bcid='.$data->programid);
// 	    $dataobj->program_session_link = '<a href='.$url.'>'.$url.'</a>';
// 	    $dataobj->program_session_useremail = $adduser->email;
// 	    $trainer = $DB->get_record('user',array('id'=>$data->trainerid));
// 	    $dataobj->program_session_trainername = fullname($trainer);
// 	    $dataobj->program_session_startdate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->timestart);
// 	    $dataobj->program_session_enddate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->timefinish);
		
// 	    // $dataobj->program_completiondate = $data->description;
// 	    $dataobj->adminbody = NULL;
// 	    $dataobj->body = $get_notifications_emp->body;
// 	    $touserid = $singleuser->id;
// 	    $fromuserid = $USER->id;
// 	    $notifications_lib = new notifications();
// 	    $emailtype = 'program_session_reminder';
// 		$dataobj->moduletype="program_sessions";
// 		$dataobj->moduleid=$data->id;
// 		$days_ago = strtotime('-'.$remainder_days.' days', $data->timestart);
// 		$currdate = \local_costcenter\lib::get_userdate("d/m/Y H:i",time());
// 		$date = explode('-', $currdate);
// 		$starttime = mktime(0,0,0,$date[1],$date[0],$date[2]);
// 		if($starttime < $days_ago && $days_ago < $data->timestart && $data->timestart >  time()){
// 			// echo "mail";
// 	    	$notifications_lib->send_email_notification($emailtype, $dataobj, $touserid, $fromuserid,0,0,0,$get_notifications_emp->id);
// 	    	// echo 'hi';
// 		}
// 	}
// }//exit;
// echo $OUTPUT->footer(); 
