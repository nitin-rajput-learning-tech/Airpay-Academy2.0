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
 * @subpackage local_classroom
 */
namespace block_userdashboard\output;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;
use block_userdashboard\lib\classrooms  as classroom_lib;
use block_userdashboard\includes\generic_content;

class classroom_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';


    private $classroomtemplate=0;

    private $filter_text='';

    private $filter='';

    public function __construct($filter,$filter_text=''){
       
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = classroom_lib::inprogress_classrooms($filter_text);
                    $this->subtab='elearning_inprogress';
                break;

            case 'completed' :                           
                    $this->courseslist = classroom_lib::completed_classrooms($filter_text);
                    $this->subtab='elearning_completed';
                break;
            
            case 'cancelled' :
                    $this->courseslist = classroom_lib::cancelled_classsroom($filter_text);
                    $this->subtab='elearning_cancelled';
                break;
    

        }
        $this->filter = $filter;  
        $this->filter_text = $filter_text;
        $this->classroomtemplate= 1;
    } // end of the function
    

    


    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER, $CFG, $PAGE;
       
        $data = new stdClass(); $courses_active = ''; $tabs = '';  
        $data->inprogress_elearning = array();

        $inprogress_clc = classroom_lib::inprogress_classrooms($this->filter_text);
        $completedcount_clc = classroom_lib::completed_classrooms($this->filter_text);
        $cancelled_clc = classroom_lib::cancelled_classsroom($this->filter_text);
        $total_clc =classroom_lib::gettotal_classrooms();

        $data->total =$total_clc;
        $data->index = count($this->courseslist)-1;
        $data->filter = $this->filter;
        $data->inprogresscount= count($inprogress_clc);
        $data->completedcount = count($completedcount_clc);
        $data->filter_text = $this->filter_text;
        $data->functionname ='classroom_courses';
        $data->subtab=$this->subtab;
        $data->classroomtemplate= $this->classroomtemplate;
        $data->view_more_url = $CFG->wwwroot.'/blocks/userdashboard/userdashboard_courses.php?tab=classroom&subtab='.explode('_',$this->subtab)[1];
        $courses_view_count = count($this->courseslist);
        //--------------------------I have to start from here--------------
        $data->courses_view_count = $courses_view_count;
        if($courses_view_count > 2)
            $data->enableslider = 1;
        else    
            $data->enableslider = 0;
        
        if (!empty($this->courseslist)) 
            $data->inprogress_elearning_available =1;
        else
            $data->inprogress_elearning_available =0;

        if (!empty($this->courseslist)) {

            $data->course_count_view = generic_content::get_coursecount_class($this->courseslist);
            
            foreach ($this->courseslist as $inprogress_coursename) {
                
                $onerow=array();
                $onerow['inprogress_coursename'] = $inprogress_coursename;
                $image = generic_content::get_classroom_attachment($inprogress_coursename->id);
                $onerow['image'] =$image;

                $exist = $DB->record_exists('local_classroom_users', array('classroomid' => $inprogress_coursename->id, 'userid' => $USER->id));          

                //-------- get the course summary------------------------ 
                $onerow['coursesummary']= $this->get_coursesummary($inprogress_coursename);
               
                //---------get course fullname-----
                $onerow['course_fullname'] = $inprogress_coursename->fullname;
                $onerow['inprogress_coursename_fullname'] = $this->get_coursefullname($inprogress_coursename);

                if ($exist) {
                    $link = '<a href="' . $CFG->wwwroot . '/local/classroom/view.php?cid=' . $inprogress_coursename->id. '" title="' . $onerow['course_fullname'] . '">' . $onerow['inprogress_coursename_fullname'] . '</a>';
                    $onerow['link']= $link;
                }
                if(class_exists('local_ratings\output\renderer')){
                    $rating_render = $PAGE->get_renderer('local_ratings');
                    $onerow['rating_element'] = $rating_render->render_ratings_data('local_classroom', $inprogress_coursename->id);
                }else{
                    $onerow['rating_element'] = '';
                }
                $onerow['startdate'] = userdate($inprogress_coursename->startdate,'%d %b %y');
                $onerow['enddate'] = userdate($inprogress_coursename->enddate,'%d %b %y');
                $onerow['class_url'] = $CFG->wwwroot.'/local/classroom/view.php?cid='.$inprogress_coursename->id;
                 
                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition     
         else{
             $data->course_count_view =0;
         }


        $data->inprogress_elearning= json_encode($data->inprogress_elearning);
        $data->menu_heading = get_string('classroomtrainings','block_userdashboard');
        $data->nodata_string = get_string('noclassroomsavailable','block_userdashboard');

       // print_object($data);
        return $data;
    } // end of export_for_template function     
  

    private function get_coursesummary($course_record){   

        $coursesummary = strip_tags($course_record->description);
        $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100)."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = '<span class="w-full text-center alert alert-info pull-left text-center">'.get_string('nodecscriptionprovided','block_userdashboard').'</span>';
        }

        return $coursesummary;
    } // end of function


    private function get_coursefullname($inprogress_coursename){

        $course_fullname = $inprogress_coursename->fullname;
        if (strlen($course_fullname) >= 40) {
            $inprogress_coursename_fullname = substr($inprogress_coursename->fullname, 0, 40) . '...';
        } else {
            $inprogress_coursename_fullname = $inprogress_coursename->fullname;
        }

        return $inprogress_coursename_fullname;
    } // end of function



} // end of class
