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
 * @subpackage skillrepository
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_skillrepository\form;
use moodleform;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/lib.php');
class skills_interested_form extends moodleform {

    public function definition() {
        global $USER,$DB;

		$contextid = optional_param('contextid', 1, PARAM_INT);
		$interested_skills = $this->_customdata['interested_skills'];
        $intskill_id =  $this->_customdata['intskill_id'];
        $mform = $this->_form;        
        $sql = "SELECT id, name FROM {local_skill} AS sk WHERE sk.id > 0  ORDER BY sk.id DESC";
        $int_skills_list = $DB->get_records_sql_menu($sql);
	
		$select = $mform->addElement('autocomplete', 'skills',get_string('skills', 'local_skillrepository'), $int_skills_list);
		$mform->addRule('skills', get_string('err_courses', 'local_courses'), 'required', null, 'client');
		$mform->addRule('skills', null, 'required', null, 'client');
        $select->setMultiple(true);
        //$mform->getElement('skills')->setMultiple(true);

		$mform->addElement('hidden', 'id');
		$mform->setType('id', PARAM_INT);
		$mform->setDefault('id', $intskill_id);

		$mform->addElement('hidden', 'contextid');
		$mform->setType('contextid', PARAM_INT);
		$mform->setDefault('contextid', $contextid);

        $mform->disable_form_change_checker();	

	}

	   /**
     * Validates the data submit for this form.
     *
     * @param array $data An array of key,value data pairs.
     * @param array $files Any files that may have been submit as well.
     * @return array An array of errors.
     */
    public function validation($data, $files) {
        // print_r($data); die;
		global $DB;
        $errors = parent::validation($data, $files);
		$form_data = data_submitted();	

	 	if (isset($data['skills'])){
			if(empty($data['skills'])){
				$errors['skills'] = get_string('err_skills', 'local_skillrepository');
			}
		} 

		return $errors;
    }

	
}
