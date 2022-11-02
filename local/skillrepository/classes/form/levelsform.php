<?php
namespace local_skillrepository\form;
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
 * @package BizLMS
 * @subpackage local_skillrepository
 */
use moodleform;
use context_system;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
class levelsform extends \moodleform {
	public function definition() {
		global $USER, $CFG, $DB, $PAGE;
		$mform = $this->_form;
		$id = $this->_customdata['id'];

		$context = context_system::instance();
        if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            $sql="select id,fullname from {local_costcenter} where visible =1 AND parentid = 0";
            $costcenters = $DB->get_records_sql($sql);
            $organizationlist=array(null=>'--Select Organization--');
            foreach ($costcenters as $scl) {
                $organizationlist[$scl->id]=$scl->fullname;
            }
            $mform->addElement('autocomplete', 'costcenterid', get_string('organization', 'local_users'), $organizationlist);
            $mform->addRule('costcenterid', null, 'required', null, 'client');
            $mform->setType('costcenterid', PARAM_INT);
        } else {
            $user_dept = $DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
            $mform->addElement('hidden', 'costcenterid', null);
            $mform->setType('costcenterid', PARAM_INT);
            $mform->setConstant('costcenterid', $user_dept);
        }
        $mform->addElement('text',  'name',  get_string('levelname','local_skillrepository'));
		$mform->addRule('name', get_string('levelnamereq', 'local_skillrepository'), 'required', null, 'client');
		$mform->setType('name', PARAM_TEXT);

		$mform->addElement('text',  'code',  get_string('levelcode',  'local_skillrepository'));
		$mform->addRule('code', get_string('levelcodereq', 'local_skillrepository'), 'required', null, 'client');
		$mform->setType('code', PARAM_RAW);	
		
		$mform->addElement('hidden',  'id', $id);
		$mform->setType('id', PARAM_INT);

		$this->add_action_buttons();
	}
	public function validation($data, $files) {
        $errors = array();
        global $DB, $CFG;

        if(empty($data['name'])){
        	$error['name'] = get_string('nonemptyname', 'local_skillrepository');
        }
        if(empty($data['code'])){
        	$error['code'] = get_string('nonemptycode', 'local_skillrepository');
        }
        if ($levelid = $DB->get_field('local_course_levels', 'id', array('code' => $data['code']))) {
            if (empty($data['id']) || $levelid != $data['id']) {
                $errors['code'] = get_string('codeexists', 'local_skillrepository');
            }
        }
        return $errors;
    }
}