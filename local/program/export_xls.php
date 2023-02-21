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
 * @subpackage local_program
 */
use local_program\program;
function export_report($programid, $stable, $type) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/lib/excellib.class.php');
    $data = array();            
    // $programusers = (new program)->programusers($programid, $stable);
    $matrix = array();
    $thead =array();
    // print_object($type);
    if($type == 'programwise') {
        $filename = 'program Users.xls';
        $sql = "SELECT u.*, cu.attended_sessions, cu.hours, cu.completion_status, c.totalsessions,
            c.activesessions FROM {user} AS u
                     JOIN {local_program_users} AS cu ON cu.userid = u.id
                     JOIN {local_program} AS c ON c.id = cu.programid
                    WHERE c.id = {$programid} AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2 ORDER BY id ASC ";
        $programusers = $DB->get_records_sql($sql);
        $table = new html_table();
        if (!empty($programusers)) {
            foreach ($programusers as $sdata) {
               $programname = $DB->get_field('local_program', 'name', array('id'=>$programid));
                $line = array();
                $line[] = fullname($sdata);
                $line[] = $programname;
                $line[] = $sdata->open_employeeid;
                $line[] = $sdata->email;
                if(!empty($sdata->open_supervisorid)){
                    $supervisor = $DB->get_record('user', array('id' => $sdata->open_supervisorid),
                    'id,firstname,lastname');
                    $reportingto = $supervisor->firstname.' '.$supervisor->lastname;
                    $line[] = $reportingto;
                }else{
                    $line[] = '--';
                }
                $total_levels = $DB->count_records('local_program_levels',  array('programid' => $programid));
                $completed_levels = $DB->count_records('local_bc_level_completions',  array('programid' => $programid, 'completion_status'=>1, 'userid'=>$sdata->id));
                $line[] = $completed_levels.'/'.$total_levels;
                $line[] = $sdata->completion_status == 1 ? get_string('completedsessions', 'local_program') : get_string('not_completed', 'local_program');
                $data[] = $line;
            }
            $table->data = $data;
        }
        $table->head = array(get_string('employee', 'local_program'), get_string('program_name', 'local_program'), get_string('employeeid', 'local_program'), get_string('email'),get_string('supervisor', 'local_users'),get_string('nooflevels', 'local_program'), get_string('status'));
    } else if($type == 'coursewise') {
        $filename = 'Course wise session report.xls';
        $sql = "SELECT u.* FROM {user} AS u
                     JOIN {local_program_users} AS cu ON cu.userid = u.id
                     JOIN {local_program} AS c ON c.id = cu.programid
                    WHERE c.id = {$programid} AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2 ORDER BY id ASC";
        $programusers = $DB->get_records_sql($sql);
        $table = new html_table();
        if (!empty($programusers)) {
            foreach ($programusers as $sdata) {
                $sql = "SELECT ss.*, c.name AS programname, mc.fullname AS coursename, lbcs.name AS sessionname 
                    FROM {local_bc_session_signups} AS ss
                     JOIN {local_program} AS c ON c.id = ss.programid
                     JOIN {local_program_level_courses} AS lplc ON lplc.id = ss.bclcid
                     JOIN {course} AS mc ON lplc.courseid = mc.id
                     JOIN {local_bc_course_sessions} AS lbcs ON lbcs.id=ss.sessionid
                    WHERE c.id = {$programid} AND ss.userid = {$sdata->id} ORDER BY id ASC";
                     // JOIN {local_program_users} AS cu ON cu.userid = u.id
                $sessionenrolledusers = $DB->get_records_sql($sql);
                if(!empty($sessionenrolledusers)){
                    foreach ($sessionenrolledusers as $sessionenrolleduser) {
                        // $programname = $DB->get_field('local_program', 'name', array('id'=>$programid));
                        $programname = $sessionenrolleduser->programname;
                        // $courseid = $DB->get_field('local_program_level_courses', 'courseid', array('id'=>$sessionenrolleduser->bclcid));
                        // $coursename = $DB->get_field('course', 'fullname', array('id'=>$courseid));
                        $coursename = $sessionenrolleduser->coursename;
                        // $sessionname = $DB->get_field('local_bc_course_sessions', 'name', array('id'=>$sessionenrolleduser->sessionid));
                        $sessionname = $sessionenrolleduser->sessionname;
                        $line = array();
                        $line[] = fullname($sdata);
                        $line[] = $sdata->open_employeeid;
                        $line[] = $sdata->email;
                        $line[] = $programname;
                        $line[] = $coursename;
                        $line[] = $sessionname;
                        $line[] = $sessionenrolleduser->completion_status == 1 ? get_string('present', 'local_program') : get_string('absent', 'local_program');
                        $line[] = $sessionenrolleduser->completion_status == 1 ? \local_costcenter\lib::get_userdate('Y-m-d', $sessionenrolleduser->completiondate) : get_string('not_available', 'local_program');
                        $data[] = $line;
                    }
                } else {
                    $programname = $DB->get_field('local_program', 'name', array('id'=>$programid));
                    $line1 = array();
                    $line1[] = fullname($sdata);
                    $line1[] = $sdata->open_employeeid;
                    $line1[] = $sdata->email;
                    $line1[] = $programname;
                    $line1[] = '--';
                    $line1[] = '--';
                    $line1[] = '--';
                    $line1[] = '--';
                    $data[] = $line1;
                }
            }
            $table->data = $data;
        }
        $table->head = array(get_string('employee', 'local_program'), get_string('employeeid', 'local_program'), get_string('email'), get_string('program_name', 'local_program'),get_string('course', 'local_program'),get_string('session_name', 'local_program'), get_string('status'), 'Date');
    }
    // print_object($table->head);
    // print_object($table->data);exit;
    if (!empty($table->head)) {
        foreach ($table->head as $key => $heading) {
            // $matrix[0][0] = $reportname;
            $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(\local_costcenter\lib::strip_tags_custom(nl2br($heading))));
        }
    }

    if (!empty($table->data)) {
        foreach ($table->data as $rkey => $row) {
            foreach ($row as $key => $item) {
                $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(\local_costcenter\lib::strip_tags_custom(nl2br($item))));
            }
        }
    }
    $downloadfilename = clean_filename($filename);
    /// Creating a workbook
    $workbook = new MoodleExcelWorkbook("-");
    /// Sending HTTP headers
    $workbook->send($downloadfilename);
    /// Adding the worksheet
    $myxls = $workbook->add_worksheet($filename);
    // print_object($matrix);
    foreach ($matrix as $ri => $col) {
        foreach ($col as $ci => $cv) {
            //Formatting by sowmya
            $format = array('border'=>1);
            if($ri == 0){
                $format['bold'] = 1;
                $format['bg_color'] = '#f0a654';
                $format['color'] = '#FFFFFF';
            }
            
            if(is_numeric($cv)){
                $format['align'] = 'center';
                $myxls->write_number($ri, $ci, $cv, $format);
            } else {
                $myxls->write_string($ri, $ci, $cv, $format);
            }
        }
    }//exit;
    $workbook->close();
    exit;
}