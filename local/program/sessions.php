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
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/local/program/lib.php');
require_login();
use local_program\program;

global $PAGE, $CFG, $DB;
$programid = required_param('bcid', PARAM_INT);
$levelid = optional_param('levelid', 0, PARAM_INT);
$bclcid = required_param('bclcid', PARAM_INT);
$action = optional_param('action', '', PARAM_RAW);
if(empty($action)){
    $action = 'upcomingsessions';
} else {
    $action = $action;
}
require_login();
$categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
$PAGE->set_context($categorycontext);
$url = new moodle_url($CFG->wwwroot . '/local/program/sessions.php', array('bcid' => $programid,
    'levelid' => $levelid, 'bclcid' => $bclcid));
$courseid = $DB->get_field('local_program_level_courses', 'courseid', array('id' => $bclcid));
$coursename = $DB->get_field('course', 'fullname', array('id' => $courseid));
$renderer = $PAGE->get_renderer('local_program');

$program=$renderer->programview_check($programid);

$PAGE->requires->js_call_amd('local_program/ajaxforms', 'load', array());
$PAGE->requires->js_call_amd('local_program/program', 'load', array());
if (!is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext)) {
    $PAGE->requires->js_call_amd('local_program/program', 'SessionDatatable',
                    array(array('programstatus' => -1, 'programid' => $programid,
                        'levelid' => $levelid, 'bclcid' => $bclcid)));
} else {
    // echo 'hi';
    $PAGE->requires->js_call_amd('local_program/program', 'SessionDatatable',
                    array(array('programstatus' => -1, 'programid' => $programid,
                        'levelid' => $levelid, 'bclcid' => $bclcid, 'action'=>$action)));
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('sessions', 'local_program'));
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('/local/program/index.php'));
$PAGE->navbar->add($program->name, new moodle_url('/local/program/view.php', array('bcid' => $programid)));
$PAGE->navbar->add(get_string("sessions", 'local_program'));
$stringhelper = new stdClass();
$stringhelper->coursename = $coursename;
$stringhelper->levelname = $DB->get_field('local_program_levels', 'level', array('id' => $levelid));
$PAGE->set_heading(get_string('sessionscourses', 'local_program', $stringhelper));
$renderer = $PAGE->get_renderer('local_program');
$PAGE->requires->jquery();
$PAGE->requires->js('/blocks/achievements/js/jquery-ui.min.js',true);
$PAGE->requires->js('/local/program/js/tabs_script.js',true);
// $PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js('/blocks/achievements/js/jquery.dataTables.min.js',true);
echo $OUTPUT->header();
$stable = new stdClass();
$stable->thead = true;
$stable->start = 0;
$stable->length = -1;
$stable->search = '';
$bclcdata = new stdClass();
$bclcdata->programid = $programid;
$bclcdata->levelid = $levelid;
$bclcdata->bclcid = $bclcid;

$programuser = $DB->record_exists('local_program_users', array('programid' => $bclcdata->programid, 'userid' => $USER->id));
$userview = false;
$enrolmentpending = false;
if ($programuser && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext)) {
    $bclevel = new stdClass();
    $bclevel->programid = $bclcdata->programid;
    $bclevel->levelid = $bclcdata->levelid;
    $notcmptlcourses = (new program)->mynextlevelcourses($bclevel);
    if (!empty($notcmptlcourses)) {
        $coursesql = "SELECT *
                        FROM {course} c
                        JOIN {local_program_level_courses} blc ON blc.courseid = c.id
                       WHERE blc.id = :blcid ";
        $course = $DB->get_record_sql($coursesql, array('blcid' => $notcmptlcourses[0]));
        unset($notcmptlcourses[0]);
        if (!empty($notcmptlcourses) && array_search($bclcid, $notcmptlcourses) !== false) {
            echo '<div class="alert alert-warning">Enrolment will open post completion of ' . $course->fullname . ' course!</div>';
            $enrolmentpending = true;
        }
    }
    $userview = true;
}
if (is_siteadmin() || has_capability('local/program:createprogram', $categorycontext)) {
        // $PAGE->requires->js_call_amd('local_program/program', 'SessionDatatable',
        //             array(array('programstatus' => -1, 'programid' => $programid,
        //                 'levelid' => $levelid, 'bclcid' => $bclcid)));
    echo $renderer->viewprogramsessionstabs($bclcdata, $stable, $userview, $enrolmentpending, $action);
    echo $renderer->viewprogramsessions($bclcdata, $stable, $userview, $enrolmentpending, $action);
} else {
    echo $renderer->viewprogramsessions($bclcdata, $stable, $userview, $enrolmentpending);
}
echo $OUTPUT->footer();