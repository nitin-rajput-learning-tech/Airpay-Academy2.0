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
 * @package Bizlms 
 * @subpackage local_program
 */
namespace local_program\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot.'/local/program/lib.php');
use \local_program\program as program;
use moodleform;
use context_system;

class level_completion_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;

        $categorycontext =  (new \local_program\lib\accesslib())::get_module_context($pid);

        $mform = &$this->_form;
        $pid = $this->_customdata['pid'];
        $levelid = $this->_customdata['levelid'];
        $id = $this->_customdata['id'];

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'programid', $pid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('hidden', 'levelid', $levelid);
        $mform->setType('levelid', PARAM_INT);

        $completionflag = local_program_completion_form_flag($mform, $pid, $levelid);
        if(!$completionflag){
            $course_tracking=array('ALL'=>get_string('allcourses','local_program'),
                                   'AND'=>get_string('section_allcoursescompletion', 'local_program'),
                                    'OR'=>get_string('section_anycoursecompletion', 'local_program'));
                             
            $mform->addElement('select', 'coursetracking', get_string('coursetracking', 'local_program'), $course_tracking, array());


            if (isset($ajaxformdata['courseids']) && (!is_array($ajaxformdata['courseids']) ||
                $this->_ajaxformdata['courseids'] == '_qf__force_multiselect_submission')) {
                $this->_ajaxformdata['courseids']=array();
            }

            $courses = array();
            $courses = $this->_ajaxformdata['courseids'];
            if (!empty($courses)) {
                $courses = $courses;
            } else if ($id > 0) {
                $courses = $DB->get_records_menu('local_bcl_cmplt_criteria',
                    array('id' => $id), 'id', 'id, courseids');
            }

            if (isset($courses) && (is_array($courses)) && !empty(array_filter($courses))) {

                $courses=implode(',',$courses);

                $courses_sql = " SELECT c.id,c.fullname as fullname 
                    FROM {course} as c 
                    JOIN {local_program_level_courses} as lplc ON lplc.courseid = c.id
                    JOIN {local_program_levels} as lpl on lpl.id=lplc.levelid 
                    WHERE lplc.programid = {$pid} and lpl.id = {$levelid} and c.id IN ({$courses}) ";
                $courses = $DB->get_records_sql_menu($courses_sql);
            }else{
                $courses_sql = "SELECT c.id,c.fullname as fullname 
                    FROM {course} as c 
                    JOIN {local_program_level_courses} as lplc ON lplc.courseid = c.id
                    JOIN {local_program_levels} as lpl ON lpl.id = lplc.levelid 
                    WHERE lplc.programid = {$pid} and lpl.id = {$levelid} ";
                $courses = $DB->get_records_sql_menu($courses_sql);
            }
            $options = array(
                'ajax' => 'local_program/form-options-selector',
                'multiple' => true,
                'data-contextid' => $categorycontext->id,
                'data-action' => 'program_completions_courses_selector',
                'data-options' => json_encode(array('id' => $id,'programid'=>$pid,'levelid'=>$levelid)),
            );
            
            $mform->addElement('autocomplete', 'courseids', get_string('course_completion', 'local_program'), $courses, $options);
            $mform->hideIf('courseids', 'coursetracking', 'eq','ALL');
        }
        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $CFG, $DB, $USER;
        $errors = parent::validation($data, $files);
        if (isset($data['coursetracking']) && ($data['coursetracking'] == "OR" || $data['coursetracking'] == "AND") && isset($data['courseids']) && empty($data['courseids'])) {
            $errors['courseids'] = get_string('select_courses', 'local_program');
        }
        return $errors;
    }
}
