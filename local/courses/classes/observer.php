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
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
/**
 * Event observer for local_courses. Dont let other user to view unauthorized courses
 */
class local_courses_observer extends \core\event\course_viewed {
    /**
     * Triggered via course_viewed event.
     *
     * @param \core\event\course_viewed $event
     */
    public static function course_viewed(\core\event\course_viewed $event) {
        global $DB, $CFG, $USER, $COURSE;
        $systemcontext = context_system::instance();
        if (!(is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations',$systemcontext))) {
            $enroltypeslist  = $DB->get_records_sql_menu("select id, id as enrolid from {enrol} where courseid = $COURSE->id AND status = 0");
			$enroltypes = implode(',', $enroltypeslist);
			$exist = $DB->get_record_sql("select id  from {user_enrolments} where userid = $USER->id AND status = 0 AND enrolid IN ($enroltypes)");
            if (!$exist) {
                // $user_costcenter = $DB->get_field('user', 'open_costcenterid', array('id'=>$USER->id));
                // $course_costcenter = $DB->get_field('course', 'open_costcenterid', array('id'=>$COURSE->id));
                $user_costcenter =$DB->get_record('user',array('id'=>$USER->id),  $fields='id,open_costcenterid,open_departmentid');
                $course_costcenter =$DB->get_record('course',array('id'=>$COURSE->id),  $fields='id,open_costcenterid,open_departmentid');


                if (has_capability('local/costcenter:manage_ownorganization',$systemcontext)) {

                    if (($user_costcenter->open_costcenterid != $course_costcenter->open_costcenterid)) {
                        $message = get_string('notyourorgcourse_msg','local_courses');
                        redirect($CFG->wwwroot.'/local/courses/courses.php', $message, null, NOTIFY_ERROR);
                        die;
                    }

                }elseif (has_capability('local/costcenter:manage_owndepartments',$systemcontext)) {

                    if (($user_costcenter->open_costcenterid != $course_costcenter->open_costcenterid) || ( !in_array($user_costcenter->open_departmentid, explode(',', $course_costcenter->open_departmentid)))) {
                        $message = get_string('notyourdeptcourse_msg','local_courses');
                        redirect($CFG->wwwroot.'/local/courses/courses.php', $message, null, NOTIFY_ERROR);
                        die;
                    }
                }
            }

        }
    }

    /**
    * Event observer for local_courses. Dont let other user to view moodle deafult course categories
    */

    /**
     * Triggered via course_category_viewed event.
     *
     * @param \core\event\course_category_viewed $event
     */
    // public static function course_category_viewed(\core\event\course_category_viewed $event) {
    //     global $CFG;
   
    //     if (!is_siteadmin() ) {
    //         redirect($CFG->wwwroot.'/local/courses/index.php');
    //         die;
    //     }
    // }
    public static function course_completed_notification(\core\event\course_completed $event){
        global $DB;
        $coursedetails = $DB->get_record('course',  array('id' => $event->courseid));
        $userinfo = core_user::get_user($event->relateduserid);
        if(class_exists('\local_courses\notification')){
            $notification = new \local_courses\notification($DB);
            $notification->send_course_completion_notification($coursedetails, $userinfo);
        }
    }
    public static function grade_report_viewed(\core\event\grade_report_viewed $event) {
        global $DB,$USER,$CFG;
        $systemcontext = context_system::instance();
        if (!(is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations',$systemcontext))) {
            $enroltypeslist  = $DB->get_records_sql_menu("select id, id as enrolid from {enrol} where courseid = $event->courseid AND status = 0");
            $enroltypes = implode(',', $enroltypeslist);
            $exist = $DB->get_record_sql("select id  from {user_enrolments} where userid = $USER->id AND status = 0 AND enrolid IN ($enroltypes)");
            if (!$exist) {
                $user_costcenter = $DB->get_field('user', 'open_costcenterid', array('id'=>$USER->id));
                $course_costcenter = $DB->get_field('course', 'open_costcenterid', array('id'=>$event->courseid));
                if ($user_costcenter != $course_costcenter) {
                    $message = get_string('notyourorgcoursereport_msg','local_courses');
                    redirect($CFG->wwwroot.'/local/courses/courses.php', $message, null, NOTIFY_ERROR);
                    die;
                }
            }

        }
    }


    /**
     * Triggered via course_module_viewed event.
     *
     * @param \core\event\course_module_viewed $event
     */
    public static function module_viewed(\core\event\course_module_viewed $event) {
        global $DB, $CFG, $USER, $COURSE;
        $systemcontext = context_system::instance();
        if (!(is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations',$systemcontext))) {
            $sql = "select id, id as enrolid 
                    FROM {enrol} 
                    WHERE courseid = $COURSE->id AND status = 0 ";
            $enroltypeslist  = $DB->get_records_sql_menu($sql);
            $enroltypes = implode(',', $enroltypeslist);

            $sql = "select id  
                    FROM {user_enrolments} 
                    WHERE userid = $USER->id AND status = 0 AND enrolid IN ($enroltypes)";
            $exist = $DB->record_exists_sql($sql);

            if (!$exist) {
                $user_costcenter =$DB->get_record('user',array('id'=>$USER->id),  $fields='id,open_costcenterid,open_departmentid');
                $course_costcenter =$DB->get_record('course',array('id'=>$COURSE->id),  $fields='id,open_costcenterid,open_departmentid');


                if (has_capability('local/costcenter:manage_ownorganization',$systemcontext)) {
                    if (($user_costcenter->open_costcenterid != $course_costcenter->open_costcenterid)) {
                        $message = get_string('notyourorg_msg','local_courses');
                        redirect($CFG->wwwroot.'/local/courses/courses.php', $message, null, NOTIFY_ERROR);
                        die;
                    }

                }elseif (has_capability('local/costcenter:manage_owndepartments',$systemcontext)) {

                    if (($user_costcenter->open_costcenterid != $course_costcenter->open_costcenterid) || ($user_costcenter->open_departmentid != $course_costcenter->open_departmentid)) {
                        $message = get_string('notyourdept_msg','local_courses');
                        redirect($CFG->wwwroot.'/local/courses/courses.php', $message, null, NOTIFY_ERROR);
                        die;
                    }
                }
            }

        }
    }
}

