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
require_once($CFG->dirroot . '/local/courses/filters_form.php');

$id        = optional_param('id', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$jsonparam    = optional_param('jsonparam', '', PARAM_RAW);

$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
if (!has_capability('local/courses:view', $categorycontext) && !has_capability('local/courses:manage', $categorycontext)) {
    print_error("You don't have permissions to view this page.");
}
$PAGE->set_context($categorycontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/courses/coursestypes.php');
$PAGE->set_title(get_string('course_type','local_courses'));
$PAGE->set_heading(get_string('course_type', 'local_courses'));
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_courses/createCoursetype', 'Datatable', array());
$PAGE->requires->js_call_amd('local_courses/createCourseProviders', 'Datatable', array());
$PAGE->navbar->add(get_string('pluginname', 'local_courses'), new moodle_url('/local/courses/courses.php'));
$PAGE->navbar->add(get_string('manage_courses', 'local_courses'));
$renderer = $PAGE->get_renderer('local_courses');
echo $OUTPUT->header();
$result = $DB->get_records('local_course_types',array(),$sort='id DESC');
foreach($result as $res){
    if(in_array($res->id,array('1','2','3','4','5'))){
        $show = false;
    }else{
        $show = true;
    }
    $res->display = $show;
    $res->identifiedas = $DB->get_field('course','open_identifiedas',array('open_identifiedas'=>$res->id));

}
$data = (object)[
    'result' => array_values($result),
];

echo $OUTPUT->render_from_template('local_courses/coursetypes_table', $data);
echo $OUTPUT->footer();
