<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This classroom is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This classroom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this classroom.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage local_classroom
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
global $CFG, $DB, $PAGE, $USER, $OUTPUT;
require_once($CFG->dirroot.'/local/classroom/lib.php');
require_login();
use local_classroom\classroom;
$classroomid = required_param('cid', PARAM_INT);
$sessionid = optional_param('sid', 0, PARAM_INT);
require_login();
$categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
$PAGE->set_context($categorycontext);
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$url = new moodle_url($CFG->wwwroot . '/local/classroom/attendance.php', array('cid' => $classroomid));
if($sessionid > 0) {
    $url->param('sid', $sessionid);
}

$renderer = $PAGE->get_renderer('local_classroom');

$classroom=$renderer->classroomview_check($classroomid);

// $DB->set_debug(true);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

$classroom_name = $classroom->name;
$PAGE->navbar->add(get_string("pluginname", 'local_classroom'), new moodle_url('view.php',array('cid'=>$classroomid)));
$PAGE->navbar->add($classroom_name,new moodle_url('view.php',array('cid'=>$classroomid)));
$PAGE->navbar->add(get_string('sessions', 'local_classroom'),new moodle_url('view.php',array('cid'=>$classroomid)));
$PAGE->navbar->add(get_string('attendance', 'local_classroom'));
$PAGE->set_title($classroom_name);
$PAGE->set_heading(get_string('session_attendance_heading', 'local_classroom',$classroom_name));
require_capability('local/classroom:takesessionattendance', $categorycontext);

$classroom = new classroom();
$attendancedata = data_submitted();
if (!empty($attendancedata)) {
    if ($attendancedata->reset == 'Reset Selected') {
        $DB->execute("UPDATE {local_classroom_attendance} SET status = 0
            WHERE classroomid = :classroomid AND sessionid = :sessionid",
            array('classroomid' => $attendancedata->cid,
                'sessionid' => $attendancedata->sid));
        redirect($PAGE->url);
    } else if ($attendancedata->action == 'attendance') {
        foreach ($attendancedata->attendeedata as $k => $attendancesignup) {
            $decodeddata = json_decode(base64_decode($attendancesignup));
            if ($decodeddata->attendanceid > 0) {
                $checkattendeestatus = new stdClass();
                $checkattendeestatus->id = $decodeddata->attendanceid;
                if (isset($attendancedata->status[$attendancesignup]) && $attendancedata->status[$attendancesignup]) {
                    $checkattendeestatus->status = SESSION_PRESENT;
                } else {
                    $checkattendeestatus->status = SESSION_ABSENT;
                }
                $checkattendeestatus->timemodified = time();
                $checkattendeestatus->usermodified = $USER->id;
                $DB->update_record('local_classroom_attendance',  $checkattendeestatus);
                    $params = array(
                    'context' => (new \local_classroom\lib\accesslib())::get_module_context($decodeddata->classroomid),
                    'objectid' => $checkattendeestatus->id
                );

                $event = \local_classroom\event\classroom_attendance_created_updated::create($params);
                $event->add_record_snapshot('local_classroom', $decodeddata->classroomid);
                $event->trigger();
            } else {
                $userattendance = new stdClass();
                $userattendance->classroomid = $decodeddata->classroomid;
                $userattendance->sessionid = $decodeddata->sessionid;
                $userattendance->userid = $decodeddata->userid;
                if (isset($attendancedata->status[$attendancesignup]) && $attendancedata->status[$attendancesignup]) {
                    $userattendance->status = SESSION_PRESENT;
                } else {
                    $userattendance->status = SESSION_ABSENT;
                }
                $userattendance->usercreated = $USER->id;
                $userattendance->timecreated = time();
                $record_exist=$DB->record_exists_sql("SELECT id  FROM {local_classroom_attendance}
                                                    where classroomid = :classroomid
                                                    and userid = :userid and sessionid = :sessionid",array('userid' => $decodeddata->userid,'sessionid' => $decodeddata->sessionid,'classroomid' => $decodeddata->classroomid));
               if(!$record_exist){
                    $id=$DB->insert_record('local_classroom_attendance',  $userattendance);
                }

                $params = array(
                    'context' => (new \local_classroom\lib\accesslib())::get_module_context($decodeddata->classroomid),
                    'objectid' => $id
                );

                $event = \local_classroom\event\classroom_attendance_created_updated::create($params);
                $event->add_record_snapshot('local_classroom', $decodeddata->classroomid);
                $event->trigger();
            }
             $attendedsessions = $DB->count_records('local_classroom_attendance',
                array('classroomid' => $decodeddata->classroomid,
                    'userid' => $decodeddata->userid, 'status' => SESSION_PRESENT)); 
            // $attendedsessions = $DB->count_records('local_classroom_attendance',
            //     array('classroomid' => $decodeddata->classroomid, 'sessionid' =>$decodeddata->sessionid, 'status' => SESSION_PRESENT));

            $attendedsessions_hours=$DB->get_field_sql("SELECT ((sum(lcs.duration))/60) AS hours
                                        FROM {local_classroom_sessions} as lcs
                                        WHERE  lcs.classroomid = :classroomid
                                        and lcs.id in(SELECT sessionid  FROM {local_classroom_attendance} where classroomid=$classroomid and userid=$decodeddata->userid and status=1)",array('classroomid' => $classroomid));

            if(empty($attendedsessions_hours)){
                $attendedsessions_hours=0;
            }

            $DB->execute('UPDATE {local_classroom_users} SET attended_sessions = ' .
                $attendedsessions . ',hours = ' .
                $attendedsessions_hours . ', timemodified = ' . time() . ',
                usermodified = ' . $USER->id . ' WHERE classroomid = :classroomid AND userid = :userid',array('classroomid' => $decodeddata->classroomid, 'userid' => $decodeddata->userid));
        }
        if($sessionid > 0) {
            $DB->execute("UPDATE {local_classroom_sessions} SET attendance_status = 1 WHERE id = :id ", array('id' => $sessionid));
                $params = array(
                    'context' => (new \local_classroom\lib\accesslib())::get_module_context($classroomid),
                    'objectid' =>  $sessionid
                );

                $event = \local_classroom\event\classroom_sessions_updated::create($params);
                $event->add_record_snapshot('local_classroom',$classroomid);
                $event->trigger();
        } else {
            $DB->execute("UPDATE {local_classroom_sessions} SET attendance_status = 1,
            timemodified = :timemodified, usermodified = :usermodified WHERE
            classroomid = :classroomid ", array('classroomid' => $classroomid,
                'usermodified' => $USER->id, 'timemodified' => time()));
        }

        redirect($PAGE->url);
    }
}

echo $OUTPUT->header();
if ($sessionid > 0) {
    $sessionattendance = new \local_classroom\output\session_attendance($sessionid);

    echo $renderer->render($sessionattendance);
}
echo $renderer->viewclassroomattendance($classroomid, $sessionid);

$continue='<div class="w-100 pull-left text-right mt-1">';
$continue.='<a href='.$CFG->wwwroot.'/local/classroom/view.php?cid='.$classroomid.' class="singlebutton"><button>'.get_string('continue', 'local_classroom').'</button></a>';
$continue.='</div>';
echo $continue;

echo $OUTPUT->footer();
