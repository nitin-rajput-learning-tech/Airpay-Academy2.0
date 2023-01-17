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
 * @package    local_learningplan
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
/**
 * Event observer for local_learningplan. Dont let other user to view unauthorized courses
 */
class local_learningplan_observer extends \core\event\course_viewed {

    public static function course_enrolments_trigger(\core\event\course_completed $event){
        global $DB;
        $learningplandetails = $DB->get_record('local_learningplan',  array('id' => $event->courseid));
        $userinfo = core_user::get_user($event->relateduserid);
        if(class_exists('\local_learningplan\notification')){
            $notification = new \local_learningplan\notification($DB);
            $notification->send_course_completion_notification($learningplandetails, $userinfo);
        }
    }
}