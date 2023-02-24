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
 * @subpackage program
 * @author eabyas  <info@eabyas.in>
**/
namespace local_program\local;
class general_lib{
	public function get_custom_data($fields = '*', $params){
		global $DB;
		$sql = "SELECT {$fields} FROM {local_program} WHERE 1=1 ";
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
	public function get_module_logo_url($programid){
		global $CFG;
		if(file_exists($CFG->dirroot . '/local/includes.php')){
			require_once($CFG->dirroot . '/local/includes.php');
	    	$includes = new \user_course_details();
			$program = $this->get_custom_data('id, name, programlogo', array('id' => $programid));
			if ($program->programlogo > 0) {
	    		$programlogo_url = (new \local_program\program)->program_logo($program->programlogo);
				if ($programlogo_url == false) {
	        		$programlogo_url = $includes->get_classes_summary_files($program);
	    		}
			} else {
	        	$programlogo_url = $includes->get_classes_summary_files($program);	
			}
		}else{
			$programlogo_url = False;
		}
		return $programlogo_url;
	}
	public function get_completion_count_from($moduleid, $userstatus, $date = NULL){
		global $DB;
		$params = array('moduleid' => $moduleid);
		switch($userstatus){
			case 'enrolled':
				$count_sql = "SELECT count(id) FROM {local_program_users} WHERE programid = :moduleid ";
				if(!is_null($date)){
					$count_sql .= " AND timecreated > :fromtime ";
					$params['fromtime'] = $date;
				}
			break;
			case 'completed':
				$count_sql = "SELECT count(id) FROM {local_program_users} WHERE programid = :moduleid AND completion_status =1 ";
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
		return ['componenticonclass' => 'program_icon', 'customimage_required' => True];
	}
	 public function get_program_info($id){
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot.'/local/search/lib.php');
        $program = $DB->get_record('local_program', array('id' => $id));
        if($program){
            $program->fullname = $program->name;
            $program->summary = $program->description;
            $program->points = $course->open_points;

            if(file_exists($CFG->dirroot.'/local/includes.php')){
                require_once($CFG->dirroot.'/local/includes.php');
                $includes = new \user_course_details();
            }
            $coursefileurl = (new \local_program\program)->program_logo($coursefileurl = $program->programlogo);
            if($coursefileurl == false){
                $coursefileurl = $includes->get_classes_summary_files($program);
            }
            $program->isenrolled = $DB->record_exists('local_program_users', array('programid' => $program->id, 'userid' => $USER->id));
            $waitlist = $DB->get_field('local_program_waitlist','id',array('programid' => $list->id,'userid'=>$USER->id,'enrolstatus'=>0));
            $program->requeststatus = MODULE_NOT_ENROLLED;
            $certificate_code = ($DB->get_field('tool_certificate_issues','code',array('moduletype'=> 'program','moduleid' => $program->id, 'userid' => $USER->id))) ;
            $program->certificateid = $certificate_code ? $certificate_code : '';
            if($waitlist > 0){
                $program->requeststatus = MODULE_ENROLMENT_WAITING;
            }else{
                if($program->isenrolled){
                    $program->requeststatus = MODULE_ENROLLED;
                }else{
                    if($program->approvalreqd == 1){
                        $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                        $requeststatus = $DB->get_field_sql($sql, array('componentid' => $program->id,'compname' => 'program', 'createdbyid'=>$USER->id));
                        if($requeststatus == 'PENDING'){
                            $program->requeststatus = MODULE_ENROLMENT_PENDING;
                        }
                    }
                }
            }
            $program->bannerimage = is_object($coursefileurl) ? $coursefileurl->out() : $coursefileurl;
            $program->category = ($DB->get_field('local_custom_category','fullname',array('id' => $program->open_category))) ;
            $program_capacity_check = (new \local_program\program)->program_capacity_check( $program->id);
            $program->enrolment_status_message = 0;
            if($program_capacity_check && $program->status == 1 && !$program->isenrolled &&  $program->allow_waitinglistusers == 0){
                $program->enrolment_status_message = 1;
            }else if($program->nomination_startdate > 0 && $program->nomination_startdate >  time()){
                $program->enrolment_status_message = 2;
            }else if($program->nomination_enddate > 0 && $program->nomination_enddate < time()){
                $program->enrolment_status_message = 3;
            }
            $program->coursecount = $DB->count_records_sql("SELECT count(c.id) FROM {course} AS c JOIN {local_program_courses} AS lcc ON lcc.courseid = c.id WHERE lcc.programid = :programid ", array('programid' => $program->id));

            $ratinginfo = $DB->get_record('local_ratings_likes', array('module_id' => $program->id, 'module_area' => 'local_learningplan'));
            if($ratinginfo){
                $program->avgrating = $ratinginfo->module_rating;
                $program->ratedusers = $ratinginfo->module_rating_users;
                // $program->likes = $ratinginfo->module_like;
                // $program->dislikes = $ratinginfo->module_like_users - $ratinginfo->module_like;
            }
            $program->module = 'local_program';
            return $program;
        }else{
            throw new \Exception("program Not found");
        }
    }
}