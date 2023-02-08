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
 * @subpackage local_program
 */

namespace local_program\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');
use context_system;
use local_program\local\querylib;
use moodleform;
use core_component;

class program_form extends moodleform {
    public $formstatus;

    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {

        $this->formstatus = array(
            'generaldetails' => get_string('generaldetails', 'local_program'),
            'target_audience' => get_string('target_audiencedetails', 'local_program'),
            );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }

    public function definition() {
        global $CFG, $USER, $PAGE, $DB;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $renderer = $PAGE->get_renderer('local_program');
        $context = context_system::instance();
        $form_status = $this->_customdata['form_status'];
        $id = $this->_customdata['id'] > 0 ? $this->_customdata['id'] : 0;
        $formheaders = array_keys($this->formstatus);
        $formheader = $formheaders[$formstatus];

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'form_status', $form_status);
        $mform->setType('form_status', PARAM_INT);

        $core_component = new core_component();

        if($form_status == 0){

            $querieslib = new querylib();
            $mform->addElement('text', 'name', get_string('program_name', 'local_program'), array());
            if (!empty($CFG->formatstringstriptags)) {
                $mform->setType('name', PARAM_TEXT);
            } else {
                $mform->setType('name', PARAM_CLEANHTML);
            }
            $mform->addRule('name', null, 'required', null, 'client');

            if (is_siteadmin() || ((has_capability('local/program:manage_multiorganizations', context_system::instance()) ||has_capability('local/costcenter:manage_multiorganizations', context_system::instance()))) ) {
                $costcenters = array();
                $costcenterslist = $this->_ajaxformdata['costcenter'];
                if (!empty($costcenterslist)) {
                    $costcenterslist = $costcenterslist;
                } else if ($id > 0) {
                    $costcenterslistsql = "SELECT cc.id
                                             FROM {local_costcenter} cc
                                             JOIN {local_program} c ON c.costcenter = cc.id
                                                AND cc.parentid = 0 AND cc.visible = 1 AND
                                                 c.id = :programid";
                    $costcenterslist = $DB->get_field_sql($costcenterslistsql, array('programid' => $id));
                }
                if (!empty($costcenterslist)) {
                    $costcenterslist = $DB->get_records_menu('local_costcenter',
                            array('visible' => 1, 'parentid' => 0, 'id' => $costcenterslist),
                            'id', 'id, fullname');
                    $costcenters = array(null => get_string('select_costcenter',
                            'local_program')) + $costcenterslist;
                }

                $options = array(
                    'ajax' => 'local_program/form-options-selector',
                    'data-contextid' => $context->id,
                    'data-action' => 'program_costcenter_selector',
                    'data-options' => json_encode(array('id' => $id, 'depth' => 1, 'parnetid' => 0)),
                    'class' => 'organizationselect',
                    'data-class' => 'organizationselect'
                );

                $mform->addElement('autocomplete', 'costcenter',
                        get_string('costcenter', 'local_program'), $costcenters, $options);
                $mform->addRule('costcenter', get_string('errororganization', 'local_users'), 'required', null, 'client');
                //$mform->addRule('costcenter', null, 'required', null, 'client');
                $mform->setType('costcenter', PARAM_INT);
            } else {
                $mform->addElement('hidden', 'costcenter',
                        get_string('costcenter', 'local_program'),
                        array( 'data-class' => 'organizationselect'));
                $mform->setType('costcenter', PARAM_INT);
                $mform->setDefault('costcenter', $USER->open_costcenterid);
            }

            $selfenrol = array();
            $selfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('yes'), 1, $attributes);
            $selfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('no'), 0, $attributes);
            $mform->addGroup($selfenrol, 'selfenrol', get_string('selfenrol', 'local_program'), array('&nbsp;&nbsp;'), false);
            $mform->addHelpButton('selfenrol','selfenroll','local_program');

            $manageapproval = array();
            $manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('yes'), 1, $attributes);
            $manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('no'), 0, $attributes);
            $mform->addGroup($manageapproval, 'approvalreqd', get_string('need_manage_approval', 'local_program'), array('&nbsp;&nbsp;'), false);
            $mform->hideIf('approvalreqd', 'selfenrol', 'neq', '1');
            
            $stream = $querieslib->get_program_streamlist();
            $stream = array(null => '--SELECT--') + $stream;
            $mform->addElement('autocomplete', 'stream', get_string('stream', 'local_program'),
                    $stream);
            $mform->addRule('stream', null, 'required', null, 'client');
            $mform->addRule('stream', null, 'numeric', null, 'client');
            $mform->setType('stream', PARAM_INT);
            $mform->addHelpButton('stream','streams','local_program');

            $mform->addElement('text', 'points', get_string('points','local_program'));
            $mform->addHelpButton('points', 'open_pointsprogram', 'local_program');
            $mform->setType('points', PARAM_INT);

            // tags
            $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 
                'program', 'component' => 'local_program')); 

            $mform->addElement('filepicker', 'programlogo',
                    get_string('programlogo', 'local_program'), null,
                    array('maxbytes' => 2048000, 'accepted_types' => '.jpg'));
            $mform->addHelpButton('programlogo','image','local_program');
            $editoroptions = array(
                'noclean' => false,
                'autosave' => false
            );
            $mform->addElement('editor', 'cr_description',
                    get_string('description', 'local_program'), null, $editoroptions);
            $mform->setType('cr_description', PARAM_RAW);
            $mform->addHelpButton('cr_description', 'description', 'local_program');

            //certificate
            $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
            if($certificate_plugin_exist){
                $checkboxes = array();
                $checkboxes[] = $mform->createElement('advcheckbox', 'map_certificate', null, '', array(),array(0,1));
                $mform->addGroup($checkboxes, 'map_certificate', get_string('add_certificate', 'local_program'), array(' '), false);
                $mform->addHelpButton('map_certificate', 'add_certificate', 'local_program');


                $select = array(null => get_string('select_certificate','local_program'));

                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array(),'name', 'id,name');
                }else{
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array('costcenter'=>$USER->open_costcenterid),'name', 'id,name');
                }
                $certificateslist = $select + $cert_templates;

                $mform->addElement('select',  'certificateid', get_string('certificate_template','local_program'), $certificateslist);
                $mform->addHelpButton('certificateid', 'certificate_template', 'local_program');
                $mform->setType('certificateid', PARAM_INT);
                $mform->hideIf('certificateid', 'map_certificate', 'neq', 1);
            }

        }else if($form_status == 1){
            $capability_array = array('local/costcenter:manage_multiorganizations', 
                'local/program:manageprogram',
                'local/costcenter:manage_ownorganization', 
            );
            
            if (is_siteadmin() || (has_any_capability($capability_array, $context) && !has_capability('local/costcenter:manage_owndepartments',$context))) {
                $departments = array();
                $departmentslist = $this->_ajaxformdata['department'];

                $params = array();
                if (!empty($departmentslist)) {
                    $departmentslist = $departmentslist;
                } else if ($id > 0) {
                    $departmentlist = $DB->get_field('local_program', 'department', array('id' => $id));
                    $departmentslist = explode(', ', $departmentlist);
                }
                if (!empty($departmentslist)) {

                    
                    if (is_array($departmentslist)){
                        $departmentslist=implode(',',$departmentslist);
                    }
                    $params['visible'] = 1;
                    $params['depth'] = 2;
                    $departmentlistsql = "SELECT id, fullname
                                            FROM {local_costcenter}
                                           WHERE visible = :visible AND depth = :depth";
                    if(!empty($departmentslist)) {
                        $departmentlistsql .= " AND id in ($departmentslist)";
                    }
                    $departmentlist = $DB->get_records_sql_menu($departmentlistsql, $params);
                    $departments = array(-1 => get_string('all')) + $departmentlist;
                }

                $options = array(
                    'ajax' => 'local_program/form-options-selector',
                    'data-contextid' => $context->id,
                    'data-action' => 'program_costcenter_selector',
                    'data-options' => json_encode(array('id' => $id, 'depth' => 2,
                        'organizationselect' => '.organizationselect', 'department' => true,
                    'organizationselect' => 'organizationselect')),
                    'class' => 'departmentselect',
                    'id' => 'departmentselect',
                    'data-class' => 'departmentselect',
                    'multiple' => true
                );

                $mform->addElement('autocomplete', 'department', get_string('department',
                    'local_classroom'), $departments, $options);
                $mform->setType('department', PARAM_INT);
                
            }else{
                $mform->addElement('hidden',  'department',  $USER->open_departmentid, array('data-class' => 'departmentselect'));
                $mform->setType('department', PARAM_INT);
            }
            if (is_siteadmin() || (has_any_capability($capability_array, $context) || has_capability('local/costcenter:manage_owndepartments',$context))) {
                $subdepartments = array();
                $subdepartmentlist = $this->_ajaxformdata['subdepartment'];

                $params = array();
                if (!empty($subdepartmentlist)) {
                    $subdepartmentlist = $subdepartmentlist;
                } else if ($id > 0) {
                    $subdepartmentlist = $DB->get_field('local_program', 'subdepartment', array('id' => $id));
                    $subdepartmentlist = explode(', ', $subdepartmentlist);
                }
                if (!empty($subdepartmentlist)) {

                    
                    if (is_array($subdepartmentlist)){
                        $subdepartmentlist=implode(',',array_filter($subdepartmentlist));
                    }
                    $params['visible'] = 1;
                    $params['depth'] = 3;
                    $subdepartmentlistsql = "SELECT id, fullname
                                            FROM {local_costcenter}
                                           WHERE visible = :visible AND depth = :depth";
                    if(!empty($subdepartmentlist)) {
                        $subdepartmentlistsql .= " AND id in ($subdepartmentlist)";
                    }
                    $subdepartmentlist = $DB->get_records_sql_menu($subdepartmentlistsql, $params);
                    $subdepartments = array(-1 => get_string('all')) + $subdepartmentlist;
                }else{
                    $subdepartments = array(-1 => get_string('all')); 
                }

                $options = array(
                    'ajax' => 'local_program/form-options-selector',
                    'data-contextid' => $context->id,
                    'data-action' => 'program_costcenter_selector',
                    'data-options' => json_encode(array('id' => $id, 'depth' => 3,
                        'departmentselect' => 'departmentselect', 'subdepartment' => true)),
                    'class' => 'subdepartmentselect',
                    'multiple' => true
                );

                $mform->addElement('autocomplete', 'subdepartment', get_string('subdepartment',
                    'local_costcenter'), $subdepartments, $options);
                $mform->setType('subdepartment', PARAM_INT);
                
            }else{
                $mform->addElement('hidden',  'subdepartment',  $USER->open_subdepartmentid);
                $mform->setType('subdepartment', PARAM_INT);
            }
            $users_plugin_exist = $core_component::get_plugin_directory('local','users');
            if ($users_plugin_exist) {
                require_once($CFG->dirroot . '/local/users/lib.php');
                $functionname ='globaltargetaudience_elementlist';
                 if(function_exists($functionname)) {
                   $modulecostcenter = $DB->get_field('local_program', 'costcenter',array('id' => $id));

                    $mform->modulecostcenter = $modulecostcenter;

                    $functionname($mform,array('group','hrmsrole','designation','location'));
                }
            }
        }

        $mform->disable_form_change_checker();
    }
    public function validation($data, $files) {
        global $CFG, $DB, $USER;

        $errors = parent::validation($data, $files);
        $form_status = $data['form_status'];
        if($form_status == 0){
            if (isset($data['name']) && empty(trim($data['name']))) {
                $errors['name'] = get_string('valnamerequired', 'local_program');
            }

            if (!isset($data['stream']) || $data['stream'] < 1) {
                $errors['stream'] = 'Please select the Stream.';
            }

            if ($data['map_certificate'] == 1 && empty($data['certificateid'])){
                $errors['certificateid'] = get_string('err_certificate', 'local_courses');
            }

            if (isset($data['costcenter']) && $data['form_status'] == 0){
             if($data['costcenter'] == 0){
                $errors['costcenter'] = get_string('pleaseselectorganization', 'local_program');
             }
         }

        }
        return $errors;
    }

    public function set_data($components) {
        global $DB;
        $context = context_system::instance();
        $data = $DB->get_record('local_program', array('id' => $components->id));
        //populate tags
        $data->tags = \local_tags_tag::get_item_tags_array('local_program', 'program', $components->id);
        $data->cr_description = array();
        $data->cr_description['text'] = $data->description;
        $draftitemid = file_get_submitted_draft_itemid('programlogo');
        file_prepare_draft_area($draftitemid, $context->id, 'local_program', 'programlogo',
            $data->programlogo, null);
        $data->programlogo = $draftitemid;
        $data->open_group =(!empty($data->open_group)) ? array_diff(explode(',',$data->open_group), array('')) :array(NULL=>NULL);
        $data->open_hrmsrole =(!empty($data->open_hrmsrole)) ? array_diff(explode(',',$data->open_hrmsrole), array('')) :array(NULL=>NULL);
        $data->open_designation =(!empty($data->open_designation)) ? array_diff(explode(',',$data->open_designation), array('')) :array(NULL=>NULL);
        $data->open_location =(!empty($data->open_location)) ? array_diff(explode(',',$data->open_location), array('')) :array(NULL=>NULL);
        if(!empty($data->certificateid)){
            $data->map_certificate = 1;
        }
        parent::set_data($data);
    }
}