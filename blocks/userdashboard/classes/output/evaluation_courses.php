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
//require_once $CFG->dirroot . '/local/evaluation/lib.php';
use renderable;
use renderer_base;
use templatable;
use context_course;
use html_writer;
use stdClass;
use moodle_url;
use block_userdashboard\lib\evaluations  as evaluations_lib;
use block_userdashboard\includes\generic_content;

class evaluation_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $evaluationtemplate=0;

    private $filter_text='';

    private $filter='';


    public function __construct($filter, $filter_text=''){
       global $USER;
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = evaluations_lib::inprogress_evaluations($filter_text);
                    $this->subtab='elearning_inprogress'; 
                break;

            case 'completed' :                           
                    $this->courseslist = evaluations_lib::completed_evaluations($filter_text);
                    $this->subtab='elearning_completed';
                break; 
            
        }  
        $this->filter = $filter;  
        $this->filter_text = $filter_text;
        $this->evaluationtemplate=1;

    } // end of the function
    

    


    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER, $CFG, $OUTPUT;
       
        $data = new stdClass(); 
        $courses_active = ''; 
        $tabs = '';  
        $data->inprogress_elearning = array();    
        $inprogress_programs = evaluations_lib::inprogress_evaluations($this->filter_text);
        $completed_programs = evaluations_lib::completed_evaluations($this->filter_text);
        $inprogresscount = count($this->courseslist);
        $completedcount = count($completed_programs);
        $total = $inprogresscount+$completedcount;
        // $total = courseslist_lib::gettotal_programs();
         if ($courseslist == '') {
            $courseslist = null;
        }

        if ($inprogress_learningplan == '') {
            $inprogress_learningplan = null;
        }

        if ($completed_learningplan == '') {
            $completed_learningplan = null;
        }
        
        $data->course_count_view =0;
        $data->total =$total;
        $data->index = count($this->courseslist)-1;
        $data->inprogresscount= count($this->courseslist);
        $data->completedcount = count($completed_programs);
        $data->functionname ='evaluation_courses';
        $data->subtab= $this->subtab;
        $data->filter = $this->filter; 
        $data->filter_text = $this->filter_text;
        $courses_view_count = count($this->courseslist);
        //$data->sub_tab= $this->subtab;

        $data->evaluationtemplate= $this->evaluationtemplate;
        $data->view_more_url = $CFG->wwwroot.'/blocks/userdashboard/userdashboard_courses.php?tab=feedbacks&subtab='.explode('_',$this->subtab)[1];
        $data->courses_view_count = $courses_view_count;
        if($courses_view_count > 2)
            $data->enableslider = 1;
        else    
            $data->enableslider = 0;

        $courses_view_count = count($this->courseslist);
        $data->courses_view_count = $courses_view_count;
        if (!empty($this->courseslist)) 
            $data->inprogress_elearning_available = 1;
        else
            $data->inprogress_elearning_available = 0;
        
       
         if (!empty($this->courseslist)) {

            $data->course_count_view = generic_content::get_coursecount_class($this->courseslist);

                //$data->table_class = 'inprogress';
                foreach ($this->courseslist as $inprogress_coursename) {
                    $onerow=array();
                    $time = time();
                    $buttons = array();
                    $showcompleted = $DB->get_field('local_evaluation_completed', 'id', array('userid'=>$USER->id, 'evaluation'=>$inprogress_coursename->id));
                    $time = time();
                    if ($inprogress_coursename->timeclose !=0 AND $time >= $inprogress_coursename->timeclose)
                    $buttons[] = '';
                    elseif ($inprogress_coursename->timeopen !=0 AND $time <= $inprogress_coursename->timeopen)
                    $buttons[] = '';
                    elseif ($showcompleted AND $inprogress_coursename->multiple_submit == 0 )
                    $buttons[] = '';
                    else
                    $buttons[] = html_writer::link(new moodle_url('/local/evaluation/complete.php', array('id' => $inprogress_coursename->id, 'sesskey' => sesskey())), get_string('submit', 'local_evaluation'));
                    // $OUTPUT->pix_icon('t/go', get_string('answerquestions', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => ''))
                    if ($showcompleted)
                    $buttons[] = html_writer::link(new moodle_url('/local/evaluation/show_entries.php', array('id' => $inprogress_coursename->id,'userid'=>$USER->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/preview', get_string('responses', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')));
            
                    $buttons = implode('',$buttons);
                     if($inprogress_coursename->timeopen==0 AND $inprogress_coursename->timeclose==0) {
                        $dates= get_string('open', 'local_evaluation');
                    } elseif(!empty($inprogress_coursename->timeopen) AND empty($inprogress_coursename->timeclose)) {
                        $dates = 'From '.date("j M 'Y", $inprogress_coursename->timeopen);
                    } elseif (empty($inprogress_coursename->timeopen) AND !empty($inprogress_coursename->timeclose)) {
                        $dates = 'Ends on '. date("j M 'Y", $inprogress_coursename->timeclose);
                    } else {
                        $Yettostart = false;
                        if($inprogress_coursename->timeopen > $time){
                            $Yettostart = true;
                        }
                        $dates = date("j M 'Y", $inprogress_coursename->timeopen).  ' - '  .date("j M 'Y", $inprogress_coursename->timeclose);
                    }

                    $compeltionrecord = $DB->get_record('local_evaluation_completed', array('evaluation'=>$inprogress_coursename->id, 'userid'=>$USER->id));
                    if ($compeltionrecord) {
                        // if ($this->subtab == 'elearning_inprogress')
                        //     continue;
                        $completedon = date("j M 'Y", $compeltionrecord->timemodified);
                    } else {
                        // if ($this->subtab == 'elearning_completed')
                        //     continue;
                        $completedon = '-';
                    }
                    $evaltype = ($inprogress_coursename->type == 1)? get_string('feedback', 'local_evaluation'):get_string('survey', 'local_evaluation');
                    $eval_name = $inprogress_coursename->name;
                    $evalname = strlen($eval_name) > 32 ? substr($eval_name, 0, 32)."..." : $eval_name;
                    $onerow['inprogress_coursename'] = $inprogress_coursename;
                    $enrolledon = date("j M 'Y", $inprogress_coursename->joinedate);
                    $onerow['eval_name'] = $eval_name;
                    $onerow['name'] = $evalname;
                    $onerow['dates'] = $dates;
                    $onerow['type'] = $evaltype;
                     $onerow['enrolledon'] = $enrolledon;
                     $onerow['completedon'] = $completedon;
                    $onerow['actions'] = $buttons;
                    $onerow['Yettostart'] = $Yettostart;
                    array_push($data->inprogress_elearning, $onerow);
                    
                }  // end of foreach 
            } // end of if condition   

        $data->inprogress_elearning= json_encode($data->inprogress_elearning);
        $data->menu_heading = get_string('feedbacks','block_userdashboard');
        $data->nodata_string = get_string('noevaluationsavailable','block_userdashboard');

        return $data;
    } // end of export_for_template function


} // end of class
