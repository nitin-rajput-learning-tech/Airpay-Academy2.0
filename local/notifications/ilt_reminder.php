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
// this has been depricated and will be removed in later versions of bizlms....
// has been added as an task in "local_classroom"

// require_once(dirname(__FILE__) . '/../../config.php');
// require_once($CFG->dirroot . '/local/notifications/notification.php');
// require_once($CFG->dirroot.'/local/includes.php');
// // require_once($CFG->dirroot . '/local/notifications/renderer.php');
// global $DB, $CFG, $USER, $PAGE, $OUTPUT;
// // ini_set('display_errors', 1);
// // ini_set('display_startup_errors', 1);
// // error_reporting(E_ALL);
// require_login();
// $PAGE->set_url('/local/notifications/ilt_reminder.php');
// $PAGE->set_context(context_system::instance());
// $PAGE->set_pagelayout('admin');
// $PAGE->set_title(get_string('pluginname', 'local_notifications'));
// $PAGE->navbar->add(get_string('pluginname', 'local_notifications'));
// echo $OUTPUT->header();
// $sql = "SELECT lu.id as user_id,l.*,lu.userid,lu.classroomid,DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY), DATE_ADD(CURDATE(),INTERVAL 1 DAY) 
//         FROM {local_classroom_users} lu
//         JOIN {local_classroom} l ON l.id = lu.classroomid
//         WHERE l.status = 1 ";//AND DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY) AND FROM_UNIXTIME(l.enddate,'%Y-%m-%d') = DATE_ADD(CURDATE(),INTERVAL 1 DAY)
// $records = $DB->get_records_sql($sql);
// $sql_trainer = "SELECT lt.id as trainer_id,l.*,lt.trainerid,lt.classroomid,DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY) 
//         FROM {local_classroom_trainers} lt
//         JOIN {local_classroom} l ON l.id = lt.classroomid
//         WHERE l.status = 1 ";// AND DATE_SUB(FROM_UNIXTIME(l.startdate,'%Y-%m-%d'), INTERVAL 1 DAY) AND FROM_UNIXTIME(l.enddate,'%Y-%m-%d') = DATE_ADD(CURDATE(),INTERVAL 1 DAY)";
// $records_trainer = $DB->get_records_sql($sql_trainer);
// $records = array_merge($records,$records_trainer);
// foreach($records as $record){
//     $type = 'classroom_reminder';
//     $dataobj = $record->classroomid;
//     if($record->userid){
//         $costcenter = $DB->get_record_sql("select open_costcenterid from {user} where id = $record->userid");
//     }else{
//         $costcenter_trainer = $DB->get_record_sql("select open_costcenterid from {user} where id = $USER->id");
//     }
//     $get_notification_type = $DB->get_record('local_notification_type',array('shortname'=>$type));
//     // $get_notifications = $DB->get_records('local_notification_info',array('notificationid'=>$get_notification_type->id,'active'=>1,'costcenterid'=>$costcenter->open_costcenterid));
//     // if(empty($get_notifications)){
//     //     $get_notifications = $DB->get_records('local_notification_info', array('notificationid'=>$get_notification_type->id,'active'=>1,'costcenterid'=>$costcenter_trainer->open_costcenterid));
//     // }
//     $get_notification = $DB->get_record_sql("SELECT id,body,adminbody, reminderdays FROM {local_notification_info} WHERE notificationid = $get_notification_type->id and active=1 and costcenterid=$costcenter->open_costcenterid and FIND_IN_SET($dataobj,moduleid)");
//     if(!$get_notification){
//         $get_notification = $DB->get_record_sql("SELECT id,body,adminbody, reminderdays FROM {local_notification_info} WHERE notificationid = $get_notification_type->id and active=1 and costcenterid=$costcenter->open_costcenterid and moduleid IS NULL");
//     }
//     if($record->userid){
//         $completed_users = $DB->record_exists_sql("select id from {local_classroom_users} where userid = 
//         $record->userid AND classroomid = $record->classroomid AND completion_status = 1");
//     }
//     if($get_notification){
//         $remainder_days = $get_notification->reminderdays;
//         if(empty($completed_users)){
//             if($DB->record_exists('local_emaillogs',array('to_userid' => $record->userid, 'notification_infoid' => $get_notification->id,'from_userid'=>$USER->id))){
        
//             }else{
//                 $trainer = $record->trainerid;
//                 $singleuser =  $record->userid;
//                 $data=$DB->get_record('local_classroom',array('id'=>$record->classroomid));
//                 $courses = $DB->get_records_sql("select lcc.id,lcc.courseid,
//                     c.fullname as coursename                            
//                     from {local_classroom_courses} lcc
//                     JOIN {course} c ON lcc.courseid = c.id
//                     where lcc.classroomid = $data->id");
//                 // $institutes = $DB->get_records_sql("SELECT li.fullname, li.address
//                 //     FROM {local_classroom} lc
//                 //     JOIN {local_location_institutes} li ON lc.instituteid = li.id
//                 //     WHERE lc.id = $data->id");
//                 $includes = new user_course_details();
//                  if ($data->classroomlogo > 0) {
//                     $img = new local_classroom\classroom();
//                     $classesimg = $img->classroom_logo($data->classroomlogo);
//                     if($classesimg == false){
//                        $classesimg = $includes->get_classes_summary_files($data); 
//                     }
//                 } else {
//                     $classesimg = $includes->get_classes_summary_files($data);
//                 }
//                 $renderer = $PAGE->get_renderer('local_classroom');
//                 $return = $renderer->view_classroom_sessions($data->id);
//                 if($courses){
//                     $val = array();
//                     foreach($courses as $course){
//                         $val[] = $course->coursename;
//                     }
//                     $course_val = implode(' , ',$val);
//                 }else{
//                     $course_val = 'N/A';
//                 }
                 
//                 $dataobj = new stdclass();   
//                 if($data->department){
//                     $dept=$DB->get_records_sql("SELECT fullname FROM {local_costcenter} WHERE id IN ($data->department)");
//                     $array = array();
//                     foreach($dept as $department){
//                         $array[] = $department->fullname;
//                     }
//                     $dept_array = implode(' , ',$array);
//                 }else{
//                     $dept_array='N/A';
//                 }
//                 $creater = $DB->get_record('user',array('id'=>$data->usercreated));
//                 $dataobj->classroom_name = $data->name;
//                 $dataobj->classroom_course = $course_val.'.';
//                 $dataobj->classroom_startdate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->startdate);
//                 $dataobj->classroom_enddate = \local_costcenter\lib::get_userdate("d/m/Y H:i",$data->enddate);
//                 $dataobj->classroom_creater = $creater->firstname.' '.$creater->lastname;
//                 $dataobj->classroom_department = $dept_array.'.';
//                 $dataobj->classroom_sessionsinfo = $return;
//                 $url = new moodle_url($CFG->wwwroot.'/local/classroom/view.php?cid='.$data->id);
//                 $dataobj->classroom_link = '<a href='.$url.'>'.$url.'</a>';
//                 if($data->open_location){
//                     // foreach($institutes as $institute){
//                         $dataobj->classroom_location_fullname = $data->open_location;
//                         $dataobj->classroom_Addressclassroomlocation = 'N/A';//$institute->address;
//                     // }
//                 }else{
//                     $dataobj->classroom_location_fullname = 'N/A';
//                     $dataobj->classroom_Addressclassroomlocation = 'N/A';
//                 }
//                 if($data->description){
//                     $dataobj->classroom_classroomsummarydescription = $data->description;
//                 }else{
//                     $dataobj->classroom_classroomsummarydescription = 'N/A';
//                 }
//                 $dataobj->classroom_classroom_image = html_writer::img($classesimg, $data->name,array());
//                 $fromuserid = $USER->id;
//                 $notifications_lib = new notifications();
//                 $emailtype = $type;
//                 $dataobj->adminbody = NULL;
//                 $dataobj->body = $get_notification->body;
//                 $dataobj->moduletype="classroom";
//                 $dataobj->moduleid=$data->id;
//                 $days_ago = strtotime('-'.$remainder_days.' days', $data->startdate);
//                 $currdate = \local_costcenter\lib::get_userdate("d/m/Y H:i",time());
//                 $date = explode('-', $currdate);
//                 $starttime = mktime(0,0,0,$date[1],$date[0],$date[2]);
//                 if($starttime < $days_ago && $days_ago < $data->startdate && $data->startdate >  time()){
//                     if($singleuser){
//                         $adduser = $DB->get_record('user',array('id'=>$singleuser));
//                         $dataobj->classroom_enroluserfulname = $adduser->firstname.' '.$adduser->lastname;
//                         $dataobj->classroom_enroluseremail = $adduser->email;
//                         $touserid = $singleuser;
//                         $notifications_lib->send_email_notification($emailtype, $dataobj, $touserid, $fromuserid);
//                     }
//                     if($trainer){
//                         $add_user = $DB->get_record('user',array('id'=>$trainer));
//                         $dataobj->classroom_enroluserfulname = $add_user->firstname.' '.$add_user->lastname;
//                         $dataobj->classroom_enroluseremail = $add_user->email;
//                         $touserid = $trainer;
//                         $notifications_lib->send_email_notification($emailtype, $dataobj, $touserid, $fromuserid);
//                     }
//                 }
//             }
//         }
//     }
// }
// echo $OUTPUT->footer(); 