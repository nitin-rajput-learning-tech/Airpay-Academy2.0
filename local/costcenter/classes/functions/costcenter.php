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
 * local local_costcenter
 *
 * @package    local_costcenter
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_costcenter\functions;
use moodleform;
require_once($CFG->dirroot . '/lib/formslib.php');

class costcenter extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'fullname', get_string('fieldlabel','local_costcenter'));
        $mform->addRule('fullname', get_string('notemptymsg', 'local_costcenter'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_RAW);
        $mform->disable_form_change_checker();
    }
    /**
     *
     * @param [object] $data 
     * @param [object] $files 
     * @return errors
     */
    public function validation($data, $files) {
        global $CFG;
        $errors = parent::validation($data, $files);
        if(empty(trim($data['fullname']))){
            $errors['fullname'] = get_string('notemptymsg', 'local_costcenter');
        }
        $curl = new \curl;
        $params['serial'] = $data['fullname'];
        $params['surl'] = $CFG->wwwroot;
        $param = json_encode($params);
        $string = urldecode('https%3A%2F%2Fbizlms.net%2F%3Fwc-api%3Dcustom_validate_serial_key');
        $json = $curl->post($string, $param);
        $response = (object)json_decode($json);
        if ($response->success != 'true') {
            $errors['fullname'] = $response->message;
        }
        return $errors;
    }
}
