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
 * @copyright  2018 Maheshchandra <maheshchandra@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_userdashboard\lib;

use renderable;
use renderer_base;
use templatable;
use context_course;
use stdClass;


class certification{

    public static function inprogress_certification($filter_text='') {
        global $DB, $USER;
        $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_certification} AS lc 
                JOIN {local_certification_users} AS lcu ON lc.id=lcu.certificationid
                WHERE lc.status=1 AND lcu.userid={$USER->id} ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $sql .= " ORDER BY lcu.id desc";
        $coursenames = $DB->get_records_sql($sql);
        return $coursenames;
    }

    public static function completed_certification($filter_text='') {
            global $DB, $USER;
            $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_certification} as lc     
                    JOIN {local_certification_users} AS lcu ON lc.id=lcu.certificationid
                    WHERE  lc.status=4 and lcu.userid={$USER->id} ";
            if(!empty($filter_text)){
                $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
            }
            $sql .= " ORDER BY lcu.id desc";
            $coursenames = $DB->get_records_sql($sql);
            return $coursenames;
    }

    public static function gettotal_certification($filter_text=''){
            global $DB, $USER;
            $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_certification} AS lc 
                    JOIN {local_certification_users} AS lcu ON lc.id=lcu.certificationid
                    WHERE lc.status IN(1,4) AND lcu.userid={$USER->id} ";
            $coursenames = $DB->get_records_sql($sql);
            return count($coursenames);
    }

    public static function get_certification_attachment($programid){
        global $DB, $CFG;
        
        $fileitemid = $DB->get_field('local_certification', 'certificationlogo', array('id'=>$programid));
        $imgurl = false;
        if(!empty($fileitemid)){
            $sql = "SELECT * FROM {files} WHERE itemid = $fileitemid AND filename != '.' ORDER BY id DESC "; // LIMIT 1
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
        return $imgurl;
    }
} // end of class

