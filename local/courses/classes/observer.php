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

    public static function course_completed_notification(\core\event\course_completed $event){
        global $DB;
        $coursedetails = $DB->get_record('course',  array('id' => $event->courseid));
        $userinfo = core_user::get_user($event->relateduserid);
        if(class_exists('\local_courses\notification')){
            $notification = new \local_courses\notification($DB);
            $notification->send_course_completion_notification($coursedetails, $userinfo);
        }
    }
    public static function course_viewed(\core\event\course_viewed $event) {
        global $DB, $CFG, $USER, $COURSE;
        $canaccesscourse = \local_courses\courses::can_access_course($COURSE->id, $USER->id);
        if(!$canaccesscourse['status']){
            //redirect($CFG->wwwroot.'/my/dashboard.php', $message, null, NOTIFY_ERROR);
            print_error('nopermissiontoviewpage');
            die;
        }
    }
}

