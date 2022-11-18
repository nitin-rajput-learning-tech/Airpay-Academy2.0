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
namespace block_userdashboard\lib;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;


class elearning_courses{

    /**********Function Or Queries for the user dashboard view of the course Done By Ravi_369******/
    public static function courses_todo() {
        global $DB, $USER, $CFG;

        $sql = "SELECT count(distinct(course.id)) as countid

                FROM
                {enrol} AS en
                JOIN {user_enrolments} AS ue ON ue.enrolid = en.id AND en.enrol IN('self','manual','auto')
                JOIN {course} AS course ON en.courseid = course.id
                JOIN {local_coursedetails} lcd ON course.id = lcd.courseid
                JOIN {user} AS mdluser ON mdluser.id = ue.userid

                WHERE mdluser.id  IN (
                    SELECT ra.userid
                        FROM {course} AS c
                        JOIN {context} AS ctx ON c.id = ctx.instanceid
                        JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
                        WHERE c.id=course.id and ra.roleid=5 and ra.userid={$USER->id})
                AND CONCAT(',',course.open_identifiedas,',') LIKE '%,3,%' 
                AND en.enrol IN ('self','manual','auto') AND course.visible = 1
                ";

        $courses_todo = $DB->get_record_sql($sql);

        if ($courses_todo->countid > 0) {

            //$count=$this->tocheckcompleted();

            // print_object($count);
            return $courses_todo->countid;
        } else {
            return $courses_todo->countid;
        }

    }


    /*******Function to Check the completed courses of the LOGIN User**********/
    public static function tocheckcompleted($userid) {
        global $DB, $USER, $CFG; $count=array();
        if (!$userid) {
            $userid = $USER->id;
        }
        $sql1 = "SELECT distinct(course.id),course.id as name
                FROM
                {enrol} AS en
                JOIN {user_enrolments} AS ue ON ue.enrolid = en.id AND en.enrol IN('self','manual','auto')
                JOIN {course} AS course ON en.courseid = course.id
                JOIN {course_completions} cc ON cc.userid = ue.userid
                WHERE ue.userid  IN (
                SELECT ra.userid
                FROM {course} AS c
                JOIN {context} AS ctx ON c.id = ctx.instanceid
                JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
                WHERE c.id=course.id and ra.roleid=5 and ra.userid=$userid)
                AND CONCAT(',',course.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND course.visible = 1 ";

        $course = $DB->get_records_sql_menu($sql1);
        $course = implode(',', $course);
        if ($course) {
            $sql2 = "SELECT id,course as list from {course_completions} where userid={$userid} and course IN ({$course}) and timecompleted is NOT NULL";

            $count = $DB->get_records_sql_menu($sql2);
            // $this->two = implode(',', $count);
        }
        return $count;
    }
    /*******End of the function ******/


    /******Function to the show the inprogress course names in the E-learning Tab********/
    public static function inprogress_coursenames($filter_text='', $mobile = false, $status = 'inprogress', $limit = false, $type = '', $page = 0, $perpage = 10) {
        global $DB, $USER;
        
            if ($mobile) {
                $sqlquery = "SELECT course.* ";
                $sqlcount = "SELECT COUNT(course.id) ";
            }
            else {
                $sqlquery = "SELECT course.id,course.fullname,course.summary ";
            }
            $sql = " FROM {course} AS course
                    JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol IN('self','manual','auto')
                    JOIN {user_enrolments} ue ON e.id = ue.enrolid ";
            if($status == 'enrolled' && $mobile && $type == 'recentlyaccessed'){
                $sql .= " JOIN {user_lastaccess} as ul ON ul.courseid = course.id AND ul.userid = $USER->id";
            }
            $sql .= " WHERE ue.userid = $USER->id AND course.open_costcenterid = $USER->open_costcenterid AND CONCAT(',',course.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND course.id>1";
            if(!empty($filter_text)){
               $sql .= " AND course.fullname LIKE '%%{$filter_text}%%'";
            }
            // if ($mobile && $type == 'recentlyaccessed') {
                $sql .= ' AND course.visible=1 ';
            // }
            if ($status == 'enrolled' && $mobile) {


            } else {

                if($mobile) {
                        list($completed_courses,$completed_count) = elearning_courses::completed_coursenames('', $mobile,0, 0);

                    } else {
                        $completed_courses = elearning_courses::completed_coursenames();
                    }
                    
                if(!empty($completed_courses)){
                    $complted_id = array();

                    foreach($completed_courses as $complted_course){
                        $completed_id[] = $complted_course->id;
                    }
                    $completed_ids = implode(',', $completed_id);
                    $sql .= " AND course.id NOT IN($completed_ids)";
                }
            }
            if ($limit) {
                $sql .= ' order by ue.timecreated DESC '; //LIMIT 10
                $sql_limit = 10; 
            } else if ($status == 'enrolled' && $mobile && $type == 'recentlyaccessed') {
                $sql .= "ORDER BY ul.timeaccess DESC "; //LIMIT 10
                $sql_limit = 10; 
            } else {
                $sql .= ' order by ue.timecreated desc';
                $sql_limit = 0;
            }

        $inprogress_courses = $DB->get_records_sql($sqlquery . $sql, array(), $page * $perpage, $perpage);
        if ($mobile) {
            $inprogress_coursescount = $DB->count_records_sql($sqlcount . $sql);
                 return array($inprogress_courses, $inprogress_coursescount);
        } else {
            return $inprogress_courses;
        }
    }

    public static function inprogress_coursenames_count($filter_text){
        global $USER, $DB;
        $sql = "SELECT COUNT(course.id)  FROM {course} AS course
            JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol IN('self','manual','auto')
            JOIN {user_enrolments} ue ON e.id = ue.enrolid 
            WHERE ue.userid = {$USER->id} AND 
            course.open_costcenterid = {$USER->open_costcenterid} 
            AND CONCAT(',',course.open_identifiedas,',') LIKE CONCAT('%,',3,',%') 
            AND course.id>1 AND course.visible=1 ";
        if(!empty($filter_text)){
           $sql .= " AND course.fullname LIKE '%%{$filter_text}%%'";
        }
        $inprogress_count = $DB->count_records_sql($sql);
        return $inprogress_count;
    }
    /*********End of the Function*********/

    /******Function to the show the Completed course names in the E-learning Tab********/
    public static function completed_coursenames($filter_text='', $mobile = false, $page = 0, $perpage = 10) {
        global $DB, $USER;
        if($mobile){
            $sqlquery = "SELECT c.* ";
            $sqlcount = "SELECT COUNT(c.id)  ";
        }
        else {
            $sqlquery = "SELECT cc.id as completionid,c.id,c.fullname,c.summary";
        }
        $sql .= " FROM {course_completions} cc
                JOIN {course} c ON c.id = cc.course AND cc.userid = $USER->id
                JOIN {enrol} e ON c.id = e.courseid AND e.enrol IN('self','manual','auto')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') 
                AND ue.userid = {$USER->id} AND c.open_costcenterid = $USER->open_costcenterid
                AND cc.timecompleted IS NOT NULL AND c.visible = 1 AND c.id > 1 ";
          if(!empty($filter_text)){
           $sql .= " AND c.fullname LIKE '%%{$filter_text}%%'";
        }
        $sql .= " ORDER BY cc.timecompleted DESC ";

        $coursenames = $DB->get_records_sql($sqlquery . $sql, array(), $page * $perpage, $perpage);
        if ($mobile) {
            $completed_coursescount = $DB->count_records_sql($sqlcount . $sql);
            return array($coursenames, $completed_coursescount);
        } else {
            return $coursenames;
        }
    }
    /********end of the Function****/

   public static function completed_coursenames_count($filter_text = ''){

    }
  
    /******Function to the show the Pending course names in the E-learning Tab********/
    // public static function pastdue_coursenames($filter_text = '') {
    //     global $DB, $USER;

    //     $sql = "SELECT course.id,course.fullname,en.enrol

    //             FROM
    //             {enrol} AS en
    //             JOIN {user_enrolments} AS ue ON ue.enrolid = en.id AND en.enrol IN('self','manual','auto')
    //             JOIN {course} AS course ON en.courseid = course.id
    //             JOIN {local_coursedetails} lcd ON course.id = lcd.courseid

    //             WHERE ue.userid  IN (
    //             SELECT ra.userid
    //             FROM {course} AS c
    //             JOIN {context} AS ctx ON c.id = ctx.instanceid
    //             JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
    //             WHERE c.id=course.id and ra.roleid=5 and ra.userid={$USER->id})
    //             and CONCAT(',',course.open_identifiedas,',') LIKE CONCAT('%,',3,',%')
    //             AND course.startdate > 0
    //             AND course.visible=1
    //             ";

    //     $count = elearning_courses::tocheckcompleted($USER->id);
    //     if ($count) {
    //         $counted = implode(',', $count);
    //         $sql .= "and course.id NOT IN($counted)";
    //     }

    //     $pastdue_courses = $DB->get_records_sql($sql);

    //     return $pastdue_courses;
    // }
    /*******End of the function******/


    /******Function to the show the Completed course names in the E-learning Tab********/
    // public static function upcoming_courses() {
    //     global $DB, $USER, $CFG;
    //     $sql = "SELECT distinct(c.id) as id ,c.fullname,e.enrol from {course} c
    //            JOIN {enrol} e ON c.id = e.courseid
    //            JOIN {user_enrolments} ue ON e.id = ue.enrolid
    //            JOIN {local_coursedetails} cd ON c.id = cd.courseid
    //            JOIN {course_completions} cc ON cc.course!= c.id
    //            WHERE cc.userid = $USER->id AND FIND_IN_SET(3,cd.identifiedas)  AND e.roleid=5 and e.enrol IN('self','manual','auto')  AND DATE(FROM_UNIXTIME(c.startdate)) >= DATE(NOW()) AND cc.timecompleted IS NULL
    //            AND course.visible=1 AND c.id>1
    //            ";
    //     $upcoming_courses = $DB->get_record_sql($sql);
    //     return $upcoming_courses->countid;
    // }


    // public static function upcoming_coursenames($userid = false) {
    //     global $DB, $USER;
    //     if (!$userid) {
    //         $userid = $USER->id;
    //     }

    //     $sql = "SELECT course.id,course.fullname
    //             FROM
    //             {enrol} AS en
    //             JOIN {user_enrolments} AS ue ON ue.enrolid = en.id AND en.enrol IN('self','manual','auto')
    //             JOIN {course} AS course ON en.courseid = course.id
    //             JOIN {local_coursedetails} lcd ON course.id = lcd.courseid

    //             WHERE ue.userid  IN (
    //             SELECT ra.userid
    //             FROM {course} AS c
    //             JOIN {context} AS ctx ON c.id = ctx.instanceid
    //             JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
    //             WHERE c.id=course.id and ra.roleid=5 and ra.userid=$userid)
    //             and FIND_IN_SET(3,lcd.identifiedas)
    //             AND DATE(FROM_UNIXTIME(course.startdate)) > DATE(NOW()) AND course.visible=1 AND course.id>1
    //             ";

    //     $count = $this->tocheckcompleted($userid);
    //     if ($count) {
    //         $counted = implode(',', $count);
    //         $sql .= "and course.id NOT IN($counted)";
    //     }
    //     $upcoming_courses = $DB->get_records_sql($sql);

    //     if ($upcoming_courses) {
    //         $upcoming = $upcoming_courses;
    //     } else {
    //         $upcoming = "";
    //     }
    //     return $upcoming;
    // }
    /********end of the function*******/




    /******Function to the show the Completed Classroom's in the Classroom Training********/
    // public static function completed_courses1() {
    //     global $DB, $USER;
    //     $sql = "SELECT count(a.id) as completed FROM {facetoface} as a JOIN {local_facetoface_users} as lfu ON a.id=lfu.f2fid
    //           where a.course=1  and a.active IN(8,1) and lfu.userid=$USER->id ";

    //     $completed = $DB->get_record_sql($sql);

    //     return $completed->completed;
    // }


   /*****End of the code****/
    // public static function add_popular_courses($courseid, $mode = 'view') {

    //     global $DB, $USER, $CFG;
    //     $data = $DB->get_field('local_coursedetails', 'costcenterid', array('courseid' => $courseid));
    //     $popularcourses = new stdClass();

    //     $popularcourses->courseid = $courseid;
    //     $popularcourses->userid = $USER->id;
    //     $popularcourses->mode = $mode;
    //     $popularcourses->timecreated = time();

    //     $popularcourses->costcenterid = $data;
    //     $currentdate = date("d m Y");

    //     $selectquery = "SELECT * from {local_courses_popular} where courseid=$courseid and userid=$USER->id and DATE_FORMAT(FROM_UNIXTIME(timecreated),'%Y-%m-%d')='" . $currentdate . "'";
    //     //SELECT * FROM `mdl_local_courses_popular` where DATE_FORMAT(FROM_UNIXTIME(timecreated),'%Y-%m-%d')='2017-02-23'
    //     $resultdata = $DB->get_records_sql($selectquery);
    //     if (count($resultdata) == 0) {

    //         return $lastinsertid = $DB->insert_record('local_courses_popular', $popularcourses);
    //     } else {
    //         return null;
    //     }

    // }


} // end of class
