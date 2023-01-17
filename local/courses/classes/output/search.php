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
 * Class containing data for search
 *
 * @package    local_courses
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_courses\output;
require_once($CFG->dirroot.'/local/courses/lib.php');
require_once($CFG->dirroot.'/local/search/lib.php');
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use context_user;
use html_writer;
use core_component;
use local_search\output\searchlib;
use local_request\api\requestapi;
use core_completion\progress;

/**
 * Class containing data for search
 *
 * @copyright  2019 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search implements renderable{

    public function get_elearning_courselist_query($perpage, $startlimit, $return_noofrecords=false,$returnobjectlist=false, $filters = array()){

        global $DB,$USER,$COURSE,$CFG;

        $search = searchlib::$search;
        $selectsql = " SELECT c.* ";
        $fromsql = " FROM {course} c ";
        $leftjoinsql = '';

       // added condition for not displaying retired courses except exams.
        $wheresql = " WHERE c.id > 1 AND c.selfenrol = 1 ";

        $categorycontext = \local_costcenter\lib\accesslib::get_module_context();
        $params = [];
        if(!is_siteadmin()){

            $usercostcenterpaths = $DB->get_records('local_userdata', array('userid' => $USER->id));
            $paths = [];
            foreach($usercostcenterpaths AS $userpath){
                $userpathinfo = $userpath->costcenterpath;
                $paths[] = $userpathinfo.'%';
                while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {
                    $userpathinfo = rtrim($userpathinfo, '/');
                    if ($userpathinfo === '') {
                      break;
                    }
                    $paths[] = $userpathinfo;
                }
            }
            if(!empty($paths)){
                foreach($paths AS $path){
                    $pathsql[] = " c.open_path LIKE '{$path}' ";
                }
                $wheresql .= " AND ( ".implode(' OR ', $pathsql).' ) ';
            }
        }
        foreach($filters AS $filtertype => $filtervalues){
            switch($filtertype){
                case 'status':
                    $statussql = [];
                    foreach($filters['status'] AS $statusfilter){
                        switch ($statusfilter) {
                            case 'notenrolled':
                                $statussql[] = " c.id not in (SELECT e.courseid FROM {enrol} AS e JOIN {user_enrolments} AS ue on ue.enrolid = e.id AND ue.status = 0 where ue.userid = {$USER->id} AND e.status = 0) ";
                            break;
                            case 'inprogress':
                                $statussql[] = " c.id in (SELECT e.courseid FROM {enrol} AS e JOIN {user_enrolments} AS ue on ue.enrolid = e.id AND ue.status = 0 where ue.userid = {$USER->id} AND e.status = 0) AND c.id NOT in (SELECT cc.course FROM {course_completions} AS cc where cc.userid = {$USER->id} AND cc.timecompleted IS NOT NULL) ";
                            break;
                            case 'completed':
                                $statussql[] = " c.id in (SELECT cc.course FROM {course_completions} AS cc where cc.userid = {$USER->id} AND cc.timecompleted IS NOT NULL) ";
                            break;
                        }
                    }
                    if(!empty($statussql)){
                        $wheresql .= " AND (".implode('OR', $statussql).' ) ';
                    }
                break;
                case 'learningtype':
                    $learningtypes = is_array($filtervalues) ? $filtervalues : [$filtervalues];
                    list($learningtypesql, $learningtypeparams) = $DB->get_in_or_equal($learningtypes, SQL_PARAMS_NAMED, 'learningtype');
                    $wheresql .= " AND c.open_identifiedas $learningtypesql ";
                    $params = array_merge($params, $learningtypeparams);
                break;
                case 'categories':
                    $categories = is_array($filtervalues) ? $filtervalues : [$filtervalues];
                    list($categoriessql, $categoriesparams) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'categories');
                    $wheresql .= " AND c.open_categoryid $categoriessql ";
                    $params = array_merge($params, $categoriesparams);
                break;
                case 'level':
                    $level = is_array($filtervalues) ? $filtervalues : [$filtervalues];
                    list($levelsql, $levelparams) = $DB->get_in_or_equal($level, SQL_PARAMS_NAMED, 'level');
                    $wheresql .= " AND c.open_level $levelsql ";
                    $params = array_merge($params, $levelparams);
                break;
                case 'skill':
                    $skill = is_array($filtervalues) ? $filtervalues : [$filtervalues];
                    list($skillsql, $skillparams) = $DB->get_in_or_equal($skill, SQL_PARAMS_NAMED, 'skill');
                    $wheresql .= " AND c.open_skill $skillsql ";
                    $params = array_merge($params, $skillparams);
                break;
            }
        }
        $course_searchsql = "";
        if(searchlib::$search && searchlib::$search!='null'){
            $course_searchsql = " AND c.fullname LIKE '%$search%'";
        }
        if($coursetype){
            $types = implode(',',array_filter($coursetype,'is_numeric'));
            $wheresql .= " AND c.open_identifiedas IN ($types) ";
        }

        $wheresql .= " AND c.visible = 1 ";


        $groupby = " GROUP BY c.id ";

        $countsql = "SELECT c.id ";
        $finalcountquery = $countsql.$fromsql.$wheresql.$course_searchsql.$groupby;
        $numberofrecords = 0;
        if($return_noofrecords)
            $numberofrecords = sizeof($DB->get_records_sql($finalcountquery, $params));

        $finalsql = $selectsql.$fromsql.$wheresql.$course_searchsql.$groupby;
        // var_dump($finalsql);
        $finalsql .= "  ORDER by c.id DESC";

        $courseslist = $DB->get_records_sql($finalsql, $params, $startlimit, $perpage);

        if($return_noofrecords && !$returnobjectlist){
            $return = array('numberofrecords'=>$numberofrecords);
        }else if($returnobjectlist && !$return_noofrecords){
            $return =  array('list'=>$courseslist);
        }else{
            if($return_noofrecords && $returnobjectlist){
                $return =  array('numberofrecords'=>$numberofrecords,'list'=>$courseslist);
            }
        }

       return $return;
    } // end of get_elearning_courselist_query



   public function export_for_template($perpage,$startlimit,$selectedfilter = array()){
        global $DB, $USER,$CFG, $OUTPUT,$PAGE;
        $context = \local_costcenter\lib\accesslib::get_module_context();
        $courseslist_ar = $this->get_elearning_courselist_query($perpage,$startlimit, true, true, $selectedfilter);

        $courseslist=$courseslist_ar['list'];

        $finalresponse= array();
        $statuslist = array(1=>'Announced',
                                2=>'Active',3=>'Beta',4=>'Retired');

        foreach ($courseslist as $course) {
            $grid="";
            $course->statusstring = $statuslist[$course->open_status];
            $course_category = $DB->get_field('course_categories','name',array('id' => $course->category));
            $course->bannerimage = searchlib::convert_urlobject_intoplainurl($course);
            if(is_enrolled(context_course::instance($course->id))){
                if(file_exists($CFG->dirroot.'/local/includes.php')){
                    require_once($CFG->dirroot.'/local/includes.php');
                    $completion = new \completion_info($course);
                    if($completion->is_enabled()){
                        $progressbarpercent = progress::get_course_progress_percentage($course, $USER->id);
                    }
                }
                if(empty($progressbarpercent)){
                    $course->progressbarpercent = 0;
                    $course->progressbarpercent_width = 0;
                }else{
                    $course->progressbarpercent = floor($progressbarpercent);
                    $course->progressbarpercent_width = 1;
                }
            }else{
                $course->progressbarpercent = 0;
                $course->progressbarpercent_width = 0;
            }
            $course->id = $course->id;
            $course->coursename = $course->fullname;
            $course->course_fullname = searchlib::format_thestring($course->fullname);
            $iltname = searchlib::format_thestring($course->fullname);
            if (strlen($iltname)>57){
                $iltname = substr($iltname, 0, 57)."...";
                $course->course_shortname = $iltname ;
            } else {
                $course->course_shortname = searchlib::format_thestring($course->fullname);
            }
            $course->categoryname = $course_category;
            $course->formattedcategoryname = searchlib::format_thestring($categoryname);
            $course->summary = searchlib::format_thesummary($course->summary);
            $courseurl = new moodle_url('/local/search/courseinfo.php', array('id'=> $course->id));
            $courselink = html_writer::link($courseurl, $course_fullname, array('style'=>'color:#000;font-weight: 300;cursor:pointer;', 'title'=>$course->fullname, 'class'=>'available_course_link'));
            $course->course_url = $CFG->wwwroot.'/course/view.php?id='.$course->id;
            $course->courselink = $courselink;
            $course->coursecompletiondays = $this->get_coursecompletiondays_format($course->duration);

            $coursecontext   = context_course::instance($course->id);
            $enroll=is_enrolled($coursecontext, $USER->id);
            $course->enroll = $enroll;
            $course->isenrolled = $enroll;
            if($enroll){
                $course->requeststatus = MODULE_ENROLLED;
            }else{
                $course->requeststatus = MODULE_NOT_ENROLLED;
                if($course->approvalreqd == 1){
                    $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                    $requeststatus = $DB->get_field_sql($sql, array('componentid' => $course->id,'compname' => 'elearning', 'createdbyid'=>$USER->id));
                    if($requeststatus == 'PENDING'){
                        $course->requeststatus = MODULE_ENROLMENT_PENDING;
                    }
                }
            }

            if($course->approvalreqd == 1){
                $course->enrolmethods[] = 'request';
            }else if($course->selfenrol == 1){
                $course->enrolmethods[] = 'self';
            }
            $course->selfenrol = $this->get_enrollbutton($enroll, $course);

            $course->rating_element = '';
            $course->avgrating = 0;
            $course->ratedusers = 0;
            $course->likes = 0;
            $course->dislikes = 0;
            if(class_exists('local_ratings\output\renderer')){
                $rating_render = $PAGE->get_renderer('local_ratings');
                $ratinginfo = $DB->get_record('local_ratings_likes', array('module_id' => $course->id, 'module_area' => 'local_courses'));
                if($ratinginfo){
                    $course->avgrating = $ratinginfo->module_rating;
                    $course->ratedusers = $ratinginfo->module_rating_users;
                    $course->likes = $ratinginfo->module_like;
                    $course->dislikes = $ratinginfo->module_like_users - $ratinginfo->module_like;
                    $course->rating_element = $rating_render->render_ratings_data('local_classroom', $list->id ,$ratinginfo->module_rating, 14);
                }
            }

            $dur_min_sql = "SELECT cd.charvalue
                            FROM {customfield_data} cd
                            JOIN {customfield_field} cff ON cff.id = cd.fieldid
                            WHERE instanceid = $course->id AND cff.shortname = 'duration_in_minutes'
                            ";
            $dur_min = $DB->get_field_sql($dur_min_sql);
            if($dur_min){
                $hours = floor($dur_min / 60);
                if($hours > 1){
                    $hours = floor($dur_min / 60).' Hrs ';
                }elseif($hours == 1){
                    $hours = floor($dur_min / 60).' Hr ';
                }elseif($hours == 0){
                    $hours = '';
                }
                $minutes = ($dur_min % 60).' Mins.';
                $course->durationinmin  = $hours.$minutes;
            }else{
                $min = 0;
                $course->durationinmin = 'N/A.';
            }

            $course->modulescount = 0;

            $activitiescount = $this->get_modulescount($course->id);
               if($activitiescount > 0){
                 $course->modulescount = $activitiescount;
                }

             $course->modulescount = $course->modulescount ? $course->modulescount : 'N/A';

             $course->open_skillcategory = ($DB->get_field('local_skill_categories','name',array('id' => $course->open_skillcategory))) ? ($DB->get_field('local_skill_categories','name',array('id' => $course->open_skillcategory))) : 'N/A';

             $enrolldata = $DB->get_record_sql("SELECT ue.* FROM {user_enrolments} ue JOIN {enrol} e ON ue.enrolid = e.id JOIN {course} c ON c.id = e.courseid WHERE e.courseid = $course->id AND ue.userid = $USER->id");
            if($enrolldata){
                $course->enrol_date = date("d M Y", $enrolldata->timecreated);
            }else{
                $course->enrol_date = 'N/A';
            }
            $course->skill = $course->open_skill ? searchlib::$skills[$course->open_skill] : '';
            $course->level = $course->open_level ? searchlib::$levels[$course->open_level] : '';
            if($enrolldata){
                // $course->redirect = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'" class="viewmore_btn">'.get_string('resume','local_search').'</a>';
                $course->redirect = false;
            }else{
                $course->redirect='<a href="'.$CFG->wwwroot.'/local/search/coursedetails.php?id='.$course->id.'" class="viewmore_btn">'.get_string('view_details','local_search').'</a>';
            }

            $course->copylink = '';
            if(is_siteadmin()){
                $course->copylink = '<a data-action="courseinfo'.$course->id.'" onclick ="(function(e){ require(\'local_search/courseinfo\').copy_url({module:\'course\', moduleid:'.$course->id.'}) })(event)" class="cat_btn viewmore_btn">'.get_string('copyurl', 'local_search').'</a>';
            }

            $course->type = elearning;
            $course->module = 'local_courses';
            $coursecontext = context_course::instance($course->id);

            if(isset($course->open_learningformat)){
                $contextitemcoursetype = $DB->get_field_sql("SELECT lf.name
                                FROM {local_courses_learningformat} lf where lf.id=:learningformat",array('learningformat'=>$course->open_learningformat));
                $course->coursetype = $contextitemcoursetype ? $contextitemcoursetype : 'N/A';

            }else{
                $course->coursetype =  'N/A';
            }
            $coursecost=$DB->get_field('enrol','cost',array('courseid'=>$course->id,'status'=>0,'enrol'=>'stripepayment'));
            $course->stripepayment =$coursecost ? $coursecost : 0 ;

            $eol = $DB->get_field_sql("SELECT cd.value FROM {customfield_data} AS cd JOIN {customfield_field} AS cf ON cf.id = cd.fieldid WHERE cd.instanceid = :courseid AND cf.shortname LIKE 'end_of_life' ", ['courseid' => $course->id]);
            $course->eol = $eol ? date('d-m-Y', $eol) : 0;

            $ratings_plugin_exist = core_component::get_plugin_directory('local', 'ratings');
                if($ratings_plugin_exist){
                    require_once($CFG->dirroot . '/local/ratings/lib.php');
                    $course->course_ratings = display_rating($course->id,'local_courses');
                }
            $finalresponse[]= $course;
        }
        $finalresponse['numberofrecords']=$courseslist_ar['numberofrecords'];

        return $finalresponse;

    } //end of  get_facetofacelist

    public function get_modulescount($courseid){
        global $DB;
        $count_sql = "SELECT count(id)
                    FROM {course_modules}
                    WHERE course = $courseid
                    AND deletioninprogress = 0 AND visibleoncoursepage =1 AND visible = 1 ";
        $activities_count = $DB->count_records_sql($count_sql);
        $count  = $activities_count ? $activities_count : 0 ;
        return $count;
    }
    public function enrol_user_to_component($enrolmethod, $moduleid){
        global $DB, $USER;
        $course = get_course($moduleid);
        if(empty($course)){
            throw new \Exception("Course not found");
        }
        $can_self_enrol = $this->is_course_accessible($course);
        if(!$can_self_enrol){
            throw new \Exception("You cannot enrol to this course");
        }
        if(is_enrolled(context_course::instance($moduleid, $USER->id))){
            throw new \Exception("Already enrolled");
        }
        switch($enrolmethod){
            case 'request':
                if($course->approvalreqd != 1){
                    throw new \Exception("Enrollment method inactive");
                }else{
                    \local_request\api\requestapi::create('elearning', $moduleid);
                }
            break;
            case 'self':
                $sql = "SELECT * from {enrol} where courseid = {$moduleid} and enrol = 'self' AND status = 0 ";
                $instance = $DB->get_record_sql($sql);
                if($course->approvalreqd == 1 || $course->selfenrol != 1 || empty($instance)){
                    throw new \Exception("Enrollment method inactive");
                }else{
                    $self = enrol_get_plugin('self');
                    $type = 'course_enrol';
                    $dataobj = $id;
                    $fromuserid = 2;

                    if($instance){
                        $test =  $self->enrol_user($instance,$USER->id,$instance->roleid);
                        $emaillogs = new \local_courses\notification();
                        $notificationdata = $emaillogs->get_existing_notification($course, $type);
                        if($notificationdata){
                            $emaillogs->send_course_email($course, $USER, $type, $notificationdata);
                        }
                    }
                }
            break;
            default:
                throw new \Exception("Unknown enrollment method");
            break;
        }
    }
    public function is_course_accessible($course){
        global $DB, $USER;
        $selectsql = " SELECT c.id FROM {course} c ";
        $wheresql = " WHERE c.id > 1 AND c.visible = 1 AND c.selfenrol = 1 AND c.id = :courseid";
        $usercostcenterpaths = $DB->get_records('local_userdata', array('userid' => $USER->id));
        $paths = [];
        foreach($usercostcenterpaths AS $userpath){
            $userpathinfo = $userpath->costcenterpath;
            $paths[] = $userpathinfo.'%';
            while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {
                $userpathinfo = rtrim($userpathinfo, '/');
                if ($userpathinfo === '') {
                  break;
                }
                $paths[] = $userpathinfo;
            }
        }
        if(!empty($paths)){
            foreach($paths AS $path){
                $pathsql[] = " c.open_path LIKE '{$path}' ";
            }
            $wheresql .= " AND ( ".implode(' OR ', $pathsql).' ) ';
        }
        return $DB->record_exists_sql($selectsql.$wheresql, ['courseid' => $course->id]);
    }
    public function get_enrollbutton($enroll, $courseinfo){
        global $DB,$CFG,$USER;
        $courseid = $courseinfo->id;
        $coursename = $courseinfo->coursename;
        if(!is_siteadmin()){
            if($enroll){
                $selfenrolbutton = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'" class="cat_btn viewmore_btn btn">'.get_string('start_now','local_search').'</a>';
            }else{
                if($courseinfo->approvalreqd==1){
                    $componentid =$courseid;
                    $component = 'elearning';
                    $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                    $request = $DB->get_field_sql($sql,array('componentid' => $courseid,'compname' => $component,'createdbyid'=>$USER->id));

                    if($request=='PENDING'){
                        $selfenrolbutton = '<button class="cat_btn btn-primary viewmore_btn">Processing</button>';
                    }else{
                        $selfenrolbutton = requestapi::get_requestbutton($componentid, $component, $courseinfo->fullname);
                    }
                } else if($courseinfo->selfenrol == 1){
                    $currenttime = time();
                    $stripepayment=$DB->record_exists('enrol',array('courseid'=>$courseid,'status'=>0,'enrol'=>'stripepayment'));
                    if($stripepayment){
                       $string = get_string('buy','local_search');
                    }else{
                       $string = get_string('selfenrol','local_search');
                    }
                    $selfenrolbutton = '<a data-action="courseselfenrol'.$courseid.'" class="courseselfenrol cat_btn viewmore_btn  enrolled'.$courseid.'" onclick ="(function(e){ require(\'local_search/courseinfo\').coursetest({selector:\'courseselfenrol'.$courseid.'\', courseid:'.$courseid.', enroll:1, coursename: \''.$courseinfo->fullname.'\' }) })(event)">'.$string.'</a>';
                } else {
                    $selfenrolbutton = '';
                }
            }
        }else{
            $selfenrolbutton = '';
        }
        return $selfenrolbutton;
    } // end of get_enrollbutton function
    private function get_coursecompletiondays_format($duration){

        if(!empty($duration)){
            if($duration >= 60 ){
                $hours = floor($duration / 60);
                $minutes = ($duration % 60);
                $hformat = $hours > 1 ? $hformat = '%01shrs': $hformat = '%01shr';
                if($minutes == NULL){
                    $mformat = '';
                }else{
                    $mformat = $minutes > 1 ? $mformat = '%01smins': $mformat = '%01smin';
                }
                $format = $hformat . ' ' . $mformat;
                $coursecompletiondays = sprintf($format, $hours, $minutes);
            }else{
                $minutes = $duration;
                $coursecompletiondays = $duration > 1 ? $duration.'mins' : $duration.'min';
            }
        }else{
            $coursecompletiondays = 'N/A';
        }

        return $coursecompletiondays;

    } // end of get_coursecompletiondays_format function
} // end of class




