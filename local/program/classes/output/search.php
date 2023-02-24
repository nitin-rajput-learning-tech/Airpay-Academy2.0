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
 * @package    local_program
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_program\output;
require_once($CFG->dirroot.'/local/program/lib.php');
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
use local_search\output\searchlib;
use local_program\program as pgrm;
use user_course_details;
use local_request\api\requestapi;

/**
 * Class containing data for search
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search implements renderable{

        public function get_programslist_query($perpage,$startlimit,$return_noofrecords=false, $returnobjectlist=false){
        global $DB, $USER, $CFG;
        $search = cataloglib::$search;
        $sortid = cataloglib::$sortid;
        //------main queries written here to fetch Program or  session based on condition
        $csql ="SELECT  lp.*,lp.startdate as trainingstartdate, lp.enddate as trainingenddate ";
          $cfromsql = " from {local_program} lp  ";
          $usql = " UNION ";
          $tsql ="SELECT lp.*,lp.startdate as trainingstartdate, lp.enddate as trainingenddate ";
          $tfromsql = " from {local_program} lp ";
          $tjoinsql = " JOIN {tag_instance} tgi ON tgi.itemid = lp.id AND tgi.itemtype = 'program' AND tgi.component = 'local_program' JOIN {tag} t ON t.id = tgi.tagid ";
          $leftjoinsql = $groupby = $orderby = $avgsql = '';
        if (!empty($sortid)) {
          switch($sortid) {
            case 'highrate':
            if ($DB->get_manager()->table_exists('local_rating')) {
                $avgsql .= " , AVG(r.rating) as rates ";
                $leftjoinsql .= " LEFT JOIN {local_rating} as r ON r.itemid = lp.id AND r.ratearea = 'local_program' ";
                $groupby .= " group by lp.id ";
                $orderby .= " order by rates desc ";
            }
            break;
            case 'lowrate':
            if ($DB->get_manager()->table_exists('local_rating')) {
                $avgsql .= " , AVG(r.rating) as rates  ";
                $leftjoinsql .= " LEFT JOIN {local_rating} as r ON r.itemid = lp.id AND r.ratearea = 'local_program' ";
                $groupby .= " group by lp.id ";
                $orderby .= " order by rates asc ";
            }
            break;
            case 'latest':
            $orderby .= " order by a.timecreated desc ";
            break;
            case 'oldest':
            $orderby .= " order by a.timecreated asc ";
            break;
            default:
            $orderby .= " order by a.timecreated desc ";
            break;
            }
        }
          $wheresql = " where 1=1 and lp.visible=1 ";
            //------if not site admin sessions list will be filter by location or bands
         if(is_siteadmin()){
            if(cataloglib::$search && cataloglib::$search!='null'){
                 $cwrsql = " AND lp.name LIKE '%$search%'";
                 $twrsql = " AND t.name LIKE '%$search%'";
            }
         }

        $usercontext = context_user::instance($USER->id);
        if(!is_siteadmin()){

            //-----filter by costcenter
            if($USER->open_costcenterid){
                $wheresql .= " AND $USER->open_costcenterid in (lp.costcenter)";
            }

             //OL-1042 Add Target Audience to Program//
                $params = array();

                $group_list = $DB->get_records_sql_menu("SELECT  cm.id,cm.cohortid as groupid from {cohort_members} cm where cm.userid IN ({$USER->id})");

                $grouquery = array(" lp.open_group IS NULL ");
                if($grouquery){
                    foreach ($group_list as $key => $group) {
                        $grouquery[] = " CONCAT(',',lp.open_group,',') LIKE CONCAT('%,',{$group},',%') ";
                    }
                }
                $groupqueeryparams =implode('OR',$grouquery);
                $params[]= '('.$groupqueeryparams.')';

                // if(!empty($USER->open_departmentid)){
                if(!empty($USER->open_departmentid) && $USER->open_departmentid != ""){
                    $sqlparams[] = "%,$USER->open_departmentid,%";
                }else{
                    $sqlparams[] = "";
                }
                $params[]= " 1 = CASE WHEN lp.department !='-1'
                    THEN
                        CASE WHEN CONCAT(',',lp.department,',') LIKE ?
                        THEN 1
                        ELSE 0 END
                    ELSE 1 END ";
                //FIND_IN_SET($USER->open_departmentid,lp.department)
                // }
                if(!empty($USER->open_subdepartment) && $USER->open_subdepartment != ""){
                    $sqlparams[] = "%,$USER->open_subdepartment,%";
                }else{
                    $sqlparams[] = "";
                }
                $params[]= " 1 = CASE WHEN lp.subdepartment !='-1'
                    THEN
                        CASE WHEN CONCAT(',',lp.subdepartment,',') LIKE ?
                        THEN 1
                        ELSE 0 END
                    ELSE 1 END ";//FIND_IN_SET($USER->open_departmentid,lp.department)
                // }
                // if(!empty($USER->open_hrmsrole)){
                // if(!empty($USER->open_hrmsrole) && $USER->open_hrmsrole != ""){
             //     $sqlparams[] = "%,$USER->open_hrmsrole,%";
                // }else{
                //  $sqlparams[] = "";
                // }
          //       $params[]= " 1 = CASE WHEN lp.open_hrmsrole IS NOT NULL
                //  THEN
                //      CASE WHEN CONCAT(',',lp.open_hrmsrole,',') LIKE ?
                //          THEN 1
                //          ELSE 0 END
                //  ELSE 1 END ";
                //FIND_IN_SET('$USER->open_hrmsrole',lp.open_hrmsrole)
                // }
               // if(!empty($USER->open_designation)){
                if(!empty($USER->open_designation) && $USER->open_designation != ""){
                    $sqlparams[] = "%,$USER->open_designation,%";
                }else{
                    $sqlparams[] = "";
                }
                $params[]= " 1 = CASE WHEN lp.open_designation IS NOT NULL
                    THEN
                        CASE WHEN CONCAT(',',lp.open_designation,',') LIKE ?
                            THEN 1
                            ELSE 0 END
                    ELSE 1 END ";//FIND_IN_SET('$USER->open_designation',lp.open_designation)
               // }


               // if(!empty($USER->city)){
                // if(!empty($USER->open_location) && $USER->open_location != ""){
                //  $sqlparams[] = "%,$USER->open_location,%";
                // }else{
                //  $sqlparams[] = "";
                // }
          //       $params[]= " 1 = CASE WHEN lp.open_location IS NOT NULL
                //      THEN
                //          CASE WHEN CONCAT(',',lp.open_location,',') LIKE ?
                //              THEN 1
                //              ELSE 0 END
                //      ELSE 1 END ";
                //FIND_IN_SET('$USER->city',lp.open_location)
                //
                // }


               $userlocation = $DB->get_field('user','open_location',array('id' => $USER->id));

               if($userlocation){
                 $wheresql .=" AND (FIND_IN_SET('$userlocation', lp.open_location) OR lp.open_location IS NULL OR lp.open_location = '') ";
               }else{
                  $wheresql .=" AND (lp.open_location IS NULL OR lp.open_location = '') ";
               }

                if(!empty($USER->open_level) && $USER->open_level != ""){
                    $sqlparams[] = "%,$USER->open_level,%";
                }else{
                    $sqlparams[] = "";
                }
                $params[]= " 1 = CASE WHEN lp.level IS NOT NULL
                                    THEN
                                        CASE
                                            WHEN CONCAT(',',lp.level,',') LIKE ?
                                                THEN 1
                                                ELSE 0
                                        END
                                    ELSE 1 END ";

                if(!empty($USER->open_resourcegroupid) && $USER->open_resourcegroupid != ""){
                    $sqlparams[] = "%,$USER->open_resourcegroupid,%";
                }else{
                    $sqlparams[] = "";
                }
                $params[]= " 1 = CASE WHEN lp.resourcegroup IS NOT NULL
                                    THEN
                                        CASE
                                            WHEN CONCAT(',',lp.resourcegroup,',') LIKE ?
                                                THEN 1
                                                ELSE 0
                                        END
                                    ELSE 1 END ";

                if(!empty($USER->open_resourcetypeid) && $USER->open_resourcetypeid != ""){
                    $sqlparams[] = "%,$USER->open_resourcetypeid,%";
                }else{
                    $sqlparams[] = "";
                }
                $params[]= " 1 = CASE WHEN lp.resourcetype IS NOT NULL
                                    THEN
                                        CASE
                                            WHEN CONCAT(',',lp.resourcetype,',') LIKE ?
                                                THEN 1
                                                ELSE 0
                                        END
                                    ELSE 1 END ";
                if(!empty($USER->open_doj) && $USER->open_doj != ""){
                    $open_doj = $USER->open_doj;
                }else{
                    $open_doj = 0;
                }
                $params[]= " 1 = CASE WHEN lp.open_doj IS NOT NULL
                                    THEN
                                        CASE
                                            WHEN lp.open_doj <= {$open_doj}
                                                THEN 1
                                                ELSE 0
                                        END
                                    ELSE 1 END ";

                if(!empty($params)){
                    $finalparams=implode('AND',$params);
                }else{
                    $finalparams= '1=1' ;
                }
             //OL-1042 Add Target Audience to Program//

            if(cataloglib::$search && cataloglib::$search!='null'){
                $cwrsql = " AND lp.name LIKE '%$search%'";
                $twrsql = " AND t.name LIKE '%$search%'";
            }
            $category=cataloglib::$category;
            if(cataloglib::$category && cataloglib::$category>0){

            }
            $joinsql = " AND $finalparams ";// OR (lp.open_hrmsrole IS NULL AND lp.open_designation IS NULL AND lp.open_location IS NULL AND lp.open_group IS NULL AND lp.department='-1' )

            if(cataloglib::$enrolltype && cataloglib::$enrolltype>0 ){
                if(cataloglib::$enrolltype==1){
                    if(cataloglib::$category && cataloglib::$category>0){

                    }
                    $wheresql .= " AND lp.id in (select distinct programid from {local_program_users} where userid=$USER->id) ";//AND lp.status in (1,3,4)
                }else{
                    $wheresql .= " AND lp.id not in (select distinct programid from {local_program_users} where userid=$USER->id) AND lp.selfenrol = 1 ";//AND lp.status in (1)
                       $wheresql .= $joinsql;

                }
            }else{
                    //$enrolled_programs=$DB->get_field_sql("select GROUP_CONCAT(programid) from {local_program_users} where userid=$USER->id");
                    //
                    //$yetto_enrolled_programs=$DB->get_field_sql("select GROUP_CONCAT(lp.id) from {local_program} AS lp  where  lp.id not in (select distinct programid from {local_program_users} where userid=$USER->id) and lp.status in (1) $joinsql");

                   $wheresql .= " AND (lp.id in (select programid from {local_program_users} where userid=$USER->id) or lp.id in (select lp.id from {local_program} AS lp  where  lp.id not in (select distinct programid from {local_program_users} where userid=$USER->id) AND lp.selfenrol = 1 $joinsql )) ";//AND lp.status in (1,3,4)
            }
            //$finalsql .= " AND lp.id not in (select distinct programid from {local_program_trainers} where trainerid=$USER->id)";
        }
        // $orderby = ' ORDER BY lp.id DESC';//group by lp.id
        // echo $finalsql;
        $csqlparams = $tsqlparams = $sqlparams;
        $mainparams = array_merge($csqlparams, $tsqlparams);

        // $finalsql .=" limit $startlimit,$perpage";
        $finalsql = "select a.* from ( ".$csql.$avgsql.$cfromsql.$leftjoinsql.$wheresql.$cwrsql.$groupby.$usql.$tsql.$avgsql.$tfromsql.$tjoinsql.$leftjoinsql.$wheresql.$twrsql.$groupby." ) as a ";
        $numofilt = $DB->get_records_sql($finalsql, $mainparams);
        $numberofrecords = sizeof($numofilt);

        if (empty($sortid)) {
            $finalsql .= " order by a.id desc ";
        } else {
            $finalsql .= $orderby;
        }
        $facetofacelist=$DB->get_records_sql($finalsql, $mainparams,$startlimit,$perpage);


        if($return_noofrecords && !$returnobjectlist){
            return  array('numberofrecords'=>$numberofrecords);
        }
        else if($returnobjectlist && !$return_noofrecords){
            return  array('list'=>$facetofacelist);
        }
        else{
            if($return_noofrecords && $returnobjectlist){
                return  array('numberofrecords'=>$numberofrecords,'list'=>$facetofacelist);
            }
        }

    }//end of get_programslist_query.
    public function export_for_template($perpage,$startlimit){
        global $DB, $USER,$CFG,$PAGE;
        $programslist_ar =$this->get_programslist_query($perpage, $startlimit, true, true);
        $programslist= $programslist_ar['list'];
        $localtags = new \local_tags\tags();
        foreach($programslist as $list){
          //    $programlocation=$DB->get_field('local_location_institutes','fullname',array('id'=>$list->instituteid));
          //    if($programlocation){
                // $list->programlocation=$programlocation;
          //    }
            // if($list->approvalreqd)
                // print_object($list);
            $course=$DB->get_record('course', array('id'=>$list->course));

            // $coursefileurl = (new pgrm)->program_logo($list->programlogo);
            if(file_exists($CFG->dirroot.'/local/includes.php')){
                require_once($CFG->dirroot.'/local/includes.php');
                $includes = new user_course_details();
            }
            if ($list->programlogo > 0){
                $coursefileurl = (new pgrm)->program_logo($list->programlogo);
                if($coursefileurl == false){
                    $coursefileurl = $includes->get_classes_summary_files($list);
                }
            } else {
                $coursefileurl = $includes->get_classes_summary_files($list);
            }

            // $list->categoryname = $name;
            // $list->formattedcategoryname = cataloglib::format_thestring($name);
            $list->iltformatname = cataloglib::format_thestring($list->name);


            if(is_object($coursefileurl)){
                    $coursefileurl=$coursefileurl->out();
            }
            $list->fileurl =   $coursefileurl;

            $list->intro = cataloglib::format_thesummary($list->description);
            //------------------Date-----------------------
            $startdate = cataloglib::get_thedateformat($list->startdate);
            $enddate = cataloglib::get_thedateformat($list->enddate);
            $list->date = $startdate.' - '.$enddate;
            $list->start_date = date("j M 'y", $list->startdate);
            //-------bands----------------------------
            $list->bands = cataloglib::trim_theband($list->bands);
            $list->type = PROGRAM;
            $list->enroll = $this->get_enrollflag($list->id);
            $userenrolstatus = $DB->record_exists('local_program_users', array('programid' => $list->id, 'userid' => $USER->id));
            $return=false;
            if($list->id > 0 && ($list->nomination_startdate!=0 || $list->nomination_enddate!=0)){
                $params1 = array();
                $params1['programid'] = $list->id;
                $params1['nomination_startdate'] = date('Y-m-d H:i',time());
                $params1['nomination_enddate'] = date('Y-m-d H:i',time());

                $sql1="SELECT * FROM {local_program} where id=:programid and CASE WHEN nomination_startdate > 0
                        then (from_unixtime(nomination_startdate,'%Y-%m-%d %H:%i')<=:nomination_startdate)
                        else 1 = 1 end and CASE WHEN nomination_enddate > 0
                        then (from_unixtime(nomination_enddate,'%Y-%m-%d %H:%i')>=:nomination_enddate)
                        else 1 = 1 end";

                $return=$DB->record_exists_sql($sql1,$params1);

            }elseif($list->id > 0 && $list->nomination_startdate==0 && $list->nomination_enddate==0){
                $return=true;
            }
            $list->selfenroll=1;
            // print_object($list);
            if (!$userenrolstatus && $return) {//$list->status == 1 &&
                $list->selfenroll=0;
            }
            $program_capacity_check=(new pgrm)->program_capacity_check($list->id);
            if($program_capacity_check&&$list->status == 1 && !$userenrolstatus){
                  $list->selfenroll=2;
            }
            $list->enrollmentbtn = $this->get_enrollbtn($list);

            if(class_exists('local_ratings\output\renderer')){
            $rating_render = $PAGE->get_renderer('local_ratings');
            $list->rating_element = $rating_render->render_ratings_data('local_courses', $list->id ,null, 14);
            }else{
            $list->rating_element = '';
            }

            $list->redirect='<a data-action="program'.$list->id.'" class="programinfo" onclick ="(function(e){ require(\'local_catalog/courseinfo\').programinfo({selector:\'program'.$list->id.'\', programid:'.$list->id.'}) })(event)"><button class="cat_btn viewmore_btn">'.get_string('viewmore','local_catalog').'</button></a>';
            $list->programlink= $CFG->wwwroot.'/local/program/view.php?bcid='.$list->id;
            $context = context_system::instance();
            $tags = $localtags->get_item_tags('local_program', 'program', $list->id, $context->id, $arrayflag = 0, $more = 0);
            $course->tags_title = $tags;
            $tags = strlen($tags) > 25 ? substr($tags, 0, 25)."..." : $tags;
            $list->tags = (!empty($tags) ) ? '<span title="Tags"><i class="fa fa-tags" aria-hidden="true"></i></span> '.$tags: '';
            $finallist[]= $list;
        } // end of foreach

        $finallist['numberofrecords']=$programslist_ar['numberofrecords'];

        return $finallist;
    }

    private function get_enrollflag($programid){
        global $USER, $DB;

        $enrolled = $DB->record_exists('local_program_users',array('programid'=>$programid,'userid'=>$USER->id));
        if($enrolled){
            $flag=1;
        }else{
            $flag=0;
        }
        return $flag;
    } // end of get_enrollflag

    public function get_enrollbtn($pgminfo){
    global $DB,$USER;
        $pgmid = $pgminfo->id;
        $pgmname =  $pgminfo->name;

        if(!is_siteadmin()){
            if($pgminfo->approvalreqd==1){
                $componentid =$pgmid;
                $component = 'program';
                // $request = $DB->get_field('local_request_records','status',array('componentid' => $pgmid,'compname' => $component,'createdbyid'=>$USER->id));
                $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                $request = $DB->get_field_sql($sql,array('componentid' => $pgmid,'compname' => $component,'createdbyid'=>$USER->id));
                if($request=='PENDING'){
                    $enrollmentbtn = '<button class="cat_btn btn-primary viewmore_btn">Processing</button>';
                }else{
                    $enrollmentbtn =requestapi::get_requestbutton($componentid, $component, $pgmname);
                }
            } else if($pgminfo->selfenrol==1){
               $enrollmentbtn = '<a href="javascript:void(0);" class="" alt = ' . get_string('enroll','local_catalog'). ' title = ' .get_string('enroll','local_catalog'). ' onclick="(function(e){ require(\'local_program/program\').ManageprogramStatus({action:\'selfenrol\', id: '.$pgmid.', programid:'.$pgmid.',actionstatusmsg:\'program_self_enrolment\',programname:\''.$pgmname.'\'}) })(event)" ><button class="cat_btn viewmore_btn" ><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('enroll','local_catalog').'</button></a>';
            }
        }
        return $enrollmentbtn;
    } // end of get_enrollbtn
} // end of class






