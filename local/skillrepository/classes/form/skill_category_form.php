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
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class skill_category_form extends moodleform {

    public function definition() {
        global $DB,$USER;
        $mform = $this->_form;

        $id = optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $context =(new \local_skillrepository\lib\accesslib())::get_module_context();

       local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1),false, 'local_skillrepository', $context, $multiple = false);
        $mform->addElement('text', 'name', get_string('name', 'local_skillrepository'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_skillrepository'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', null, 'required', null, 'client');

        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'];
        $id = $data['id'];
        $record = $DB->get_record_sql('SELECT * FROM {local_skill_categories} WHERE shortname = ? AND  id <> ?', array($shortname, $id));
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_skillrepository');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_skillrepository');
        }
        return $errors;
    }

}
