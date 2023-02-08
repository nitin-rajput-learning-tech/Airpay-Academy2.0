<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');
class filters_form extends moodleform {
    public $thisform;
    function definition() {
        global $CFG;
        $this->thisform = $this->_form;

        $mform    = $this->_form;
        $filterlist        = $this->_customdata['filterlist'];// this contains the data of this form
        $filterparams      = isset($this->_customdata['filterparams']) ? $this->_customdata['filterparams'] : null;
        $action            = isset($this->_customdata['action']) ? $this->_customdata['action'] : null;
        $options           = isset($filterparams['options']) ? $filterparams['options'] : null;
        $dataoptions       = isset($filterparams['dataoptions']) ? $filterparams['dataoptions'] :null;
        $submit            = isset($this->_customdata['submitid']) ? $this->_customdata['submitid']: null;
        $submitid          = $submit ? $submit : 'filteringform';
       // $submitid =  $this->_customdata['submitid'] ? $this->_customdata['submitid'] : 'filteringform';

        $this->_form->_attributes['id'] = $submitid;
        $this->_form->_attributes['class'] = $submitid;
        //$this->_form->_attributes['onsubmit'] = '(function(e){ require("local_costcenter/cardPaginate").filteringData(e,"'.$submitid.'") })(event)';

        if(in_array("enrolid",$filterlist)){
            $enrolid           = $this->_customdata['enrolid']; // this contains the data of this form
            $mform->addElement('hidden', 'enrolid', $enrolid);
            $mform->setType('enrolid', PARAM_INT);
        }
        if(in_array("courseid",$filterlist)){
            $courseid          = $this->_customdata['courseid']; // this contains the data of this form
            $mform->addElement('hidden', 'id', $courseid);
            $mform->setType('id', PARAM_INT);
        }

        $mform->addElement('hidden', 'options', $options);
        $mform->setType('options', PARAM_RAW);

        $mform->addElement('hidden', 'dataoptions', $dataoptions);
        $mform->setType('dataoptions', PARAM_RAW);

        
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        foreach ($filterlist as $key => $value) {
            if($value === 'categories' || $value === 'elearning' || $value === 'status' || $value === 'coursetype'){
                $filter = 'courses';
            } else if($value === 'email' || $value ==='states' || $value ==='district' || $value ==='subdistrict' || $value ==='village' || $value === 'employeeid' || $value === 'username' || $value === 'users' || $value === 'hrmsrole'){
                $filter = 'users';
            } else if($value === 'organizations' || $value === 'departments' || $value === 'subdepartment' || $value === 'department4level' || $value === 'department5level'){
                $filter = 'costcenter';
            } else if($value === 'sorting' || $value === 'requeststatus'){
                $filter = 'request';
            }else if($value === 'evaluation_type'){
                $filter = 'evaluation';
            } else if($value === 'hierarchy_fields'){
                require_once($CFG->dirroot.'/local/costcenter/lib.php');
                local_costcenter_set_costcenter_path($this->_customdata);
                local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,null, false, 'local_users', $categorycontext, $multiple = true, $prefix = 'filter');
                continue;
            } else if($value === 'geographyfields'){
                require_once($CFG->dirroot.'/local/users/lib.php');
                local_users_get_userprofile_fields($mform, $this->_ajaxformdata, $this->_customdata, $allenable = false, 'local_users', $categorycontext, $multiple = true, $prefix = 'filter');
                continue;

            }else{
                $filter = $value;
            }
            $core_component = new \core_component();
            $courses_plugin_exist = $core_component::get_plugin_directory('local', $filter);
            if ($courses_plugin_exist) {
                require_once($CFG->dirroot . '/local/' . $filter . '/lib.php');
                $functionname = $value.'_filter';
                $functionname($mform);
            }
        }
        // When two elements we need a group.
        if($action === 'user_enrolment'){
            $buttonarray = array();
            $applyclassarray = array('class' => 'form-submit');
            $buttonarray[] = &$mform->createElement('submit', 'filter_apply', get_string('apply','local_courses'), $applyclassarray);
            $buttonarray[] = &$mform->createElement('cancel', 'cancel', get_string('reset','local_courses'), $applyclassarray);
        }else{
            $buttonarray = array();
            $applyclassarray = array('class' => 'form-submit','onclick' => '(function(e){ require("local_costcenter/cardPaginate").filteringData(e,"'.$submitid.'") })(event)');
            $cancelclassarray = array('class' => 'form-submit','onclick' => '(function(e){ require("local_costcenter/cardPaginate").resetingData(e,"'.$submitid.'") })(event)');
            $buttonarray[] = &$mform->createElement('button', 'filter_apply', get_string('apply','local_courses'), $applyclassarray);
            $buttonarray[] = &$mform->createElement('button', 'cancel', get_string('reset','local_courses'), $cancelclassarray);

        }
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
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
