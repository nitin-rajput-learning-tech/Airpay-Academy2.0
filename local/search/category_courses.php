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
 * @subpackage local_search
 */

require_once(dirname(__FILE__) . '/../../config.php');

    global $USER, $CFG, $PAGE, $OUTPUT, $DB;
    $category = optional_param('category', 0, PARAM_INT);
    //require_once($CFG->dirroot.'/blocks/manage/lib.php');
    $PAGE->set_context(\local_costcenter\lib\accesslib::get_module_context());
    $PAGE->set_url('/local/search/category_courses.php');

    //echo $OUTPUT->header();

    if($category == 0){
        $sql = "select c.id, c.category, c.fullname, c.summary, cd.grade, cd.credits from
                {course} c
                join {local_coursedetails} cd
                on c.id = cd.courseid
                where c.id > 1";
        $cat_courses = $DB->get_records_sql($sql);
    }else{
        $sql = "select c.id, c.category, c.fullname, c.summary, cd.grade, cd.credits from
                {course} c
                join {local_coursedetails} cd
                on c.id = cd.courseid
                where c.id > 1 and c.category = $category";
        $cat_courses = $DB->get_records_sql($sql);
    }
    //print_object($cat_courses);
    $course_info = array();
    if($cat_courses){
        foreach($cat_courses as $cat_course){
            $required_info = array();
            $required_info[] = $cat_course->fullname;
            $required_info[] = $cat_course->summary;
            $courses_details = $DB->get_record('local_coursedetails', array('courseid'=>$cat_course->id));
            $required_info[] = $courses_details->grade;
            $required_info[] = $courses_details->credits;
            $course_info[] = $required_info;
        }
    }

    if($course_info){
        //print_object($course_info);
        echo "category courses";
    }else{
        echo "No courses in this category";
    }

     //print json_encode($course_info);


    //echo $OUTPUT->footer();