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
use core;
use moodleform;
use context_system;

if (!defined('MOODLE_INTERNAL')) {
	die('Direct access to this script is forbidden.'); ///  It must be included from a Moodle page
}

require_once "{$CFG->dirroot}/lib/formslib.php";

class catform extends moodleform {

	/**
	 * Definition of the room form
	 */
	function definition() {
		global $DB;

		$mform = &$this->_form;
		// $roomid = $this->_customdata['roomid'];
		$categoryid = $this->_customdata['id'];

		$selected_ins_name = $DB->get_records('local_costcenter');
		// $mform->addElement('hidden', 'id', $roomid);
		$mform->setType('id', PARAM_INT);

		$mform->addElement('hidden', 'id', $instituteid);
		$mform->setType('categoryid', PARAM_INT);

		// $institutes = $DB->get_records('local_costcenter');
		// $institutenames = array();
		// $institutenames[null] = get_string('select', 'local_location');
		// if ($institutes) {
		// 	foreach ($institutes as $institute) {
		// 		$institutenames[$institute->id] = $institute->fullname;
		// 	}
		// }

		// $mform->addElement('select', 'costcenter', get_string('department', 'local_location'), $institutenames, array());
		// $mform->addRule('costcenter', null, 'required', null, 'client');

		// $allow_multi_session = array();
		// $allow_multi_session[] = $mform->createElement('radio', 'institute_type', '', get_string('internal', 'local_location'), 1);
		// $allow_multi_session[] = $mform->createElement('radio', 'institute_type', '', get_string('external', 'local_location'), 2);
		// $mform->addGroup($allow_multi_session, 'radioar', get_string('institutetype', 'local_location'), array(' '), false);
		// $mform->setDefault('institute_type',1);

		$mform->addElement('text', 'fullname', get_string('category_name', 'local_classroom'));
		$mform->setType('fullname', PARAM_TEXT);
		$mform->addRule('fullname', null, 'required', null, 'client');

		// $mform->addElement('textarea', 'address', get_string('address', 'local_classroom')	);
		// $mform->setType('address', PARAM_TEXT);
		// $mform->addRule('address', null, 'required', null, 'client');



		$this->add_action_buttons();
	}


}
