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


class programs{

    public static function inprogress_programs($filter_text='') {
        global $DB, $USER;
        // $sql = "SELECT lp.id,lp.name AS fullname,lp.startdate,lp.enddate,lp.description  FROM {local_program} AS lp 
        //         JOIN {local_program_users} AS lpu ON lp.id=lpu.programid
        //         WHERE lp.status=1 AND lpu.userid=$USER->id ";
        // if(!empty($filter_text)){
        //     $sql .= " AND lp.name LIKE '%%$filter_text%%'";
        // }

        $sql = "SELECT bc.id, bc.name AS fullname, bc.stream, bc.description
                  FROM {local_program} AS bc
                  JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                 WHERE bcu.userid = {$USER->id} AND bcu.programid NOT IN (SELECT programid
                        FROM {local_program_users} WHERE completion_status = 1 AND completiondate > 0
                            AND userid = {$USER->id} ) AND bc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }

        $sql .= " ORDER BY bcu.id desc";
        $coursenames = $DB->get_records_sql($sql);
        // print_object($sql);
        // print_object($coursenames);
        // $_SESSION['classpro'] = count($coursenames);
        return $coursenames;
    }


    public static function completed_programs($filter_text='') {
            global $DB, $USER;
            // $sql = "SELECT lp.id,lp.name AS fullname,lp.startdate,lp.enddate,lp.description  FROM {local_program} as lp     
            //         JOIN {local_program_users} AS lpu ON lp.id=lpu.programid
            //         WHERE  lp.status=4 and lpu.userid=$USER->id ";
            // if(!empty($filter_text)){
            //     $sql .= " AND lp.name LIKE '%%$filter_text%%'";
            // }
            $sql = "SELECT bc.id, bc.name AS fullname, bc.stream, bc.description
                      FROM {local_program} as bc
                      JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                     WHERE bcu.completion_status = 1 AND bcu.completiondate > 0
                            AND bcu.userid = {$USER->id} and bc.visible=1";
            if(!empty($filter_text)){
                $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
            }
            $sql .= " ORDER BY bcu.id desc";
            $coursenames = $DB->get_records_sql($sql);
            $_SESSION['classcom'] = count($coursenames);
            return $coursenames;
    }


    public static function cancelled_programs($filter_text=''){
            global $DB,$USER;
            $sql = "SELECT lp.id,lp.name AS fullname,lp.startdate,lp.enddate,lp.description  
                FROM {local_program} AS lp 
                JOIN {local_program_users} AS lpu ON lp.id=lpu.programid
                WHERE lp.status=3 AND lpu.userid={$USER->id} and lp.visible=1 ";
            if(!empty($filter_text)){
                $sql .= " AND lp.name LIKE '%%{$filter_text}%%'";
            }
            $sql .= " ORDER BY lpu.id desc";
            $cancelled_classsroom = $DB->get_records_sql($sql);
            return $cancelled_classsroom;
    }


    public static function gettotal_programs(){
            global $DB, $USER;
            $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description 
                FROM {local_program} AS lc 
                JOIN {local_program_users} AS lcu ON lc.id=lcu.programid
                WHERE lc.status IN(1,4) AND lcu.userid={$USER->id} and lc.visible=1 ";
            $coursenames = $DB->get_records_sql($sql);
            return count($coursenames);
    }


    public static function get_program_attachment($programid){
        global $DB, $CFG;
        
        $fileitemid = $DB->get_field('local_program', 'programlogo', array('id'=>$programid));
        $imgurl = false;
        if(!empty($fileitemid)){
            $sql = "SELECT * FROM {files} WHERE itemid = $fileitemid AND filename != '.' ORDER BY id DESC ";//LIMIT 1
            $filerecord = $DB->get_record_sql($sql);
        }
            if($filerecord!=''){
            $imgurl = file_encode_url($CFG->wwwroot."/pluginfile.php", '/' . $filerecord->contextid . '/' . $filerecord->component . '/' .$filerecord->filearea .'/'.$filerecord->itemid. $filerecord->filepath. $filerecord->filename);
            }
            if(empty($imgurl)){
                $dir = $CFG->wwwroot.'/local/costcenter/pix/course_images/image3.jpg';
                for($i=1; $i<=10; $i++) {
                    $image_name = $dir;
                    $imgurl = $image_name;
                    break;
                }
            }
        //}
        return $imgurl;
    }

} // end of class

