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
 * @subpackage local_request
 */

namespace local_request\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');

use context_system;
use moodleform;

class request_form extends moodleform {
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post',
        $target = '', $attributes = null, $editable = true, $formdata = null) {
        $this->formstatus = array(
            'manage_classroom' => get_string('manage_classroom', 'local_classroom'),
            'location_date' => get_string('location_date', 'local_classroom'),
            'classroom_misc' => get_string('assign_course', 'local_classroom'),
        );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }

    public function definition() {
        global $CFG, $USER, $PAGE, $DB;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $renderer = $PAGE->get_renderer('local_classroom');
        $context = context_system::instance();
        $formstatus = $this->_customdata['form_status'];
        $id = $this->_customdata['id'] > 0 ? $this->_customdata['id'] : 0;
        $formheaders = array_keys($this->formstatus);
        $formheader = $formheaders[$formstatus];

        //$mform->addElement('header', 'general', $this->formstatus[$formheader]);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        if ($formstatus == 0) {
            $querieslib = new querylib();
            $mform->addElement('text', 'name', get_string('classroom_name', 'local_classroom'), array());
            if (!empty($CFG->formatstringstriptags)) {
                $mform->setType('name', PARAM_TEXT);
            } else {
                $mform->setType('name', PARAM_CLEANHTML);
            }
            $mform->addRule('name', null, 'required', null, 'client');

            if (is_siteadmin() || ((has_capability('local/classroom:manage_multiorganizations', context_system::instance()) ||has_capability('local/costcenter:manage_multiorganizations', context_system::instance()))) ) {

                $costcenters = array();
                $costcenterslist = $this->_ajaxformdata['costcenter'];
                if (!empty($costcenterslist)) {
                    $costcenterslist = $costcenterslist;
                } else if ($id > 0) {
                    $costcenterslistsql = "SELECT cc.id
                                             FROM {local_costcenter} cc
                                             JOIN {local_classroom} c ON c.costcenter = cc.id
                                             AND cc.parentid = 0 AND cc.visible = 1 AND
                                             c.id = :classroomid";
                    $costcenterslist = $DB->get_field_sql($costcenterslistsql, array('classroomid' => $id));
                }
                if (!empty($costcenterslist)) {
                    $costcenterslist = $DB->get_records_menu('local_costcenter',
                        array('visible' => 1, 'parentid' => 0, 'id' => $costcenterslist),
                        'id', 'id, fullname');
                    $costcenters = array(null => get_string('select_costcenter',
                        'local_classroom')) + $costcenterslist;
                }

                $options = array(
                    'ajax' => 'local_classroom/form-options-selector',
                    'data-contextid' => $context->id,
                    'data-action' => 'classroom_costcenter_selector',
                    'data-options' => json_encode(array('id' => $id, 'depth' => 1, 'parnetid' => 0)),
                    'class' => 'organizationselect',
                    'data-class' => 'organizationselect'
                );

                $mform->addElement('autocomplete', 'costcenter',
                    get_string('costcenter', 'local_classroom'), $costcenters, $options);
                $mform->addRule('costcenter', null, 'required', null, 'client');
                $mform->setType('costcenter', PARAM_INT);
            } else {
                $mform->addElement('hidden', 'costcenter',get_string('costcenter', 'local_classroom'),
                    array( 'data-class' => 'organizationselect'));
                $mform->setType('costcenter', PARAM_INT);
                $mform->setDefault('costcenter', $USER->open_costcenterid);
            }

           if ((!is_siteadmin() && (((!has_capability('local/classroom:manage_multiorganizations', context_system::instance()) &&! has_capability('local/costcenter:manage_multiorganizations', context_system::instance()))) && has_capability('local/classroom:manageclassroom', $context)&&(!has_capability('local/classroom:manage_owndepartments',$context)&&
                                         !has_capability('local/costcenter:manage_owndepartments',$context))))) {
                $departments = array();
                $departmentslist = $this->_ajaxformdata['department'];

                $params = array();
                if (!empty($departmentslist)) {
                    $departmentslist = $departmentslist;
                } else if ($id > 0) {
                    $departmentlist = $DB->get_field('local_classroom', 'department', array('id' => $id));
                    $departmentslist = explode(', ', $departmentlist);
                }
                if (!empty($departmentslist)) {

                    //list($departmentslistsql, $departmentslistparams) = $DB->get_in_or_equal($departmentslist, SQL_PARAMS_NAMED, 'crdept');
                    //$params = array_merge($params, $departmentslistparams);
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
                    $departments = array(null => get_string('select_department',
                        'local_classroom')) + $departmentlist;
                }

                $options = array(
                    'ajax' => 'local_classroom/form-options-selector',
                    'multiple' => true,
                    'data-contextid' => $context->id,
                    'data-action' => 'classroom_costcenter_selector',
                    'data-options' => json_encode(array('id' => $id, 'depth' => 2,
                        'organizationselect' => '.organizationselect', 'department' => true,
                    'organizationselect' => 'organizationselect')),
                    'class' => 'departmentselect'
                );

                $mform->addElement('autocomplete', 'department', get_string('department',
                    'local_classroom'), $departments, $options);
                $mform->setType('department', PARAM_INT);
                
            }elseif (is_siteadmin() || ((!has_capability('local/classroom:manage_multiorganizations', context_system::instance()) &&! has_capability('local/costcenter:manage_multiorganizations', context_system::instance())) && has_capability('local/classroom:manageclassroom', $context)&&(has_capability('local/classroom:manage_owndepartments',$context)||has_capability('local/costcenter:manage_owndepartments',$context)))) {
                
                $mform->addElement('hidden', 'department', get_string('department',
                    'local_classroom'));
                $mform->setType('department', PARAM_INT);
                $mform->setDefault('department', $USER->open_departmentid);
            }

            $type = array(1 => get_string('classroom', 'local_classroom'),
                2 => get_string('learningplan', 'local_classroom'),
                3 => get_string('certification', 'local_classroom'),
            );
            $mform->addElement('hidden', 'type', get_string('type', 'local_classroom'), $type,
             array());
            $mform->addRule('type', null, 'required', null, 'client');
            $mform->setType('type', PARAM_INT);

             //*OPEN LMSOL-333 Employee_Search_Classroom*//
             
                //$manageapproval = array();
                //$manageapproval[] = $mform->createElement('radio', 'manage_approval', '', get_string('yes'), 1, $attributes);
                //$manageapproval[] = $mform->createElement('radio', 'manage_approval', '', get_string('no'), 0, $attributes);
                //$mform->addGroup($manageapproval, 'manage_approval',
                //    get_string('need_manage_approval', 'local_classroom'),
                //    array('&nbsp;&nbsp;'), false);
            
            //*OPEN LMSOL-333 Employee_Search_Classroom*//
        
            $allowmultisession = array();
            $allowmultisession[] = $mform->createElement('radio', 'allow_multi_session', '',
             get_string('fixed', 'local_classroom'), 1, $attributes);
            $allowmultisession[] = $mform->createElement('radio', 'allow_multi_session', '',
             get_string('custom', 'local_classroom'), 0, $attributes);
            $mform->addGroup($allowmultisession, 'radioar',
                get_string('allow_multi_session', 'local_classroom'), array('&nbsp;&nbsp;'),
                 false);
            $mform->addRule('radioar', null, 'required');

           // $mform->addElement('hidden', 'mincapacity', 0);
           // $mform->setType('mincapacity', PARAM_INT);

            $mform->addElement('text', 'capacity', get_string('capacity', 'local_classroom'),
             array());
            $mform->setType('capacity', PARAM_RAW);
           // $mform->addRule('capacity', null, 'required', null, 'client');
           // $mform->addRule(array('capacity', 'mincapacity'), get_string('capacity_positive',
             //'local_classroom'), 'compare', 'gt', 'client');

            $trainers = array();
            $trainerslist = $this->_ajaxformdata['trainers'];
            if (!empty($trainerslist)) {
                $trainerslist = $trainerslist;
            } else if ($id > 0) {
                $trainerslist = $DB->get_records_menu('local_classroom_trainers',
                    array('classroomid' => $id), 'id', 'id, trainerid');
            }
            if (!empty($trainerslist)) {
                $trainers = $querieslib->get_user_department_trainerslist(false, false,
                    $trainerslist);
            }
            $options = array(
                'ajax' => 'local_classroom/form-options-selector',
                'multiple' => true,
                'data-contextid' => $context->id,
                'data-action' => 'classroom_trainer_selector',
                'data-options' => json_encode(array('id' => $id)),
            );

            $mform->addElement('autocomplete', 'trainers', get_string('trainers', 'local_classroom'), $trainers, $options);
           // $mform->addRule('trainers', get_string('required'), 'required', null, 'client');


            $mform->addElement('date_time_selector', 'startdate', get_string('startdate',
                'local_classroom'), array('optional' => false));
            $mform->addRule('startdate', null, 'required', null, 'client');

            $mform->addElement('date_time_selector', 'enddate', get_string('enddate',
                'local_classroom'), array('optional' => false));
            $mform->addRule('enddate', null, 'required', null, 'client');

        } else if ($formstatus == 1) {

            $allowmultisession = array();
            $allowmultisession[] = $mform->createElement('radio', 'institute_type', '',
                get_string('internal', 'local_classroom'), 1, $attributes);
            $allowmultisession[] = $mform->createElement('radio', 'institute_type', '',
                get_string('external', 'local_classroom'), 2, $attributes);
            $mform->addGroup($allowmultisession, 'radioar', get_string('clrm_location_type',
                'local_classroom'), array(' '), false);
            //$mform->addRule('radioar', null, 'required');


            $classroomlocations =  array(null => get_string('select_institutions',
                        'local_classroom'));
            $instituteid = $this->_ajaxformdata['instituteid'];
            //print_object($instituteid);
            if (!empty($instituteid)) {
                $instituteid = $instituteid;
            } else if ($id > 0) {
                $instituteid = $DB->get_field('local_classroom', 'instituteid',
                    array('id' => $id));
            }
            if (!empty($instituteid)) {
                $classroomlocations =$DB->get_records_menu('local_location_institutes',
                 array('id' => $instituteid), 'id', 'id, fullname');

            }
            $options = array(
                'ajax' => 'local_classroom/form-options-selector',
                'data-contextid' => $context->id,
                'data-action' => 'classroom_institute_selector',
                'data-options' => json_encode(array('id' => $id)),
                'data-institute_type' => 'institute_type'
            );

            $mform->addElement('autocomplete', 'instituteid', get_string('classroom_location',
             'local_classroom'),$classroomlocations, $options);
            //$mform->addRule('instituteid', null, 'required', null, 'client');

            $mform->addElement('date_time_selector', 'nomination_startdate',
                get_string('nomination_startdate', 'local_classroom'),
                array('optional' => true));
            //$mform->addRule('nomination_startdate', null, 'required', null, 'client');

            $mform->addElement('date_time_selector', 'nomination_enddate',
             get_string('nomination_enddate', 'local_classroom'),
             array('optional' => true));

            //$mform->addRule('nomination_enddate', null, 'required', null, 'client');

        } else if ($formstatus == 2) {



            $mform->addElement('filepicker', 'classroomlogo', get_string('file'), null,
                array('maxbytes' => 2048000, 'accepted_types' => '.jpg'));

            $editoroptions = array(
                'noclean' => false,
                'autosave' => false
            );
            $mform->addElement('editor', 'cr_description', get_string('description',
                'local_classroom'), null, $editoroptions);
            $mform->setType('cr_description', PARAM_RAW);
            $mform->addHelpButton('cr_description', 'description', 'local_classroom');

        }

        $mform->disable_form_change_checker();
    }
    public function validation($data, $files) {
        global $CFG, $DB, $USER;

        $errors = parent::validation($data, $files);

         $data_db = $DB->get_record_sql('SELECT id,startdate,enddate,capacity FROM {local_classroom}
                WHERE id = ' . $data['id']);
         //print_object($data_db);
         //print_object($data);
        if (isset($data['startdate']) && $data['startdate'] &&
                isset($data['enddate']) && $data['enddate']) {
            if ($data['enddate'] <= $data['startdate']) {
                $errors['enddate'] = get_string('enddateerror', 'local_classroom');
            }
        }
        
       
        if(isset($data['name']) &&empty(trim($data['name']))){
            $errors['name'] = get_string('valnamerequired','local_classroom');
        }
        
	    if(isset($data['capacity']) &&!empty(trim($data['capacity']))){
                // print_object($data);
	    		if(!is_numeric(trim($data['capacity']))){
	    			$errors['capacity'] = get_string('numeric','local_classroom');
	    		}
                if(is_numeric(trim($data['capacity']))&&trim($data['capacity'])<0){
	    			$errors['capacity'] = get_string('positive_numeric','local_classroom');
	    		}
		}
        
		
        if($data['id']>0){
            $allocatedseats=$DB->count_records('local_classroom_users',array('classroomid'=>$data['id'])) ;
            if($data['capacity']!=NULL&&trim($data['capacity'])<$allocatedseats){
                        $errors['capacity'] = get_string('capacity_enroll_check','local_classroom');
            }
        }
        if ((isset($data['nomination_startdate']) && $data['nomination_startdate'])||
                 (isset($data['nomination_enddate']) && $data['nomination_enddate'])&&$data['id']>0) {

            $data['startdate']= $data_db->startdate;
            $data['enddate']= $data_db->enddate;

            if ($data['nomination_startdate'] > $data['startdate']) {
                $errors['nomination_startdate'] = get_string('nomination_startdateerror', 'local_classroom');
            }
            if ($data['nomination_enddate'] > $data['startdate']) {
                $errors['nomination_enddate'] = get_string('nomination_enddateerror', 'local_classroom');
            }
            elseif ($data['nomination_enddate'] <= $data['nomination_startdate']) {
                $errors['nomination_enddate'] = get_string('nomination_error', 'local_classroom');
            }
        }
        return $errors;
    }

    public function set_data($components) {
        global $DB;
     
        if ($components->form_status == 0) {
            $data = $DB->get_record('local_classroom', array('id' => $components->id));
            if($data->department==-1){
                $data->department=null;
            }
            $params = array();
            $params['classroomid'] = $components->id;

            $sql = " SELECT ct.trainerid AS crtrid, ct.trainerid
                      FROM {local_classroom} c
                      JOIN {local_classroom_trainers} ct ON ct.classroomid = c.id
                     WHERE c.id = :classroomid";
            $trainers = $DB->get_records_sql_menu($sql, $params);
            $data->trainers = $trainers;
        } else if ($components->form_status == 1) {
         
            $data = $DB->get_record_sql('SELECT id, institute_type, instituteid,
                nomination_startdate, nomination_enddate,startdate,enddate FROM {local_classroom}
                WHERE id = ' . $components->id);
            if($data->instituteid==0){
                $data->instituteid=null;
            }

        } else if ($components->form_status == 2) {
            $data = $DB->get_record_sql('SELECT id, description, manage_approval,
             classroomlogo, nomination_startdate, nomination_enddate
             FROM {local_classroom} WHERE id = ' . $components->id);
            $data->cr_description['text'] = $data->description;
        }
        
        parent::set_data($data);
    }
}