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


class bootcamp{

    public static function inprogress_bootcamps($filter_text='') {
        global $DB, $USER;
        $sql = "SELECT bc.id, bc.name AS fullname, bc.stream, bc.description
                  FROM {local_program} AS bc
                  JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                 WHERE bcu.userid = $USER->id AND bcu.programid NOT IN (SELECT programid
                        FROM {local_program_users} WHERE completion_status = 1 AND completiondate > 0
                            AND userid = {$USER->id} ) and bc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }
        $inprogress_bootcamps = $DB->get_records_sql($sql);
        return $inprogress_bootcamps;
    }
    public static function completed_bootcamps($filter_text='') {
            global $DB, $USER;
            $sql = "SELECT bc.id, bc.name AS fullname, bc.stream, bc.description
                      FROM {local_program} as bc
                      JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                     WHERE bcu.completion_status = 1 AND bcu.completiondate > 0
                            AND bcu.userid = {$USER->id} and bc.visible=1 ";
            if(!empty($filter_text)){
                $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
            }
            $completed_bootcamps = $DB->get_records_sql($sql);
            $completed_count = count($completed_bootcamps);
            return $completed_bootcamps;
    }


    public static function gettotal_bootcamps(){
            global $DB, $USER;
            $sql = "SELECT bc.id,bc.name AS fullname, bc.stream, bc.description  FROM {local_program} AS bc
                    JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                    WHERE bc.status IN(1,4) AND bcu.userid={$USER->id} and bc.visible=1 ";
            $coursenames = $DB->get_records_sql($sql);
            return count($coursenames);
    }

} // end of class

