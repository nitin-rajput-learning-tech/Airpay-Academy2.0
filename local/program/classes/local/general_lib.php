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
}