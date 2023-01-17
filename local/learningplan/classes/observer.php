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
        try{
            $learningplan_courses = $DB->get_records_sql("SELECT lpc.*
                FROM {local_learningplan_courses} AS lpc
                JOIN {local_learningplan_user} AS llu ON llu.planid = lpc.planid
                WHERE lpc.courseid = :courseid AND lower(lpc.nextsetoperator) LIKE 'and' AND llu.userid = :userid ", array('courseid' => $event->courseid, 'userid' => $event->relateduserid));
            if($learningplan_courses){
                foreach($learningplan_courses AS $lpcourse){
                    $courseid = $DB->get_field_sql("SELECT llc.courseid FROM {local_learningplan_courses} as llc WHERE llc.planid = :planid AND llc.sortorder > :sortorder AND lower(llc.nextsetoperator) LIKE 'and' ", ['planid' => $lpcourse->planid, 'sortorder' => $lpcourse->sortorder]);
                    if($courseid){
                        $learningplan_lib = new local_learningplan\lib\lib();
                        $enrol=$learningplan_lib->to_enrol_users($lpcourse->planid, $event->relateduserid, $courseid, false);
                    }
                }
            }
        }catch(\Exception $e){
            debugging($e->getMessage());
        }
    }
}