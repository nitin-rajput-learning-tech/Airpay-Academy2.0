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
namespace local_evaluation\output;
//require_once $CFG->dirroot . '/local/evaluation/lib.php';
// use renderable;
use renderer_base;
// use templatable;
use context_course;
use html_writer;
use stdClass;
use moodle_url;
use local_evaluation\local\userdashboard_content  as DashboardEvaluation;
use block_userdashboard\includes\generic_content;

class evaluation_courses implements \renderable, \templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $evaluationtemplate=0;

    private $filter_text='';

    private $filter='';


    public function __construct($filter, $filter_text='', $offset, $limit){
       $this->inprogressCount = DashboardEvaluation::inprogress_evaluations_count($filter_text);
       $this->completedCount = DashboardEvaluation::completed_evaluations_count($filter_text);
       $this->enrolledCount = DashboardEvaluation::enrolled_evaluations_count($filter_text);
      
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = DashboardEvaluation::inprogress_evaluations($filter_text, $offset, $limit);
                    $this->coursesViewCount = $this->inprogressCount;
                    $this->subtab='elearning_inprogress'; 
                break;

            case 'completed' :                           
                    $this->courseslist = DashboardEvaluation::completed_evaluations($filter_text, $offset, $limit);
                    $this->coursesViewCount = $this->completedCount;
                    $this->subtab='elearning_completed';
                break; 
             case 'enrolled' :                           
                    $this->courseslist = DashboardEvaluation::enrolled_evaluations($filter_text, $filter_offset, $filter_limit);
                    $this->coursesViewCount = $this->enrolledCount;
                    $this->subtab = 'elearning_enrolled';
                break; 
            
        }  
        $this->filter = $filter;
        $this->offset = $offset;
        $this->limit = $limit;
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
        $inprogresscount = $this->inprogressCount;
        $completedcount = $this->completedCount;
        $total = $inprogresscount+$completedcount;
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
        $data->index = $this->coursesViewCount > 10 ? 9 : $this->coursesViewCount - 1;
        $data->viewMoreCard = $this->coursesViewCount > 10 ? true : false;
        $data->inprogresscount= $this->inprogressCount;
        $data->completedcount = $this->completedCount;
        $data->functionname ='evaluation_courses';
        $data->subtab= $this->subtab;
        $data->filter = $this->filter; 
        $data->filter_text = $this->filter_text;
        $courses_view_count = $this->coursesViewCount;
        $data->evaluationtemplate= $this->evaluationtemplate;
        $data->view_more_url = $CFG->wwwroot.'/local/evaluation/userdashboard.php?tab='.explode('_',$this->subtab)[1];
        $data->enrolled_url = $CFG->wwwroot.'/local/evaluation/userdashboard.php?tab=enrolled';
        $data->inprogress_url = $CFG->wwwroot.'/local/evaluation/userdashboard.php?tab=inprogress';
        $data->completed_url = $CFG->wwwroot.'/local/evaluation/userdashboard.php?tab=completed';

        $data->courses_view_count = $courses_view_count;
        if($courses_view_count > 2)
            $data->enableslider = 1;
        else    
            $data->enableslider = 0;
        if($this->courseslist > 0){
        $courses_view_count = count($this->courseslist);
    }
        $data->courses_view_count = $courses_view_count;
        if (!empty($this->courseslist)) 
            $data->inprogress_elearning_available = 1;
        else
            $data->inprogress_elearning_available = 0;
        
       
         if (!empty($this->courseslist)) {

            $data->course_count_view = generic_content::get_coursecount_class($this->courseslist);

                $i = 0;
                foreach ($this->courseslist as $inprogress_coursename) {
                    $onerow=array();
                    $onerow['index'] = $i++;
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
                        $dates = 'From '. \local_costcenter\lib::get_userdate("d/m/Y ", $inprogress_coursename->timeopen);
                    } elseif (empty($inprogress_coursename->timeopen) AND !empty($inprogress_coursename->timeclose)) {
                        $dates = 'Ends on '. \local_costcenter\lib::get_userdate("d/m/Y ", $inprogress_coursename->timeclose);
                    } else {
                        $Yettostart = false;
                        if($inprogress_coursename->timeopen > $time){
                            $Yettostart = true;
                        }
                        $dates = \local_costcenter\lib::get_userdate("d/m/Y H:i", $inprogress_coursename->timeopen).  ' - '  . \local_costcenter\lib::get_userdate("d/m/Y H:i", $inprogress_coursename->timeclose);
                    }

                    $compeltionrecord = $DB->get_record('local_evaluation_completed', array('evaluation'=>$inprogress_coursename->id, 'userid'=>$USER->id));
                    if ($compeltionrecord) {
                        $completedon = \local_costcenter\lib::get_userdate("d/m/Y H:i", $compeltionrecord->timemodified);
                    } else {
                        $completedon = '-';
                    }
                    $evaltype = ($inprogress_coursename->type == 1)? get_string('feedback', 'local_evaluation'):get_string('survey', 'local_evaluation');
                    $eval_name = $inprogress_coursename->name;
                    $evalname = strlen($eval_name) > 32 ? clean_text(substr($eval_name, 0, 32))."..." : $eval_name;
                    $enrolledon = \local_costcenter\lib::get_userdate("d/m/Y H:i", $inprogress_coursename->joinedate);
                    $onerow['eval_name'] = $eval_name;
                    $onerow['name'] = $evalname;
                    $onerow['dates'] = $dates;
                    $onerow['type'] = $evaltype;
                    $onerow['enrolledon'] = $enrolledon;
                    $onerow['completedon'] = $completedon;
                    $onerow['actions'] = $buttons;
                    $onerow['Yettostart'] = $Yettostart;
                    $onerow['evaluation_url'] = $CFG->wwwroot.'/local/evaluation/complete.php?id='.$inprogress_coursename->id.'&sesskey='. sesskey();
                    
                   array_push($data->inprogress_elearning, $onerow);
                }  // end of foreach 
            } // end of if condition   

        $data->moduledetails = $data->inprogress_elearning;
        $data->menu_heading = get_string('feedbacks','block_userdashboard');
        $data->nodata_string = get_string('noevaluationsavailable','block_userdashboard');

        return $data;
    } // end of export_for_template function


} // end of class
