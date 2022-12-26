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
 * @author eabyas
 */
/**
 * Assign roles to users.
 * @package    local
 * @subpackage courses
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courses\form;
use moodleform;
use core_component;
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/lib.php');
class coursetype_form extends moodleform {

    public function definition() {
        global $USER,$DB;

		$contextid = optional_param('contextid', 1, PARAM_INT);
		$mform    =&$this->_form;
        $id = $this->_customdata['id'];
        $course_type = $this->_customdata['name'];
		$shortname = $this->_customdata['shortname'];
		$orgid = (int)$this->_customdata['orgid'];
		$orgname = $this->_customdata['orgname'];
		$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
		if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$categorycontext)) {
		$organisation_select = [null => get_string('selectorg','local_courses')];
		if($id || $this->_ajaxformdata['orgid']){
			$organisations = $organisation_select + $DB->get_records_menu('local_costcenter', array('id' => $orgid), '',  $fields='id, fullname'); 
		}else{
			$orgid = 0;
			$organisations = $organisation_select;
		}
		$costcenteroptions = array(
			'ajax' => 'local_costcenter/form-options-selector',
			'data-contextid' => $categorycontext->id,
			'data-action' => 'costcenter_organisation_selector',
			'data-options' => json_encode(array('id' => $orgid)),
			'class' => 'organisationnameselect',
			'data-class' => 'organisationselect',
			'multiple' => false,
		);
		$mform->addElement('autocomplete', 'orgid', get_string('organization','local_courses'), $organisations, $costcenteroptions);
		$mform->setType('orgid', PARAM_INT);
		$mform->addRule('orgid', get_string('required','local_courses'), 'required', null);
		}
		else if(has_capability('local/costcenter:manage_ownorganization',$categorycontext)){

		$mform->addElement('hidden', 'orgid', null, array('id' => 'id_open_costcenterid', 'data-class' => 'organisationselect'));
		$mform->setType('orgid', PARAM_INT);
		$mform->setConstant('orgid', $USER->open_costcenterid);
		}
	
        $mform->addElement('text', 'name', get_string('course_type','local_courses'), 'maxlength="100" size="10"');
        $mform->addRule('name', get_string('required'), 'required', null);
        $mform->setType('name', PARAM_RAW);
		$mform->setDefault('name', $course_type);

		$mform->addElement('text', 'shortname', get_string('course_type_shortname','local_courses'), 'maxlength="100" size="10"');
        $mform->addRule('shortname', get_string('required'), 'required', null);
        $mform->setType('shortname', PARAM_RAW);
		$mform->setDefault('shortname', $shortname);
			
		$mform->addElement('hidden', 'id');
		$mform->setType('id', PARAM_INT);
		$mform->setDefault('id', $id);

		$mform->addElement('hidden', 'contextid');
		$mform->setType('contextid', PARAM_INT);
		$mform->setDefault('contextid', $contextid);
		$mform->disable_form_change_checker();
		//$this->add_action_buttons($cancel = null,get_string('featured_course', 'local_courses'));
	}

	   /**
     * Validates the data submit for this form.
     *
     * @param array $data An array of key,value data pairs.
     * @param array $files Any files that may have been submit as well.
     * @return array An array of errors.
     */
    public function validation($data, $files) {
		global $DB;
        $errors = parent::validation($data, $files);
		$form_data = data_submitted();	
		if ($coursetype = $DB->get_record('local_course_types', array('name' => $data['coursetype']), '*', IGNORE_MULTIPLE)) {
            if (empty($data['id']) || $coursetype->id != $data['id']) {
                $errors['coursetype'] = get_string('coursetypeexists', 'local_courses', $coursetype->name);
            }
        }  
		
		if (isset($data['name'])){
			if(empty($data['name'])){
				$errors['name'] = get_string('err_coursetype', 'local_courses');
			}
		}
		if ($coursetype = $DB->get_record('local_course_types', array('shortname' => $data['shortname']), '*', IGNORE_MULTIPLE)) {
            if (empty($data['id']) || $coursetype->id != $data['id']) {
                $errors['shortname'] = get_string('coursecodeexists', 'local_courses', $coursetype->shortname);
            }
        }  
	 	if (isset($data['shortname'])){
			if(empty($data['shortname'])){
				$errors['shortname'] = get_string('err_coursetypeshortname', 'local_courses');
			}
		} 
		if (isset($data['id']) && isset($data['orgid'])){
			if(($data['orgid']==0) && empty($data['orgid'])){
                $errors['orgid'] = get_string('pleaseselectorganization', 'local_courses');
            }
        }
		
		return $errors;
    }

	
}
