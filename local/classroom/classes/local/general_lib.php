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
 * @package   local
 * @subpackage classroom
 * @author eabyas  <info@eabyas.in>
**/
namespace local_classroom\local;
class general_lib{
	public function get_completion_count_from($moduleid, $userstatus, $date = NULL){
		global $DB;
		$params = array('moduleid' => $moduleid);
		switch($userstatus){
			case 'enrolled':
				$count_sql = "SELECT count(id) FROM {local_classroom_users} WHERE classroomid = :moduleid ";
				if(!is_null($date)){
					$count_sql .= " AND timecreated > :fromtime ";
					$params['fromtime'] = $date;
				}
			break;
			case 'completed':
				$count_sql = "SELECT count(id) FROM {local_classroom_users} WHERE classroomid = :moduleid AND completion_status = 1 ";
				if(!is_null($date)){
					$count_sql .= " AND completiondate > :fromtime ";
					$params['fromtime'] = $date;
				}
			break;
		}
		$count = $DB->count_records_sql($count_sql, $params);
		return $count;
	}
	public function get_custom_icon_details(){
		return ['componenticonclass' => 'classroom_icon', 'customimage_required' => True];
	}

	public function get_classroom_info($id){
        global $DB, $USER, $CFG;
        $classroom = $DB->get_record('local_classroom', array('id' => $id));
        if($classroom){
            $classroom->fullname = $classroom->name;
            $classroom->summary = $classroom->description;
            $classroom->points = $course->open_points;

            if(file_exists($CFG->dirroot.'/local/includes.php')){
                require_once($CFG->dirroot.'/local/includes.php');
                $includes = new \user_course_details();
            }
            $coursefileurl = (new \local_classroom\classroom)->classroom_logo($coursefileurl = $classroom->id);
            if($coursefileurl == false){
                $coursefileurl = $includes->get_classes_summary_files($classroom);
            }
            $classroom->isenrolled = $DB->record_exists('local_classroom_users', array('classroomid' => $classroom->id, 'userid' => $USER->id));
            $classroom->bannerimage = is_object($coursefileurl) ? $coursefileurl->out() : $coursefileurl;
            $classroom->category = ($DB->get_field('local_custom_category','fullname',array('id' => $classroom->open_category))) ;

            $ratinginfo = $DB->get_record('local_ratings_likes', array('module_id' => $classroom->id, 'module_area' => 'local_learningplan'));
            if($ratinginfo){
                $classroom->avgrating = $ratinginfo->module_rating;
                $classroom->ratedusers = $ratinginfo->module_rating_users;
                // $classroom->likes = $ratinginfo->module_like;
                // $classroom->dislikes = $ratinginfo->module_like_users - $ratinginfo->module_like;
            }
            return $classroom;
        }else{
            throw new \Exception("Classroom Not found");
        }
    }
}
