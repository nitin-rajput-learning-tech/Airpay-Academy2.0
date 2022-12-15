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
 * usersprofilefields_village
 *
 * @package    usersprofilefields_team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');

function usersprofilefields_village_output_fragment_create_village($args){
    global $CFG,$DB, $PAGE;
    $args = (object) $args;
    $context = $args->context;
    $o = '';

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    if ($args->id > 0) {
        $heading = get_string('update_village', 'usersprofilefields_village');
        $data = $DB->get_record('local_village', array('id'=>$args->id));
        $mform = new \usersprofilefields_village\forms\village_form(null, (array)$data, 'post', '', null, true, $formdata);
        $mform->set_data($data);
    }
    else{
        $mform = new \usersprofilefields_village\forms\village_form(null, array(), 'post', '', null, true, $formdata);
    }

    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) >2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}