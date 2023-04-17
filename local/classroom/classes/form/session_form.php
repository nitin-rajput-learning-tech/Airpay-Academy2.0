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
 * @package Bizlms 
 * @subpackage local_classroom
 */

namespace local_classroom\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
use \local_classroom\classroom as classroom;
use moodleform;
use local_classroom\local\querylib;

class session_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        
        $mform = &$this->_form;
        $cid = $this->_customdata['cid'];
        $sid = $this->_customdata['id'];
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($cid);
        $mform->addElement('hidden', 'id', $sid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'datetimeknown', 1);
        $mform->setType('datetimeknown', PARAM_INT);

        $mform->addElement('hidden', 'classroomid', $cid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'mincapacity', 0);
        $mform->setType('mincapacity', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), array());
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        // added by sachin----------------------------------------------------
        $mform->addElement('text', 'recordinglink', get_string('recordinglink', 'local_classroom'), array());
        $mform->setType('recordinglink', PARAM_URL);
        $mform->addRule('recordinglink', get_string('recordinglink_err', 'local_classroom'), 'regex', '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', 'server');

        $mform->addElement('text', 'messagelink', get_string('messagelink', 'local_classroom'), array());
        $mform->addHelpButton('messagelink', 'messagelink', 'local_classroom');
        $mform->setType('messagelink', PARAM_URL);
        $mform->addRule('messagelink', get_string('messagelink_err', 'local_classroom'), 'regex', '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', 'server');
        $mform->disabledIf('messagelink', 'onlinesession', 'checked');
        //-------------------------------------------------------------------
        $get_config=get_config('local_classroom', 'classroom_onlinesession_type');
		
        if(!empty($get_config)){
            $instance_type=explode('_',$get_config);
			$visible = $DB->get_field('modules','visible',array('name'=>$instance_type[1]));
			if($visible){
                $mform->addElement('advcheckbox', 'onlinesession', get_string('onlinesession',
                    'local_classroom'), '', array(), array(0, 1));
                $mform->addHelpButton('onlinesession', 'onlinesession', 'local_classroom');
            }
        }

        $locationroomlists = $querieslib->get_classroom_institute_rooms($cid);
        $locationrooms = array(null => get_string('select_room', 'local_classroom')) + $locationroomlists;

        $mform->addElement('select', 'roomid', get_string('location_room', 'local_classroom'), $locationrooms, array());
        $mform->addHelpButton('roomid', 'roomid', 'local_classroom');
        $mform->disabledIf('roomid', 'onlinesession', 'checked');

//        $trainers = array();
        $trainers = array(0 => get_string('select_trainers', 'local_classroom'));
        $trainerid = $this->_ajaxformdata['trainerid'];
        if (!empty($trainerid)) {
            $trainerid = $trainerid;
        } else if ($sid > 0) {
            $trainerid = $DB->get_field('local_classroom_sessions', 'trainerid',
                array('id' => $sid));
        }
        if (!empty($trainerid)) {
            $trainers = $DB->get_records_menu('user', array('id' => $trainerid), '',
                "id, CONCAT(firstname, ' ' , lastname) AS fullname");
        }
        $options = array(
            'ajax' => 'local_classroom/form-options-selector',
            'data-contextid' => $categorycontext->id,
            'data-action' => 'classroomsession_trainer_selector',
            'data-options' => json_encode(array('classroomid' => $cid)),
        );

        $mform->addElement('autocomplete', 'trainerid', get_string('trainer', 'local_classroom'), $trainers, $options);
        $mform->setType('trainerid', PARAM_INT);

        $mform->addElement('date_time_selector', 'timestart', get_string('cs_timestart', 'local_classroom'));
        $mform->setType('timestart', PARAM_INT);

        $mform->addElement('date_time_selector', 'timefinish', get_string('cs_timefinish', 'local_classroom'));
        $mform->setType('timefinish', PARAM_INT);

        $editoroptions = array(
            'noclean' => false,
            'autosave' => false
        );
        $mform->addElement('editor', 'cs_description', get_string('description', 'local_classroom'), null, $editoroptions);
        $mform->setType('cs_description', PARAM_RAW);
        $mform->addHelpButton('cs_description', 'description', 'local_classroom');

        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $CFG, $DB, $USER;
        $errors = parent::validation($data, $files);
        $classroomdates = $DB->get_record_select('local_classroom', 'id = :classroomid ',
            array('classroomid' => $data['classroomid']), 'startdate, enddate');
        if (isset($data['timestart']) && $data['timestart'] && isset($data['timefinish']) && $data['timefinish']) {
            $classroomstartdate = $classroomdates->startdate;
            $classroomenddate = $classroomdates->enddate;
            $sessionstartdate = $data['timestart'];
            $sessionenddate = $data['timefinish'];
			
			
			$sessions_validation_start=(new classroom)->sessions_validation($data['classroomid'],$sessionstartdate,$data['id']);
			
			$sessions_validation_end=(new classroom)->sessions_validation($data['classroomid'],$sessionenddate,$data['id']);

            if ($sessionstartdate < $classroomstartdate) {
                $errors["timestart"] = get_string('sessionstartdateerror1', 'local_classroom');
            } else if ($sessionstartdate > $classroomenddate) {
                $errors["timestart"] = get_string('sessionstartdateerror2', 'local_classroom');
            }
            if ($data['timefinish'] <= $data['timestart']) {
                $errors["timefinish"] = get_string('enddateerror', 'local_classroom');
            } else if ($classroomstartdate > $sessionenddate) {
                $errors["timefinish"] = get_string('sessionenddateerror1', 'local_classroom');
            } else if ($sessionenddate > $classroomenddate) {
                $errors["timefinish"] = get_string('sessionenddateerror2', 'local_classroom');
            }elseif($sessions_validation_start || $sessions_validation_end){
				$errors["timestart"] = get_string('sessionexisterror', 'local_classroom');
				$errors["timefinish"] = get_string('sessionexisterror', 'local_classroom');
			}
        }
		if(isset($data['name']) &&empty(trim($data['name']))){
            $errors['name'] = get_string('sessionvalnamerequired','local_classroom');
        }
        return $errors;
    }
}
