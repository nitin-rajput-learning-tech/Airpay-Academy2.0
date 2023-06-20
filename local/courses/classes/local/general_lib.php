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
 * @subpackage  courses
 * @author eabyas  <info@eabyas.in>
**/
namespace local_courses\local;
class general_lib{
	public function get_custom_data($fields = '*', $params){
		global $DB;
		$sql = "SELECT {$fields} FROM {course} WHERE 1=1 ";
		foreach($params AS $key => $value){
			if($key == 'unique_module')
				continue;
			$sql .= " AND {$key} =:{$key} ";
		}
		if((isset($params['unique_module']) && $params['unique_module'] ==  true) || (isset($params['id']) && $params['id'] > 0) ){
			$data = $DB->get_record_sql($sql, $params);
		}else{
			$data = $DB->get_records_sql($sql, $params);
		}
		return $data;
	}
	public function get_module_logo_url($courseid){
		global $CFG;
		if(file_exists($CFG->dirroot.'/local/includes.php')){
			require_once($CFG->dirroot.'/local/includes.php');
			$courseobject = get_course($courseid);
			$includes = new \user_course_details();
			$url_object = $includes->course_summary_files($courseobject);
			return $url_object;
		}
	}

	public function enable_enrollment_to_module($courseid, $user){
		$coursecontext = \context_course::instance($courseid);
		if(is_enrolled($coursecontext, $user, '', $onlyactive = true)){
			return true;
		}
		$params = array('id' => $courseid);
		$coursedata = $this->get_custom_data('*', $params);
		if(($coursedata->open_path == $user->open_path) &&
			($coursedata->open_departmentid == $user->open_departmentid
				|| $coursedata->open_departmentid == 0) &&
			($coursedata->open_subdepartment == $user->open_subdepartment
				|| $coursedata->open_subdepartment == 0)
		){
			$classname = "\\local_request\\api\\requestapi";
			if(class_exists($classname)){
				$class = new $classname();
				if($coursedata->selfenrol == 1 && $coursedata->approvalreqd == 1){
					if(method_exists($class, 'create')){
						$class->create('local_courses', $courseid);
					}
				}else if($coursedata->selfenrol == 1){
					if(method_exists($class, 'enroll_to_component')){
						$class->enroll_to_component('local_courses', $courseid, $user->id);
					}
				}
			}
		}
	}
	public function get_completion_count_from($moduleid, $userstatus, $date = NULL){
		global $DB;
		$params = array('moduleid' => $moduleid);
		switch($userstatus){
			case 'enrolled':
				$count_sql = "SELECT count(ue.id) FROM {user_enrolments} AS ue
					JOIN {enrol} AS e ON e.id = ue.enrolid
					WHERE e.enrol NOT IN ('classroom', 'program', 'learningplan') AND e.courseid = :moduleid ";
				if(!is_null($date)){
					$count_sql .= " AND ue.timecreated > :fromtime ";
					$params['fromtime'] = $date;
				}
			break;
			case 'completed':
				$count_sql = "SELECT count(cc.id) FROM {course_completions} AS cc
					JOIN {enrol} AS e ON e.courseid = cc.course AND e.enrol IN ('self', 'manual', 'auto', 'cohort')
					JOIN {user_enrolments} AS ue ON ue.enrolid = e.id AND ue.userid = cc.userid
					WHERE cc.course = :moduleid AND cc.timecompleted IS NOT NULL ";
				if(!is_null($date)){
					$count_sql .= " AND cc.timecompleted > :fromtime ";
					$params['fromtime'] = $date;
				}
			break;
		}
		$count = $DB->count_records_sql($count_sql, $params);
		return $count;
	}
	public function get_custom_icon_details(){
		return ['componenticonclass' => 'fa fa-book', 'customimage_required' => False];
	}

	/******Function to the show the inprogress course names in the E-learning Tab********/
    public static function inprogress_coursenames($filter_text='', $offset = 0, $limit = 10,$source = '') {
        global $DB, $USER;

        $sqlquery = "SELECT course.* ";

        $sql = " FROM {course} AS course
                JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid ";

        $sql .= " WHERE ue.userid = {$USER->id} AND course.id <> 1 AND course.visible=1 AND course.open_coursetype = 0";
        if($source == 'mobile'){
            $sql .= " AND course.open_securecourse != 1 ";
        }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('course.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }

        $sql .= " AND course.id NOT IN(SELECT course FROM {course_completions} WHERE course = course.id AND userid = {$USER->id} AND timecompleted IS NOT NULL) ";

        $sql .= ' order by ue.timecreated desc';

        $inprogress_courses = $DB->get_records_sql($sqlquery . $sql, $params, $offset, $limit);

        return $inprogress_courses;

    }

    public static function inprogress_coursenames_count($filter_text = '', $source = ''){
        global $USER, $DB;
        $sql = "SELECT COUNT(DISTINCT(course.id))  FROM {course} AS course
            JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
            JOIN {user_enrolments} ue ON e.id = ue.enrolid
            WHERE ue.userid = {$USER->id}
            AND course.id <> 1 AND course.visible = 1 AND course.id NOT IN(SELECT course FROM {course_completions} WHERE course = course.id AND userid = {$USER->id} AND timecompleted IS NOT NULL) AND course.open_coursetype = 0 ";
        if($source == 'mobile'){
            $sql .= " AND course.open_securecourse != 1 ";
        }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('course.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }
        $inprogress_count = $DB->count_records_sql($sql, $params);
        return $inprogress_count;
    }
    /*********End of the Function*********/

    /******Function to the show the Completed course names in the E-learning Tab********/
    public static function completed_coursenames($filter_text='', $offset = 0, $limit = 10, $source = '') {
        global $DB, $USER;

        $sqlquery = "SELECT cc.id as completionid,c.*";
        $sql = " FROM {course_completions} cc
                JOIN {course} c ON c.id = cc.course AND cc.userid = $USER->id
                JOIN {enrol} e ON c.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                WHERE ue.userid = {$USER->id}
                AND cc.timecompleted IS NOT NULL AND c.visible = 1 AND c.id > 1 AND c.open_coursetype = 0";
        if($source == 'mobile'){
           $sql .= " AND c.open_securecourse != 1 ";
        }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('c.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }
        $sql .= " ORDER BY cc.timecompleted DESC ";
        $coursenames = $DB->get_records_sql($sqlquery . $sql, $params, $offset, $limit);
        return $coursenames;
    }
    /********end of the Function****/

    public static function completed_coursenames_count($filter_text = '', $source = ''){
    	global $DB, $USER;

        $sql = "SELECT COUNT(DISTINCT(c.id))
                FROM {course_completions} cc
                JOIN {course} c ON c.id = cc.course AND cc.userid = {$USER->id}
                JOIN {enrol} e ON c.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid
                WHERE ue.userid = {$USER->id} AND c.visible = 1 AND c.id > 1
                AND cc.timecompleted IS NOT NULL AND c.open_coursetype = 0 ";

        if($source == 'mobile'){
            $sql .= " AND c.open_securecourse != 1 ";
        }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('c.fullname', ':coursename', false);
            $params['coursename'] = '%'.$filter_text.'%';
        }
        $completed_count = $DB->count_records_sql($sql, $params);
        return $completed_count;
    }
    public function get_courses_having_completion_criteria($courseid, $query = '', $offset = 0, $limit = 0){
    	global $DB, $CFG;
    	require_once($CFG->libdir.'/completionlib.php');
        $coursesSql = "SELECT DISTINCT c.id, c.fullname
            FROM {course} c
            LEFT JOIN {course_completion_criteria} cc ON cc.courseinstance = c.id AND cc.course = {$courseid}
            INNER JOIN {course_completion_criteria} ccc ON ccc.course = c.id
            WHERE c.enablecompletion = ".COMPLETION_ENABLED."  AND c.id <> :courseid

            AND c.open_path = (SELECT open_path FROM {course} WHERE id = :thiscourseid) AND c.open_coursetype = 0 ";
        $params = array('courseid' => $courseid, 'thiscourseid' => $courseid);
        if($query != ''){
            $coursesSql .= " AND ".$DB->sql_like('c.fullname', ":search", false);
            $params['search'] = "%$query%";
        }
        $courses = $DB->get_records_sql($coursesSql, $params, $offset, $limit);
        return $courses;
    }
    /******Function to the show the enrolled course names in the E-learning Tab********/
    public static function enrolled_coursenames($filter_text='', $offset = 0, $limit = 10, $source = '') {
        global $DB, $USER;

        $sqlquery = "SELECT course.*";

        $sql = " FROM {course} AS course
                JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid ";

        $sql .= " WHERE ue.userid = {$USER->id} AND course.id <> 1 AND course.visible=1 AND course.open_coursetype = 0 ";
        if($source == 'mobile'){
            $sql .= " AND course.open_securecourse != 1 ";
        }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('course.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }

        $sql .= ' order by ue.timecreated desc';
        $enrolled_courses = $DB->get_records_sql($sqlquery . $sql, $params, $offset, $limit);

        return $enrolled_courses;

    }
    public static function enrolled_coursenames_count($filter_text='', $source = ''){
        global $USER, $DB;
        $sql = "SELECT COUNT(DISTINCT(course.id))  FROM {course} AS course
            JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
            JOIN {user_enrolments} ue ON e.id = ue.enrolid
            JOIN {user} u ON u.id = ue.userid
            WHERE ue.userid = {$USER->id}
            AND course.id <> 1 AND course.visible = 1 AND u.id > 2 AND u.suspended = 0 AND u.deleted = 0 AND course.open_coursetype = 0 ";
        if($source == 'mobile'){
            $sql .= " AND course.open_securecourse != 1 ";
        }
        $params = [];
        if(!empty($filter_text)){
           $sql .= "   AND ".$DB->sql_like('course.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }
        $enrolled_count = $DB->count_records_sql($sql, $params);
        return $enrolled_count;
    }
    public static function enrolled_coursenames_formobile($filter_text='', $offset = 0, $limit = 10, $type = '', $source = '') {
        global $DB, $USER;

        $sqlquery = "SELECT course.*";

        $sql = " FROM {course} AS course
                JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol NOT IN ('classroom', 'program', 'learningplan')
                JOIN {user_enrolments} ue ON e.id = ue.enrolid ";
        if($type == 'recentlyaccessed'){
            $sql .= " JOIN {user_lastaccess} as ul ON ul.courseid = course.id AND ul.userid = $USER->id";
        }
        $sql .= " WHERE ue.userid = {$USER->id} AND course.id <> 1 AND course.visible=1 AND course.open_coursetype = 0 ";
        if($source == 'mobile'){
            $sql .= " AND course.open_securecourse = 0 ";
        }
        $params = [];
        if(!empty($filter_text)){
            $sql .= "   AND ".$DB->sql_like('course.fullname', ':coursename', false);
           $params['coursename'] = '%'.$filter_text.'%';
        }
        if ($type == 'recentlyaccessed') {
            $sql .= " ORDER BY ul.timeaccess DESC "; //LIMIT 10
        } else {
            $sql .= ' order by ue.timecreated desc';
        }

        $enrolled_courses = $DB->get_records_sql($sqlquery . $sql, $params, $offset, $limit);

        return $enrolled_courses;

    }
    public function get_course_info($id){
        global $DB, $CFG;
        require_once($CFG->dirroot.'/local/search/lib.php');
        $course = $DB->get_record('course', array('id' => $id));
        if($course){
            $course->points = $course->open_points;
            $course->category = ($DB->get_field('local_custom_category','fullname',array('id' => $course->open_category))) ;
            $course->isenrolled = is_enrolled(\context_course::instance($course->id), $USER->id, '', true);
            if($course->isenrolled){
                $course->requeststatus = MODULE_ENROLLED;
            }else{
                $course->requeststatus = MODULE_NOT_ENROLLED;
                if($course->approvalreqd == 1){
                    $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                    $requeststatus = $DB->get_field_sql($sql, array('componentid' => $course->id,'compname' => 'elearning', 'createdbyid'=>$USER->id));
                    if($requeststatus == 'PENDING'){
                        $course->requeststatus = MODULE_ENROLMENT_PENDING;
                    }
                }
            }
            $course->bannerimage = \local_search\output\searchlib::convert_urlobject_intoplainurl($course);

            $ratinginfo = $DB->get_record('local_ratings_likes', array('module_id' => $course->id, 'module_area' => 'local_courses'));
            if($ratinginfo){
                $course->avgrating = $ratinginfo->module_rating;
                $course->ratedusers = $ratinginfo->module_rating_users;
                // $course->likes = $ratinginfo->module_like;
                // $course->dislikes = $ratinginfo->module_like_users - $ratinginfo->module_like;
            }


            if($course->open_skill)
                $course->skill = ($DB->get_field('local_skill','name',array('id' => $course->open_skill))) ;

            if($course->open_level)
                $course->level = ($DB->get_field('local_course_levels','name',array('id' => $course->open_level))) ;

            $course->module = 'local_courses';
            return $course;
        }else{
            throw new \Exception('Course not found');
        }
    }
}
