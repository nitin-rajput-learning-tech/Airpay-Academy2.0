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
 * @author rajutummoji  <raju.tummoji@eabyas.com>
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
class assigncostcenterrole extends moodleform {

    public function definition() {
        global $USER,$DB;

		$context = (new \local_assignroles\lib\accesslib())::get_module_context(); 		

		$mform = & $this->_form;
        $costcenterid = $this->_customdata['costcenterid'];
        $hierarchyid = $this->_customdata['hierarchyid'];
		$formtype = $this->_customdata['formtype'];
		
		
		$mform->addElement('hidden', 'open_costcenterid');
		$mform->setType('open_costcenterid', PARAM_INT);
		$mform->setDefault('open_costcenterid', $costcenterid);

        $mform->addElement('hidden', 'hierarchyid');
        $mform->setType('hierarchyid', PARAM_INT);
        $mform->setDefault('hierarchyid', $hierarchyid);


		$options = array(
            'ajax' => 'local_assignroles/form-options-selector',
            'data-action' => 'role_ids',
            'data-options' => json_encode(array('id' => 0,'costcenterid' => $costcenterid,'hierarchyid' => $hierarchyid,'formtype' => $formtype)),
        );

		$roles =array();

        if($roleid=$this->_ajaxformdata['roleid']){

               $sql = "SELECT r.id, r.name as fullname
                            FROM {role} r WHERE r.id =:roleid";

                $roles = $DB->get_records_sql_menu($sql, array('roleid'=>$roleid));

        }
        $mform->addElement('autocomplete', 'roleid', get_string('roles', 'local_assignroles'), $roles, $options);
        $mform->setType('roleid', PARAM_RAW);
		$mform->addRule('roleid',  get_string('pleaseselectroles', 'local_assignroles'), 'required', null, 'client');


		$options = array(
            'ajax' => 'local_assignroles/form-options-selector',
            'multiple' => true,
            'data-action' => 'role_costcenterusers',
            'data-options' => json_encode(array('id' => 0, 'costcenterid' => $costcenterid,'hierarchyid' => $hierarchyid,'formtype' => $formtype)),
        );

		$users =array();

        if($userids=$this->_ajaxformdata['users']){


            list($relateduseridlistsql, $relateduseridlistparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'useridlist');


            $sql = "SELECT u.id, concat(u.firstname,' ',u.lastname) as fullname
                        FROM {user} AS u WHERE u.id $relateduseridlistsql ";

            $users = $DB->get_records_sql_menu($sql,$relateduseridlistparams);

        }
        $mform->addElement('autocomplete', 'users', get_string('employees', 'local_users'), $users, $options);
        $mform->setType('users', PARAM_RAW);
		$mform->addRule('users',  get_string('pleaseselectemployees', 'local_assignroles'), 'required', null, 'client');

		
	
		$mform->addElement('hidden', 'contextid');
		$mform->setType('contextid', PARAM_TEXT);
		$mform->setDefault('contextid', $context->id);
		
		$mform->disable_form_change_checker();

        // $this->add_action_buttons($cancel = null,get_string('assign', 'local_assignroles'));
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
		if (isset($data['roles'])){
			if(empty($data['roles'])){
				$errors['roles'] = get_string('pleaseselectroles', 'local_assignroles');
			}
		}

		
        return $errors;
    }
}
