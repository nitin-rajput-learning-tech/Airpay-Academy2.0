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
 * @subpackage local_users
 */
 
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');

global $CFG, $USER,$PAGE,$OUTPUT,$DB;
require_once($CFG->dirroot . '/local/courses/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');

$filtervalues = (array)json_decode($_REQUEST['formdata']);
$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
require_login(); 
$table = new html_table();
$table->id = "courses";
$table->head[] = get_string('coursename','local_courses');
$table->head[] = get_string('coursecode','local_courses');
$table->head[] = get_string('coursetype','local_courses');
$table->head[] = get_string('open_departmentlocal_courses','local_courses');
$table->head[] = get_string('open_subdepartmentlocal_courses','local_courses');
$table->head[] = get_string('open_level4departmentlocal_courses','local_courses');
// $table->head[] = get_string('open_level5departmentlocal_courses','local_courses');
$table->head[] = get_string('category','local_courses');
$table->head[] = get_string('enrollments','local_courses');
//$table->head[] = get_string('points','local_courses');
$table->head[] = get_string('completed','local_courses');
//$table->head[] = get_string('skill','local_courses');
$table->head[] = get_string('ratings','local_courses');
//$table->head[] = get_string('tags','local_courses');
$table->head[] = get_string('summary','local_courses');
$table->head[] = get_string('format','local_courses');
$table->head[] = get_string('selfenrol','local_courses');

$stable = new \stdClass();
$stable->thead = false;
$stable->start = 0;
$stable->length = 0;
$coursedata = get_listof_courses($stable, $filtervalues);
$data = [];
foreach($coursedata['hascourses'] AS $course){
    //  local_costcenter_set_costcenter_path($course);
    //, $course['points'], $course['skillname'], $course['tagstringtotal']
     $data[] = [$course['coursename'], $course['shortname'], $course['coursetype'], $course['open_department'], $course['open_subdepartment'], $course['open_level4department'], $course['catname'], $course['enrolled_count'], $course['completed_count'], $course['ratings_value'],$course['fullcoursesummary'],$course['format'],$course['selfenrol']];
}
$table->id = "users";
$table->data = $data;
 require_once($CFG->libdir . '/csvlib.class.php');
    $matrix = array();
    $filename = 'courses';
    if (!empty($table->head)) {
        $countcols = count($table->head);
        $keys = array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {
            $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
        }
    }
    if (!empty($table->data)) {
        foreach ($table->data as $rkey => $row) {
            foreach ($row as $key => $item) {
                    $item = is_array($item) ? implode(',', $item) : $item;
                    $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
            }
        }
    }
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
    foreach ($matrix as $ri => $col) {
        $csvexport->add_data($col);
    }
    ob_clean();
    $csvexport->download_file();
    exit;

 
