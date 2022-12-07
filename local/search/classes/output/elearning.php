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
 * Class containing data for course competencies page
 *
 * @package    local_search
 * @copyright  2018 hemalathacarun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_search\output;
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
use local_classroom\classroom as clroom;
use local_request\api\requestapi;
use core_completion\progress;
use local_udemysync\plugin;

/**
 * Class containing data for course competencies page
 *
 * @copyright  2019 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class elearning implements renderable{

    public function get_elearning_courselist_query($perpage, $startlimit, $return_noofrecords=false,$returnobjectlist=false, $tagitems = array(), $selectedvendors = array(), $selectedlformats = array(), $coursetype = []){

        global $DB,$USER,$COURSE,$CFG;

        $search = searchlib::$search;
        $selectsql = " SELECT c.* ";
        $fromsql = " FROM {course} c ";
        $leftjoinsql = '';

       // added condition for not displaying retired courses except exams.
        $wheresql = " WHERE c.id > 1 AND c.selfenrol = 1 ";

        $systemcontext = \local_costcenter\lib\accesslib::get_module_context();

        if(!is_siteadmin()){
           $wheresql .= " AND c.open_costcenterid = $USER->open_costcenterid";
        }
        $course_searchsql = "";
        if(searchlib::$search && searchlib::$search!='null'){
            $course_searchsql = " AND c.fullname LIKE '%$search%'";
        }
        if($coursetype){
            $types = implode(',',array_filter($coursetype,'is_numeric'));
            $wheresql .= " AND c.open_identifiedas IN ($types) ";
        }
        $category=searchlib::$category;
        if(searchlib::$category && searchlib::$category > 0){
            $wheresql .= " AND c.category=$category";
        }

        if(searchlib::$enrolltype && searchlib::$enrolltype > 0){
            if(searchlib::$enrolltype==1){
                $coursecondition= "c.id in";
            }
            else{
                $coursecondition = "c.id not in";
            }

            $wheresql .=" AND $coursecondition (select
                distinct e.courseid  from {enrol} e
                JOIN {user_enrolments} ue on ue.enrolid = e.id
                where e.courseid=c.id and ue.userid=$USER->id) ";
        }

      if(!is_siteadmin() || !has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            if(!empty($USER->open_subdepartment) && $USER->open_subdepartment != ""){
                $wheresql .= " AND (c.open_subdepartment = 0 OR c.open_subdepartment IS NULL OR (c.open_subdepartment = $USER->open_subdepartment) ) ";
            }else{
                $wheresql .= " AND (c.open_subdepartment = 0 OR c.open_subdepartment IS NULL ) ";
            }

            // if(!empty($USER->open_grade) && $USER->open_grade != ""){
            //     $wheresql .= " AND (c.open_grade = 0 OR c.open_grade IS NULL OR c.open_grade = -1 OR (concat(',',c.open_grade,',') LIKE '%,$USER->open_grade,%' ) )";
            // }else{
            //     $wheresql .= " AND (c.open_grade = 0 OR c.open_grade IS NULL OR c.open_grade = -1 ) ";
            // }

            if(!empty($USER->open_ouname) && $USER->open_ouname != ""){
                $wheresql .= " AND (c.open_ouname = 0 OR c.open_ouname IS NULL OR c.open_ouname = -1 OR (concat(',',c.open_ouname,',') LIKE '%,$USER->open_ouname,%' ) )";
            }else{
                $wheresql .= " AND (c.open_ouname = 0 OR c.open_ouname IS NULL OR c.open_ouname = -1 ) ";
            }
       }

        $wheresql .= " AND c.visible = 1 ";

         if($tagitems){

            $i = 0;
            $courseprovidersql = $courseskillsql = $coursecategorysql = [];

            foreach($tagitems AS $item){
                $type = substr($item, 0, strpos($item, '_'));
                $value = substr($item, strpos($item, '_')+1);
                switch($type){
                    case 'courseprovider':
                        $courseprovidersql[] = " concat(',', c.open_courseprovider, ',') LIKE '%,{$value},%' ";
                    break;
                    case 'skill':
                        $courseskillsql[] = " concat(',', c.open_skillcategory, ',') LIKE '%,{$value},%' ";
                    break;
                    case 'categories':
                        $coursecategorysql[] = " c.category = {$value} ";
                    break;
                }
                $i++;
            }
            if($courseprovidersql){
                $wheresql .= " AND (". implode(' OR ', $courseprovidersql).')';
            }
            if($courseskillsql){
                $wheresql .= " AND (". implode(' OR ', $courseskillsql).')';
            }
            if($coursecategorysql){
                $wheresql .= " AND (". implode(' OR ', $coursecategorysql).')';
            }
        }

        $groupby = " GROUP BY c.id ";

        $countsql = "SELECT c.id ";
        $finalcountquery = $countsql.$fromsql.$wheresql.$course_searchsql.$groupby;

        $numberofrecords = sizeof($DB->get_records_sql($finalcountquery));

        $finalsql = $selectsql.$fromsql.$wheresql.$course_searchsql.$groupby;

        $finalsql .= "  ORDER by c.id DESC";

        $courseslist = $DB->get_records_sql($finalsql, array(), $startlimit, $perpage);

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



   public function export_for_template($perpage,$startlimit,$tagitems = array(), $selectedvendors = array(), $selectedlformats){
        global $DB, $USER,$CFG, $OUTPUT,$PAGE;
        $context = \local_costcenter\lib\accesslib::get_module_context();

        $courseslist_ar = $this->get_elearning_courselist_query($perpage,$startlimit, true, true,$tagitems,$selectedvendors, $selectedlformats,$coursetype);

        $courseslist=$courseslist_ar['list'];

        $finalresponse= array();
        $statuslist = array(1=>'Announced',
                                2=>'Active',3=>'Beta',4=>'Retired');

        foreach ($courseslist as $course) {
            $grid="";
            $course->statusstring = $statuslist[$course->open_status];
            $course_category = $DB->get_field('course_categories','name',array('id' => $course->category));
            $course->fileurl = searchlib::convert_urlobject_intoplainurl($course);
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
            $course->coursegrade =$this->get_coursegrade($coursedetails->grade);
            $course->courselink = $courselink;

            if(!empty($coursedetails->credits)){
                $coursecredits = $coursedetails->credits;
            }else{
                $coursecredits = 'N/A';
            }

            $course->coursecredits = $coursecredits;
            $course->enrollstartdate =searchlib::get_thedateformat($coursedetails->enrollstartdate);
            $course->enrollenddate = searchlib::get_thedateformat($coursedetails->enrollenddate);

            $course->coursecompletiondays = $this->get_coursecompletiondays_format($course->duration);

            $coursecontext   = context_course::instance($course->id);
            $enroll=is_enrolled($coursecontext, $USER->id);
            $course->enroll = $enroll;

            $course->selfenrol = $this->get_enrollbutton($enroll, $course);

            if(class_exists('local_ratings\output\renderer')){
                $rating_render = $PAGE->get_renderer('local_ratings');
                $course->rating_element = $rating_render->render_ratings_data('local_courses',$course->id, null,14);
            }else{
                $course->rating_element = '';
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

            if($enrolldata){
                $course->redirect='<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'" class="viewmore_btn">'.get_string('resume','local_search').'</a>';
            }else{
                // $course->redirect='<a href="'.$CFG->wwwroot.'/local/catalog/coursedetails.php?id='.$course->id.'" class="viewmore_btn">'.get_string('view_details','local_search').'</a>';
                $course->redirect='<a href="'.$CFG->wwwroot.'/local/search/coursedetails.php?id='.$course->id.'" class="viewmore_btn">'.get_string('view_details','local_search').'</a>';
            }

            $course->copylink = '';
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context) || has_capability('local/costcenter:manage_ownorganization', $context) || has_capability('local/costcenter:manage_owndepartments', $context)){
                $course->copylink = '<a data-action="courseinfo'.$course->id.'" onclick ="(function(e){ require(\'local_search/courseinfo\').copy_url({module:\'course\', moduleid:'.$course->id.'}) })(event)"><button class="cat_btn viewmore_btn">'.get_string('copyurl', 'local_search').'</button></a>';
            }

            $course->type = ELE;
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

public function get_enrollbutton($enroll, $courseinfo){
        global $DB,$CFG,$USER;
        require_once($CFG->dirroot . '/local/udemysync/classes/plugin.php');
        $courseid = $courseinfo->id;
        $coursename = $courseinfo->coursename;

        if(!is_siteadmin()){

          if($courseinfo->approvalreqd==1){
            if($enroll == 0){
            $componentid =$courseid;
            //$component = 'elearning';
            $component = 'elearning';
            $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
            $request = $DB->get_field_sql($sql,array('componentid' => $courseid,'compname' => $component,'createdbyid'=>$USER->id));

            if($request=='PENDING'){
                $selfenrolbutton = '<button class="cat_btn btn-primary viewmore_btn">Processing</button>';
            }else{
                $selfenrolbutton =requestapi::get_requestbutton($componentid, $component, $courseinfo->fullname);
            }
        }
        else{
            $selfenrolbutton = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'" class=""><button class="cat_btn viewmore_btn btn">'.get_string('start_now','local_search').'</button></a>';
        }
    } else if($courseinfo->selfenrol == 1){
         if($enroll == 0){
            $currenttime = time();
            $stripepayment=$DB->record_exists('enrol',array('courseid'=>$courseid,'status'=>0,'enrol'=>'stripepayment'));
            if($stripepayment){
               $string = get_string('buy','local_search');
            }else{
               $string = get_string('selfenrol','local_search');
            }


          if($courseinfo->expirydate != 0){
            if($courseinfo->expirydate >= $currenttime){
                 $provider_shortname = $DB->get_field('local_course_providers','shortname',array('id' => $courseinfo->open_courseprovider));
                 if($provider_shortname == 'udemy'){
                     $selfenrolbutton = '<a data-action="courseselfenrol'.$courseid.'" class="courseselfenrol enrolled'.$courseid.'" onclick ="(function(e){ require(\'local_search/courseinfo\').test({selector:\'courseselfenrol'.$courseid.'\', courseid:'.$courseid.', enroll:1, coursename: \''.$courseinfo->fullname.'\' }) })(event)"><button class="cat_btn viewmore_btn btn">'.$string.'</button></a>';

                 } else {
                     $selfenrolbutton = '<a data-action="courseselfenrol'.$courseid.'" class="courseselfenrol enrolled'.$courseid.'" onclick ="(function(e){ require(\'local_search/courseinfo\').coursetest({selector:\'courseselfenrol'.$courseid.'\', courseid:'.$courseid.', enroll:1, coursename: \''.$courseinfo->fullname.'\' }) })(event)"><button class="cat_btn viewmore_btn btn">'.$string.'</button></a>';
                 }
           } else if($courseinfo->expirydate < $currenttime){
                $selfenrolbutton = '<a data-action="courseselfenrol'.$courseid.'" class="courseselfenrol enrolled'.$courseid.'" onclick ="(function(e){ require(\'local_search/courseinfo\').courseexpiry({selector:\'courseselfenrol'.$courseid.'\', courseid:'.$courseid.', enroll:1, coursename: \''.$courseinfo->fullname.'\' }) })(event)"><button class="cat_btn viewmore_btn btn">'.$string.'</button></a>';
           }
        } else if($courseinfo->expirydate == 0){
               $udemyprovider_shortname = $DB->get_field('local_course_providers','shortname',array('id' => $courseinfo->open_courseprovider));
                 if($udemyprovider_shortname == 'udemy'){
                     $selfenrolbutton = '<a data-action="courseselfenrol'.$courseid.'" class="courseselfenrol enrolled'.$courseid.'" onclick ="(function(e){ require(\'local_search/courseinfo\').test({selector:\'courseselfenrol'.$courseid.'\', courseid:'.$courseid.', enroll:1, coursename: \''.$courseinfo->fullname.'\' }) })(event)"><button class="cat_btn viewmore_btn btn">'.$string.'</button></a>';
                 } else {
                     $selfenrolbutton = '<a data-action="courseselfenrol'.$courseid.'" class="courseselfenrol enrolled'.$courseid.'" onclick ="(function(e){ require(\'local_search/courseinfo\').coursetest({selector:\'courseselfenrol'.$courseid.'\', courseid:'.$courseid.', enroll:1, coursename: \''.$courseinfo->fullname.'\' }) })(event)"><button class="cat_btn viewmore_btn btn">'.$string.'</button></a>';
                 }
        }

     } //end of $enroll

  } else{
            $selfenrolbutton = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'" class=""><button class="cat_btn viewmore_btn btn">'.get_string('start_now','local_search').'</button></a>';
    }
    }else{
        $selfenrolbutton = '';
    }

  return $selfenrolbutton;

} // end of get_enrollbutton function

 private function get_coursegrade($grade){
    if(!empty($grade)){
        if($grade == -1){
            $coursegrade = get_string('all');
        }else{
            $coursegrade = $grade;
        }
    }else{
            $coursegrade = get_string('all');
    }
      return $coursegrade;

} // end of get_coursegrade


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




