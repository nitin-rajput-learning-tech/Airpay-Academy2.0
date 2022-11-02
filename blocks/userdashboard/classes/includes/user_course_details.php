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
 * @copyright  2018 hemalatha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_userdashboard\includes;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;

class user_course_details {
        
    public static function total_course_activities($courseid, $userid) {
        global $DB, $USER, $CFG;
        if(empty($courseid)){
            return false;
        }
        // $sql="SELECT COUNT(ccc.id) as totalactivities FROM {course_modules} ccc WHERE ccc.course={$courseid}";
        $sql = "SELECT count(cc.id) as total FROM {course_completion_criteria} ccc JOIN {course_modules_completion} cc ON cc.coursemoduleid = ccc.moduleinstance where ccc.course={$courseid} and cc.userid={$userid}";
        $total_activitycount = $DB->get_record_sql($sql);
        $out = $total_activitycount->total;
        return $out;
    }
    
    public static function user_course_completed_activities($courseid, $userid) {
        global $DB, $USER, $CFG;
        if(empty($courseid) || empty($userid)){
            return false;
        }
        $sql="SELECT count(cc.id) as completedact from {course_completion_criteria} ccc JOIN {course_modules_completion} cc ON cc.coursemoduleid = ccc.moduleinstance where ccc.course={$courseid} and cc.userid={$userid} and cc.completionstate!=0";
        $completioncount = $DB->get_record_sql($sql);
        $out = $completioncount->completedact;
        return $out;
    }
    
    public static function user_course_completion_progress($courseid, $userid) {
        global $DB, $USER, $CFG;
        if(empty($courseid) || empty($userid)){
            return false;
        }
         $sql="SELECT id from {course_completions} where course={$courseid} and userid={$userid} and  timecompleted IS NOT NULL";
        //echo $sql;
        $condition=$DB->get_record_sql($sql);
        
        if($condition==''){
            $total_activity_count = user_course_details::total_course_activities($courseid, $userid);
            $completed_activity_count = user_course_details::user_course_completed_activities($courseid, $userid);
        if($total_activity_count>0 && $completed_activity_count>0){
            $course_completion_percent = ($completed_activity_count/$total_activity_count)*100;
        }
        }else{
            $course_completion_percent=100;
        }
        return $course_completion_percent;
    }
    
    public static function course_summary_files($courserecord){
        global $DB, $CFG, $OUTPUT;
        if ($courserecord instanceof stdClass) {
            //require_once($CFG->libdir . '/coursecatlib.php');
            $courserecord = new core_course_list_element($courserecord);
        }
        
        // set default course image
        //$url = $OUTPUT->pix_url('/course_images/courseimg', 'local_costcenter');
        foreach ($courserecord->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            if($isimage){
                $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' .
                    $file->get_component() . '/' .$file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
            }else{
               $url = $OUTPUT->image_url('courseimg', 'local_courses');//send_file_not_found();
               // $url ='';
            }
        }
        if(empty($url)){
            $url = $OUTPUT->image_url('courseimg', 'local_courses');//send_file_not_found();
           // $url = '';
        }
        return $url;
    }
    
    public static function get_classes_summary_files($classrecord){
            global $OUTPUT;
            $url = $OUTPUT->image_url('classviewnew', 'local_classroom');
        return $url;
    }
    
    // function for insert training_at as -1, if select All, else insert as selected options
    // @param -- $training_locations array, list of selected locations
    // @param -- $organizationids , comma seperated organisation values
    // public static function get_training_at_values($training_locations, $organizationids){
    //     global $DB, $USER;
        
    //     $systemcontext = context_system::instance();
    //     $training_at = null;
        
    //     if(sizeof($training_locations)>0){
    //         $selected_locationscount = count($training_locations);
    //         $sql = "SELECT u.id, location
    //                 FROM {local_userdata} ud
    //                 JOIN {user} u ON u.id = ud.userid
    //                 WHERE u.deleted = 0 AND u.suspended = 0 AND ud.location IS NOT NULL
    //                 AND ud.location != '' ";
    //         if($organizationids){
    //             $sql .= " AND ud.costcenterid IN ($organizationids) ";
    //         }else{
    //                 // following for OH, TC role users
    //             if(!is_siteadmin() && (has_capability('local/costcenter:view', $systemcontext) || has_capability('mod/facetoface:addinstance', $systemcontext))){
    //                 $userdept = $DB->get_field('local_userdata', 'costcenterid', array('userid'=>$USER->id));
    //                 if($userdept){
    //                     $sql .= " and ud.costcenterid = $userdept ";
    //                 }
    //             }
                
    //         }
    //         $sql .= " GROUP BY ud.location";
    //         $totallocations = $DB->get_records_sql_menu($sql);
    //         $totallocationscount = count($totallocations);
    //         if($selected_locationscount == $totallocationscount){
    //             $training_at = -1;
    //         }else{
    //             $training_at = implode(',', $training_locations); 
    //         }
    //     }
        
    //     return $training_at;
    // }
    
    // function for insert band as -1, if select All, else insert as selected options
    // @param -- $selectedbands array, list of selected locations
    // @param -- $organizationids , comma seperated organisation values
    // public static function get_band_values($selectedbands, $organizationids){
    //     global $DB, $USER;
        
    //     $systemcontext = context_system::instance();
    //     $band = null;
        
    //     if(sizeof($selectedbands)>0){
    //         $selected_bandscount = count($selectedbands);
            
    //         $sql = "SELECT u.id, ud.band
    //                 FROM {local_userdata} ud
    //                 JOIN {user} u ON u.id = ud.userid
    //                 WHERE u.deleted = 0 AND u.suspended = 0 AND ud.band IS NOT NULL
    //                 AND ud.band != '' ";
                    
    //         if($organizationids){
    //             $sql .= " AND ud.costcenterid IN ($organizationids) ";
    //         }else{
    //             // following for OH, TC role users
    //             if(!is_siteadmin() && (has_capability('local/costcenter:view', $systemcontext) || has_capability('mod/facetoface:addinstance', $systemcontext))){
    //                 $userdept = $DB->get_field('local_userdata', 'costcenterid', array('userid'=>$USER->id));
    //                 if($userdept){
    //                     $sql .= " and ud.costcenterid = $userdept ";
    //                 }
    //             }
    //         }
    //         $sql .= " GROUP BY ud.band";
            
    //         $totalbands = $DB->get_records_sql_menu($sql);
    //         $totalbandscount = count($totalbands);
            
    //         if($selected_bandscount == $totalbandscount){
    //             $band = -1;
    //         }else{
    //             $band = implode(',', $selectedbands); 
    //         }
    //     }
        
    //     return $band;
    // }
    
} // end of class

