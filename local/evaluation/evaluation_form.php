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
 * @package BizLMS
 * @subpackage local_evaluation
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/local/users/lib.php');
require_once('lib.php');
use local_costcenter\functions\userlibfunctions as costcenterlib;
class evaluation_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;

        $editoroptions = evaluation_get_editor_options();
        $mform    =& $this->_form;
        $mformajax    =& $this->_ajaxformdata;
        $id = $this->_customdata['id'];
        $instance = $this->_customdata['instance'];
        $plugin = $this->_customdata['plugin'];
        $categorycontext = (new \local_evaluation\lib\accesslib())::get_module_context();
        local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_evaluation', $categorycontext, $multiple = false);
        $mform->addElement('text', 'name', get_string('name', 'local_evaluation'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('feedbackname', 'local_evaluation'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');       
        if ($instance == 0) {
            $type = array(null => get_string('selecttype', 'local_evaluation'),
                         '1'=>get_string('feedback', 'local_evaluation'),
                         '2'=>get_string('survey', 'local_evaluation'));
            $mform->addElement('autocomplete', 'type', get_string('type', 'local_evaluation'), $type);
            $mform->addRule('type', null, 'required', null, 'client');
            
            if (is_siteadmin($USER->id) || has_capability('local/evaluation:evaluationmode',$categorycontext)) {
                $radioarray=array();
                $radioarray[] = $mform->createElement('radio', 'evaluationmode', '',get_string('self_evaluation', 'local_evaluation'),'SE');
                $radioarray[] = $mform->createElement('radio', 'evaluationmode', '', get_string('supervsior_evaluation', 'local_evaluation'), 'SP');
                $mform->addGroup($radioarray, 'evaluationmode', get_string('submissiontype', 'local_evaluation'), array(' '), false);
                $mform->setDefault('evaluationmode', 'SE');
                $submissions = $DB->record_exists('local_evaluation_completed', array('evaluation'  => $id));
                if($submissions){
                    $mode = $DB->get_field('local_evaluations', 'evaluationmode', array('id' => $id));
                    $mform->disabledIf('evaluationmode', 'id', 'eq', $id);
                    $mform->setConstant('evaluationmode', $mode);
                }
                $mform->addHelpButton('evaluationmode', 'evaluationmode', 'local_evaluation');
            }
        } 
        if ($instance !=0) {
            // get elements from respective plugin
            require_once($CFG->dirroot . '/local/'.$plugin.'/lib.php');
            $function = $plugin . '_evaluationtypes';
            if(function_exists($function)) {
                $evaltypes = $function($id,$instance,'form');
                $mform->addElement('select', 'evaluationtype', get_string('type', 'local_evaluation'), $evaltypes);
                $mform->setType('evaluationtype', PARAM_INT);
                $mform->addRule('evaluationtype', null, 'required', null, 'client');
            }
        }
        $mform->addElement('hidden', 'instance', $instance);
        $mform->setType('instance', PARAM_INT);
       
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('instance', PARAM_INT);

        $mform->addElement('hidden', 'plugin', $plugin);
        $mform->setType('plugin', PARAM_RAW);
            
        $mform->addElement('editor', 'introeditor', get_string('moduleintro'), array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,  'subdirs' => true,'autosave' => false));
        $mform->setType('introeditor', PARAM_RAW); // no XSS prevention here, users must be trusted

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('availability'));

        $mform->addElement('date_time_selector', 'timeopen', get_string('evaluationopen', 'local_evaluation'),
            array('optional' => true));

        $mform->addElement('date_time_selector', 'timeclose', get_string('evaluationclose', 'local_evaluation'),
            array('optional' => true));
        if ($instance !=0) {
            $mform->addElement('selectyesno', 'visible', get_string('visible'));
            $mform->setDefault('visible', 1);
        }else{
            $visible = $id ? $DB->get_field('local_evaluations', 'visible', array('id' => $id)) : 1;
            $mform->addElement('hidden', 'visible', $visible);
            $mform->setDefault('visible', $visible);
        }
        //-------------------------------------------------------------------------------
        if($instance==0)
        {
        $mform->addElement('header', 'evaluationhdr', get_string('target_audiance','local_evaluation'));
        local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(2,4), true, 'local_evaluation', $categorycontext, $multiple = false);
            $functionname ='globaltargetaudience_elementlist';

            if(function_exists($functionname)) {
                $mform->modulecostcenterpath = $customdata[$firstdepth];
                $functionname($mform,array('group','designation'));
            }
        }
        //-------------------------------------------------------------------------------
     $mform->addElement('header', 'aftersubmithdr', get_string('questionandsubmission', 'local_evaluation'));

     $options=array();
     $options[1]  = get_string('anonymous', 'local_evaluation');
     $options[2]  = get_string('non_anonymous', 'local_evaluation');
     $mform->addElement('select',
                        'anonymous',
                        get_string('anonymous_edit', 'local_evaluation'),
                        $options);
     if ($instance != 0) {
         $mform->setDefault('anonymous', 2);
     }

     // check if there is existing responses to this evaluation
     if ( $evaluation = $DB->get_record("local_evaluations", array("id"=>$id))) {
         $completed_evaluation_count = evaluation_get_completeds_group_count($evaluation);
     } else {
         $completed_evaluation_count = false;
     }

     if ($completed_evaluation_count) {
         $multiple_submit_value = $evaluation->multiple_submit ? get_string('yes') : get_string('no');
         $mform->addElement('text', 'multiple_submit_static', get_string('multiplesubmit', 'local_evaluation'),
         array('size'=>'4', 'disabled'=>'disabled', 'value'=>$multiple_submit_value));
         $mform->setType('multiple_submit_static', PARAM_RAW);

         $mform->addElement('hidden', 'multiple_submit', '');
         $mform->setType('multiple_submit', PARAM_INT);
         $mform->addHelpButton('multiple_submit_static', 'multiplesubmit', 'local_evaluation');
     } else {
         $mform->addElement('selectyesno',
                            'multiple_submit',
                            get_string('multiplesubmit', 'local_evaluation'));
         if ($instance != 0) {
             $mform->setDefault('multiple_submit', 1);
         }
         

         $mform->addHelpButton('multiple_submit', 'multiplesubmit', 'local_evaluation');
     }
    //---------------------------------------------------------------------------------------------------
    }

    public function data_preprocessing(&$default_values) {
        $editoroptions = evaluation_get_editor_options();
        $categorycontext = (new \local_evaluation\lib\accesslib())::get_module_context();
        if ($default_values['id']) {
            // editing an existing evaluation - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('page_after_submit');
            $default_values['page_after_submit_editor']['text'] =
                                    file_prepare_draft_area($draftitemid, $categorycontext->id,
                                    'local_evaluation', 'page_after_submit', false,
                                    $editoroptions,
                                    $default_values['page_after_submit']);

            $default_values['page_after_submit_editor']['format'] = $default_values['page_after_submitformat'];
            $default_values['page_after_submit_editor']['itemid'] = $draftitemid;
            // setting for intro field
            $draftitemid = file_get_submitted_draft_itemid('intro');
            $default_values['introeditor']['text'] =
                                    file_prepare_draft_area($draftitemid, $categorycontext->id,
                                    'local_evaluation', 'intro', false,
                                    $editoroptions,
                                    $default_values['intro']);

            $default_values['introeditor']['format'] = $default_values['introformat'];
            $default_values['introeditor']['itemid'] = $draftitemid;
            
        } else {
            // adding a new evaluation instance
            $draftitemid = file_get_submitted_draft_itemid('page_after_submit_editor');
            // no context yet, itemid not used
            file_prepare_draft_area($draftitemid, null, 'local_evaluation', 'page_after_submit', false);
            $default_values['page_after_submit_editor']['text'] = '';
            $default_values['page_after_submit_editor']['format'] = editors_get_preferred_format();
            $default_values['page_after_submit_editor']['itemid'] = $draftitemid;
            
            
            // adding a new evaluation instance
            $draftitemid = file_get_submitted_draft_itemid('introeditor');
            // no context yet, itemid not used
            file_prepare_draft_area($draftitemid, null, 'local_evaluation', 'intro', false);
            $default_values['introeditor']['text'] = '';
            $default_values['introeditor']['format'] = editors_get_preferred_format();
            $default_values['introeditor']['itemid'] = $draftitemid;
        }
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (isset($data->page_after_submit_editor)) {
            $data->page_after_submitformat = $data->page_after_submit_editor['format'];
            $data->page_after_submit = $data->page_after_submit_editor['text'];
        }
        
        if (isset($data->introeditor)) {
            $data->introformat = $data->introeditor['format'];
            $data->intro = $data->introeditor['text'];
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     **/
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if(isset($data['name']) && empty(trim($data['name']))){
            $errors['name'] = get_string('name_required','local_evaluation');
        }
        if(isset($data['open_costcenterid']) && empty(trim($data['open_costcenterid']))){
            $errors['open_costcenterid'] = get_string('orgname_required','local_evaluation');
        }
        if (isset($data['type']) && empty(trim($data['type']))) {
            $errors['type'] = get_string('typemissing', 'local_evaluation');
        }

        // Check open and close times are consistent.
        if ($data['timeopen'] && $data['timeclose'] &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'local_evaluation');
        }
        if (empty($data['instance'])) {
            if ( array_key_exists('costcenterid', $data) ) {
                if (empty($data['costcenterid']) AND ($data['id'] < 0) ) {
                    $errors['costcenterid'] = get_string('required');
                }
            }
        }
        return $errors;
    }
}
