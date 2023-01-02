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
 * @package BizLMS
 * @subpackage blocks_announcement
 */
namespace block_announcement\local;

class lib{
	public function create($data, $editoroptions = null){
		global $DB, $CFG, $USER;
        $record = new \stdClass();
        $record->name = $data->name;
        $record->startdate = $data->startdate;
        $record->enddate = $data->enddate;
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $record->timecreated = time();
        $record->courseid = 1;
        $record->visible = 1;
        $record->attachment = $data->attachment;
        $record->costcenterid = $data->open_costcenterid;
        $usercontext = (\local_users\lib\accesslib::get_module_context($USER->id));
        $tobereplace = "/draftfile.php/$usercontext->id/user/draft/";
        $replacewith = "/pluginfile.php/1/block_announcement/announcement/";
        $record->description = str_replace($tobereplace,$replacewith,$data->description['text']);

        $record->id = $DB->insert_record('block_announcement', $record);
	}
	
	public function update($data, $editoroptions = null){
		global $DB, $CFG, $USER;

		$data = (object)$data;

        $record = new \stdClass();
        $record->name = $data->name;
        $record->startdate = $data->startdate;
        $record->enddate = $data->enddate;
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $record->timecreated = time();
        $record->courseid = 1;
        $record->visible = 1;
        $record->attachment = $data->attachment;
        $record->costcenterid = $data->open_costcenterid;
        $usercontext = (\local_users\lib\accesslib::get_module_context($USER->id));
        $tobereplace = "/draftfile.php/$usercontext->id/user/draft/";
        $replacewith = "/pluginfile.php/1/block_announcement/announcement/";
        $record->description = str_replace($tobereplace,$replacewith,$data->description['text']);
        $record->id = $data->id;
        
        $DB->update_record('block_announcement', $record);
	}

	public function announcements($courseid, $limit = 0, $future = false){
        global $DB, $USER;
        $where = '';
        
        $params = array('courseid' => 1);
        $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
	   
 		$now = time();
		$twoweeksafter =strtotime('+2 week',$now);
		$beginOfDay = strtotime("today", time());
		$endOfDay   = strtotime("tomorrow", $beginOfDay) - 1;

    	$announcements_sql = "SELECT * FROM {block_announcement} WHERE courseid = :courseid";

    	if($future){
    		$announcements_sql .= " AND (startdate >= {$beginOfDay}) OR (startdate <= {$endOfDay}) ";
    		$announcements_sql .= " AND visible=1 ";
    	}
		
		$user_data = $DB->get_record('user' ,array('id' => $USER->id),'open_path');
		$open_path = explode('/',$user_data->open_path);
		$open_costcenterid = $open_path[1];

		if(has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
	        if(!is_siteadmin()){
				$announcements_sql .= " AND costcenterid={$open_costcenterid} ";
			}
		}else{

			$announcements_sql .= " AND costcenterid={$open_costcenterid} ";
		
			$announcements_sql .= " AND visible=1";
		}

       	$announcements_sql .= " ORDER BY id DESC ";
        $limit_start=0;
        $limit_end=3;
        $announcements = $DB->get_records_sql($announcements_sql, $params,$limit_start,$limit_end);
        return $announcements;
    }



    public function announcements_count($courseid, $limit = 0, $future = false){
        global $DB, $USER;

        $params = array('courseid' => 1);
        $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
	   
 		$now = time();
		$twoweeksafter =strtotime('+2 week',$now);
    	$announcements_sql = "SELECT count(id) AS total FROM {block_announcement} WHERE courseid = :courseid";

    	if($future){ //future is the variable to view futureand past announcements. 
    		$announcements_sql .= " AND (startdate=0 OR startdate>{$now}) AND (enddate=0 OR enddate<{$twoweeksafter}) ";
    		$announcements_sql .= " AND visible=1 ";
    	}
		
			$user_data = $DB->get_record('user' ,array('id' => $USER->id), 'open_path');
			$open_path = explode('/',$user_data->open_path);
			$open_costcenterid = $open_path[1];
			if(has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
	        	if(!is_siteadmin()){
					$announcements_sql .= " AND costcenterid={$open_costcenterid} ";
				}
			}else{
				$announcements_sql .= " AND costcenterid={$open_costcenterid} ";
				$announcements_sql .= " AND visible=1";
			}
        $announcements_totalcount = $DB->count_records_sql($announcements_sql, $params);
        return $announcements_totalcount;
    }


    public function get_announcement_details($courseid, $limit = 0, $future = false){
        global $DB, $USER;
        
        $params = array('courseid' => 1);
        $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
	   
 		$now = time();
		$twoweeksafter =strtotime('+2 week',$now);
    	$announcements_sql = "SELECT * FROM {block_announcement} WHERE courseid = :courseid";

    	if($future){ //future is the variable to view futureand past announcements. 
    		$announcements_sql .= " AND (startdate=0 OR startdate>{$now}) AND (enddate=0 OR enddate<{$twoweeksafter}) ";
    		$announcements_sql .= " AND visible=1 ";
    	}

		$user_data = $DB->get_record('user' ,array('id' => $USER->id), 'open_path');
		$open_path = explode('/',$user_data->open_path);
		if(array_key_exists("1",$open_path))
		$open_costcenterid = $open_path[1];

		if(has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
	        if(!is_siteadmin()){
				$announcements_sql .= " AND costcenterid={$open_costcenterid} ";
	        }
		}else{
			$announcements_sql .= " AND costcenterid={$open_costcenterid} ";
		
			$announcements_sql .= " AND visible=1";
		}

       	$announcements_sql .= " ORDER BY id DESC ";
        $announcements = $DB->get_records_sql($announcements_sql, $params,$limit);
        return $announcements;
    }

    public function announcement_icon($itemid, $blockinstanceid) {
	    global $DB, $CFG, $USER, $OUTPUT;
	    $file = $DB->get_record('files', array('itemid' => $itemid,'filearea'=>'announcement'));
	    if (empty($file)) {
	        $defaultlogo = $OUTPUT->image_url('sample_announcement', 'block_announcement');
	        $logo = $defaultlogo;
	    } else {
	        $context = (new \block_announcement\lib\accesslib())::get_module_context();
	        $fs = \get_file_storage();
	        $files = $fs->get_area_files($context->id, 'block_announcement', 'announcement', $file->itemid, 'filename', false);
	        $url = array();
	    if(!empty($files)){
	        foreach ($files as $file) {
	            $isimage = $file->is_valid_image();
	            $filename = $file->get_filename();
	            $ctxid = $file->get_contextid();
	            $component = $file->get_component();
	            $itemid = $file->get_itemid();
	            if ($isimage) {
	                $url[] = $CFG->wwwroot."/pluginfile.php/$ctxid/block_announcement/announcement/$itemid/$filename";
	            }
	        }
	        if(!empty($url[0])){
	            $logo = $url[0];
	        }else{
	            $defaultlogo = $OUTPUT->image_url('sample_announcement', 'block_announcement');
	            $logo = $defaultlogo;
	        }
	    } else{
	        return $OUTPUT->image_url('sample_announcement', 'block_announcement');
	    }
	}
	return $logo;
	}
}
