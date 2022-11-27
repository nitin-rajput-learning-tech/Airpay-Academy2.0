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
}