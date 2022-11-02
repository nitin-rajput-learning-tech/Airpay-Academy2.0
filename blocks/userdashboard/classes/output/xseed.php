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
use block_userdashboard\lib\bootcamp  as bootcampslib;
use block_userdashboard\includes\generic_content;

class xseed implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $xseedtemplate=0;

    private $filter_text='';

    private $filter='';

    public function __construct($filter, $filter_text=''){
       
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = bootcampslib::inprogress_bootcamps($filter_text);
                    $this->subtab='elearning_inprogress'; 
                break;

            case 'completed' :                           
                    $this->courseslist = bootcampslib::completed_bootcamps($filter_text);
                    $this->subtab='elearning_completed';
                break; 
        }
        $this->filter = $filter;  
        $this->filter_text = $filter_text;
        $this->xseedtemplate=1;

    } // end of the function
    

    


    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER, $CFG, $PAGE;
       
        $data = new stdClass(); 
        $courses_active = ''; 
        $tabs = '';  
        $data->inprogress_elearning = array();    
        $inprogress_bootcamps = bootcampslib::inprogress_bootcamps($this->filter_text);
        $completed_bootcamps = bootcampslib::completed_bootcamps($this->filter_text);
        $inprogresscount = count($this->courseslist);
        $completedcount = count($completed_bootcamps);
        $total = $inprogresscount+$completedcount;

        if ($courseslist == '') {
            $courseslist = null;
        }

        if ($inprogress_bootcamps == '') {
            $inprogress_bootcamps = null;
        }

        if ($completed_bootcamps == '') {
            $completed_bootcamps = null;
        }
        $data->course_count_view =0;
        $data->total =$total;
        $data->index = count($this->courseslist)-1;
        $data->filter = $this->filter;
        $data->filter_text = $this->filter_text;
        $data->inprogresscount= count($this->courseslist);
        $data->completedcount = count($completed_bootcamps);
        $data->functionname ='xseed';
        $data->subtab= $this->subtab;
        $data->xseedtemplate= $this->xseedtemplate;
        $data->view_more_url = $CFG->wwwroot.'/blocks/userdashboard/userdashboard_courses.php?tab=xseed&subtab=inprogress';
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

            foreach ($this->courseslist as $inprogress_bootcamps) {
                $onerow=array();

                //-------- get the bootcamp summary------------------------ 
                $onerow['bootcampdescription']= $this->get_coursesummary($inprogress_bootcamps);

                //---------get bootcamp fullname-----
                $onerow['bootcamp_fullname'] = $inprogress_bootcamps->fullname;
                $onerow['inprogress_bootcamp_fullname'] = $this->get_coursefullname($inprogress_bootcamps);
                $onerow['bootcamp_url'] = $CFG->wwwroot.'/local/program/view.php?bcid='.$inprogress_bootcamps->id;
                $onerow['bootcampicon'] = $CFG->wwwroot.'/theme/epsilon/pix/scheme_icons/bcschemefff.png';
                $bootcamps_completed= $completedcount;
                if(class_exists('local_ratings\output\renderer')){
                    $rating_render = $PAGE->get_renderer('local_ratings');
                    $onerow['rating_element'] = $rating_render->render_ratings_data('local_program', $inprogress_bootcamps->id);
                }else{
                    $onerow['rating_element'] = '';
                }
                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition   
        $data->inprogress_elearning= json_encode($data->inprogress_elearning);
        $data->menu_heading = get_string('bootcamps','block_userdashboard');
        $data->nodata_string = get_string('noprogramsavailable','block_userdashboard');
        
        return $data;  
    }


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
