<?php
namespace local_location\form;
use core;
use moodleform;
/*
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
 * @package Local
 * @subpackage classroom
 */

if (!defined('MOODLE_INTERNAL')) {
	die('Direct access to this script is forbidden.'); ///  It must be included from a Moodle page
}

require_once "{$CFG->dirroot}/lib/formslib.php";

class roomform extends moodleform {

	/**
	 * Definition of the room form
	 */
	function definition() {
		global $DB,$USER;

		$mform = &$this->_form;
		$roomid = $this->_customdata['id'];

		$categorycontext = (new \local_location\lib\accesslib())::get_module_context();


		$selected_ins_name = $DB->get_records('local_location_institutes');
		$mform->addElement('hidden', 'id', $roomid);
		$mform->setType('id', PARAM_INT);

		$params = array();
		$sql = "SELECT id,fullname FROM {local_location_institutes} where 1=1 ";
         if ((has_capability('local/location:manageroom', $categorycontext)) && (!is_siteadmin() ) ) {
            $sql .= " AND (costcenter = :costcenter OR usercreated = :usercreated)";
            $params['costcenter'] = $USER->open_costcenterid;
            $params['usercreated'] = $USER->id;
        }
       
    	$institutes = $DB->get_records_sql($sql,$params);
		$institutenames = array();
		$institutenames[null] = get_string('select', 'local_location');
		if ($institutes) {
			foreach ($institutes as $institute) {
				$institutenames[$institute->id] = $institute->fullname;
			}
		}

		$mform->addElement('select', 'instituteid', get_string('institute', 'local_location'), $institutenames, array());
		$mform->addRule('instituteid', null, 'required', null, 'client');

		
		$mform->addElement('text', 'name', get_string('room_name', 'local_location'));
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', null, 'required', null, 'client');

		$mform->addElement('text', 'building', get_string('building', 'local_location'), array('size' => '45'));
		$mform->setType('building', PARAM_TEXT);
		$mform->addRule('building', null, 'required', null, 'client');

		$mform->addElement('text', 'address', get_string('address', 'local_location'), array('size' => '45'));
		$mform->setType('address', PARAM_TEXT);
		$mform->addRule('address', null, 'required', null, 'client');

		$mform->addElement('hidden', 'mincapacity', 0);
        $mform->setType('mincapacity', PARAM_INT);
        $maxcapacity = 1500;//9223372036854775807
        $mform->addElement('hidden', 'maxcapacity', $maxcapacity);
        $mform->setType('maxcapacity', PARAM_INT);
		$mform->addElement('text', 'capacity', get_string('capacity', 'local_location'));
		$mform->setType('capacity', PARAM_INT);
		$mform->addHelpButton('capacity','capacityofusers','local_location');
        $mform->addRule('capacity', null, 'required', null, 'client');
		$mform->addRule(array('capacity', 'mincapacity'), get_string('capacity_positive',
             'local_classroom'), 'compare', 'gt', 'client');
		$mform->addRule(array('capacity', 'maxcapacity'), 
			get_string('capacity_limitexceeded', 'local_classroom', $maxcapacity), 'compare', 'lt', 'client');


		/*$mform->addElement('textarea', 'description', get_string('description', 'local_location'));
		$mform->setType('description', PARAM_TEXT);
		$mform->addRule('description', null, 'required', null, 'client');
		$mform->addHelpButton('description', 'descript', 'local_location');
*/
		// $this->add_action_buttons();
		$mform->disable_form_change_checker();
	}
}
