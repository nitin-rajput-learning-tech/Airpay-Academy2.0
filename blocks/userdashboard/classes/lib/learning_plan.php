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


class learning_plan{

    // /******Function to the show the Inprogress LEP in the Classroom Training********/
    // public static function inprogress_lep() {
    //     global $DB, $USER, $CFG;
    //     $data = $DB->get_record('local_userdata', array('userid' => $USER->id));

    //     $sql = "select count(llp.id) as completed from {local_learningplan} llp JOIN {local_learningplan_user} as lla on llp.id=lla.planid where userid=$USER->id and llp.visible=1";
    //     $completed = $DB->get_record_sql($sql);

    //     return $completed->completed;
    // }



     public static function inprogress_lepnames($filter_text='', $mobile = false) {
        global $DB, $USER, $CFG;
        $sqlquery = "SELECT llp.id,llp.name as fullname, llp.description as description";
        $sqlcount = "SELECT COUNT(llp.id)";
        $sql = " from {local_learningplan} llp JOIN {local_learningplan_user} as lla on llp.id=lla.planid where userid={$USER->id} and lla.completiondate is NULL and status is NULL and llp.visible=1";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%$filter_text%%'";
        }
        $sql .= " ORDER BY lla.id desc";
        if ($mobile) {
            $completed = $DB->get_records_sql($sqlquery . $sql);
            $count = $DB->count_records_sql($sqlcount . $sql);
            return array($completed, $count);
        } else {
            $completed = $DB->get_records_sql($sqlquery . $sql);
            return $completed;
        }
    }
    /****End of the function****/


    /******Function to the show the Completed LEP in the Classroom Training********/
     public static function completed_lepnames($filter_text='', $mobile = false) {
        global $DB, $USER, $CFG;
        $sqlquery = "SELECT llp.id,llp.name as fullname, llp.description as description";
        $sqlcount = "SELECT COUNT(llp.id)";
        $sql = " FROM {local_learningplan} llp 
            JOIN {local_learningplan_user} as lla on llp.id=lla.planid 
            WHERE userid={$USER->id} and lla.completiondate is NOT NULL 
            AND status=1 and llp.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%{$filter_text}%%'";
        }
        $sql .= " ORDER BY lla.id desc";
        if ($mobile) {
            $completed = $DB->get_records_sql($sqlquery . $sql);
            $count = $DB->count_records_sql($sqlcount . $sql);
            return array($completed, $count);
        } else {
            $completed = $DB->get_records_sql($sqlquery . $sql);
            return $completed;
        }
    }
    /*****End of the code****/


} // end of class

