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

/**
 *  This file contains the form for creating trainingcourses.
 *
 * @package    local_courses
 * @copyright  2024 Moodle India Information Solutions Pvt Ltd
 * @author     2024 Rizwana <rizwana.madire@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courses\form;

defined('MOODLE_INTERNAL') || die;

use core_form\dynamic_form;
use moodle_url;
use stdClass;
require_once($CFG->dirroot . '/local/costcenter/lib.php');

class adddashboardcourse_form extends dynamic_form {

    /**
     * Form definition. Abstract method - always override!
     */
    public function get_context_for_dynamic_submission(): \context {
        $categorycontext =  (new \local_courses\lib\accesslib())::get_module_context();
        $contextid = $this->optional_param('contextid', $categorycontext->id, PARAM_INT);
        return \context::instance_by_id($contextid);
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $DB;

        $systemcontext = \context_system::instance();
        $categorycontext =  (new \local_courses\lib\accesslib())::get_module_context();
        $mform = &$this->_form;

        $id = $this->optional_param('id', 0, PARAM_INT);
        $courseids = $this->_ajaxformdata['courseids'];
        $condition = [
            'id' => $id,

        ];

        $mform->addElement('hidden', 'id',$id);
        $mform->setType('id', PARAM_INT);

        // $dashboardcourserecord = $DB->get_record('local_dashboardcourses', []);
        // $id = $dashboardcourserecord->id;

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
            'id' => 'courseids',
            'class' => 'courseids',
            'data-class' => 'courseids',
        );
    
        $mform->addElement('autocomplete', 'courseids', get_string('courses', 'local_courses'), $coursesarr, $options);
        $mform->setType('courseids', PARAM_INT);
        $mform->addRule('courseids', get_string('courses','local_courses'), 'required', null, 'client');
        
        $mform->disable_form_change_checker();
    }

    /**
     * Require access.
     */
    public function require_access(): void {

    }

    /**
     * Check if current user has access to this form.
     */
    public function check_access_for_dynamic_submission(): void {
    }


    /**
     * Process the form submission
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        if($data->id > 0){
            $DB->update_record('local_dashboardcourses', $data);
        } else{
            $DB->insert_record('local_dashboardcourses', $data);
        }
    }


    /**
     * Load in existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
       if ($id = $this->optional_param('id', 0, PARAM_INT)) {
            $courseidslist = $DB->get_field('local_dashboardcourses', 'courseids', ['id' => $id], '*', MUST_EXIST);
            $courseids = explode(',', $courseidslist);
            $data = new stdClass();
            
            $data->courseids = $courseids;
            $this->set_data($data);
        }
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/courses/courses.php');
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
            $errors['courseids'] = get_string('maxcoursesadded', 'local_courses');
        }
        return $errors;
    }
}
