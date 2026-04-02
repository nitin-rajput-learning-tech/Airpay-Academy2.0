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
namespace local_learningplan\output;

use renderer_base;
use context_course;
use stdClass;
use local_learningplan\local\userdashboard_content  as DashboardLearningplan;
use block_userdashboard\includes\generic_content;
use local_learningplan\lib\lib as learninngplan_lib;

class learningplan_courses implements \renderable, \templatable {

    //-----hold the courselist inprogress or completed
    private $courseslist;

    private $subtab='';

    private $learningplantemplate=0;

    private $filter_text='';

    private $filter='';

    public function __construct($filter, $filter_text='', $offset, $limit){
       $this->inprogressCount = DashboardLearningplan::inprogress_lepnames_count($filter_text);
       $this->completedCount = DashboardLearningplan::completed_lepnames_count($filter_text);
       $this->enrolledcount = DashboardLearningplan::enrolled_lepnames_count($filter_text);
        switch ($filter){
            case 'inprogress':
                    $this->courseslist = DashboardLearningplan::inprogress_lepnames($filter_text, $offset, $limit);
                    $this->coursesViewCount = $this->inprogressCount;
                    $this->subtab='elearning_inprogress';
                break;

            case 'completed' :
                    $this->courseslist = DashboardLearningplan::completed_lepnames($filter_text, $offset, $limit);
                    $this->coursesViewCount = $this->completedCount;
                    $this->subtab='elearning_completed';
                break;
            case 'enrolled' :
                    $this->courseslist =  DashboardLearningplan::enrolled_lepnames($filter_text, $filter_offset, $filter_limit);
                    $this->coursesViewCount = $this->enrolledcount;
                    $this->subtab = 'elearning_enrolled';
                break;

        }
        $this->filter = $filter;
        $this->filter_text = $filter_text;
        $this->learningplantemplate=1;

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

        $inprogresscount = $this->inprogressCount;
        $completedcount = $this->completedCount;
        $total = $inprogresscount+$completedcount;
        // $total = DashboardLearningplan::gettotal_programs();

        $data->course_count_view =0;
        $data->total =$total;
        $data->index = $this->coursesViewCount > 10 ? 9 : $this->coursesViewCount-1;
        $data->viewMoreCard = $this->coursesViewCount > 10 ? true : false;
        $data->filter = $this->filter;
        $data->inprogresscount= $this->inprogressCount;
        $data->completedcount = $this->completedCount;
        $data->functionname ='learningplan_courses';
        $data->subtab= $this->subtab;
        $data->view_more_url = $CFG->wwwroot.'/local/learningplan/userdashboard.php?tab='.explode('_',$this->subtab)[1];

        $data->enrolled_url = $CFG->wwwroot.'/local/learningplan/userdashboard.php?tab=enrolled';
        $data->inprogress_url = $CFG->wwwroot.'/local/learningplan/userdashboard.php?tab=inprogress';
        $data->completed_url = $CFG->wwwroot.'/local/learningplan/userdashboard.php?tab=completed';

        $data->learningplantemplate= $this->learningplantemplate;
        $data->filter_text = $this->filter_text;
        $plan_view_count = $this->coursesViewCount;
        $data->plan_view_count = $plan_view_count;
        if($plan_view_count > 2)
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
            foreach ($this->courseslist as $inprogress_coursename) {
                $onerow=array();
                $onerow['index'] = $i++;
                // $i++;
                $onerow['inprogress_coursename'] = $inprogress_coursename;
                $lastaccessstime= $DB->get_field('user_lastaccess', 'timeaccess', array('userid'=>$USER->id, 'courseid' => $inprogress_coursename->id));

                /* get the courses of learning path added by rizwana */
                $lplanassignedcourses = (new learninngplan_lib)->get_learningplan_assigned_courses($inprogress_coursename->id);
                $coursescount = count($lplanassignedcourses);
                 $onerow ['pathcourses'] = array();
                if(count($lplanassignedcourses)>=2) {
                    $i = 1;
                    foreach($lplanassignedcourses as $assignedcourse){
                            $onerow ['pathcourses'][] = array('coursename'=>$assignedcourse->fullname, 'coursename_string'=>'C'.$i);
                        $i++;

                        if($i>10){
                            break;
                        }
                    }

                }
                $onerow['lastaccessstime']= $lastaccessstime;
                 if($lastaccessstime){
                   $onerow['lastaccessdate'] = \local_costcenter\lib::get_userdate("d/m/Y H:i", $lastaccessstime);
                 }else{
                    $onerow['lastaccessdate'] = get_string('none');
                 }

                $course_record = $DB->get_record('course', array('id' => $inprogress_coursename->id));


                $onerow['plan_image_url'] = (new learninngplan_lib)->get_learningplansummaryfile($inprogress_coursename->id);

                //-------- get the course summary------------------------
                $description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($inprogress_coursename->description),array('overflowdiv' => false, 'noclean' => false, 'para' => false));
                $description_string = strlen($description) > 220 ? clean_text(substr($description, 0, 220))."..." : $description;
                $onerow['planSummary']= $description_string; //$this->get_coursesummary($inprogress_coursename);


                //---------get course fullname-----
                $onerow['planFullname'] = $inprogress_coursename->fullname;
                $onerow['displayPlanFullname'] = $this->get_coursefullname($inprogress_coursename);
                $onerow['planUrl'] = $CFG->wwwroot.'/local/learningplan/view.php?id='.$inprogress_coursename->id;
                if(class_exists('local_ratings\output\renderer')){
                    $rating_render = $PAGE->get_renderer('local_ratings');
                    $onerow['rating_element'] = $rating_render->render_ratings_data('local_learningplan', $inprogress_coursename->id);
                }else{
                    $onerow['rating_element'] = '';
                }
                $planpercent = $this->planpercent($inprogress_coursename->id,$USER->id);
                if($lastaccessstime){
                    $onerow['label_name'] = get_string('continue_plan','local_learningplan');
                }else{                    
                    $onerow['label_name'] = $planpercent < 100 ? get_string('start_plan','local_learningplan') : get_string('completed');
                }

                $completedon = $DB->get_field('local_learningplan_user', 'completiondate', array('planid'=> $inprogress_coursename->id, 'userid'=> $USER->id));

                if($completedon){
                    $onerow['course_completedon'] = date("d M Y", $completedon);
                }else{
                    $onerow['course_completedon'] = null;
                }                
                require_once($CFG->dirroot.'/local/ratings/lib.php');
                $display_ratings = get_rating($inprogress_coursename->id, 'local_learningplan');
                $onerow['ratingavg'] = $display_ratings->avg;
                $onerow['lpstatus'] = $planpercent < 100 ? get_string('inprogress') : get_string('completed');
                $onerow['completedpathpercent'] = $planpercent;             
                array_push($data->inprogress_elearning, $onerow);
            } // end of foreach
        } // end of if condition
        $data->moduledetails = $data->inprogress_elearning;
        $data->menu_heading = get_string('learningpaths','block_userdashboard');
        $data->nodata_string = get_string('nolearningplansavailable','block_userdashboard');
        return $data;
    } // end of export_for_template function.
    public static function planpercent($planid,$userid){
        global $DB,$USER;
        // $sql = "SELECT c.* FROM {local_learningplan_courses} as lpc inner join {course} as c on c.id= lpc.courseid where lpc.planid=:planid";
        // $params =array("planid"=>$planid);
        // $courses = $DB->get_records_sql($sql,$params);
        // $coursescount= count($courses);
        // // foreach($courses as $course){
        // //     $cinfo = new \completion_info($course);
        // //     $completetedcoursecount += $cinfo->is_course_complete($USER->id) ? 0 : 1; 
        // // }
        //     $lp_params = array();
        //     $coursesql = "SELECT  cc.id,cc.course FROM {course_completions} AS cc
        //                   JOIN {local_learningplan_courses} AS llc ON llc.courseid = cc.course
        //                   WHERE cc.userid = :userid AND llc.planid = :lplanid  AND cc.timecompleted IS NOT NULL";
        //     $lp_params['userid'] = $USER->id;
        //     $lp_params['lplanid'] = $planid;



            // $coursecompletions = $DB->get_records_sql_menu($coursesql,$lp_params);
            // $completetedcoursecount = count($coursecompletions);
            // $complete_percent = (($completetedcoursecount/$coursescount) * 100);
            // if($complete_percent > 0){
            //     return $complete_percent;
            // }else{
            //     return 0;
            // }
        // return $completetedcoursecount/$coursescount * 100;
        if ($planid) {
			$sql = "SELECT llc.courseid as id, llc.courseid 
						FROM {local_learningplan_courses} as llc 
						JOIN {course} as c ON llc.courseid = c.id
						JOIN {local_learningplan_user} as llu ON llc.planid = llu.planid 
						WHERE llc.planid=$planid AND llc.nextsetoperator='and' 
						AND llu.userid = $userid ";
			$courses = $DB->get_records_sql_menu($sql);
			$check = array();
			$completed = array();
			$optional_completed = array();
			if ($courses) {
				foreach ($courses as $course) {
					$sql = "SELECT id 
						FROM {course_completions} 
						WHERE course={$course} AND userid= $userid 
						AND timecompleted IS NOT NULL";
					$check = $DB->get_record_sql($sql);
					if ($check) {
						$completed['completed'][] = 1;
					} else {
						$completed['notcompleted'][] = 0;
					}
				}
			} else {
				$sql = "SELECT llc.courseid as id, llc.courseid 
						FROM {local_learningplan_courses} as llc 
						JOIN {course} as c ON llc.courseid = c.id
						JOIN {local_learningplan_user} as llu on llc.planid=llu.planid 
						WHERE llc.planid = $planid AND llu.userid=$userid ";
				$courses = $DB->get_records_sql_menu($sql);
				foreach ($courses as $course) {
					$sql = "SELECT id 
							FROM {course_completions} 
							WHERE course=:course AND userid=:user 
							AND timecompleted IS NOT NULL";
					$check = $DB->get_record_sql($sql, array('course' => (int)$course, 'user' => (int) $userid));
					if ($check) {
						$optional_completed['completed'][] = 1;
					} else {
						$optional_completed['notcompleted'][] = 0;
					}
				}
			}
            if ($completed) {
                $notcompletedcount = isset($completed['notcompleted']) ? count($completed['notcompleted']) : 0;
                 $completedcount = isset($completed['completed']) ? count($completed['completed']) : 0;
                 $totalcount = $notcompletedcount+$completedcount."count";
				if ($notcompletedcount  == 0) {
                    $percent = 100;
				}else{
                    $percent = $completedcount/$totalcount * 100;
                }
            } else
            if ($optional_completed) {
				if (isset($optional_completed['completed']) && in_array("1", $optional_completed['completed'])) {
                    $percent = 100;
                }else{
                    $percent = 0;
                }
            }
            return round($percent);
        }
    }

    private function get_coursesummary($course_record){
        $coursesummary = \local_costcenter\lib::strip_tags_custom($course_record->description);
        $summarystring = strlen($coursesummary) > 100 ? clean_text(substr($coursesummary, 0, 100))."..." : $coursesummary;
        $coursesummary = $summarystring;
        if(empty($coursesummary)){
            $coursesummary = get_string('nodecscriptionprovided','block_userdashboard');

        }

        return $coursesummary;
    } // end of get_coursesummary function

   private function get_coursefullname($inprogress_coursename){

        $course_fullname = $inprogress_coursename->fullname;
        if (strlen($course_fullname) >= 38) {
            $inprogress_coursename_fullname = clean_text(substr($inprogress_coursename->fullname, 0, 38)) . '...';
        } else {
            $inprogress_coursename_fullname = $inprogress_coursename->fullname;
        }

        return $inprogress_coursename_fullname;
    } // end of  get_coursesummary function



} // end of class
