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
namespace block_userdashboard\output;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;
use block_userdashboard\lib\programs  as courseslist_lib;
use block_userdashboard\includes\generic_content;

class program_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $programtemplate=0;

    private $filter_text='';

    private $filter='';

    public function __construct($filter, $filter_text=''){
       
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = courseslist_lib::inprogress_programs($filter_text);
                    $this->subtab='elearning_inprogress'; 
                break;

            case 'completed' :                           
                    $this->courseslist = courseslist_lib::completed_programs($filter_text);
                    $this->subtab='elearning_completed';
                break; 
            case 'cancelled' :
                    $this->courseslist = courseslist_lib::cancelled_programs($filter_text);
                    $this->subtab='elearning_cancelled'; 
                break;
    

        }
        $this->filter = $filter;  
        $this->filter_text = $filter_text;
        $this->programtemplate=1;

    } // end of the function
    

    


    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER, $CFG;
       
        $data = new stdClass(); 
        $courses_active = ''; 
        $tabs = '';  
        $data->inprogress_elearning = array();    
        $inprogress_programs = courseslist_lib::inprogress_programs($this->filter_text);
        $completed_programs = courseslist_lib::completed_programs($this->filter_text);
        $cancelled = courseslist_lib::cancelled_programs($this->filter_text);
        $inprogresscount = count($this->courseslist);
        $completedcount = count($completed);
        $cancelledcount = count($cancelled);
        // $total = $inprogresscount+$completedcount;
        $total = courseslist_lib::gettotal_programs();
        if ($courseslist == '') {
            $courseslist = null;
        }

        if ($inprogress_programs == '') {
            $inprogress_programs = null;
        }

        if ($completed_programs == '') {
            $completed_programs = null;
        }
        $data->course_count_view =0;
        $data->total =$total;
        $data->index = count($this->courseslist)-1;
        $data->filter = $this->filter;
        $data->filter_text = $this->filter_text;
        $data->inprogresscount= count($this->courseslist);
        $data->completedcount = count($completed_programs);
        $data->functionname ='program_courses';
        $data->subtab= $this->subtab;
        $data->programtemplate= $this->programtemplate;
        $data->view_more_url = $CFG->wwwroot.'/blocks/userdashboard/userdashboard_courses.php?tab=program&subtab='.explode('_',$this->subtab)[1];
        $courses_view_count = count($this->courseslist);
        $data->courses_view_count = $courses_view_count;
        if($courses_view_count > 2)
            $data->enableslider = 1;
        else    
            $data->enableslider = 0;
        
        if (!empty($this->courseslist)) 
            $data->inprogress_elearning_available = 1;
        else
            $data->inprogress_elearning_available = 0;

        
        if (!empty($this->courseslist)) {

            $data->course_count_view = generic_content::get_coursecount_class($this->courseslist);

            foreach ($this->courseslist as $inprogress_coursename) {
                $onerow=array();
                $onerow['inprogress_coursename'] = $inprogress_coursename;
                $lastaccessstime= $DB->get_field('user_lastaccess', 'timeaccess', array('userid'=>$USER->id, 'courseid' => $inprogress_coursename->id));

                $onerow['lastaccessstime']= $lastaccessstime;
                $onerow['lastaccessdate'] =\local_costcenter\lib::get_userdate('d m Y', $lastaccessstime);
               
                // $course_record = $DB->get_record('course', array('id' => $inprogress_coursename->id));

             
                $onerow['course_image_url'] = generic_content::program_logo_files($inprogress_coursename->id);    

                //-------- get the course summary------------------------ 
                $onerow['coursesummary']= $this->get_coursesummary($inprogress_coursename);

                //------get progress bar and width value in the form of array
                // $progressbarvalues = $this->get_progress_value($inprogress_coursename); 
                // $onerow['progress'] =$progressbarvalues['progress'];
                // $onerow['progress_bar_width'] =$progressbarvalues['progress_bar_width'];

                //---------get course fullname-----
                $onerow['course_fullname'] = $inprogress_coursename->fullname;
                $onerow['inprogress_coursename_fullname'] = $this->get_coursefullname($inprogress_coursename);
                $onerow['program_url'] = $CFG->wwwroot.'/local/program/view.php?pid='.$inprogress_coursename->id;
                $programuserssql = "SELECT count(cu.id) as total
                                           FROM {local_program_users} AS cu
                                            WHERE cu.programid = {$inprogress_coursename->id}";
                $programusers= $DB->count_records_sql($programuserssql);
                $programuserssql.= " AND cu.completion_status=1";
                $programusers_completed= $DB->count_records_sql($programuserssql);
                                            
                if (empty($programusers)||$programusers==0) {
                    $programprogress = 0;
                } else {
                    $programprogress = round(($programusers_completed/$programusers)*100);
                }
                $onerow['programprogress'] = $programprogress;

             
                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition     
         
        $data->inprogress_elearning= json_encode($data->inprogress_elearning);
        $data->menu_heading = get_string('programtrainings','block_userdashboard');
        $data->nodata_string = get_string('noprogramsavailable','block_userdashboard');
        
        return $data;
    } // end of export_for_template function

    private function get_coursesummary($course_record){   

        $coursesummary = strip_tags($course_record->description);
        $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100)."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = '<span class="alert alert-info w-full pull-left text-center mt-10">'.get_string('nodecscriptionprovided','block_userdashboard').'</span>';
        }

        return $coursesummary;
    } // end of function


    private function get_coursefullname($inprogress_coursename){

        $course_fullname = $inprogress_coursename->fullname;
        if (strlen($course_fullname) >= 22) {
            $inprogress_coursename_fullname = substr($inprogress_coursename->fullname, 0, 22) . '...';
        } else {
            $inprogress_coursename_fullname = $inprogress_coursename->fullname;
        }

        return $inprogress_coursename_fullname;
    } // end of function



} // end of class
