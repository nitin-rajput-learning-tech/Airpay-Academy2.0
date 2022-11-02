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
require_once $CFG->dirroot . '/local/onlinetests/lib.php';
use renderable;
use renderer_base;
use templatable;
use context_course;
use html_writer;
use stdClass;
use moodle_url;
use block_userdashboard\lib\onlinetests  as onlinetests_lib;
use block_userdashboard\includes\generic_content;

class onlinetests_courses implements renderable, templatable {

    //-----hold the courselist inprogress or completed 
    private $courseslist;

    private $subtab='';

    private $onlineteststemplate=0;

    private $filter_text='';

    private $filter='';


    public function __construct($filter, $filter_text=''){
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = onlinetests_lib::inprogress_onlinetests($filter_text);
                    $this->subtab='elearning_inprogress'; 
                break;

            case 'completed' :                           
                    $this->courseslist = onlinetests_lib::completed_onlinetests($filter_text);
                    $this->subtab='elearning_completed';
                break; 
            
        }  
        $this->filter = $filter;  
        $this->filter_text = $filter_text;
        $this->onlineteststemplate=1;

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
        $can_review = 0;
        $courses_active = ''; 
        $tabs = '';  
        $data->inprogress_elearning = array();    
        $inprogress_programs = onlinetests_lib::inprogress_onlinetests($this->filter_text);
        $completed_onlinetests = onlinetests_lib::completed_onlinetests($this->filter_text);
        $inprogresscount = count($this->courseslist);
        $completedcount = count($completed_onlinetests);
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
        $data->completedcount = count($completed_onlinetests);
        $data->functionname ='onlinetests_courses';
        $data->subtab= $this->subtab;
        $data->filter = $this->filter; 
        $data->filter_text = $this->filter_text;
        $data->onlineteststemplate= $this->onlineteststemplate;
        $courses_view_count = count($this->courseslist);
        $data->view_more_url = $CFG->wwwroot.'/blocks/userdashboard/userdashboard_courses.php?tab=onlinetests&subtab='.explode('_',$this->subtab)[1];
        $data->courses_view_count = $courses_view_count;
        if($courses_view_count > 2)
            $data->enableslider = 1;
        else    
            $data->enableslider = 0;

        if (!empty($this->courseslist)) 
            $data->inprogress_elearning_available = 1;
        else
            $data->inprogress_elearning_available = 0;

        // if($this->subtab == 'inprogress_onlinetests')
        //     $data->sub_tab = 0;
        // else if($this->subtab == 'completed_onlinetests')
        //     $data->sub_tab = 1; 
        // $data->table_class = ''; 
         if (!empty($this->courseslist)) {
            $data->course_count_view = generic_content::get_coursecount_class($this->courseslist);
            foreach ($this->courseslist as $inprogress_coursename) {
                $onerow = array();

                $cm = get_coursemodule_from_instance('quiz', $inprogress_coursename->quizid, 0, false, MUST_EXIST);
                $gradeitem = $DB->get_record('grade_items', array('iteminstance'=>$inprogress_coursename->quizid, 'itemmodule'=>'quiz', 'courseid'=>1));
                $sql="SELECT * 
                    FROM {quiz_attempts} 
                    WHERE id= (SELECT max(id) id 
                                from {quiz_attempts} 
                                where userid={$USER->id} and quiz={$inprogress_coursename->quizid})";
                $userattempt = $DB->get_record_sql($sql);
                $attempts = ($userattempt->attempt) ? $userattempt->attempt : 0;
                $grademax = ($gradeitem->grademax) ? round($gradeitem->grademax): '-';
                $gradepass = ($gradeitem->gradepass) ? round($gradeitem->gradepass): '-';
                $userquizrecord = $DB->get_record_sql("SELECT * FROM {local_onlinetest_users} where onlinetestid={$inprogress_coursename->id} AND userid = {$USER->id}");
                $enrolledon = date("j M 'Y", $userquizrecord->timecreated);
                if ($gradeitem->id)
                    $usergrade = $DB->get_record_sql("SELECT * 
                                                    FROM {grade_grades} 
                                                    WHERE itemid = {$gradeitem->id} AND userid = {$USER->id}");
                if ($usergrade) {
                    $mygrade = round($usergrade->finalgrade, 2);
                    if ($usergrade->finalgrade >= $gradepass) {
                        $completedon = date("j M 'Y", $usergrade->timemodified);
                        $status = get_string('completed','block_userdashboard');
                        $can_review = 1;
                        // if ($this->subtab == 'elearning_inprogress') // incomplete
                        //  continue;
                    } else {
                        $status = get_string('incomplete','block_userdashboard');
                        $completedon = '-';
                        // if ($this->subtab == 'elearning_completed') // complete
                        //  continue;
                    }
                    
                } else {
                    // if ($this->subtab == 'elearning_inprogress') // incomplete
                    //      continue;
                    $mygrade = '-';
                    $status = get_string('pending','block_userdashboard');
                    $completedon = '-';
                    $attempts = 0;
                }
                if($inprogress_coursename->timeopen == 0 AND $inprogress_coursename->timeclose == 0) {
                    $dates= get_string('open', 'local_onlinetests');
                } elseif(!empty($inprogress_coursename->timeopen) AND empty($inprogress_coursename->timeclose)) {
                    $dates = 'From '.date("j M 'Y", $inprogress_coursename->timeopen);
                } elseif (empty($inprogress_coursename->timeopen) AND !empty($inprogress_coursename->timeclose)) {
                    $dates = 'Ends on '. date("j M 'Y", $inprogress_coursename->timeclose);
                } else {
                    $dates = date("j M 'Y", $inprogress_coursename->timeopen).  ' - '  .date("j M 'Y", $inprogress_coursename->timeclose);
                }
                $testid = $inprogress_coursename->id;
                $testfullname = $inprogress_coursename->name;
                $testname = strlen($testfullname) > 35 ? substr($testfullname, 0, 35)."..." : $testfullname;
                if(!is_siteadmin()){
                    $switchedrole = $USER->access['rsw']['/1'];
                    if($switchedrole){
                        $userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
                    }else{
                        $userrole = null;
                    }
//            if(is_null($userrole) || $userrole == 'user'){
             if(is_null($userrole) || $userrole == 'employee'){
                        $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');
                        if($certificate_plugin_exist){
                            if(!empty($inprogress_coursename->certificateid)){
                                $certificate_exists = true;
                                     $usergrade = $DB->get_record_sql("select * from {grade_grades} where itemid = ? AND userid = ? ", [$gradeitem->id, $USER->id]);
                if($usergrade) {
                $mygrade = round($usergrade->finalgrade, 2);
                if ($usergrade->finalgrade >= $gradepass) {
                    $can_review = 1;
                    $status =  get_string('completed','block_userdashboard');
                    $certificate_download= true;
                } else {
                    $status = get_string('incomplete','block_userdashboard');
                    $certificate_download = false;
                }                   
            }
                            $certificateid = $inprogress_coursename->certificateid;
                            }
                        }
                    }
                }
                $onerow['id'] = $testid;
                $onerow['name'] = $testname;
                $onerow['testfullname'] = $testfullname;
                $onerow['maxgrade'] = $grademax;
                $onerow['passgrade'] = $gradepass;
                $onerow['mygrade'] = $mygrade;
                $onerow['attempts'] = $attempts;
                $onerow['enrolledon'] = $enrolledon;
                $onerow['completedon'] = $completedon;
                $onerow['status'] = $status;
                $onerow['canreview'] = $can_review;
                $onerow['dates'] = $dates;
                $onerow['userattemptid'] = $userattempt->id;
                $onerow['sesskey'] = sesskey();
                $onerow['configpath'] = $CFG->wwwroot;
                $onerow['certificate_exists'] = $certificate_exists;
                $onerow['certificate_download'] = $certificate_download;
                $onerow['certificateid'] = $certificateid;
                $onerow['testid'] = $testid;
                if($inprogress_coursename->timeclose < time() && $inprogress_coursename->timeclose > 0){
                    $onerow['starttest_url'] = 'javascript:void(0)';
                    $onerow['can_start_test'] = False;
                }else{
                    $onerow['starttest_url'] = $CFG->wwwroot .'/mod/quiz/view.php?id='. $cm->id .'';
                    $onerow['can_start_test'] = True;
                }
                array_push($data->inprogress_elearning, $onerow);  
            }
        }
        $data->inprogress_elearning = json_encode($data->inprogress_elearning);
        $data->menu_heading = get_string('onlineexams','block_userdashboard');
        $data->nodata_string = get_string('noonlinetestsavailable','block_userdashboard');
        return $data;
    } // end of export_for_template function
} // end of class
