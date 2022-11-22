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

		$data = (object)$data;
		$newskill_category = new stdClass();

		$newskill_category->name = $data->name;
		$newskill_category->shortname = $data->shortname;

		if (!is_siteadmin()){
			$costcenter = $DB->get_field('user','open_costcenterid',array('id'=>$USER->id));
		} else {
			$costcenter = $data->costcenterid;
		}

		if($data->id > 0){
			$newskill_category->id = $data->id;
			$newskill_category->costcenterid = $costcenter;
			$newskill_category->timemodified = time();
			$newskill_category->usermodified = $USER->id;
			$DB->update_record('local_skill_categories', $newskill_category);
			$perform = $newskill_category->id;
		}else{
			$newskill_category->costcenterid = $costcenter;
			$newskill_category->timecreated = time();
			$newskill_category->usercreated = $USER->id;
			$perform = $DB->insert_record('local_skill_categories', $newskill_category);
		}

	    return $perform;
	}
}
