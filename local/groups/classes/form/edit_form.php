<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_groups
 * @copyright  2018 sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_groups\form;
use moodleform;
use context_instance;


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/local/users/lib.php');

use local_users\functions\userlibfunctions as userlib;
class edit_form extends moodleform {

    /**
     * Define the group edit form
    */
    public function definition() {
        global $DB, $USER;
        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $cohort = $this->_customdata['data'];
        $context = (new \local_groups\lib\accesslib())::get_module_context();
        local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_groups', $context, $multiple = false);
        $mform->addElement('text', 'name', get_string('name', 'local_groups'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('groupname','local_groups'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);
        
        $mform->addElement('hidden', 'contextid', $context->id);
        $mform->setType('contextid', PARAM_INT);
        $mform->setConstant('contextid', $context->id);

        $mform->addElement('text', 'idnumber', get_string('idnumber', 'local_groups'), 'maxlength="254" size="50"');
        $mform->setType('idnumber', PARAM_RAW); // Idnumbers are plain text, must not be changed.
        $mform->addRule('idnumber', get_string('idnumber','local_groups'), 'required', null, 'client');

        $mform->addElement('advcheckbox', 'visible', get_string('visible', 'local_groups'));
        $mform->setDefault('visible', 1);
        $mform->addHelpButton('visible', 'visible', 'cohort');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if (isset($this->_customdata['returnurl'])) {
            $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl']->out_as_local_url());
            $mform->setType('returnurl', PARAM_LOCALURL);
        }

        $this->add_action_buttons();
        $this->set_data($cohort);
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);       
        $idnumber = trim($data['idnumber']);
        if ($idnumber === '') {
            // Fine, empty is ok.

        } else if ($data['id']) {
            $current = $DB->get_record('cohort', array('id'=>$data['id']), '*', MUST_EXIST);
            if ($current->idnumber !== $idnumber) {
                if ($DB->record_exists('cohort', array('idnumber'=>$idnumber))) {
                    $errors['idnumber'] = get_string('duplicateidnumber', 'local_groups');
                }
            }

        } else {
            if ($DB->record_exists('cohort', array('idnumber'=>$idnumber))) {
                $errors['idnumber'] = get_string('duplicateidnumber', 'local_groups');
            }
        }

          if (isset($data['costcenterid'])){
            if($data['costcenterid'] == 0){
                $errors['costcenterid'] = get_string('pleaseselectorganization', 'local_groups');
            }
        }
        if (isset($data['idnumber'])){
            if($data['idnumber'] == ''){
                $errors['idnumber'] = get_string('pleaseselectidnumber', 'local_groups');
            }
        }
        return $errors;
    }

    protected function get_category_options($currentcontextid) {
        global $CFG;
        //require_once($CFG->libdir. '/coursecatlib.php');
        $displaylist = core_course_category::make_categories_list('moodle/cohort:manage');
        $options = array();
        $syscontext = (new \local_groups\lib\accesslib())::get_module_context();
        if (has_capability('moodle/cohort:manage', $syscontext)) {
            $options[$syscontext->id] = $syscontext->get_context_name();
        }
        foreach ($displaylist as $cid=>$name) {
            $context = context_coursecat::instance($cid);
            $options[$context->id] = $name;
        }
        // Always add current - this is not likely, but if the logic gets changed it might be a problem.
        if (!isset($options[$currentcontextid])) {
            $context = context::instance_by_id($currentcontextid, MUST_EXIST);
            $options[$context->id] = $syscontext->get_context_name();
        }
        return $options;
    }
}

