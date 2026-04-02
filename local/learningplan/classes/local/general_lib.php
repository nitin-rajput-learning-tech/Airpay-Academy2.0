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
 * @subpackage learningplan
 * @author eabyas  <info@eabyas.in>
**/
namespace local_learningplan\local;
class general_lib{
	public function get_custom_data($fields = '*', $params){
		global $DB;
		$sql = "SELECT {$fields} FROM {local_learningplan} WHERE 1=1 ";
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
	public function get_module_logo_url($planid){
		$planlib = new \local_learningplan\lib\lib();
		return $planlib->get_learningplansummaryfile($planid);
	}
	public function get_completion_count_from($moduleid, $userstatus, $date = NULL){
		global $DB;
		$params = array('moduleid' => $moduleid);
		switch($userstatus){
			case 'enrolled':
				$count_sql = "SELECT count(id) FROM {local_learningplan_user} WHERE planid = :moduleid ";
				if(!is_null($date)){
					$count_sql .= " AND timecreated > :fromtime ";
					$params['fromtime'] = $date;
				}
			break;
			case 'completed':
				$count_sql = "SELECT count(id) FROM {local_learningplan_user} WHERE planid = :moduleid AND status = 1 ";
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
		return ['componenticonclass' => 'fa fa-map', 'customimage_required' => False];
	}

	public function get_learningplan_info($id){
        global $DB, $CFG,$USER;
        require_once($CFG->dirroot.'/local/search/lib.php');
        $learningplans = $DB->get_record('local_learningplan', array('id' => $id));
        if($learningplans){
            $learningplans->fullname = $learningplans->name;
            $learningplans->summary = $learningplans->description;
            $learningplans->points = $learningplans->open_points;

            $coursefileurl = (new \local_learningplan\lib\lib)->get_learningplansummaryfile($coursefileurl = $learningplans->id);
            $learningplans->bannerimage =  is_object($coursefileurl) ? $coursefileurl->out() : $coursefileurl;
            $learningplans->category = ($DB->get_field('local_custom_category','fullname',array('id' => $learningplans->open_category))) ;
            $learningplans->isenrolled = $DB->record_exists('local_learningplan_user', array('planid' => $learningplans->id, 'userid' => $USER->id));
            $certificate_code = ($DB->get_field('tool_certificate_issues','code',array('moduletype'=> 'learningplan','moduleid' => $learningplans->id, 'userid' => $USER->id)));
            $learningplans->certificateid = $certificate_code ? $certificate_code : '';
            $learningplans->requeststatus = MODULE_NOT_ENROLLED;
            if($learningplans->isenrolled){
                $learningplans->requeststatus = MODULE_ENROLLED;
            }else{
                if($learningplans->approvalreqd){
                    $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                    $requeststatus = $DB->get_field_sql($sql, array('componentid' => $list->id,'compname' => 'learningplan', 'createdbyid'=>$USER->id));
                    if($requeststatus == 'PENDING'){
                        $learningplans->requeststatus = MODULE_ENROLMENT_PENDING;
                    }
                }
            }

            $learningplans->optional = $DB->count_records_sql("SELECT count(c.id) FROM {course} AS c JOIN {local_learningplan_courses} AS lpc ON lpc.courseid = c.id WHERE lpc.nextsetoperator LIKE 'or' AND lpc.planid = :planid ", array('planid' => $learningplans->id));
            $learningplans->mandatory = $DB->count_records_sql("SELECT count(c.id) FROM {course} AS c JOIN {local_learningplan_courses} AS lpc ON lpc.courseid = c.id WHERE lpc.nextsetoperator LIKE 'and' AND lpc.planid = :planid ", array('planid' => $learningplans->id));
            $learningplans->totalcourses = $learningplans->mandatory + $learningplans->optional;
            $ratinginfo = $DB->get_record('local_ratings_likes', array('module_id' => $learningplans->id, 'module_area' => 'local_learningplan'));
           
            $likecount = $DB->count_records('local_like', array('itemid' => $learningplans->id, 'likearea' => 'local_learningplan','likestatus'=>'1'));
            $dislikecount = $DB->count_records('local_like', array('itemid' => $learningplans->id, 'likearea' => 'local_learningplan','likestatus'=>'2'));

            if($ratinginfo){
                $learningplans->avgrating = $ratinginfo->module_rating ? $ratinginfo->module_rating : 0;
                $learningplans->ratingusers = $ratinginfo->module_rating_users ? $ratinginfo->module_rating_users : 0;
                $learningplans->likes = $likecount ? $likecount : 0;
                $learningplans->dislikes = $dislikecount ? $dislikecount : 0;
            }else{
				$learningplans->avgrating = 0;
                $learningplans->ratingusers = 0;
                $learningplans->likes = 0;
                $learningplans->dislikes = 0;
			}
            $learningplans->module = 'local_learningpath';
            $likeinfo = ($DB->get_field('local_like','likestatus',array('itemid' => $learningplans->id,'likearea' => 'local_learningplan','userid'=>$USER->id))) ;
			$learningplans->likedstatus =$likeinfo ? $likeinfo : '0';
            return $learningplans;
        }else{
            throw new \Exception("Learningplan Not found");
        }
    }
}
