<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
global $CFG, $DB;
$categorycontext =  (new \local_courses\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$PAGE->set_url('/local/courses/upload/coursehelp.php');
$PAGE->set_pagelayout('standard');
$strheading = get_string('pluginname', 'local_courses') . ' : ' . get_string('manual', 'local_courses');
$PAGE->set_title($strheading);

$PAGE->set_heading(get_string('manual', 'local_courses'));
$PAGE->navbar->add(get_string('pluginname', 'local_courses'), new moodle_url('/local/courses/courses.php'));
$PAGE->navbar->add(get_string('uploadcourses', 'local_courses'), new moodle_url('/local/courses/upload/index.php'));
$PAGE->navbar->add(get_string('manual', 'local_courses'));
echo $OUTPUT->header();
//echo $OUTPUT->heading(get_string('manual', 'local_courses'));
if (isset($CFG->allowframembedding) and ! $CFG->allowframembedding) {
    echo $OUTPUT->box(get_string('coursemanual', 'local_courses'));
    echo '<div style="float:right;"><a href="index.php"><img src="'. $OUTPUT->image_url('e/undo') . '"  alt = "'.get_string('back_upload', 'local_courses').'"title = "'.get_string('back_upload', 'local_courses').'" class="icon"/></a></div>';
      /* echo '<div style="float:right;"><a href="index.php"><button>' . get_string('back_upload', 'local_courses') . '</button></a></div>';*/
}
echo get_string('help_1', 'local_courses');
echo get_string('help_2', 'local_courses');

echo $OUTPUT->footer();
