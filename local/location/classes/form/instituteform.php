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

class instituteform extends moodleform {

	/**
	 * Definition of the room form
	 */
	function definition() {
		global $DB,$USER;

		$costcenterid = isset($USER->open_path) && !empty($USER->open_path) ? explode('/',$USER->open_path)[1] : 0;
		// print_r($costcenterid); die;

		$mform = &$this->_form;

		$instituteid = $this->_customdata['id'];

		$categorycontext = (new \local_location\lib\accesslib())::get_module_context($instituteid);

		$selected_ins_name = $DB->get_records('local_costcenter');
		$mform->setType('id', PARAM_INT);

		$mform->addElement('hidden', 'id', $instituteid);
		$mform->setType('instituteid', PARAM_INT);

		$sql = "SELECT id,fullname FROM {local_costcenter} where 1=1 AND parentid=0 and fullname IS NOT NULL";

		$params = array();
		if ((has_capability('local/location:manageinstitute', $categorycontext)) && (!is_siteadmin() ) ) {
            $sql .= " AND (id = :costcenter)";
            $params['costcenter'] = $costcenterid;
            
        }
       
    	$institutes = $DB->get_records_sql($sql,$params);
		$institutenames = array();
		$institutenames[null] = get_string('select', 'local_location');
		if ($institutes) {
			foreach ($institutes as $institute) {
				$institutenames[$institute->id] = $institute->fullname;
			}
		}

		if (( is_siteadmin() ) ) {
				$mform->addElement('select', 'costcenter', get_string('organization', 'local_location'), $institutenames, array());
				$mform->addRule('costcenter', null, 'required', null, 'client');
	    }else{
	    	$mform->addElement('hidden', 'costcenter', $costcenterid);
	    }

		$allow_multi_session = array();
		$allow_multi_session[] = $mform->createElement('radio', 'institute_type', '', get_string('internal', 'local_location'), 1);
		$allow_multi_session[] = $mform->createElement('radio', 'institute_type', '', get_string('external', 'local_location'), 2);
		$mform->addGroup($allow_multi_session, 'radioar', get_string('institutetype', 'local_location'), array(' '), false);
		$mform->setDefault('institute_type',1);
		$mform->addHelpButton('radioar', 'locationtype', 'local_location');   

		$mform->addElement('text', 'fullname', get_string('institute_name', 'local_location'));
		$mform->setType('fullname', PARAM_TEXT);
		$mform->addRule('fullname', null, 'required', null, 'client');

		$mform->addElement('textarea', 'address', get_string('address', 'local_location')	);
		$mform->setType('address', PARAM_TEXT);
		$mform->addRule('address', null, 'required', null, 'client');



		// $this->add_action_buttons();
		$mform->disable_form_change_checker();
	}
	public function validation($data, $files){
		// print_r($data[costcenter]);die;
		$errors = parent::validation($data, $files);
		if(strlen($data['address'])> 500){
			$errors['address'] = get_string('addresstoolong', 'local_location');
		}
		return $errors; 
	}

}
