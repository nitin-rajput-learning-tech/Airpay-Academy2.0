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
 * @package    local_classroom
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_classroom\output;
require_once($CFG->dirroot.'/local/classroom/lib.php');
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use context_user;
use local_search\output\searchlib;
use local_classroom\classroom as clroom;
use user_course_details;
use local_request\api\requestapi;

/**
 * Class containing data for search
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search implements renderable{


    public function get_facetofacelist_query($perpage,$startlimit,$return_noofrecords=false, $returnobjectlist=false, $filters = array()){
        global $DB, $USER, $CFG;
        $search = searchlib::$search;
        //------main queries written here to fetch Classrooms or  session based on condition
        $csql = "SELECT  lc.*, lc.startdate as trainingstartdate, lc.enddate as trainingenddate ";
        $cfromsql = " FROM {local_classroom} lc  ";

        $leftjoinsql = '';

        // added condition for not displaying retired ILT's.
        $wheresql = " WHERE lc.visible=1 AND lc.status <> 4 ";

        $searchsql = '';
        if(searchlib::$search && searchlib::$search != 'null'){
            $searchsql = " AND lc.name LIKE '%$search%'";
        }
        $usercontext = context_user::instance($USER->id);
        $sqlparams = array();
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
                    $pathsql[] = " lc.open_path LIKE '{$path}' ";
                }
                $wheresql .= " AND ( ".implode(' OR ', $pathsql).' ) ';
            }

            $params = array();

            $group_list = $DB->get_records_sql_menu("SELECT cm.id,cm.cohortid as groupid from {cohort_members} cm where cm.userid IN ({$USER->id})");

            if (!empty($group_list)){
                $groups_members = implode(',', $group_list);
                if(!empty($group_list)){
                    $grouquery = array();
                    foreach ($group_list as $key => $group) {
                        $grouquery[] = " CONCAT(',',lc.open_group,',') LIKE CONCAT('%,',{$group},',%') ";
                    }
                    $groupqueeryparams =implode('OR',$grouquery);

                    $params[]= '('.$groupqueeryparams.')';
                }
            }

            if(count($params) > 0){
                $opengroup=implode('AND',$params);
            }else{
                $opengroup =  " 1 != 1 ";
            }

            $params = array();
            $params[]= " 1 = CASE WHEN lc.open_group is NOT NULL
                    THEN
                        CASE
                            WHEN $opengroup
                                THEN 1
                                ELSE 0
                        END
                    ELSE 1 END ";




            if(!empty($USER->open_designation) && $USER->open_designation != ""){
                $sqlparams[] = "%,$USER->open_designation,%";
            }else{
                $sqlparams[] = "";
            }
            $params[]= " 1 = CASE WHEN lc.open_designation IS NOT NULL
                        THEN
                            CASE
                                WHEN CONCAT(',',lc.open_designation,',') LIKE ?
                                    THEN 1
                                    ELSE 0
                            END
                        ELSE 1 END ";


            if(!empty($params)){
                $finalparams = implode('AND',$params);
            }else{
                $finalparams= '1=1' ;
            }

            $joinsql = " AND ($finalparams) ";
            $wheresql .= $joinsql;
        }
        if($filters['status']){
            $statussql = [];
            foreach($filters['status'] AS $statusfilter){
                switch ($statusfilter) {
                    case 'notenrolled':
                        $statussql[] = " lc.id not in (select distinct classroomid from {local_classroom_users} where userid=$USER->id) AND lc.status in (1) ";
                    break;
                    case 'enrolled':
                        $wheresql .= " AND lc.id in (select distinct classroomid from {local_classroom_users} where userid=$USER->id) AND lc.status in (1,3,4)";
                    break;
                    case 'completed':
                        $statussql[] = " lc.id in (select distinct classroomid from {local_classroom_users} where userid=$USER->id AND completion_status = 1) AND lc.status in (1,3,4) ";
                    break;
                }
            }
            if(!empty($statussql)){
                $wheresql .= " AND (".implode('OR', $statussql)." ) ";
            }else{
                $wheresql .= " AND lc.status in (1,3,4) ";
            }
        }else{
            $wheresql .= " AND lc.status in (1,3,4) ";
        }

        // foreach($filters AS $filtertype => $filtervalues){
        //     switch($filtertype){
        //         case 'categories':
        //         break;
        //     }
        // }
        $groupby = " GROUP BY lc.id ";

        $countsql = "SELECT lc.id ";
        $finalcountquery = $countsql.$cfromsql.$leftjoinsql.$wheresql.$searchsql.$groupby;
        // print_r($finalcountquery);exit;
        $numberofrecords = 0;
        if($return_noofrecords)
            $numberofrecords = sizeof($DB->get_records_sql($finalcountquery,$sqlparams));

        $finalsql = $csql.$cfromsql.$leftjoinsql.$wheresql.$searchsql.$groupby;
        $finalsql .= " ORDER BY lc.id DESC ";
        $classroomslist = $DB->get_records_sql($finalsql, $sqlparams, $startlimit,$perpage);

        if($return_noofrecords && !$returnobjectlist){
            return  array('numberofrecords'=>$numberofrecords);
        }
        else if($returnobjectlist && !$return_noofrecords){
            return  array('list'=>$classroomslist);
        }
        else{
            if($return_noofrecords && $returnobjectlist){
                return  array('numberofrecords'=>$numberofrecords,'list'=>$classroomslist);
            }
        }

    } // end of get_facetofacelist_query


    public function export_for_template($perpage,$startlimit, $selectedfilter = array()){
        global $DB, $USER, $CFG, $PAGE,$OUTPUT;

        $facetofacelist_ar =$this->get_facetofacelist_query($perpage, $startlimit, true, true, $selectedfilter);
        $facetofacelist= $facetofacelist_ar['list'];

        foreach($facetofacelist as $list){

           $iltlocation=$DB->get_field('local_location_institutes','fullname',array('id'=>$list->instituteid));
           if($iltlocation){
            $list->iltlocation=$iltlocation;
           }

            $course=$DB->get_record('course', array('id'=>$list->course));

            $name="categoryname";

            if(file_exists($CFG->dirroot.'/local/includes.php')){
                require_once($CFG->dirroot.'/local/includes.php');
                $includes = new user_course_details();
            }

            if($list->classroomlogo > 0){
                $coursefileurl = (new clroom)->classroom_logo($list->classroomlogo);
                if($coursefileurl == false){
                    $coursefileurl = $includes->get_classes_summary_files($list);
                }
            } else {
                 $coursefileurl = $includes->get_classes_summary_files($list);
            }

            $list->categoryname = $name;
            $categoryname = $list->categoryname;
            $list->formattedcategoryname = searchlib::format_thestring($name);
            $list->iltfullformatname = searchlib::format_thestring($list->name);
            $list->fullname = searchlib::format_thestring($list->name);
            $list->shortnme = searchlib::format_thestring($list->shortname);
            $iltname = searchlib::format_thestring($list->name);
            $list->iltformatname = $iltname ;
            $list->duration = (empty($list->duration)) ? 'N/A':$list->duration;
            $list->price = (empty($list->price)) ? '-':$list->price;

            //-----classroom image file url-------
            if(is_object($coursefileurl)){
                $coursefileurl=$coursefileurl->out();
            }
            $list->fileurl = $coursefileurl;
            $list->bannerimage = $coursefileurl;


            $list->intro = searchlib::format_thesummary($list->description);
            $list->summary = searchlib::format_thesummary($list->description);

             //------------------Date-----------------------
            $startdate =searchlib::get_thedateformat($list->startdate);
            $enddate= searchlib::get_thedateformat($list->enddate);


            $list->date = $startdate.' - '.$enddate;
            $list->start_date = date("j M 'y", $list->startdate);

            $list->bands=searchlib::trim_theband($list->bands);
            $list->type = classroom;
            $list->module = 'local_classroom';

            $list->enroll=$this->get_the_enrollflag($classroomid);
            $list->isenrolled=$list->enroll;

            $userenrolstatus = $DB->record_exists('local_classroom_users', array('classroomid' => $list->id, 'userid' => $USER->id));
            if($course->approvalreqd == 1){
                $course->enrolmethods[] = 'request';
            }else if($course->selfenrol == 1){
                $course->enrolmethods[] = 'self';
            }
            $list->userenrolstatus = $userenrolstatus;
            $return=false;
            if($list->id > 0 && ($list->nomination_startdate!=0 || $list->nomination_enddate!=0)){
                $params1 = array();
                $params1['classroomid'] = $list->id;
                $params1['nomination_startdate'] = time();
                $params1['nomination_enddate'] = time();

                $sql1="SELECT *
                        FROM {local_classroom} WHERE id=:classroomid
                        AND CASE WHEN nomination_startdate > 0
                        THEN
                            CASE WHEN nomination_startdate <= :nomination_startdate
                            THEN 1
                            ELSE 0 END
                        ELSE 1  END = 1 AND
                        CASE WHEN nomination_enddate > 0
                            THEN CASE WHEN nomination_enddate >= :nomination_enddate
                                THEN 1
                                ELSE 0 END
                        ELSE 1 END = 1 ";

                $return = $DB->record_exists_sql($sql1,$params1);

            }elseif($list->id > 0 && $list->nomination_startdate==0 && $list->nomination_enddate==0){
                $return=true;
            }

            $list->selfenroll=1;
            if ($list->status == 1 && !$userenrolstatus && $return) {
                $list->selfenroll=0;
            }
              $classroom_capacity_check=(new clroom)->classroom_capacity_check( $list->id);
              if($classroom_capacity_check&&$list->status == 1 && !$userenrolstatus&&  $list->allow_waitinglistusers==0){
                  $list->selfenroll=2;
              }

            $list->enrollmentbtn= $this->get_enrollbtn($list);
            $list->rating_element = '';
            $list->avgrating = 0;
            $list->ratedusers = 0;
            $list->likes = 0;
            $list->dislikes = 0;
            if(class_exists('local_ratings\output\renderer')){
                $rating_render = $PAGE->get_renderer('local_ratings');
                $ratinginfo = $DB->get_record('local_ratings_likes', array('module_id' => $list->id, 'module_area' => 'local_classroom'));
                if($ratinginfo){
                    $list->avgrating = $ratinginfo->module_rating;
                    $list->ratedusers = $ratinginfo->module_rating_users;
                    $list->likes = $ratinginfo->module_like;
                    $list->dislikes = $ratinginfo->module_like_users - $ratinginfo->module_like;
                    $list->rating_element = $rating_render->render_ratings_data('local_classroom', $list->id ,$ratinginfo->module_rating, 14);
                }
            }

            // classroom view link
            $list->classroomlink= $CFG->wwwroot.'/local/classroom/view.php?cid='.$list->id;
            if (!$userenrolstatus){
              $list->redirect = '<a href="'.$list->classroomlink.'" class="classroominfo" ><button class="cat_btn viewmore_btn">'.get_string('viewmore','local_search').'</button></a>';
            } else {
              $list->redirect = '<a href="'.$list->classroomlink.'" class="classroominfo" ><button class="cat_btn viewmore_btn">'.get_string('start_now','local_classroom').'</button></a>';
            }

            $context = \local_costcenter\lib\accesslib::get_module_context();

            $finallist[]= $list;
        } // end of foreach

        $finallist['numberofrecords']=$facetofacelist_ar['numberofrecords'];
        $finallist['cfgwwwroot']= $CFG->wwwroot;

        return $finallist;

    } //end of  get_facetofacelist

   private function get_the_enrollflag($classroomid){
        global $USER, $DB;

        $enrolled =$DB->record_exists('local_classroom_users',array('classroomid'=>$classroomid,'userid'=>$USER->id));
        if($enrolled){
            $flag=1;
        }else{
            $flag=0;
        }

        return $flag;
    } // end of get_the_enrollflag

    private function get_enrollbtn($classroominfo){
        global $DB,$USER;
        $classroomid = $classroominfo->id;
        $classroomname =  $classroominfo->name;

        if(!is_siteadmin()){
            if($classroominfo->approvalreqd==1){
                   $waitlist = $DB->get_field('local_classroom_waitlist','id',array('classroomid' => $classroomid,'userid'=>$USER->id,'enrolstatus'=>0));
                if($waitlist > 0){
                        $enrollmentbtn = '<button class="cat_btn btn-primary viewmore_btn">Waiting</button>';
                }else{
                    $componentid =$classroomid;
                    $component = 'classroom';
                    $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                    $request = $DB->get_field_sql($sql, array('componentid' => $classroomid,'compname' => $component,'createdbyid'=>$USER->id));
                    if($request=='PENDING'){
                        $enrollmentbtn = '<button class="cat_btn btn-primary viewmore_btn">Processing</button>';
                    }else{
                        $enrollmentbtn =requestapi::get_requestbutton($componentid, $component, $classroomname);
                    }
                }
            }else{
                $waitlist = $DB->get_field('local_classroom_waitlist','id',array('classroomid' => $classroomid,'userid'=>$USER->id,'enrolstatus'=>0));
                if($waitlist > 0){
                        $enrollmentbtn = '<button class="cat_btn btn-primary viewmore_btn">Waiting</button>';
                }else{
                     $enrollmentbtn = '<a href="javascript:void(0);" class="" alt = ' . get_string('enroll','local_classroom'). ' title = ' .get_string('enroll','local_classroom'). ' onclick="(function(e){ require(\'local_classroom/classroom\').ManageclassroomStatus({action:\'selfenrol\', id: '.$classroomid.', classroomid:'.$classroomid.',actionstatusmsg:\'classroom_self_enrolment\',classroomname:\''.$classroomname.'\'}) })(event)" ><button class="cat_btn viewmore_btn">'.get_string('enroll','local_classroom').'</button></a>';
                }
            }
        }
        return $enrollmentbtn;
    } // end of get_enrollbtn
} // end of class






