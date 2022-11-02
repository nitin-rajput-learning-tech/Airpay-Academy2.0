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

namespace local_users\forms;
        defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;
use csv_import_reader;
use core_text;
define('ONLY_ADD', 1);
define('ONLY_UPDATE', 2);
define('ADD_UPDATE', 3);
class hrms_async extends moodleform {


    public function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'userfile', get_string('file'));
        $mform->addRule('userfile', null, 'required');
        $mform->addElement('hidden',  'delimiter_name');
        $mform->setType('delimiter_name', PARAM_TEXT);
        $mform->setDefault('delimiter_name',  'comma');
        $mform->addElement('hidden',  'encoding');
        $mform->setType('encoding', PARAM_RAW);
        $mform->setDefault('encoding',  'UTF-8');
        $mform->addElement('hidden', 'enrollmentmethod');
        $mform->setType('enrollmentmethod', PARAM_INT);
        $mform->setDefault('enrollmentmethod', MANUAL_ENROLL);
        $mform->addElement('advcheckbox', 'createpassword', get_string('createpassword', 'auth'));
        $mform->addElement('hidden', 'option', ADD_UPDATE);
        $mform->setType('option', PARAM_INT);

        $this->add_action_buttons(true, get_string('upload'));
    }

}
