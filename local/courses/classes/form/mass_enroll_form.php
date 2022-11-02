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
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courses\form;
use core;
use moodleform;
use context_system;
require_once($CFG->dirroot . '/lib/formslib.php');

class mass_enroll_form extends moodleform {

	function definition() {
		global $CFG,$DB;
		$mform = & $this->_form;
		$course = $this->_customdata['course'];
		$context = $this->_customdata['context'];
		$type = $this->_customdata['type'];

		// $mform->addElement('header', 'general', ''); //fill in the data depending on page params
		//later using set_data
		$mform->addElement('filepicker', 'attachment', get_string('location', 'enrol_flatfile'));

		$mform->addRule('attachment', null, 'required');
		
		$choices = \csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = \core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');
		

	    $id=$DB->get_field('role','id',array('shortname'=>'employee'));
	    // if(! $id)
	    // 	$id=$DB->get_field('role','id',array('shortname'=>'student'));
		$mform->addElement('hidden', 'roleassign', $id);
        $mform->setType('roleassign', PARAM_INT);

		$ids = array (
			'email' => get_string('email', 'local_courses')
		);
		$mform->addElement('hidden',  'firstcolumn',  'email');
		$mform->setDefault('firstcolumn', 'email');
/*		$mform->addElement('select', 'firstcolumn', get_string('firstcolumn', 'local_courses'), $ids);
		$mform->setDefault('firstcolumn', 'idnumber');

		$mform->addElement('selectyesno', 'creategroups', get_string('creategroups', 'local_courses'));
		$mform->setDefault('creategroups', 1);

		$mform->addElement('selectyesno', 'creategroupings', get_string('creategroupings', 'local_courses'));
		$mform->setDefault('creategroupings', 1);*/

		// buttons
		if($type == 'course') {
			$buttonname = get_string('enroll', 'local_courses');
		} else if($type == 'onlinetest'){
			$buttonname = get_string('enroll', 'local_onlinetests');
		} else if($type == 'groups'){
			$buttonname = get_string('enroll', 'local_groups');
		} else if($type == 'program'){
			$buttonname = get_string('enroll', 'local_program');
		} else {
			$buttonname = 'Enroll';
		}
		$this->add_action_buttons(true, $buttonname);

		$mform->addElement('hidden', 'id', $course->id);
		$mform->setType('id', PARAM_INT);
	}

	function validation($data, $files) {
		$errors = parent :: validation($data, $files);
		return $errors;
	}
}