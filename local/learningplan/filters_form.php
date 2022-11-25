<?php
// use core_component;
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');
class filters_form extends moodleform {

    function definition() {
        global $CFG;

        $mform    = $this->_form;
        $filterlist        = $this->_customdata['filterlist'];// this contains the data of this form
        $filterparams      = isset($this->_customdata['filterparams']);
        $submit            = isset($this->_customdata['submitid']);
        $submitid          = $submit ? $submit : 'filteringform'; 
        //$submitid = $this->_customdata['submitid'] ? $this->_customdata['submitid'] : 'filteringform';

        $this->_form->_attributes['id'] = $submitid;
       
        foreach ($filterlist as $key => $value) {
            if($value === 'organizations' || $value === 'departments' || $value == 'subdepartment'){
                $filter = 'costcenter';
            }else if($value == 'status'){
                $filter = 'courses';
            }else if($value === 'learningplan'){
                $filter = 'learningplan';
            }else if($value === 'program'){
                $filter = 'program';
            }else{
                $filter = $value;
            }
            $core_component = new core_component();
            $courses_plugin_exist = $core_component::get_plugin_directory('local', $filter);
            if ($courses_plugin_exist) {
                require_once($CFG->dirroot . '/local/' . $filter . '/lib.php');
                $functionname = $value.'_filter';
                $functionname($mform);
            }
        }
        
        $buttonarray = array();
        // $applyclassarray = array();
        // $cancelclassarray = array();
        // $buttonarray[] = &$mform->createElement('button', 'filter_apply', get_string('apply','local_courses'),array());
        // $buttonarray[] = &$mform->createElement('button', 'cancel', get_string('reset','local_courses'), array());
        // $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        
        $this->add_action_buttons(true, get_string('apply', 'local_courses'));
        $mform->disable_form_change_checker();

    }
     /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        return $errors;
    }
}
