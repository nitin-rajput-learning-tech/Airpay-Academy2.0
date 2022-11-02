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

class evaluations{

    public static function inprogress_evaluations($filter_text='', $mobile = false) {
        global $DB, $USER;
        // $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_classroom} as lc
        //         JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
        //         WHERE  lc.status=4 and lcu.userid=$USER->id ";
        $sqlquery = "SELECT a.*, eu.creatorid, eu.timemodified as joinedate";
        $sqlcount = "SELECT COUNT(DISTINCT(a.id))";
        $sql =" FROM {local_evaluations} a , {local_evaluation_users} eu
            WHERE a.plugin = 'site' AND a.id = eu.evaluationid AND eu.userid = {$USER->id}
            AND instance = 0 AND a.visible = 1
            AND a.id NOT IN (SELECT evl.id from {local_evaluations} evl, {local_evaluation_completed} lec WHERE lec.evaluation = evl.id AND lec.userid = {$USER->id})
            AND a.evaluationmode LIKE 'SE' AND a.deleted != 1 "; //

        //$sql ="SELECT a.*, eu.creatorid, eu.timemodified as joinedate from {local_evaluations} a, {local_evaluation_users} eu where a.id NOT IN (SELECT evl.id from {local_evaluations} evl, {local_evaluation_completed} lec where lec.evaluation = evl.id AND lec.userid = $USER->id) ";
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%{$filter_text}%%' ";
        }
        $sql .=" order by eu.timecreated DESC";
        $inprogress_evaluations = $DB->get_records_sql($sqlquery . $sql);
        if ($mobile) {
            $inprogress_evaluationscount = $DB->count_records_sql($sqlcount . $sql);
            return array($inprogress_evaluations, $inprogress_evaluationscount);
        }
        //$_SESSION['classcom'] = count($coursenames);
        else {
            return $inprogress_evaluations;
        }
    }
    /**********End of the function********/


    public static function completed_evaluations($filter_text='', $mobile = false){
        global $DB,$USER;
        $sqlquery = "SELECT a.*, eu.timemodified as joinedate";
        $sqlcount = "SELECT COUNT(DISTINCT(a.id))";
        $sql = " from {local_evaluations} a, {local_evaluation_completed} ec, {local_evaluation_users} eu where a.plugin = 'site' AND ec.evaluation = a.id AND ec.userid = {$USER->id} AND a.id = ec.evaluation AND eu.userid = {$USER->id} AND a.evaluationmode LIKE 'SE' AND a.deleted != 1  ";
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%$filter_text%%'";
        }
        $sql .=" order by ec.timemodified DESC";
        $completed_evaluations = $DB->get_records_sql($sqlquery . $sql);
        if ($mobile) {
            $completed_evaluationscount = $DB->count_records_sql($sqlcount . $sql);
            return array($completed_evaluations, $completed_evaluationscount);
        } else {
        return $completed_evaluations;
        }
    }

} // end of class

