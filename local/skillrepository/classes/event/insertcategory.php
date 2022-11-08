<?php
namespace local_skillrepository\event;
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
 * @subpackage local_skillrepository
 */
use context_system;
use stdClass;
require_once($CFG->dirroot.'/local/costcenter/lib.php');
class insertcategory{
	
	public function create_skill_category($data) {
	    global $DB, $CFG, $USER;
		
		$systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
		$costcenter=$DB->get_field('user','open_costcenterid',array('id'=>$USER->id));
		$data = (object)$data;
		$newskill_category = new stdClass();
		
		$newskill_category->name = $data->name;
		$newskill_category->shortname = $data->shortname;
			
		if (!is_siteadmin()){
			$costcenter=$DB->get_field('user','open_costcenterid',array('id'=>$USER->id));
		} else {
			$costcenter = $data->costcenterid;
		}

	    if(!empty($data->parentid)){
			$newskill_category->parentid = $data->parentid;
	        $newskill_category->depth = 2;
			
			/* ---get next child item that need to provide--- */
			if (!$sortorder = $this->get_next_child_sortthread($data->parentid, 'local_skill_categories')) {
				return false;
			}
			$newskill_category->sortorder = $sortorder;
	    }else{
			$newskill_category->parentid = 0;
			$newskill_category->sortorder = 0;
	        $newskill_category->depth = 1;
	    }
		
		if($data->id > 0){
			$newskill_category->id = $data->id;
			$newskill_category->costcenterid=$costcenter;
			$newskill_category->timemodified = time();
			$newskill_category->usermodified = $USER->id;
			$DB->update_record('local_skill_categories', $newskill_category);
			$perform = $newskill_category->id;
		}else{
			$newskill_category->costcenterid=$costcenter;
			$newskill_category->timecreated = time();
			$newskill_category->usercreated = $USER->id;
			$perform = $DB->insert_record('local_skill_categories', $newskill_category);
		}
		
		// Update path (only possible after we know the category id.
		$pathupdate = new stdClass();
		$pathupdate->id = $perform;
		if(!empty($data->parentid)){
			$pathupdate->path = '/'.$data->parentid . '/' . $pathupdate->id;
		}else{
			$pathupdate->path = '/' . $pathupdate->id;
		}
		$newskill_category = $DB->update_record('local_skill_categories', $pathupdate);
	    return $newskill_category;
	}
    function get_next_child_sortthread($parentid, $table) {
        global $DB, $CFG;
        $maxthread = $DB->get_record_sql("SELECT MAX(sortorder) AS sortorder FROM {$CFG->prefix}{$table} WHERE parentid = ?", array($parentid));
        
        if (!$maxthread || strlen($maxthread->sortorder) == 0) {
            if ($parentid == 0) {
                // first top level item
                return $this->inttovancode(1);
            } else {
                // parent has no children yet
                return $DB->get_field('local_skill_categories', 'sortorder', array('id' => $parentid)) . '.' . $this->inttovancode(1);
            }
        }
        return $this->increment_sortorder($maxthread->sortorder);
    }
	
	function increment_sortorder($sortorder, $inc = 1) {
        if (!$lastdot = strrpos($sortorder, '.')) {
            // root level, just increment the whole thing
            return $this->increment_vancode($sortorder, $inc);
        }
        $start = substr($sortorder, 0, $lastdot + 1);
        $last = substr($sortorder, $lastdot + 1);
        // increment the last vancode in the sequence
        return $start . $this->increment_vancode($last, $inc);
    }
	
	/**
     * Increment a vancode by N (or decrement if negative)
     *
     */
    function increment_vancode($char, $inc = 1) {
        return $this->inttovancode($this->vancodetoint($char) + (int) $inc);
    }
	/**
     * Convert a integer to an vancode
     * @param integer $int integer to convert. Must be <= 'zzzzzzzzzz'
     * @return Vancode The Vancode representation of the specified integer
     */
	
	function inttovancode($int = 0) {
        $num = base_convert((int) $int, 10, 36);
        $length = strlen($num);
        return chr($length + ord('0') - 1) . $num;
    }
	
	/**
     * Convert a vancode to an integer
     * @param string $char Vancode to convert. Must be <= '9zzzzzzzzzz'
     * @return integer The integer representation of the specified vancode
     */
    function vancodetoint($char = '00') {
        return base_convert(substr($char, 1), 36, 10);
    }
}
