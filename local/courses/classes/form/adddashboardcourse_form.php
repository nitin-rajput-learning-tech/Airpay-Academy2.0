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
 * @copyright  2024 Sachin W <sachin.waghmare@moodle.com>
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
		$dashboardcourserecord = $DB->get_record('local_dashboardcourses', []);
		$id = $dashboardcourserecord->id;
        $categorycontext =  (new \local_courses\lib\accesslib())::get_module_context();
		//later using set_data
        $external_costcenterpath = $DB->get_field('local_costcenter', 'path', array('shortname' => 'external'));
        $params = [];
		$courses_sql = " SELECT id, fullname
                        FROM {course} WHERE open_module IS NULL AND id > 1 ";

        $courses_sql .= " AND open_path = :external_costcenterpath ";
        $params['external_costcenterpath'] = $external_costcenterpath;

    	if(!is_siteadmin()) {
			$costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
			$courses_sql .= $costcenterpathconcatsql;
		}
		$courses = $DB->get_records_sql_menu($courses_sql, $params);
        $coursesarr = [];
        foreach($courses as $key => $value) {
			$course = $DB->get_record('course',['id'=>$key]);
			$course=(array)$course;
    		local_costcenter_set_costcenter_path($course);
			$costcentername = $DB->get_field('local_costcenter','fullname',['id'=>$course['open_costcenterid']]);
                $coursesarr[$key] = format_string($value).'('.format_string($costcentername ).')';
        }
        $options = array(
            'multiple' => true,
            'data-contextid' => $categorycontext->id,
            'id' => 'courseids'
        );
	
        $mform->addElement('autocomplete', 'courseids', get_string('courses', 'local_courses'), $coursesarr,$options);
		$mform->setType('courseids', PARAM_INT);
		$mform->addRule('courseids', get_string('courses','local_courses'), 'required', null);
		$mform->addElement('hidden', 'id',$id);
		$mform->setType('id', PARAM_INT);
	}

	/**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
		// $dashboardcourses = $DB->get_field('local_dashboardcourses', 'courseids', ['id' => $data['id']]);

        // $courseids[] = explode(',', $dashboardcourses);
        if((isset($data['courseids']) && count($data['courseids']) > 6)) {
        	$errors['courseids[]'] = get_string('maxcoursesadded', 'local_courses');
        }
        return $errors;
    }
}
