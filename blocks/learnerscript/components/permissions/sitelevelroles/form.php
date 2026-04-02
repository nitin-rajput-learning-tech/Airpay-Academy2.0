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
 * @package BizLMS
 * @subpackage block_learnerscript
 */
if (!defined('MOODLE_INTERNAL')) {
    die(get_string('nodirectaccess','block_learnerscript'));///  It must be included from a Moodle page
}

require_once($CFG->libdir . '/formslib.php');

class sitelevelroles_form extends moodleform {

    function definition() {
        global $DB;

        $mform = & $this->_form;

        $mform->addElement('header', 'crformheader', get_string('sitelevelroles', 'block_learnerscript'), '');

        $sql = "SELECT r.id, 
                CASE
                    WHEN r.name != '' THEN r.name
                    ELSE r.shortname
                END AS name
                FROM {role} r
                JOIN {role_context_levels} rcl ON rcl.roleid = r.id 
                WHERE rcl.contextlevel = 40";
        $systemroles = $DB->get_records_sql_menu($sql);

        $authuserid = $DB->get_field('role','id',array('shortname'=>'user'));
        $authuser = array($authuserid => 'Employee');
        $roles = $systemroles + $authuser;


        $mform->addElement('select', 'roleid', get_string('roles'), $roles);

        // buttons
        $this->add_action_buttons(true, get_string('add'));
    }

}
