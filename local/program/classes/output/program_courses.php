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
namespace local_program\output;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;
use local_program\local\userdashboard_content  as DashboardProgram;
use block_userdashboard\includes\generic_content;

class program_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $xseedtemplate=0;

    private $filter_text='';

    private $filter='';

    public function __construct($filter, $filter_text='', $offset, $limit){
        $this->inprogressCount = DashboardProgram::inprogress_programs_count($filter_text);
        $this->completedCount = DashboardProgram::completed_programs_count($filter_text);
        $this->enrolledcount = DashboardProgram::enrolled_programs_count($filter_text);
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = DashboardProgram::inprogress_programs($filter_text, $offset, $limit);
                    $this->coursesViewCount = $this->inprogressCount;
                    $this->subtab='elearning_inprogress'; 
                break;

            case 'completed' :                           
                    $this->courseslist = DashboardProgram::completed_programs($filter_text, $offset, $limit);
                    $this->coursesViewCount = $this->completedCount;
                    $this->subtab='elearning_completed';
                break;
            case 'enrolled' :                           
                    $this->courseslist = DashboardProgram::enrolled_programs($filter_text, $filter_offset, $filter_limit);
                    $this->coursesViewCount = $this->enrolledcount;
                    $this->subtab = 'elearning_enrolled';
                break;  
        }
        $this->filter = $filter;
        $this->offset = $offset;
        $this->limit = $limit;   
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
        // $inprogress_bootcamps = DashboardProgram::inprogress_bootcamps($this->filter_text);
        // $completed_bootcamps = DashboardProgram::completed_bootcamps($this->filter_text);
        $inprogresscount = $this->inprogressCount;
        $completedcount = $this->completedCount;
        $total = $inprogresscount+$completedcount;

        if ($courseslist == '') {
            $courseslist = null;
        }
        $data->course_count_view =0;
        $data->total =$total;
        $data->index = $this->coursesViewCount > 10 ? 9 : $this->coursesViewCount-1;
        $data->viewMoreCard = $this->coursesViewCount > 10 ? true : false;
        $data->filter = $this->filter;
        $data->filter_text = $this->filter_text;
        $data->inprogresscount= $this->inprogressCount;
        //$data->completedcount = count($completed_bootcamps);
        $data->completedcount = $this->completedCount;
        $data->functionname ='xseed';
        $data->subtab= $this->subtab;
        $data->xseedtemplate= $this->xseedtemplate;
        $data->view_more_url = $CFG->wwwroot.'/local/program/userdashboard.php?tab='.explode('_',$this->subtab)[1];
        
        $data->enrolled_url = $CFG->wwwroot.'/local/program/userdashboard.php?tab=enrolled';
        $data->inprogress_url = $CFG->wwwroot.'/local/program/userdashboard.php?tab=inprogress';
        $data->completed_url = $CFG->wwwroot.'/local/program/userdashboard.php?tab=completed';

        $program_view_count = count($this->courseslist);
        $data->program_view_count = $program_view_count;
        if($program_view_count > 2)
            $data->enableslider = 1;
        else    
            $data->enableslider = 0;
        
        if (!empty($this->courseslist)) 
            $data->inprogress_elearning_available = 1;
        else
            $data->inprogress_elearning_available = 0;

        
        if (!empty($this->courseslist)) {

            $data->course_count_view = generic_content::get_coursecount_class($this->courseslist);
            $i = 0;
            foreach ($this->courseslist as $inprogress_bootcamps) {
                $onerow=array();

                //-------- get the Program summary------------------------ 
                $onerow['ProgramDescription']= $this->get_summary($inprogress_bootcamps);
                $onerow['index'] = $i++;
                // $i++;
                //---------get bootcamp fullname-----
                $onerow['ProgramFullname'] = $inprogress_bootcamps->fullname;
                $onerow['DisplayProgramFullname'] = $this->get_fullname($inprogress_bootcamps);
                $onerow['ProgramUrl'] = $CFG->wwwroot.'/local/program/view.php?bcid='.$inprogress_bootcamps->id;
                $onerow['ProgramIcon'] = $CFG->wwwroot.'/theme/epsilon/pix/scheme_icons/bcschemefff.png';
                $bootcamps_completed= $completedcount;
                if(class_exists('local_ratings\output\renderer')){
                    $rating_render = $PAGE->get_renderer('local_ratings');
                    $onerow['rating_element'] = $rating_render->render_ratings_data('local_program', $inprogress_bootcamps->id);
                }else{
                    $onerow['rating_element'] = '';
                }
                require_once($CFG->dirroot.'/local/ratings/lib.php');
                $crratings = get_rating($inprogress_bootcamps->id, 'local_program');
                $onerow['ratingavg'] = $crratings->avg;
                if($DB->record_exists('local_program_completion', array("programid"=>$inprogress_bootcamps->id))){
                    $onerow['statusname'] = get_string('completed','local_program');
                }else{
                    $onerow['statusname'] =  get_string('launch','block_userdashboard');
                }
                array_push($data->inprogress_elearning, $onerow);
                
            } // end of foreach 

        } // end of if condition   
        $data->moduledetails= $data->inprogress_elearning;
        $data->menu_heading = get_string('bootcamps','block_userdashboard');
        $data->nodata_string = get_string('noprogramsavailable','block_userdashboard');
        
        return $data;  
    }


    private function get_summary($course_record){   

        $coursesummary = \local_costcenter\lib::strip_tags_custom($course_record->description);
        $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100)."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = '<span class="w-full pull-left">'.get_string('nodecscriptionprovided','block_userdashboard').'</span>';
        }

        return $coursesummary;
    } // end of function


    private function get_fullname($inprogress_coursename){

        $course_fullname = $inprogress_coursename->fullname;
        if (strlen($course_fullname) >= 22) {
            $inprogress_coursename_fullname = substr($inprogress_coursename->fullname, 0, 22) . '...';
        } else {
            $inprogress_coursename_fullname = $inprogress_coursename->fullname;
        }

        return $inprogress_coursename_fullname;
    } // end of function



} // end of class
