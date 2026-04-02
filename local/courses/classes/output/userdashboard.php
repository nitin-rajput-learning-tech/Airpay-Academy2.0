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
 * @package    local_courses
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_courses\output;
use renderable;
use renderer_base;
use templatable;
use core_completion\progress;
use local_courses\local\general_lib  as general_lib;
use block_userdashboard\includes\generic_content;
// use block_userdashboard\includes\user_course_details as user_course_details;



class userdashboard implements renderable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $elearningtemplate=0;

    private $filter_text='';

    private $filter=''; 

    public function __construct($filter, $filter_text='', $filter_offset = 0, $filter_limit = 0){
        $this->inprogresscount = general_lib::inprogress_coursenames_count($filter_text);
        $this->completedcount = general_lib::completed_coursenames_count($filter_text);
        $this->enrolledcount = general_lib::enrolled_coursenames_count($filter_text);
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = general_lib::inprogress_coursenames($filter_text, $filter_offset, $filter_limit);
                    $this->coursesViewCount = $this->inprogresscount;
                    $this->subtab='elearning_inprogress';
                break;

            case 'completed' :                           
                    $this->courseslist = general_lib::completed_coursenames($filter_text, $filter_offset, $filter_limit);
                    $this->coursesViewCount = $this->completedcount;
                    $this->subtab = 'elearning_completed';
                break;
            case 'enrolled' :                           
                    $this->courseslist = general_lib::enrolled_coursenames($filter_text, $filter_offset, $filter_limit);
                    $this->coursesViewCount = $this->enrolledcount;
                    $this->subtab = 'elearning_enrolled';
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
        $data = new \stdClass(); $courses_active = ''; $tabs = '';  
        $data->inprogress_elearning = array();    
        // $completed = general_lib::completed_coursenames($this->filter_text);
        $completedcount = $this->completedcount;
        $inprogresscount = $this->inprogresscount;
        $total = $inprogresscount+$completedcount;

        if ($completed == '') {
            $completed = null;
        }
         $data->course_count_view =0;
        $data->total =$total;
        // $data->index = count($this->courseslist)-1;

        $data->index = $this->coursesViewCount > 10 ? 9 : $this->coursesViewCount - 1;
        $data->viewMoreCard = $this->coursesViewCount > 10 ? true : false;
        // $data->inprogresscount= count($this->courseslist);
        $data->inprogresscount= $this->inprogresscount;
        $data->completedcount = $this->completedcount;
        $data->functionname ='elearning_courses';
        $data->subtab=$this->subtab;
        $data->elearningtemplate=$this->elearningtemplate;
        $data->filter = $this->filter; 
        $data->filter_text = $this->filter_text;
        // $courses_view_count = count($this->courseslist);
        $courses_view_count = $this->coursesViewCount;
        $data->courses_view_count = $courses_view_count;
        $data->view_more_url = $CFG->wwwroot.'/local/courses/userdashboard.php?tab='.explode('_',$this->subtab)[1];
        $data->enrolled_url = $CFG->wwwroot.'/local/courses/userdashboard.php?tab=enrolled';
        $data->inprogress_url = $CFG->wwwroot.'/local/courses/userdashboard.php?tab=inprogress';
        $data->completed_url = $CFG->wwwroot.'/local/courses/userdashboard.php?tab=completed';
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
            $i = 0;
            foreach ($this->courseslist as $inprogress_coursename) {
                $onerow=array();
                $onerow['inprogress_coursename'] = $inprogress_coursename;
                $lastaccessstime= $DB->get_field('user_lastaccess', 'timeaccess', array('userid'=>$USER->id, 'courseid' => $inprogress_coursename->id));
                // if($data->viewMoreCard)
                //     $onerow['indexClass'] = "dashboardCard$i";
                // else
                //     $onerow['indexClass'] = "dashboardCard";
                $onerow['index'] = $i++;
                // $i++;
                $onerow['lastaccessstime']= $lastaccessstime;
                if($lastaccessstime){
                    $onerow['lastaccessdate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i', $lastaccessstime);
                    // $onerow['lastaccessdate'] = userdate($lastaccessstime, 'd/m/Y');
                }else{
                    $onerow['lastaccessdate'] = get_string('none');
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


                $completedon = $DB->get_field('course_completions', 'timecompleted', array('course'=> $inprogress_coursename->id, 'userid'=> $USER->id));

                if($completedon){
                    $onerow['course_completedon'] = date("d M Y", $completedon);
                }else{
                    $onerow['course_completedon'] = null;
                }
                if($lastaccessstime){
                    $onerow['label_name'] = get_string('continue_course','block_userdashboard');
                }else{
                    $onerow['label_name'] = get_string('start_course','block_userdashboard');
                }
             
                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition     
         
        $data->moduledetails= $data->inprogress_elearning;
        $data->templatename = 'local_courses/dashboard_innercontent';
        $data->pluginname = 'local_courses';
        $data->tabname = $this->filter;
        $data->status = $this->filter;
        $data->menu_heading = get_string('elearning','block_userdashboard');
        $data->nodata_string = get_string('nocoursesavailable','block_userdashboard');
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

        $coursesummary = \local_costcenter\lib::strip_tags_custom($course_record->summary);
        $summarystring = strlen($coursesummary) > 100 ? clean_text(substr($coursesummary, 0, 100))."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = '<span class="w-full pull-left">'.get_string('nodecscriptionprovided','block_userdashboard').'</span>';
        }

        return $coursesummary;
    } // end of function


    private function get_coursefullname($inprogress_coursename){

        $course_fullname = $inprogress_coursename->fullname;
        if (strlen($course_fullname) >= 20) {
            $inprogress_coursename_fullname = clean_text(substr($inprogress_coursename->fullname, 0, 20)) . '...';
        } else {
            $inprogress_coursename_fullname = $inprogress_coursename->fullname;
        }

        return $inprogress_coursename_fullname;
    } // end of function



} // end of class
