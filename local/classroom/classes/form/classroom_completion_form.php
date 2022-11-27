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
 * @subpackage local_classroom
 */

namespace local_classroom\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
use \local_classroom\classroom as classroom;
use moodleform;
use local_classroom\local\querylib;
use context_system;

class classroom_completion_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $context = context_system::instance();
        $mform = &$this->_form;
        $cid = $this->_customdata['cid'];
        $sid = $this->_customdata['id'];

        $mform->addElement('hidden', 'id', $sid);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'classroomid', $cid);
        $mform->setType('classroomid', PARAM_INT);

        $session_tracking=array(NULL=>get_string('classroom_donotsessioncompletion','local_classroom'),
                                'AND'=>get_string('classroom_allsessionscompletion', 'local_classroom'),
                                'OR'=>get_string('classroom_anysessioncompletion', 'local_classroom'));

        $mform->addElement('select', 'sessiontracking', get_string('sessiontracking', 'local_classroom'), $session_tracking, array());
        
        
        $sessions = array();
        $sessions = $this->_ajaxformdata['sessionids'];
        if (!empty($sessions)) {
            $sessions = $sessions;
        } else if ($id > 0) {
            $sessions = $DB->get_records_menu('local_classroom_completion',
                array('id' => $id), 'id', 'id, sessionids');
        }
        if (!empty($sessions)) {
                if(is_array($sessions)){
                    $sessions=implode(',',$sessions);
                 }
               
                $sessions_sql = "SELECT id, name as fullname
                                        FROM {local_classroom_sessions}
                                        WHERE classroomid = $cid and id in ($sessions)";
                $sessions = $DB->get_records_sql_menu($sessions_sql);
        }elseif (empty($sessions)) {
            $sessions_sql = "SELECT id, name as fullname
                                        FROM {local_classroom_sessions}
                                        WHERE classroomid = $cid ";
            $sessions = $DB->get_records_sql_menu($sessions_sql);
        }
        $options = array(
            'ajax' => 'local_classroom/form-options-selector',
            'multiple' => true,
            'data-contextid' => $context->id,
            'data-action' => 'classroom_completions_sessions_selector',
            'data-options' => json_encode(array('id' => $id,'classroomid'=>$cid)),
        );
            
        $mform->addElement('autocomplete', 'sessionids', get_string('session_completion', 'local_classroom'), $sessions,$options);
        $mform->hideIf('sessionids', 'sessiontracking', 'neq','OR');
        
        
        $course_tracking=array(NULL=>get_string('classroom_donotcoursecompletion','local_classroom'),
                               'AND'=>get_string('classroom_allcoursescompletion', 'local_classroom'),
                                'OR'=>get_string('classroom_anycoursecompletion', 'local_classroom'));
                         
        $mform->addElement('select', 'coursetracking', get_string('coursetracking', 'local_classroom'), $course_tracking, array());
        

        $courses = array();
        $courses = $this->_ajaxformdata['courseids'];
        if (!empty($courses)) {
            $courses = $courses;
        } else if ($id > 0) {
            $courses = $DB->get_records_menu('local_classroom_completion',
                array('id' => $id), 'id', 'id, courseids');
        }
        if (!empty($courses)) {
                 if(is_array($courses)){
                         $courses=implode(',',$courses);
                 }
                 $courses_sql = "SELECT c.id,c.fullname as fullname FROM {course} as c JOIN {local_classroom_courses} as lcc on lcc.courseid=c.id where lcc.classroomid= $cid and lcc.courseid in ($courses)";
                $courses = $DB->get_records_sql_menu($courses_sql);
        }elseif (empty($courses)) {
            $courses_sql = "SELECT c.id,c.fullname as fullname FROM {course} as c JOIN {local_classroom_courses} as lcc on lcc.courseid=c.id where lcc.classroomid= $cid ";
            $courses = $DB->get_records_sql_menu($courses_sql);
        }
   
        $options = array(
            'ajax' => 'local_classroom/form-options-selector',
            'multiple' => true,
            'data-contextid' => $context->id,
            'data-action' => 'classroom_completions_courses_selector',
            'data-options' => json_encode(array('id' => $id,'classroomid'=>$cid)),
        );
        
        $mform->addElement('autocomplete', 'courseids', get_string('course_completion', 'local_classroom'), $courses,$options);
        $mform->hideIf('courseids', 'coursetracking', 'neq','OR');


        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $CFG, $DB, $USER;
        $errors = parent::validation($data, $files);
        if (isset($data['sessiontracking']) && $data['sessiontracking'] == "OR" && isset($data['sessionids']) && empty($data['sessionids'])) {
            $errors['sessionids'] = get_string('select_sessions', 'local_classroom');
        }
        if (isset($data['coursetracking']) && $data['coursetracking'] == "OR" && isset($data['courseids']) && empty($data['courseids'])) {
            $errors['courseids'] = get_string('select_courses', 'local_classroom');
        }
        return $errors;
    }
}