<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_courses\form;

/**
 * Class adddashboardcourse
 *
 * @package    local_courses
 * @copyright  2024 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_courses\form;
use core;
use moodleform;
use context_system;
require_once($CFG->dirroot . '/lib/formslib.php');

class adddashboardcourse_form extends moodleform {

	function definition() {
		global $CFG,$DB;
		$mform = & $this->_form;
		$courseids = $this->_ajaxformdata['courseids'];
		$context = $this->_customdata['context'];
		$id = $DB->get_field_sql('SELECT id FROM {local_dashboardcourses} WHERE id > 0');
        $categorycontext =  (new \local_courses\lib\accesslib())::get_module_context();
		//later using set_data

		$courses_sql = "SELECT id, fullname as fullname
                        FROM {course} WHERE open_module IS NULL AND id > 1";
    	if(!is_siteadmin()){
			$costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
			$courses_sql .= $costcenterpathconcatsql;
		}
				$courses = $DB->get_records_sql_menu($courses_sql);
                 $coursesarr = [];
                foreach($courses as $k => $v){
					$course = $DB->get_record('course',['id'=>$k]);
					$course=(array)$course;
            		local_costcenter_set_costcenter_path($course);
					$costcentername = $DB->get_field('local_costcenter','fullname',['id'=>$course['open_costcenterid']]);
                        $coursesarr[$k] = format_string($v).'('.format_string($costcentername ).')';
                    }
            $options = array(
                'multiple' => true,
                'data-contextid' => $categorycontext->id,
            );
		
            $mform->addElement('autocomplete', 'courseids', get_string('courses', 'local_courses'), $coursesarr,$options);
        	//$mform->addRule('courseids', null, 'required', null, 'client');
			$mform->setType('courseids', PARAM_RAW);
			$mform->addElement('hidden', 'id',$id);
			$mform->setType('id', PARAM_INT);
	}

	function validation($data, $files) {
		$errors = parent :: validation($data, $files);
		return $errors;
	}
}
