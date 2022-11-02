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
 * @copyright  2018 hemalatha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_userdashboard\output;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;
use completion_info;
use core_completion\progress;
use block_userdashboard\lib\elearning_courses  as courseslist_lib;
use block_userdashboard\includes\generic_content;
use block_userdashboard\includes\user_course_details as user_course_details;



class elearning_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $elearningtemplate=0;

    private $filter_text='';

    private $filter=''; 

    public function __construct($filter, $filter_text='', $filter_offset = 0, $filter_limit = 0){
       
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = courseslist_lib::inprogress_coursenames($filter_text);
                    $this->courselistcount = courseslist_lib::inprogress_coursenames_count($filter_text);
                    $this->subtab='elearning_inprogress';
                break;

            case 'completed' :                           
                    $this->courseslist = courseslist_lib::completed_coursenames($filter_text);
                    $this->courselistcount = courseslist_lib::completed_coursenames_count($filter_text);
                    $this->subtab='elearning_completed';
                break;      
    

        }
        $this->filter = $filter;  
        $this->filter_text = $filter_text;
        // $this->offset = $filter_offset;
        // $this->limit = $filter_limit;
        $this->elearningtemplate=1;

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
        // $pending_elearning = courseslist_lib::pastdue_coursenames($this->filter_text);
        $completed = courseslist_lib::completed_coursenames($this->filter_text);
        // $inprogresscount = count($this->courseslist);
        $inprogresscount = $this->courselistcount;
        $completedcount = count($completed);
        $total = $inprogresscount+$completedcount;
        // if ($courseslist == '') {
        //     $courseslist = null;
        // }

        // if ($pending_elearning == '') {
        //     $pending_elearning = null;
        // }

        if ($completed == '') {
            $completed = null;
        }
         $data->course_count_view =0;
        $data->total =$total;
        // $data->index = count($this->courseslist)-1;
        $data->index = $this->courselistcount - 1;
        // $data->inprogresscount= count($this->courseslist);
        $data->inprogresscount= $this->courselistcount;
        $data->completedcount = count($completed);
        $data->functionname ='elearning_courses';
        $data->subtab=$this->subtab;
        $data->elearningtemplate=$this->elearningtemplate;
        $data->filter = $this->filter; 
        $data->filter_text = $this->filter_text;
        // $courses_view_count = count($this->courseslist);
        $courses_view_count = $this->courselistcount;
        $data->courses_view_count = $courses_view_count;
        $data->view_more_url = $CFG->wwwroot.'/blocks/userdashboard/userdashboard_courses.php?tab=elearning&subtab='.explode('_',$this->subtab)[1];
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
                $lastaccessstime= $DB->get_field('user_lastaccess', 'timeaccess', array('userid'=>$USER->id, 'courseid' => $inprogress_coursename->id));

                $onerow['lastaccessstime']= $lastaccessstime;
                if($lastaccessstime){
                    $onerow['lastaccessdate'] = \local_costcenter\lib::get_userdate('d m Y', $lastaccessstime);
                }else{
                    $onerow['lastaccessdate'] = get_string('none','block_userdashboard');
                }
               
                $course_record = $DB->get_record('course', array('id' => $inprogress_coursename->id));

             
                $onerow['course_image_url'] = generic_content::course_summary_files($course_record);    

                //-------- get the course summary------------------------ 
                $onerow['coursesummary']= $this->get_coursesummary($inprogress_coursename);

                //------get progress bar and width value in the form of array
                $progressbarvalues = $this->get_progress_value($inprogress_coursename); 
                $onerow['progress'] =$progressbarvalues['progress'];
                $onerow['progress_bar_width'] =$progressbarvalues['progress_bar_width'];

                //---------get course fullname-----
                $onerow['course_fullname'] = $inprogress_coursename->fullname;
                $onerow['inprogress_coursename_fullname'] = $this->get_coursefullname($inprogress_coursename);
                $onerow['course_url'] = $CFG->wwwroot.'/course/view.php?id='.$inprogress_coursename->id;
                if(class_exists('\local_ratings\output\renderer')){
                    $rating_render = $PAGE->get_renderer('local_ratings');
                    $onerow['rating_element'] = $rating_render->render_ratings_data('local_courses', $inprogress_coursename->id);
                }else{
                    $onerow['rating_element'] = '';
                }
                $classname = '\block_trending_modules\querylib';
                if(class_exists($classname)){
                    $trendingQuerylib = new $classname();
                    $onerow['element_tags'] = implode(',',$trendingQuerylib->get_my_tags_info('courses')['local_courses']);
                }else{
                    $onerow['element_tags'] = False;
                }

             
                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition     
         
        $data->inprogress_elearning= json_encode($data->inprogress_elearning);
        $data->menu_heading = get_string('elearning','block_userdashboard');
        $data->nodata_string = get_string('nocoursesavailable','block_userdashboard');
       // print_object($data);
        return $data;
    } // end of export_for_template function



     
    private function get_progress_value($inprogress_coursename){
        global $DB, $USER, $CFG; 
        
        $course = $DB->get_record('course',array('id' => $inprogress_coursename->id));
        $completion = new \completion_info($course);

            // First, let's make sure completion is enabled.
            if ($completion->is_enabled()) {
                $percentage = progress::get_course_progress_percentage($course, $USER->id);

                if (!is_null($percentage)) {
                    $percentage = floor($percentage);
                }
               $progress  = $percentage;
            }
        if (!$progress) {
            $progress = 0;
            $progress_bar_width = " min-width: 0px;";
        } else {
            $progress = round($progress);
            $progress_bar_width = "min-width: 0px;";
        }

        return (array('progress'=>$progress, 'progress_bar_width'=>$progress_bar_width));

    } // end of the function


    private function get_coursesummary($course_record){   

        $coursesummary = strip_tags($course_record->summary);
        $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100)."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = '<span class="alert alert-info text-center w-full pull-left">'.get_string('nodecscriptionprovided','block_userdashboard').'</span>';
        }

        return $coursesummary;
    } // end of function


    private function get_coursefullname($inprogress_coursename){

        $course_fullname = $inprogress_coursename->fullname;
        if (strlen($course_fullname) >= 20) {
            $inprogress_coursename_fullname = substr($inprogress_coursename->fullname, 0, 20) . '...';
        } else {
            $inprogress_coursename_fullname = $inprogress_coursename->fullname;
        }

        return $inprogress_coursename_fullname;
    } // end of function



} // end of class
