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
 * Manage program Session form
 *
 * @package    local_program
 * @copyright  2018 Arun Kumar M <arun@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_program\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
use \local_program\program as program;
use moodleform;
use local_program\local\querylib;
use context_system;

class session_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $context = context_system::instance();
        $mform = &$this->_form;
        $bcid = $this->_customdata['bcid'];
        $sid = $this->_customdata['id'];
        $levelid = $this->_customdata['levelid'];
        $bclcid = $this->_customdata['bclcid'];

        $mform->addElement('hidden', 'id', $sid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'datetimeknown', 1);
        $mform->setType('datetimeknown', PARAM_INT);

        $mform->addElement('hidden', 'programid', $bcid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('hidden', 'levelid', $levelid);
        $mform->setType('levelid', PARAM_INT);

        $mform->addElement('hidden', 'bclcid', $bclcid);
        $mform->setType('bclcid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), array());
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $getconfig = get_config('local_program', 'program_onlinesession_type');
        if (!empty($getconfig)) {
            $instancetype = explode('_', $getconfig);
            $visible = $DB->get_field('modules', 'visible', array('name' => $instancetype[1]));
            if ($visible) {
                $mform->addElement('advcheckbox', 'onlinesession',
                    get_string('onlinesession', 'local_program'), '', array(), array(0, 1));
                $mform->addHelpButton('onlinesession', 'onlinesession', 'local_program');
            }
        }

        $institutetypes = array();
        $institutetypes[] = $mform->createElement('radio', 'institute_type', '',
                get_string('internal', 'local_program'), 1, $attributes);
        $institutetypes[] = $mform->createElement('radio', 'institute_type', '',
                get_string('external', 'local_program'), 2, $attributes);
        $mform->addGroup($institutetypes, 'radioar', get_string('bc_location_type',
                'local_program'), array(' '), false);
        $mform->addRule('radioar', get_string('selectbc_location_type', 'local_program'), 'required', null, 'client');

        $programlocations =  array(null => get_string('select_institutions',
                        'local_program'));
        $instituteid = $this->_ajaxformdata['instituteid'];

        if (!empty($instituteid)) {
            $instituteid = $instituteid;
        } else if ($sid > 0) {
            $instituteid = $DB->get_field('local_bc_course_sessions', 'instituteid', array('id' => $sid));
        }
        if (!empty($instituteid)) {
            $programlocations = $DB->get_records_menu('local_location_institutes',
                array('id' => $instituteid), 'id', 'id, fullname');
        }
        $options = array(
            'ajax' => 'local_program/form-options-selector',
            'data-contextid' => $context->id,
            'data-action' => 'program_institute_selector',
            'data-options' => json_encode(array('id' => $sid, 'programid' => $bcid)),
            'data-institute_type' => 'institute_type'
        );

        $mform->addElement('autocomplete', 'instituteid', get_string('program_location',
             'local_program'), $programlocations, $options);
        $mform->addRule('instituteid', get_string('selectprogram_location', 'local_program'), 'required', null, 'client');

        $locationrooms =  array(null => get_string('select_room', 'local_program'));
        $roomid = $this->_ajaxformdata['roomid'];

        if (!empty($roomid)) {
            $roomid = $roomid;
        } else if ($sid > 0) {
            $roomid = $DB->get_field('local_bc_course_sessions', 'roomid', array('id' => $sid));
        }
        if (!empty($roomid)) {
            $locationrooms = $DB->get_records_menu('local_location_room',
                array('id' => $roomid), 'id', 'id, name');
        }

        $options = array(
            'ajax' => 'local_program/form-options-selector',
            'data-contextid' => $context->id,
            'data-action' => 'program_room_selector',
            'data-options' => json_encode(array('id' => $sid, 'programid' => $bcid)),
            'data-institute_type' => 'institute_type',
            'data-instituteid' => 'instituteid',
        );

        $mform->addElement('autocomplete', 'roomid',
            get_string('location_room', 'local_program'), $locationrooms, $options);
        $mform->addHelpButton('roomid', 'roomid', 'local_program');
        // $mform->disabledIf('roomid', 'onlinesession', 'checked');
        $mform->addRule('roomid', get_string('selectlocation_room', 'local_program'), 'required', null, 'client');

        $trainers = array();
        $trainerid = $this->_ajaxformdata['trainerid'];
        if (!empty($trainerid)) {
            $trainerid = $trainerid;
        } else if ($sid > 0) {
            $trainerid = $DB->get_field('local_bc_course_sessions', 'trainerid',
                array('id' => $sid));
        }
        if (!empty($trainerid)) {
            $trainers = $DB->get_records_menu('user', array('id' => $trainerid), '',
                "id, CONCAT(firstname, ' ', lastname) AS fullname");
        }
        $options = array(
            'ajax' => 'local_program/form-options-selector',
            'data-contextid' => $context->id,
            'data-action' => 'programsession_trainer_selector',
            'data-options' => json_encode(array('programid' => $bcid)),
        );

        $mform->addElement('autocomplete', 'trainerid', get_string('trainer', 'local_program'), $trainers, $options);
        $mform->addRule('trainerid', get_string('selecttrainer', 'local_program'), 'required', null, 'client');
        $mform->setType('trainerid', PARAM_INT);

        $mform->addElement('text', 'maxcapacity', get_string('maxcapacity', 'local_program'));
        $mform->setType('maxcapacity', PARAM_RAW);
        $mform->addRule('maxcapacity', get_string('selectmaxcapacity', 'local_program'), 'required', null, 'client');
        $mform->addRule('maxcapacity', null, 'numeric', null, 'client');

        $mform->addElement('text', 'mincapacity', get_string('mincapacity', 'local_program'));
        $mform->setType('mincapacity', PARAM_RAW);
        $mform->addRule('mincapacity', get_string('selectmincapacity', 'local_program'), 'required', null, 'client');
        $mform->addRule('mincapacity', null, 'numeric', null, 'client');

        $mform->addElement('date_time_selector', 'timestart', get_string('cs_timestart', 'local_program'));
        $mform->setType('timestart', PARAM_INT);
        $mform->addRule('timestart', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'timefinish', get_string('cs_timefinish', 'local_program'));
        $mform->setType('timefinish', PARAM_INT);
        $mform->addRule('timefinish', null, 'required', null, 'client');

        $editoroptions = array(
            'noclean' => false,
            'autosave' => false
        );
        $mform->addElement('editor', 'cs_description', get_string('description', 'local_program'), null, $editoroptions);
        $mform->setType('cs_description', PARAM_RAW);
        $mform->addHelpButton('cs_description', 'description', 'local_program');

        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $CFG, $DB, $USER;
        $errors = parent::validation($data, $files);
        $programdates = $DB->get_record_select('local_program', 'id = :programid ',
            array('programid' => $data['programid']), 'startdate, enddate');

        $mincapacity = $data['mincapacity'];
        $maxcapacity = $data['maxcapacity'];
        $timestart = $data['timestart'];
        $timefinish = $data['timefinish'];
        if($mincapacity > $maxcapacity) {
            $errors['mincapacity'] = get_string('lessmincapacity', 'local_program');
        }
        if($timestart >= $timefinish) {
            $errors['timestart'] = get_string('startdatelessthanenddate', 'local_program');
        }
        if (isset($data['name']) && empty(trim($data['name']))) {
            $errors['name'] = get_string('valnamerequired', 'local_program');
        }
        $trainerid = $data['trainerid'];
        if(!empty($trainerid)){
            $trainersql = "SELECT lbcs.id 
                FROM {local_bc_course_sessions} AS lbcs 
                WHERE lbcs.trainerid = :trainerid AND lbcs.id != :sessionid 
                AND (lbcs.timestart BETWEEN :timestart AND :timefinish 
                    OR lbcs.timefinish BETWEEN :timestart1 AND :timefinish1 ) ";
            $trainerexists = $DB->get_record_sql($trainersql, array('trainerid' => $trainerid, 'sessionid' => $data['id'], 'timestart' => $data['timestart'], 'timefinish' => $data['timefinish'], 'timestart1' => $data['timestart'], 'timefinish1' => $data['timefinish']));

            if($trainerexists){
                $errors['trainerid'] = get_string('traineralreadymapped', 'local_program');
            }
        }
        $programid = $data['programid'];
        $sessions_validation_start = (new program)->sessions_validation($programid,
                                    $timestart, $data['id']);
        $session->duration = ($timefinish - $timestart)/60;
        if ($sessions_validation_start > 0) {
            $errors['timestart'] = 'There is already another session at this time';
        }
        $sessions_validation_end = (new program)->sessions_validation($programid,
            $timefinish, $data['id']);
        if ($sessions_validation_end > 0) {
            $errors['timefinish'] = 'There is already another session at this time';
        }
        $session_room_validation = (new program)->room_validation((object)$data);
        if (!empty($session_room_validation)) {
            $errors['roomid'] = $session_room_validation;
        }
        return $errors;
    }
}