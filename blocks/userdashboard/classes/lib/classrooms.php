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
use file_encode_url;


class classrooms{

    public static function completed_classrooms($filter_text='', $mobile = false, $page = 0, $perpage = 10) {
        global $DB, $USER;
        $sqlquery = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description ";
        $sqlcount = "SELECT COUNT(lc.id) ";
        $sql = " FROM {local_classroom} as lc   
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE  lc.status=4 and lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $coursenames = $DB->get_records_sql($sqlquery . $sql, array(), $page * $perpage, $perpage);
        $_SESSION['classcom'] = count($coursenames);
        if ($mobile) {
            $completed_classroomcount = $DB->count_records_sql($sqlcount . $sql);
            return array($coursenames, $completed_classroomcount);
        } else {
            return $coursenames;
        }
    }
    /**********End of the function********/

   
    public static function cancelled_classsroom($filter_text=''){
        global $DB,$USER;
        $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status=3 AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $cancelled_classsroom = $DB->get_records_sql($sql);
        return $cancelled_classsroom;
    }


    /**
     * [inprogress_classrooms description]
     * @return [type] [description]
     */
    public static function inprogress_classrooms($filter_text='', $mobile = false, $page = 0, $perpage = 10) {
        global $DB, $USER;
        $sqlquery = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description ";
        $sqlcount = "SELECT COUNT(lc.id) ";
        $sql = " FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status=1 AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $coursenames = $DB->get_records_sql($sqlquery . $sql, array(), $page * $perpage, $perpage);
        $_SESSION['classpro'] = count($coursenames);
        if ($mobile) {
            $inprogress_classroomcount = $DB->count_records_sql($sqlcount . $sql);
            return array($coursenames, $inprogress_classroomcount);
        } else {
            return $coursenames;
        }
    }


    public static function gettotal_classrooms($mobile = false){
        global $DB, $USER;
        $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status IN(1,4) AND lcu.userid={$USER->id} and lc.visible=1  ";
        $coursenames = $DB->get_records_sql($sql);
        if($mobile){
          return array(count($coursenames));  
        } else {
            return count($coursenames);
        }
    }
    /*********end of the function******/

    



    /**
    * Returns url/path of the facetoface attachment if exists, else false
    *
    * @param int $iltid, facetoface id
    */
    public static function get_ilt_attachment($iltid){
        global $DB, $CFG;
        
        $fileitemid = $DB->get_field('local_classroom', 'classroomlogo', array('id'=>$iltid));
        $imgurl = false;
        if(!empty($fileitemid)){
            $sql = "SELECT * FROM {files} WHERE itemid = $fileitemid AND filename != '.' ORDER BY id DESC ";// LIMIT 1
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

