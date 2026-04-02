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

define('AJAX_SCRIPT',true);
require_once(dirname(__FILE__) . '/../../config.php');

    global $USER, $CFG, $PAGE, $OUTPUT, $DB;
    $page_number = optional_param('page', 1, PARAM_INT);
    $PAGE->set_context(\local_costcenter\lib\accesslib::get_module_context());
    $PAGE->set_url('/local/search/ajax.php');
    require_login();
    echo $OUTPUT->header();

    $page_number = 1; //if there's no page number, set it to 1

    //get total number of records from database for pagination
    if(is_siteadmin()){
        $allcourses = $DB->get_records_sql("SELECT * FROM {course} where id > 1");
    }else{

         $sql="SELECT c.*
              FROM {course} AS c
              JOIN {context} AS cxt
              ON cxt.instanceid = c.id
              JOIN {role_assignments} AS ras
              ON ras.contextid = cxt.id
              WHERE cxt.contextlevel = 50 AND ras.userid = $USER->id";

         $allcourses = $DB->get_records_sql($sql); // all enrolled courses
    }

    $item_per_page = 6;

    $total_pages = ceil(count($allcourses)/$item_per_page);

    //get starting position to fetch the records
    $page_position = (($page_number-1) * $item_per_page);

    if(is_siteadmin()){
        $mycourses = $DB->get_records_sql("SELECT * FROM {course} where id > 1 LIMIT $page_position, $item_per_page");
    }else{

         $sql="SELECT c.*
              FROM {course} AS c
              JOIN {context} AS cxt
              ON cxt.instanceid = c.id
              JOIN {role_assignments} AS ras
              ON ras.contextid = cxt.id
              WHERE cxt.contextlevel = 50 AND ras.userid = $USER->id LIMIT $page_position, $item_per_page";

         $mycourses = $DB->get_records_sql($sql); // all enrolled courses
    }

    if($mycourses){
        $coursefileurl = $OUTPUT->pix_url('/course_images/courseimg', 'local_costcenter');
        $img = html_writer:: empty_tag('img',array('src'=>$coursefileurl, 'width'=>'100%'));

        $grid = '';
        $grid .= html_writer:: start_tag('div', array('class'=>'list span12'));
        foreach($mycourses as $course){
            $courserecord = $DB->get_record('course', array('id'=>$course->id));
            if($courserecord->summary){
                  $summary = clean_text($courserecord->summary);
            }else{
                  $summary = '';
            }

            $grid .= html_writer:: start_tag('div', array('class'=>'singlecourse_data'));
                 $grid .= html_writer:: tag('div', $img, array('class'=>'courseimg'));
                 $grid .= html_writer:: tag('div', $courserecord->fullname, array('class'=>'enroll_coursename'));
                 $grid .= html_writer:: tag('div', strip_tags(html_entity_decode(clean_text($summary)),array('overflowdiv' => false, 'noclean' => false, 'para' => false)), array('class'=>'course_description'));
            $grid .= html_writer:: end_tag('div');
        }
        $grid .= html_writer:: end_tag('div');

    }else{
         $grid = '-- No courses --';
    }

    $grid .= '<div align="center">';
    /* We call the pagination function here to generate Pagination link for us.
    As you can see I have passed several parameters to the function. */
    $grid .= paginate_function($item_per_page, $page_number, count($allcourses), $total_pages);
    $grid = '</div>';



    echo $grid;

    // ################ pagination function #########################################
    function paginate_function($item_per_page, $current_page, $total_records, $total_pages)
    {
        $pagination = '';
        if($total_pages > 0 && $total_pages != 1 && $current_page <= $total_pages){ //verify total pages and current page number
            $pagination .= '<ul class="pagination">';

            $right_links    = $current_page + 3;
            $previous       = $current_page - 3; //previous link
            $next           = $current_page + 1; //next link
            $first_link     = true; //boolean var to decide our first link

            if($current_page > 1){
                $previous_link = ($previous==0)? 1: $previous;
                $pagination .= '<li class="first"><a href="#" data-page="1" title="First">&laquo;</a></li>'; //first link
                $pagination .= '<li><a href="#" data-page="'.$previous_link.'" title="Previous">&lt;</a></li>'; //previous link
                    for($i = ($current_page-2); $i < $current_page; $i++){ //Create left-hand side links
                        if($i > 0){
                            $pagination .= '<li><a href="#" data-page="'.$i.'" title="Page'.$i.'">'.$i.'</a></li>';
                        }
                    }
                $first_link = false; //set first link to false
            }

            if($first_link){ //if current active page is first link
                $pagination .= '<li class="first active">'.$current_page.'</li>';
            }elseif($current_page == $total_pages){ //if it's the last active link
                $pagination .= '<li class="last active">'.$current_page.'</li>';
            }else{ //regular current link
                $pagination .= '<li class="active">'.$current_page.'</li>';
            }

            for($i = $current_page+1; $i < $right_links ; $i++){ //create right-hand side links
                if($i<=$total_pages){
                    $pagination .= '<li><a href="#" data-page="'.$i.'" title="Page '.$i.'">'.$i.'</a></li>';
                }
            }
            if($current_page < $total_pages){
                    $next_link = ($i > $total_pages) ? $total_pages : $i;
                    $pagination .= '<li><a href="#" data-page="'.$next_link.'" title="Next">&gt;</a></li>'; //next link
                    $pagination .= '<li class="last"><a href="#" data-page="'.$total_pages.'" title="Last">&raquo;</a></li>'; //last link
            }

            $pagination .= '</ul>';
        }
        return $pagination; //return pagination links
    }

    echo $OUTPUT->footer();