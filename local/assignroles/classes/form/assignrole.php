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
 * @author Maheshchandra  <maheshchandra@eabyas.in>
 */
/**
 * Assign roles to users.
 * @package    local
 * @subpackage assignroles
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_assignroles\form;
use moodleform;
require_once("{$CFG->libdir}/formslib.php");
require_once($CFG->dirroot . '/local/assignroles/lib.php');
require_once($CFG->dirroot . '/local/lib.php');
class assignrole extends moodleform {

    public function definition() {
        global $USER,$DB;

		$context = (new \local_assignroles\lib\accesslib())::get_module_context(); 		

		$mform = & $this->_form;
		$roleid = $this->_customdata['roleid'];
		$costcenterid = $this->_customdata['costcenterid'];
		$users = $this->_customdata['users'];
		//Adding organizations dropdown --start(30.05.22)
 		$organisation_select = [null => get_string('selectorg', 'local_courses')];
		
	 	if ($costcenterid) { 
			$open_costcenter = $costcenterid;
			$organisations = $organisation_select + $DB->get_records_menu('local_costcenter', array('id' => $open_costcenter), '',  $fields = 'id, fullname');
		} else {
			$open_costcenter = 0;
			$organisations = $organisation_select;
		} 

	 	$costcenteroptions = array(
			'ajax' => 'local_assignroles/form-options-selector',
			'data-contextid' => $context->id,
			'data-action' => 'costcenter_organisation_selector',
			'data-options' => json_encode(array('id' => $open_costcenter)),
			'class' => 'organisationnameselect',
			'data-class' => 'organisationselect',
			'multiple' => false,
		);
		
		if (!$open_costcenter) {
			$mform->addElement('autocomplete', 'open_costcenterid', get_string('organization', 'local_courses'), $organisations, $costcenteroptions);
			$mform->addHelpButton('open_costcenterid', 'open_costcenteridcourse', 'local_courses');
			$mform->setType('open_costcenterid', PARAM_INT);
			$mform->addRule('open_costcenterid', get_string('pleaseselectorganization', 'local_courses'), 'required', null, 'client');
		
		} else {
			$mform->addElement('static', 'open_costcenter',get_string('organization', 'local_courses'), $organisations[$open_costcenter]);
			$mform->addElement('hidden', 'open_costcenterid');
			$mform->setType('open_costcenterid', PARAM_INT);
			$mform->setDefault('open_costcenterid', $open_costcenter);
		} 
	
		$options = array(
            'ajax' => 'local_assignroles/form-options-selector',
            'multiple' => true,
            'data-action' => 'role_users',
            'data-options' => json_encode(array('id' => 0, 'roleid' => $roleid, 'costcenterid' => $open_costcenter)),
        );
		$users =array();
        $mform->addElement('autocomplete', 'users', get_string('employees', 'local_users'), $users, $options);
        $mform->setType('users', PARAM_RAW);
		$mform->addRule('users',  get_string('pleaseselectemployees', 'local_assignroles'), 'required', null, 'client');

		$mform->addElement('hidden', 'roleid');
		$mform->setType('roleid', PARAM_TEXT);
		$mform->setDefault('roleid', $roleid);
		
		if(!$context->id){
			$mform->addElement('text', 'contextid', get_string('contextid', 'local_assignroles'));
			$mform->setType('contextid', PARAM_TEXT);
			$mform->setDefault('contextid', $context->id);
		}else{
			$mform->addElement('hidden', 'contextid');
			$mform->setType('contextid', PARAM_TEXT);
			$mform->setDefault('contextid', $context->id);
		}
		$mform->disable_form_change_checker();
        $this->add_action_buttons($cancel = null,get_string('assign', 'local_assignroles'));
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

		if (isset($data['open_costcenterid'])){
			if(empty($data['open_costcenterid'])){
				$errors['open_costcenterid'] = get_string('pleaseselectorganization', 'local_courses');
			}
		}

		if (isset($data['users'])){
			if(empty($data['users'])){
				$errors['users'] = get_string('pleaseselectemployees', 'local_assignroles');
			}
		}

		
        return $errors;
    }
}
