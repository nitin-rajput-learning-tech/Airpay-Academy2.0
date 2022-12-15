<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 */

namespace local_myteam\output;

defined('MOODLE_INTERNAL') || die;
use stdClass;

class team_status_lib{

	//getting team members of logged in user
	public function get_team_members($count = false,$stable = ''){
		global $DB, $USER;
		$countsql = " SELECT count(u.id)";
		$selectsql = "SELECT u.* ";
		$sql =" FROM {user} as u
				WHERE u.open_supervisorid = :loggedinuserid AND u.id != :userid
				AND u.confirmed = :confirmed AND u.suspended = :suspended AND u.deleted = :deleted AND u.id > 2 ";
		$params = array();
		$params['loggedinuserid'] = $USER->id;
		$params['userid'] =  $USER->id;;
		$params['deleted'] = 0;
		$params['suspended'] = 0;
		$params['confirmed'] = 1;

		if($count){
			$get_team_members = $DB->count_records_sql($countsql.$sql,$params);
		}else{
			$get_team_members = $DB->get_records_sql($selectsql.$sql,$params,$stable->start,$stable->length);
		}
		return $get_team_members;
	}
	
	
	public function get_colorcode_tm_dashboard($score, $total){
		if($total == 0){
			$total = 1;
		}
		$totalpercentage = ($score/$total)*100;
		
		if($totalpercentage == 100){
			$color = 'indianred';
		}elseif($totalpercentage < 50){
			$color = 'green';
		}else{
			$color = 'yellow';
		}
		return $color;
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
            $sql = "SELECT c.id,c.name,c.course
					 FROM {certificate} AS c
					 JOIN {certificate_issues} AS ci ON c.id = ci.certificateid
					 WHERE ci.userid = :userid";
	        $params = array();
	        $params['userid'] = $userid;
	        list($relatedecoursecompsql, $relatedcoursecompparams) = $DB->get_in_or_equal($etypecoursescomp, SQL_PARAMS_NAMED, 'course');
	        $params = array_merge($params,$relatedcoursecompparams);
	        $sql .= " AND c.course $relatedecoursecompsql";
	        $certificates = $DB->get_records_sql($sql,$params);
        }
        return $certificates;
    }
    function get_user_badges($userid=false){
        global $DB,$USER;
        if($userid){
			$userid = $userid;
		}else{
			$userid = $USER->id;
		}
        $etypecoursescomp = $this->get_user_completed_elearningcourses($userid,$xp=false);
        $badges = null;
        if($etypecoursescomp){
            $sql = "SELECT b.id,b.name,b.courseid 
						FROM {badge} AS b
						JOIN {badge_issued} AS bi ON b.id = bi.badgeid
						WHERE bi.userid = :userid";
            $params = array();
	        $params['userid'] = $userid;
	        list($relatedecoursecompsql, $relatedcoursecompparams) = $DB->get_in_or_equal($etypecoursescomp, SQL_PARAMS_NAMED, 'courseid');
	        $params = array_merge($params,$relatedcoursecompparams);
	        $sql .= " AND b.courseid $relatedecoursecompsql";
	        $badges = $DB->get_records_sql($sql,$params);
        }
        return $badges;
    }
    function get_user_credits($creditscount = false,$userid=false){
        global $DB,$USER;
        if($userid){
			$userid = $userid;
		}else{
			$userid = $USER->id;
		}
        $etypecoursescomp = $this->get_user_completed_elearningcourses($userid,$xp=true);
		
        $credits = null;
		if($etypecoursescomp){
			if($creditscount){
				$sql = "SELECT SUM(cd.xp) as count
							FROM {block_xp} AS cd
							WHERE cd.userid=:userid";

				$params = array();
		        $params['userid'] = $userid;
		        list($relatedecoursecompsql, $relatedcoursecompparams) = $DB->get_in_or_equal($etypecoursescomp, SQL_PARAMS_NAMED, 'courseid');
		        $params = array_merge($params,$relatedcoursecompparams);
		        $sql .= " AND cd.courseid $relatedecoursecompsql";

		        $count = $DB->get_record_sql($sql,$params);
				$credits = $count->count;
			}else{
				$sql = "SELECT cd.id, cd.courseid, cd.xp
							FROM {block_xp} AS cd
							WHERE cd.userid=:userid";
				$params = array();
		        $params['userid'] = $userid;
		        list($relatedecoursecompsql, $relatedcoursecompparams) = $DB->get_in_or_equal($etypecoursescomp, SQL_PARAMS_NAMED, 'courseid');
		        $params = array_merge($params,$relatedcoursecompparams);
		        $sql .= " AND cd.courseid $relatedecoursecompsql";

		        $credits = $DB->get_records_sql($sql,$params);
			}
		}
        return $credits;
    }
    function get_user_completed_elearningcourses($userid=false,$xp=false){
        global $DB,$USER;
        if($userid){
			$userid = $userid;
		}else{
			$userid = $USER->id;
		}
        $enrolledcourses = enrol_get_myteam_courses($userid);
        $usercourses = array();
        if($enrolledcourses){
            foreach($enrolledcourses as $enrolledcourse){
                $usercourses[$enrolledcourse->id] = $enrolledcourse->id;
            }
        }
        $imp_usercourses = implode(',',$usercourses);
        if($usercourses){
        	$params = array();
        	$params['userid'] = $userid;
			if($xp){
				$table= " {block_xp} ";
				$sql="SELECT cc.id, cd.courseid
                        FROM $table AS cd
                        JOIN {course} AS cc ON cd.courseid = cc.id
                        WHERE cd.userid = :userid
                         ";
			}else{
				$table=" {course} ";
				$sql="SELECT cc.id, cc.course
	                    FROM $table AS c
	                    JOIN {course_completions} AS cc ON c.id = cc.course
	                    WHERE cc.timecompleted!='' AND cc.userid = :userid
	                     ";
	            list($relatedusercoursesql, $relatedusercourseparams) = $DB->get_in_or_equal($usercourses, SQL_PARAMS_NAMED, 'courseid');
		        $params = array_merge($params,$relatedusercourseparams);
		        $sql .= " AND c.id $relatedusercoursesql";
			}
            $etypecoursescomp = $DB->get_records_sql_menu($sql,$params);
        }
        return $etypecoursescomp;
    }
}
