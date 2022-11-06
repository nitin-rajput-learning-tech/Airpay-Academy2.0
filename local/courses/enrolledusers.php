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
 * @subpackage local_courses
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/enrol/locallib.php'); 
global $DB, $PAGE,$USER;

$courseid = required_param('id', PARAM_INT);
$delete = optional_param('delete',0,PARAM_INT);
$userid = optional_param('ue',0,PARAM_INT);
require_login();

$systemcontext =(new \local_courses\lib\accesslib())::get_module_context();
$PAGE->set_pagelayout('standard');

$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_courses/courses', 'usersdatatable', array(array('courseid' => $courseid,
                                                                            'action'=>'enrolledusers')));
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/courses/enrolledusers.php');
$PAGE->set_title(get_string('courses'));
$PAGE->set_heading(get_string('enrolledusers','local_courses'));
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('manage_courses','local_courses'),new moodle_url('/local/courses/courses.php'));
$courses = $DB->get_field('course','shortname',array('id'=>$courseid));
$url = $CFG->wwwroot.'/course/view.php?id='.$courseid;
$PAGE->navbar->add($courses,$url);

//$PAGE->navbar->add(get_string('courses','local_courses'));
$PAGE->navbar->add(get_string('enrolledusers','local_courses'));
$renderer = $PAGE->get_renderer('local_courses');

echo $OUTPUT->header();
if(!empty($userid)) { 
            $sql = "SELECT * FROM {user_enrolments} WHERE id = ".$userid;
            $userenrol = $DB->get_record_sql($sql); 
            $sql = "SELECT enrol FROM {enrol} WHERE id= $userenrol->enrolid";
            $enrolmethods = $DB->get_record_sql($sql);
            $enrolmethod = enrol_get_plugin($enrolmethods->enrol);
            $roleid = $DB->get_field('role', 'id', array('shortname' => 'employee'));
            $instance = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => $enrolmethods->enrol), '*', MUST_EXIST);
 if (!empty($instance)) {
                    $enrolmethod->unenrol_user($instance, $userenrol->userid, $roleid, time());
            }
        }
echo $renderer->display_course_enrolledusers($courseid);

echo $OUTPUT->footer();
