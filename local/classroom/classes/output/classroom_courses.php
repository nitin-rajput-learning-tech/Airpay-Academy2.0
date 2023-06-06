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
 * elearning  courses
 *
 * @package    block_userdashboard
 * @copyright  2018 Maheshchandra <maheshchandra@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_classroom\output;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;
use local_classroom\local\userdashboard_content  as classroom_lib;
use block_userdashboard\includes\generic_content;

class classroom_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';


    private $classroomtemplate=0;

    private $filter_text='';

    private $filter='';

    public function __construct($filter,$filter_text='', $offset, $limit){
        $this->inprogressCount = classroom_lib::inprogress_classrooms_count($filter_text);
        $this->completedCount = classroom_lib::completed_classrooms_count($filter_text);
        $this->cancelledCount = classroom_lib::cancelled_classrooms_count($filter_text);
        $this->enrolledCount = classroom_lib::enrolled_classrooms_count($filter_text);
        switch ($filter){
            case 'inprogress':
                $this->courseslist = classroom_lib::inprogress_classrooms($filter_text, $offset, $limit);
                $this->coursesViewCount = $this->inprogressCount;
                $this->subtab='elearning_inprogress';
            break;

            case 'completed' :                           
                $this->courseslist = classroom_lib::completed_classrooms($filter_text, $offset, $limit);
                $this->coursesViewCount = $this->completedCount;
                $this->subtab='elearning_completed';
            break;
            
            case 'cancelled' :
                $this->courseslist = classroom_lib::cancelled_classsroom($filter_text, $offset, $limit);
                $this->coursesViewCount = $this->cancelledCount;
                $this->subtab='elearning_cancelled';
            break;
            case 'enrolled' :
                $this->courseslist = classroom_lib::enrolled_classrooms($filter_text, $offset, $limit);
                $this->coursesViewCount = $this->enrolledCount;
                $this->subtab='elearning_enrolled';
            break;

        }
        $this->filter = $filter;
        $this->offset = $offset;
        $this->limit = $limit;
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

        // $inprogress_clc = classroom_lib::inprogress_classrooms($this->filter_text);
        // $completedcount_clc = classroom_lib::completed_classrooms($this->filter_text);
        // $cancelled_clc = classroom_lib::cancelled_classsroom($this->filter_text);
        $total_clc =classroom_lib::gettotal_classrooms();

        $data->total = $total_clc;
        $data->index = $this->coursesViewCount > 10 ? 9: $this->coursesViewCount-1;
        $data->viewMoreCard = $this->coursesViewCount > 10 ? true : false;
        $data->filter = $this->filter;
        $data->inprogresscount= $this->inprogressCount;
        $data->completedcount = $this->completedCount;
        $data->filter_text = $this->filter_text;
        $data->functionname ='classroom_courses';
        $data->subtab=$this->subtab;
        $data->classroomtemplate= $this->classroomtemplate;
        $data->view_more_url = $CFG->wwwroot.'/local/classroom/userdashboard.php?tab='.explode('_',$this->subtab)[1];

         $data->enrolled_url = $CFG->wwwroot.'/local/classroom/userdashboard.php?tab=enrolled';
        $data->inprogress_url = $CFG->wwwroot.'/local/classroom/userdashboard.php?tab=inprogress';
        $data->completed_url = $CFG->wwwroot.'/local/classroom/userdashboard.php?tab=completed';
        
        $classroom_view_count = count($this->courseslist);
        //--------------------------I have to start from here--------------
        $data->classroom_view_count = $classroom_view_count;
        if($classroom_view_count > 2)
            $data->enableslider = 1;
        else    
            $data->enableslider = 0;
        
        if (!empty($this->courseslist)) 
            $data->inprogress_elearning_available =1;
        else
            $data->inprogress_elearning_available =0;

        if (!empty($this->courseslist)) {

            $data->course_count_view = generic_content::get_coursecount_class($this->courseslist);
            $i = 0;
            foreach ($this->courseslist as $inprogress_coursename) {
                
                $onerow=array();
                $onerow['inprogress_coursename'] = $inprogress_coursename;
                $image = generic_content::get_classroom_attachment($inprogress_coursename->id);
                $onerow['image'] =$image;
                $onerow['classroomid'] = $inprogress_coursename->id;
                // $exist = $DB->record_exists('local_classroom_users', array('classroomid' => $inprogress_coursename->id, 'userid' => $USER->id));          
                $onerow['index'] = $i++;
                // $i++;
                //-------- get the course summary------------------------ 
                $onerow['classroomSummary']= $this->get_coursesummary($inprogress_coursename);
               
                //---------get course fullname-----
                $onerow['classroomFullname'] = $inprogress_coursename->fullname;
                $onerow['displayClassroomFullname'] = $this->get_coursefullname($inprogress_coursename);

                // if ($exist) {
                //     $link = '<a href="' . $CFG->wwwroot . '/local/classroom/view.php?cid=' . $inprogress_coursename->id. '" title="' . $onerow['course_fullname'] . '">' . $onerow['inprogress_coursename_fullname'] . '</a>';
                //     $onerow['link']= $link;
                // }
                if(class_exists('local_ratings\output\renderer')){
                    $rating_render = $PAGE->get_renderer('local_ratings');
                    $onerow['rating_element'] = $rating_render->render_ratings_data('local_classroom', $inprogress_coursename->id);
                }else{
                    $onerow['rating_element'] = '';
                }
                $onerow['startdate'] = userdate($inprogress_coursename->startdate,'%d/%m/%Y %H %M');
                $onerow['enddate'] = userdate($inprogress_coursename->enddate,'%d/%m/%Y %H %M');
                $onerow['classroom_url'] = $CFG->wwwroot.'/local/classroom/view.php?cid='.$inprogress_coursename->id;
                require_once($CFG->dirroot.'/local/ratings/lib.php');
                $crratings = get_rating($inprogress_coursename->id, 'local_classroom');
                $onerow['ratingavg'] = $crratings->avg;
                if($DB->record_exists('local_classroom_completion', array("classroomid"=>$inprogress_coursename->id))){
                    $onerow['statusname'] = get_string('completed','local_classroom');
                }else{
                    $onerow['statusname'] =  get_string('launch','block_userdashboard');
                }
                
                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition     
         else{
             $data->course_count_view =0;
         }


        $data->moduledetails= $data->inprogress_elearning;
        $data->menu_heading = get_string('classroomtrainings','block_userdashboard');
        $data->nodata_string = get_string('noclassroomsavailable','block_userdashboard');

       // print_object($data);
        return $data;
    } // end of export_for_template function     
  

    private function get_coursesummary($course_record){   

        $coursesummary = \local_costcenter\lib::strip_tags_custom($course_record->description);
        $summarystring = strlen($coursesummary) > 100 ? clean_text(substr($coursesummary, 0, 100))."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = '<span class="w-full pull-left">'.get_string('nodecscriptionprovided','block_userdashboard').'</span>';
        }

        return $coursesummary;
    } // end of function


    private function get_coursefullname($inprogress_coursename){

        $course_fullname = $inprogress_coursename->fullname;
        if (strlen($course_fullname) >= 40) {
            $inprogress_coursename_fullname = clean_text(substr($inprogress_coursename->fullname, 0, 40)) . '...';
        } else {
            $inprogress_coursename_fullname = $inprogress_coursename->fullname;
        }

        return $inprogress_coursename_fullname;
    } // end of function



} // end of class
