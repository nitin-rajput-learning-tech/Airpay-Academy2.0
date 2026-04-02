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
 * Version details
 *
 * @package    block_achievements
 * @copyright  2017 eAbyas info solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG;

class achievements{
    
    function get_user_completed_elearningcourses($userid=false){
        global $DB,$USER;
        if($userid){
			$userid = $userid;
		}else{
			$userid = $USER->id;
		}
        $enrolledcourses = enrol_get_users_courses($userid);
        $usercourses = array();
        if($enrolledcourses){
            foreach($enrolledcourses as $enrolledcourse){
                $usercourses[$enrolledcourse->id] = $enrolledcourse->id;
            }
        }
        $imp_usercourses = implode(',',$usercourses);
        
        if($usercourses){
			$table=" {course} ";
			// $and = " AND CONCAT(',',3,',') LIKE CONCAT('%,',cd.open_identifiedas,',%') ";
			$sql="SELECT cc.id, cc.course FROM $table AS cd
                JOIN {course_completions} AS cc ON cd.id = cc.course
                WHERE cd.id IN ($imp_usercourses) AND cc.timecompleted!=''
                AND cc.userid = $userid ";
            $etypecoursescomp = $DB->get_records_sql_menu($sql);				
        }
        return $etypecoursescomp;
    }
    
	function get_user_certificates($userid=false){
        global $DB,$USER;
		if($userid){
			$userid = $userid;
		}else{
			$userid = $USER->id;
		}
        $etypecoursescomp = $this->get_user_completed_elearningcourses($userid,$xp=false);
        $certificates = null;
        if($etypecoursescomp){
            $imp_etypecoursescomp = implode(',',$etypecoursescomp);
            $certificates = $DB->get_records_sql("SELECT c.id,c.name,cm.id AS moduleid,c.course
				FROM {certificate} AS c
				JOIN {certificate_issues} AS ci ON c.id = ci.certificateid
				JOIN {course_modules} AS cm ON c.id=cm.instance
				JOIN {modules} AS m ON cm.module=m.id
				WHERE c.course IN ($imp_etypecoursescomp) AND m.name='certificate' AND ci.userid = $userid");
        }
        return $certificates;
    }
	
	
	function get_user_credits($creditscount = false,$userid=false){
        global $DB,$USER;
        if($userid){
			$userid = $userid;
		}else{
			$userid = $USER->id;
		}
        $etypecoursescomp = $this->get_user_completed_elearningcourses($userid);
		
        $credits = null;
		if($etypecoursescomp){
			if($creditscount){
				$imp_etypecoursescomp = implode(',',$etypecoursescomp);
				$count = $DB->get_record_sql("SELECT SUM(cd.open_points) as count
					FROM {course} AS cd
					JOIN {course_completions} as ccd on cd.id=ccd.course
					WHERE cd.id IN ($imp_etypecoursescomp) and ccd.userid=$userid");
				$credits = $count->count;
			}else{
				$imp_etypecoursescomp = implode(',',$etypecoursescomp);
				$credits = $DB->get_records_sql("SELECT cd.id,  cd.open_points
					FROM {course} AS cd
					JOIN {course_completions} as ccd on cd.id=ccd.course
					WHERE cd.id IN ($imp_etypecoursescomp) and ccd.userid=$userid");
			}
		}
        return $credits;
    }
}
