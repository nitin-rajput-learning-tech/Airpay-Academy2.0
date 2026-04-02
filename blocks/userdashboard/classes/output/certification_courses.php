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
use block_userdashboard\lib\certification  as certification_lib;
use block_userdashboard\includes\generic_content;

class certification_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';


    private $certificationtemplate=0;

    private $filter_text='';
    
    private $filter='';
    
    public function __construct($filter, $filter_text=''){
       
    switch ($filter){
        case 'inprogress':
            $this->courseslist = certification_lib::inprogress_certification($filter_text);
            $this->subtab='elearning_inprogress'; 
            break;

        case 'completed' :                           
            $this->courseslist = certification_lib::completed_certification($filter_text);
            $this->subtab='elearning_completed';
            break;

        }
        $this->filter = $filter;  
        $this->filter_text = $filter_text;
        $this->certificationtemplate= 1;
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

        $inprogress_clc = certification_lib::inprogress_certification($this->filter_text);
        $completedcount_clc = certification_lib::completed_certification($this->filter_text);
        // $cancelled_clc = classroom_lib::cancelled_classsroom();
        $total_clc =certification_lib::gettotal_certification();

        $data->total =$total_clc;
        $data->index = count($this->courseslist)-1;
        $data->filter = $this->filter;
        $data->filter_text = $this->filter_text;
        $data->inprogresscount = count($inprogress_clc);
        $data->completedcount = count($completedcount_clc);
        $data->functionname ='certification_courses';
        $data->subtab=$this->subtab;
        $data->view_more_url = $CFG->wwwroot.'/blocks/userdashboard/userdashboard_courses.php?tab=certification&subtab='.explode('_',$this->subtab)[1];
        $data->certificationtemplate= $this->certificationtemplate;
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
                //$image = certification_lib::get_certification_attachment($inprogress_coursename->id);
                //$onerow['image'] =$image;

                $exist = $DB->record_exists('local_classroom_users', array('classroomid' => $inprogress_coursename->id, 'userid' => $USER->id));          

                //-------- get the course summary------------------------ 
                $onerow['coursesummary']= $this->get_coursesummary($inprogress_coursename);
               
                //---------get course fullname-----
                $onerow['course_fullname'] = $inprogress_coursename->fullname;
                $onerow['inprogress_coursename_fullname'] = $this->get_coursefullname($inprogress_coursename);

                if ($exist) {
                    $link = '<a href="' . $CFG->wwwroot . '/local/classroom/view.php?ctid=' . $inprogress_coursename->id. '" title="' . $onerow['course_fullname'] . '">' . $onerow['inprogress_coursename_fullname'] . '</a>';
                    $onerow['link']= $link;
                }
                
                $onerow['startdate'] = userdate($inprogress_coursename->startdate,'%d %b %y');
                $onerow['enddate'] = userdate($inprogress_coursename->enddate,'%d %b %y');
                $certificationuserssql = "SELECT count(cu.id) as total
                                        FROM {local_certification_users} AS cu
                                        WHERE cu.certificationid = {$inprogress_coursename->id}";
                $certificationusers= $DB->count_records_sql($certificationuserssql);
                $certificationuserssql.= " AND cu.completion_status=1";
                $certificationusers_completed= $DB->count_records_sql($certificationuserssql);
                                            
                if(class_exists('local_ratings\output\renderer')){
                    $rating_render = $PAGE->get_renderer('local_ratings');
                    $onerow['rating_element'] = $rating_render->render_ratings_data('local_certification', $inprogress_coursename->id);
                }else{
                    $onerow['rating_element'] = '';
                }
                if (empty($certificationusers)||$certificationusers==0) {
                    $certificationprogress = 0;
                } else {
                    $certificationprogress = round(($certificationusers_completed/$certificationusers)*100);
                }
                $onerow['certificateprogress'] = $certificationprogress;
                $onerow['certificate_url'] = $CFG->wwwroot.'/local/certification/view.php?ctid='.$inprogress_coursename->id;

                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition     
         else{
             $data->course_count_view =0;
         }


        $data->inprogress_elearning= json_encode($data->inprogress_elearning);
        $data->menu_heading = get_string('certificationtrainings','block_userdashboard');
        $data->nodata_string = get_string('nocertificationavailable','block_userdashboard');

       // print_object($data);
        return $data;
    } // end of export_for_template function     
  

    private function get_coursesummary($course_record){   

        $coursesummary = strip_tags($course_record->description);
        $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100)."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = '<span class="alert alert-info w-full pull-left text-center">'.get_string('nodecscriptionprovided','block_userdashboard').'</span>';
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
