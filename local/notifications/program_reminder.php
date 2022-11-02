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
// has been removed. 

// require_once(dirname(__FILE__) . '/../../config.php');
// require_once($CFG->dirroot . '/local/notifications/notification.php');
// require_once($CFG->dirroot.'/local/includes.php');
// // require_once($CFG->dirroot . '/local/notifications/renderer.php');
// global $DB, $CFG, $USER, $PAGE, $OUTPUT;
// // ini_set('display_errors', 1);
// // ini_set('display_startup_errors', 1);
// // error_reporting(E_ALL);
// require_login();
// $PAGE->set_url('/local/notifications/program_reminder.php');
// $PAGE->set_context(context_system::instance());
// $PAGE->set_pagelayout('admin');
// $PAGE->set_title(get_string('pluginname', 'local_notifications'));
// $PAGE->navbar->add(get_string('pluginname', 'local_notifications'));
// echo $OUTPUT->header();
// $sql = "SELECT lu.id as user_id,l.*,lu.userid,lu.programid,DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY) 
//         FROM {local_program_users} lu
//         JOIN {local_program} l ON l.id = lu.programid
//         WHERE l.status = 1
//         AND DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY) AND FROM_UNIXTIME(l.enddate,'%Y-%m-%d') = DATE_ADD(CURDATE(),INTERVAL 1 
//         DAY)";
// $records = $DB->get_records_sql($sql);
// $sql_trainer = "SELECT lt.id as trainer_id,l.*,lt.trainerid,lt.programid,DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY) 
//         FROM {local_program_trainers} lt
//         JOIN {local_program} l ON l.id = lt.programid
//         WHERE l.status = 1
//         AND DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY) AND FROM_UNIXTIME(l.enddate,'%Y-%m-%d') = DATE_ADD(CURDATE(),INTERVAL 1 
//         DAY)";
// $records_trainer = $DB->get_records_sql($sql_trainer);
// $records = array_merge($records,$records_trainer);
// foreach($records as $record){
// $type = 'program_reminder';
// if($record->userid){
// $costcenter = $DB->get_record_sql("select open_costcenterid from {user} where id = $record->userid");
// }else{
// $costcenter_trainer = $DB->get_record_sql("select open_costcenterid from {user} where id = $USER->id");
// }
// $get_notification_type = $DB->get_record('local_notification_type',array('shortname'=>$type));
// $get_notifications = $DB->get_records('local_notification_info',array('notificationid'=>$get_notification_type->id,'active'=>1,'costcenterid'=>$costcenter->open_costcenterid));
// if(empty($get_notifications)){
//     $get_notifications = $DB->get_records('local_notification_info', array('notificationid'=>$get_notification_type->id,'active'=>1,'costcenterid'=>$costcenter_trainer->open_costcenterid));
// }
// if($record->userid){
// $completed_users = $DB->record_exists_sql("select id from {local_program_users} where userid = 
//     $record->userid AND programid = $record->programid AND completion_status = 1");
// }
//         foreach($get_notifications as $get_notification){
//         if(empty($completed_users)){    
//         if($DB->record_exists('local_emaillogs',array('to_userid' => $record->userid, 'notification_infoid' => $get_notification->id,'from_userid'=>$USER->id))){
    
//         }else{
//            $trainer = $record->trainerid;
//             $singleuser = $record->userid;
//             $data=$DB->get_record('local_program',array('id'=>$record->programid));
//             $courses = $DB->get_records_sql("select lcc.id,lcc.courseid,
//                 c.fullname as coursename                            
//                 from {local_program_courses} lcc
//                 JOIN {course} c ON lcc.courseid = c.id
//                 where lcc.programid = $data->id");
//             $institutes = $DB->get_records_sql("SELECT li.fullname, li.address
//                 FROM {local_program} lc
//                 JOIN {local_location_institutes} li ON lc.instituteid = li.id
//                 WHERE lc.id = $data->id");
//             $includes = new user_course_details();
//              if ($data->programlogo > 0) {
//                 $img = new local_program\program();
//                 $classesimg = $img->program_logo($data->programlogo);
//                 if($classesimg == false){
//                    $classesimg = $includes->get_classes_summary_files($data); 
//                 }
//             } else {
//                 $classesimg = $includes->get_classes_summary_files($data);
//             }
//             $renderer = $PAGE->get_renderer('local_program');
//             $return = $renderer->view_program_sessions($data->id);
//             //print_object($courses);
//             if($courses){
//                 $val = array();
//                 foreach($courses as $course){
//                     $val[] = $course->coursename;
//                 }
//                 $course_val = implode(' , ',$val);
//             }else{
//                 $course_val = 'N/A';
//             }
             
//            $dataobj = new stdclass();   
//             if($data->department){
//                 $dept=$DB->get_records_sql("SELECT fullname FROM {local_costcenter} WHERE id IN ($data->department)");
//                 $array = array();
//                 foreach($dept as $department){
//                     $array[] = $department->fullname;
//                 }
//                 $dept_array = implode(' , ',$array);
//             }else{
//                 $dept_array='N/A';
//             }
//             $creater = $DB->get_record('user',array('id'=>$data->usercreated));
//             $dataobj->program_name = $data->name;
//             $dataobj->program_course = $course_val.'.';
//             $dataobj->program_startdate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->startdate);
//             $dataobj->program_enddate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->enddate);
//             $dataobj->program_creater = $creater->firstname.' '.$creater->lastname;
//             $dataobj->program_department = $dept_array.'.';
//             $dataobj->program_sessionsinfo = $return;
//             $url = new moodle_url($CFG->wwwroot.'/local/program/view.php?cid='.$data->id);
//             $dataobj->program_link = '<a href='.$url.'>'.$url.'</a>';
//             if($institutes){
//                 foreach($institutes as $institute){
//                     $dataobj->program_location_fullname = $institute->fullname;
//                     $dataobj->program_Addressprogramlocation = $institute->address;
//                 }
//             }else{
//                 $dataobj->program_location_fullname = 'N/A';
//                 $dataobj->program_Addressprogramlocation = 'N/A';
//             }
//             if($data->description){
//                 $dataobj->program_programsummarydescription = $data->description;
//             }else{
//                 $dataobj->program_programsummarydescription = 'N/A';
//             }
//             $dataobj->program_program_image = html_writer::img($classesimg, $data->name,array());
//             $dataobj->adminbody = NULL;
//             $dataobj->body = $get_notification->body;
//             $fromuserid = $USER->id;
//             $notifications_lib = new notifications();
//             $emailtype = $type;
//             if($singleuser){
//             $adduser = $DB->get_record('user',array('id'=>$singleuser));
//             $dataobj->program_enroluserfulname = $adduser->firstname.' '.$adduser->lastname;
//             $dataobj->program_enroluseremail = $adduser->email;
//             $touserid = $singleuser;
//             $notifications_lib->send_email_notification($emailtype, $dataobj, $touserid, $fromuserid);
//             }
//             if($trainer){
//             $add_user = $DB->get_record('user',array('id'=>$trainer));
//             $dataobj->program_enroluserfulname = $add_user->firstname.' '.$add_user->lastname;
//             $dataobj->program_enroluseremail = $add_user->email;
//             $touserid = $trainer;
//             $notifications_lib->send_email_notification($emailtype, $dataobj, $touserid, $fromuserid);
//             }
//         }
//     }
//     }
// }
// echo $OUTPUT->footer(); 