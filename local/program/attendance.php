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
 * @subpackage local_program
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

require_once($CFG->dirroot.'/local/program/lib.php');
//use \local_program\notifications_emails as programnotifications_emails;
// require_once($CFG->dirroot . '/local/program/notifications_emails.php');
require_login();
use local_program\program;
$programid = required_param('bcid', PARAM_INT);
$sessionid = required_param('sid', PARAM_INT);

$levelid = optional_param('levelid', 0, PARAM_INT);
$bclcid = required_param('bclcid', PARAM_INT);
$action = optional_param('action', '', PARAM_RAW);

require_login();
$categorycontext = (new \local_program\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$url = new moodle_url($CFG->wwwroot . '/local/program/attendance.php',
    array('bcid' => $programid, 'bclcid' => $bclcid, 'levelid' => $levelid, 'action' => $action));
if ($sessionid > 0) {
    $url->param('sid', $sessionid);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

$program_name = $DB->get_field('local_program','name',array('id' => $programid));
$renderer = $PAGE->get_renderer('local_program');

$program=$renderer->programview_check($programid);
$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('view.php',
    array('bcid' => $programid, 'sid' => $sessionid)));
$PAGE->navbar->add($program_name, new moodle_url('sessions.php',array('bcid' => $programid,'bclcid' => $bclcid, 'levelid' => $levelid)));
$PAGE->navbar->add(get_string("sessions", 'local_program'), new moodle_url('sessions.php',array('bcid' => $programid,'bclcid' => $bclcid, 'levelid' => $levelid)));
$PAGE->navbar->add(get_string("attendance", 'local_program'));
$PAGE->set_title($program_name);
$PAGE->set_heading(get_string('session_attendance_heading', 'local_program', $program_name));

require_capability('local/program:takesessionattendance', $categorycontext);

$program = new program();
$attendancedata = data_submitted();
// $emaillogs = new programnotifications_emails();
$emaillogs = new \local_program\notification();
if (!empty($attendancedata)) {
    if (isset($attendancedata->reset) && $attendancedata->reset == get_string('reset_selected', 'local_program')) {
        $DB->execute("UPDATE {local_bc_session_signups} SET completion_status = 0
            WHERE programid = :programid AND sessionid = :sessionid",
            array('programid' => $attendancedata->bcid,
                'sessionid' => $attendancedata->sid));
        redirect($PAGE->url);
    } else if ($attendancedata->action == 'attendance') {
        foreach ($attendancedata->attendeedata as $k => $attendancesignup) {
            $decodeddata = json_decode(base64_decode($attendancesignup));

            $programcontent = $DB->get_record('local_program', array('id' => $programid));
            $programinstance = $programcontent;
            $sessioninfo = $DB->get_record('local_bc_course_sessions', array('id' => $decodeddata->sessionid), 'id, levelid, bclcid');
            $programinstance->levelid = $sessioninfo->levelid;
            $programinstance->sessionid = $decodeddata->sessionid;
            $programinstance->courseid = $sessioninfo->bclcid;
            $touserid = \core_user::get_user($decodeddata->userid);

            if ($decodeddata->attendanceid > 0) {
                $checkattendeestatus = new stdClass();
                $checkattendeestatus->id = $decodeddata->attendanceid;
                if (isset($attendancedata->status[$attendancesignup]) && $attendancedata->status[$attendancesignup]) {
                    $checkattendeestatus->completion_status = SESSION_PRESENT;
                    $checkattendeestatus->completiondate = time();
                } else {
                    $checkattendeestatus->completion_status = SESSION_ABSENT;
                }
                $completionstatus = $checkattendeestatus->completion_status;
                $checkattendeestatus->timemodified = time();
                $checkattendeestatus->usermodified = $USER->id;
                $DB->update_record('local_bc_session_signups', $checkattendeestatus);

                if ($checkattendeestatus->completion_status == SESSION_PRESENT) {
                    // $email_logs = $emaillogs->program_emaillogs('program_session_completion', $decodeddata->sessionid, $decodeddata->userid, $USER->id);
                    $emaillogs->program_notification('program_session_completion', $touserid, $USER, $programinstance);
                }
                $emaillogs->program_notification('program_session_attendance', $touserid, $USER, $programinstance);
                // $email_logs = $emaillogs->program_emaillogs('program_session_attendance', $decodeddata->sessionid, $decodeddata->userid, $USER->id);
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $checkattendeestatus->id
                );

                // $event = \local_program\event\program_attendance_created_updated::create($params);
                // $event->add_record_snapshot('local_program', $decodeddata->programid);
                // $event->trigger();
            } else {
                $userattendance = new stdClass();
                $userattendance->programid = $decodeddata->programid;
                $userattendance->sessionid = $decodeddata->sessionid;
                $userattendance->userid = $decodeddata->userid;
                if (isset($attendancedata->status[$attendancesignup]) && $attendancedata->status[$attendancesignup]) {
                    $userattendance->completion_status = SESSION_PRESENT;
                } else {
                    $userattendance->completion_status = SESSION_ABSENT;
                }
                $completionstatus = $userattendance->completion_status;
                $userattendance->usercreated = $USER->id;
                $userattendance->timecreated = time();
                $record_exist=$DB->record_exists_sql("SELECT id FROM {local_bc_session_signups}
                                                    WHERE programid={$decodeddata->programid}
                                                    AND userid={$decodeddata->userid} AND sessionid={$decodeddata->sessionid}");
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $id
                );

                // $event = \local_program\event\program_attendance_created_updated::create($params);
                // $event->add_record_snapshot('local_program', $decodeddata->programid);
                // $event->trigger();
            }
            $attendedsessions = $DB->count_records('local_bc_session_signups',
                array('programid' => $decodeddata->programid,
                    'userid' => $decodeddata->userid, 'completion_status' => SESSION_PRESENT));

            $attendedsessions_hours = $DB->get_field_sql("SELECT ((sum(lcs.duration))/60) AS hours
                                        FROM {local_bc_course_sessions} as lcs
                                        WHERE lcs.programid = {$programid}
                                        and lcs.id IN (SELECT sessionid  FROM {local_bc_session_signups} WHERE programid = {$programid} AND userid = {$decodeddata->userid} AND completion_status=1)");

            if (empty($attendedsessions_hours)) {
                $attendedsessions_hours = 0;
            }

            $DB->execute('UPDATE {local_program_users} SET attended_sessions = ' .
                $attendedsessions . ', hours = ' .
                $attendedsessions_hours . ', timemodified = ' . time() . ',
                usermodified = ' . $USER->id . ' WHERE programid = ' .
                $decodeddata->programid . ' AND userid = ' . $decodeddata->userid);
            // $update_user = new stdClass();
            // $update_user->attended_sessions = $attendedsessions;
            // $update_user->hours = $attendedsessions_hours;
            // $update_user->timemodified = time();
            // $update_user->usermodified = $USER->id;
            // $DB->update_record('local_program_users', $dataobject,  $bulk=false)
            if ($completionstatus == SESSION_PRESENT) {
                $userdata = new stdClass();
                $userdata->programid = $decodeddata->programid;
                $userdata->sessionid = $decodeddata->sessionid;
                $userdata->userid = $decodeddata->userid;
                (new program)->bc_level_courses_completions($userdata);

                // $bccourse = $DB->get_record('local_bc_course_sessions',
                //     array('id' => $decodeddata->sessionid));
                // (new program)->bccourse_sessions_completions($bccourse);
            }

            if($completionstatus == SESSION_ABSENT){
                $session = $DB->get_record_select('local_bc_course_sessions', 'id = :id', array('id' => $decodeddata->sessionid));
                $levelid= $DB->get_field('local_bc_session_signups', 'levelid', array('programid'=>$decodeddata->programid, 'sessionid'=>$decodeddata->sessionid));
                $checkcousrecmptlsql = "SELECT *
                                      FROM {local_bc_level_completions}
                                      WHERE userid = {$decodeddata->userid}
                                      AND programid = {$decodeddata->programid}
                                      AND levelid = {$levelid}";
                $checkcousrecmptl = $DB->get_record_sql($checkcousrecmptlsql);
                $bclcids = $checkcousrecmptl->bclcids;
                if (!empty($checkcousrecmptl->bclcids)) {
                    $bclcidslist = explode(',', $checkcousrecmptl->bclcids);
                    if (in_array($session->bclcid, $bclcidslist)) {
                        $index = array_search($session->bclcid, $bclcidslist);
                        if ( $index !== false ) {
                            unset( $bclcidslist[$index] );
                        }
                    }
                    $bclcids = implode(',', $bclcidslist);
                    $checkcousrecmptl->bclcids = $bclcids;
                    $checkcousrecmptl->usermodified = $USER->id;
                    $checkcousrecmptl->timemodified = time();
                    $DB->update_record('local_bc_level_completions', $checkcousrecmptl);
                }
                

                $bcuser = $DB->get_record('local_program_users',
                    array('programid' => $decodeddata->programid,
                        'userid' => $decodeddata->userid, 'completion_status' => 0));
                if (!empty($bcuser)) {
                    $bclevels = $DB->get_records_menu('local_program_levels',
                        array('programid' => $decodeddata->programid), 'id',
                        'id, id AS level');
                    $bcusercmptllevelids = $bcuser->levelids;
                    if (!empty($bcusercmptllevelids)) {
                        $levelids = explode(',', $bcusercmptllevelids);
                        if (in_array($session->levelid, $levelids)) {
                            $index1 = array_search($session->levelid, $levelids);
                            if ( $index1 !== false ) {
                                unset( $levelids[$index1] );
                            }
                        }
                        // $levelids[] = $session->levelid;
                        array_unique($levelids);
                        $bcuser->levelids = implode(',', $levelids);;
                    }
                    
                    $DB->update_record('local_program_users', $bcuser);
                    //program completions $bcuser->completion_status=1
                    if($bcuser->completion_status == 1){
                      $type = 'program_completion';
                      // $email_logs = $emaillogs->program_emaillogs($type, $bcuser->programid, $bcuser->userid, $USER->id);
                      $touser = \core_user::get_user($bcuser->userid);
                      $email_logs = $emaillogs->program_notification($type, $touser, $USER, $programinstance);
                    }

                }
            }
        }
        if ($sessionid > 0) {
            $activeusers = $DB->count_records('local_bc_session_signups', array('sessionid' => $sessionid, 'completion_status' => 1));
            $DB->execute("UPDATE {local_bc_course_sessions} SET attendance_status = 1
                , activeusers = $activeusers
                WHERE id = :id ", array('id' => $sessionid));
        } else {
            $DB->execute("UPDATE {local_bc_course_sessions} SET attendance_status = 1,
            timemodified = :timemodified, usermodified = :usermodified WHERE
            programid = :programid ", array('programid' => $programid,
                'usermodified' => $USER->id, 'timemodified' => time()));
        }

        redirect($PAGE->url);
    }
}

echo $OUTPUT->header();
if ($sessionid > 0) {
    $sessionattendance = new \local_program\output\session_attendance($sessionid);
    echo $renderer->render($sessionattendance);
}
echo $renderer->viewprogramattendance($programid, $sessionid);
$continue .= '<a href='.$CFG->wwwroot.'/local/program/sessions.php?action='.$action.'&bcid='.$programid.'&levelid='.$levelid.'&bclcid='.$bclcid.' class="singlebutton pull-right attendance_continue"><button>'.get_string('continue', 'local_program').'</button></a>';
echo $continue;
echo $OUTPUT->footer();