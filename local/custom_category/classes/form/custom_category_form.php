<?php
namespace local_custom_category\form;
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
 * @subpackage local_custom_category
 */
use moodleform;
use context_system;
use costcenter;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class custom_category_form extends moodleform {

    public function definition() {
        global $DB,$USER;
        $mform = $this->_form;
        // $parentid = $this->_customdata['parentid'];

        $id = optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $context =(new \local_custom_category\lib\accesslib())::get_module_context();

        local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1, 1), false, 'local_custom_category', $context, $multiple = false);


        if((is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context))){

            $depsql = "SELECT lcc.id,lcc.fullname
                        FROM {local_custom_category} as lcc";

            $parents = $DB->get_records_sql_menu($depsql, ['parentid' => 0]);
            $parents[0] = 'Top';

        }else{

            $sql = "SELECT id,fullname
                    FROM {local_custom_category} WHERE id = ?";
            $parents = $DB->get_records_sql_menu($sql, [$USER->open_costcenterid]);
        }
        $parents[0] = 'Top';
        asort($parents);

        $coursetype = array(
            'ajax' => 'local_costcenter/form-options-selector',
            'data-contextid' => (\local_costcenter\lib\accesslib::get_module_context())->id,
            'data-action' => 'custom_category_selector',
            'data-options' => json_encode(array('id' => $identifiedtype)),
            'class' => 'idparentselect',
            'data-parentclass' => 'open_costcenterid_select',
            'data-class' => 'idparentselect',
            'multiple' => false,
            );

        $mform->addElement('autocomplete', 'parentid', get_string('parent','local_costcenter'), $parents, $coursetype);
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name', 'local_custom_category'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_custom_category'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'];
        $id = $data['id'];
        $record = $DB->get_record_sql('SELECT * FROM {local_custom_category} WHERE shortname = ? AND  id <> ?', array($shortname, $id));
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_custom_category');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_custom_category');
        }
        return $errors;
    }

}
