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
 * @package   Bizlms
 * @subpackage  classroom
 * @author eabyas  <info@eabyas.in>
**/

namespace local_classroom\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/local/users/lib.php');
require_once($CFG->libdir . '/completionlib.php');
use local_classroom\local\querylib;
use moodleform;
use core_component;

class classroom_form extends moodleform {
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post',
        $target = '', $attributes = null, $editable = true, $formdata = null) {
        $this->formstatus = array(
            'manage_classroom' => get_string('manage_classroom', 'local_classroom'),
            'location_date' => get_string('location_date', 'local_classroom'),
            'classroom_misc' => get_string('assign_course', 'local_classroom'),
            'target_audience' => get_string('target_audience', 'local_users'),
        );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }

    public function definition() {
        global $CFG, $USER, $PAGE, $DB;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $renderer = $PAGE->get_renderer('local_classroom');
        $formstatus = $this->_customdata['form_status'];
        $id = $this->_customdata['id'] > 0 ? $this->_customdata['id'] : 0;
        $formheaders = array_keys($this->formstatus);
        $formheader = $formheaders[$formstatus];
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($id);

        $mform->addElement('hidden', 'id', $id, array('id' => 'classroomid'));
        $mform->setType('id', PARAM_INT);
        $org = (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path(1);
        $core_component = new core_component();
        if ($formstatus == 0) {
            $querieslib = new querylib();           

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_classroom', $categorycontext, $multiple = false);
            $mform->addElement('text', 'name', get_string('classroom_name', 'local_classroom'), array());
            if (!empty($CFG->formatstringstriptags)) {
                $mform->setType('name', PARAM_TEXT);
            } else {
                $mform->setType('name', PARAM_CLEANHTML);
            }
            $mform->addRule('name', null, 'required', null, 'client');

            $depsql = "SELECT lcc.id,lcc.fullname
                        FROM {local_custom_category} as lcc";

            $parents = $DB->get_records_sql_menu($depsql, ['parentid' => 0]);
            // $parents[0] = 'Top';
        $parents[0] = 'Select Category';       
        ksort($parents);
            $categoryinfo = array(
                'ajax' => 'local_costcenter/form-options-selector',
                'data-contextid' => (\local_costcenter\lib\accesslib::get_module_context())->id,
                'data-action' => 'custom_category_selector',
                'data-options' => json_encode(array('id' => $id)),
                'class' => 'idparentselect',
                'data-parentclass' => 'open_costcenterid_select',
                'data-class' => 'idparentselect',
                'multiple' => false,
            );

            $mform->addElement('autocomplete', 'open_categoryid', get_string('category'), $parents, $categoryinfo);
            $mform->setType('open_categoryid', PARAM_INT);
            $manageselfenrol = array();
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('yes'), 1, $attributes);
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('no'), 0, $attributes);
            $mform->addGroup($manageselfenrol, 'selfenrol',
                get_string('need_self_enrol', 'local_classroom'),
                array('&nbsp;&nbsp;'), false);
            $mform->addHelpButton('selfenrol', 'selfenrolclassroom', 'local_classroom');


           

            $manageapproval = array();
            $manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('yes'), 1, $attributes);
            $manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('no'), 0, $attributes);
            $mform->addGroup($manageapproval, 'approvalreqd',
                get_string('need_manage_approval', 'local_classroom'),
                array('&nbsp;&nbsp;'), false);
            $mform->addHelpButton('approvalreqd', 'need_manage_approval', 'local_classroom');
            $mform->hideIf('approvalreqd', 'selfenrol', 'neq', '1');



            $mform->addElement('hidden',  'visible',  1);
            $mform->setType('visible', PARAM_INT);

            $allowmultisession = array();
            $allowmultisession[] = $mform->createElement('radio', 'allow_multi_session', '',
             get_string('fixed', 'local_classroom'), 1, $attributes);
            $allowmultisession[] = $mform->createElement('radio', 'allow_multi_session', '',
             get_string('custom', 'local_classroom'), 0, $attributes);
            $mform->addGroup($allowmultisession, 'radioar',
                get_string('allow_multi_session', 'local_classroom'), array('&nbsp;&nbsp;'),
                 false);
            $mform->addHelpButton('radioar','allow_multiple_sessions','local_classroom');
            $mform->addRule('radioar', null, 'required');

            $mform->addElement('text', 'capacity', get_string('capacity', 'local_classroom'),
             array());
            $mform->setType('capacity', PARAM_RAW);
            $mform->addHelpButton('capacity','capacity_check','local_classroom');
             if (is_siteadmin() || ((has_capability('local/classroom:viewwaitinglist_userstab', $categorycontext)))){
                    $allowwaitinglistusers = array();
                    $allowwaitinglistusers[] = $mform->createElement('radio', 'allow_waitinglistusers', '',
                     get_string('yes'), 1, $attributes);
                    $allowwaitinglistusers[] = $mform->createElement('radio', 'allow_waitinglistusers', '',
                     get_string('no'), 0, $attributes);
                    $mform->addGroup($allowwaitinglistusers, 'allow_waitinglistusers',
                        get_string('allow_waitinglistusers', 'local_classroom'), array('&nbsp;&nbsp;'),
                         false);
                   $mform->addHelpButton('allow_waitinglistusers','allowuserswaitinglist','local_classroom');
             }
           
            // $mform->addElement('text', 'open_points', get_string('points','local_classroom'));
            // $mform->addHelpButton('open_points', 'open_pointsclassroom', 'local_classroom');
            // $mform->setType('open_points', PARAM_INT);

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
                'data-contextid' => $categorycontext->id,
                'data-action' => 'classroom_trainer_selector',
                'data-options' => json_encode(array('id' => $id,'organizationselect' => 'organizationselect')),
                'class' => 'trainerselect',
                'data-parentclass' => 'open_costcenterid_select',
                'data-class' => 'idparentselect',
            );
             
            $mform->addElement('autocomplete', 'trainers', get_string('trainers', 'local_classroom'), $trainers, $options);
            $mform->addHelpButton('trainers','traning','local_classroom');


            $mform->addElement('date_time_selector', 'startdate', get_string('startdate',
                'local_classroom'), array('optional' => false));
            $mform->addRule('startdate', null, 'required', null, 'client');

            $mform->addElement('date_time_selector', 'enddate', get_string('enddate',
                'local_classroom'), array('optional' => false));
            $mform->addRule('enddate', null, 'required', null, 'client');
            // tags
            // $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'classroom', 'component' => 'local_classroom'));

            //certificate
           
        } else if ($formstatus == 1) {
            $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
            if($certificate_plugin_exist){
                $checkboxes = array();
                $checkboxes[] = $mform->createElement('advcheckbox', 'map_certificate', null, '', array(),array(0,1));
                $mform->addGroup($checkboxes, 'map_certificate', get_string('add_certificate', 'local_classroom'), array(' '), false);
                $mform->addHelpButton('map_certificate', 'add_certificate', 'local_classroom');


                $select = array(null => get_string('select_certificate','local_classroom'));
                if(is_siteadmin()){
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array(),'name', 'id,name');
                }else{
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array('costcenter'=>$org),'name', 'id,name');
                }
                $certificateslist = $select + $cert_templates;

                $mform->addElement('select',  'certificateid', get_string('certificate_template','local_classroom'), $certificateslist);
                $mform->addHelpButton('certificateid', 'certificate_template', 'local_classroom');
                $mform->setType('certificateid', PARAM_INT);
                $mform->hideIf('certificateid', 'map_certificate', 'neq', 1);
            }

            $allowmultisession = array();
            $allowmultisession[] = $mform->createElement('radio', 'institute_type', '',
                get_string('internal', 'local_classroom'), 1, $attributes);
            $allowmultisession[] = $mform->createElement('radio', 'institute_type', '',
                get_string('external', 'local_classroom'), 2, $attributes);
            $mform->addGroup($allowmultisession, 'radioar', get_string('clrm_location_type',
                'local_classroom'), array(' '), false);
              $mform->addHelpButton('radioar', 'classroom_locationtype', 'local_classroom');


            $classroomlocations =  array(null => get_string('select_institutions',
                        'local_classroom'));
            $instituteid = $this->_ajaxformdata['instituteid'];

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
                'data-contextid' => $categorycontext->id,
                'data-action' => 'classroom_institute_selector',
                'data-options' => json_encode(array('id' => $id)),
                'data-institute_type' => 'institute_type'
            );

            $mform->addElement('autocomplete', 'instituteid', get_string('classroom_location',
             'local_classroom'),$classroomlocations, $options);

            $mform->addElement('date_time_selector', 'nomination_startdate',
                get_string('nomination_startdate', 'local_classroom'),
                array('optional' => true));

            $mform->addElement('date_time_selector', 'nomination_enddate',
             get_string('nomination_enddate', 'local_classroom'),
             array('optional' => true));

        } else if ($formstatus == 2) {
            $mform->addElement('filepicker', 'classroomlogo', get_string('classroomlogo','local_classroom'), null,
                array('maxbytes' => 2048000, 'accepted_types' => '.jpg'));
            $mform->addHelpButton('classroomlogo', 'bannerimage', 'local_classroom');

            $editoroptions = array(
                'noclean' => false,
                'autosave' => false
            );
            $mform->addElement('editor', 'cr_description', get_string('description',
                'local_classroom'), null, $editoroptions);
            $mform->setType('cr_description', PARAM_RAW);
            $mform->addHelpButton('cr_description', 'description', 'local_classroom');

        }else if ($formstatus == 3) {
            $mform->addElement('hidden', 'open_costcenterid');
            $mform->setType('open_costcenterid', PARAM_INT);
            
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(2,5), true, 'local_classroom', $categorycontext, $multiple = false);
           
			// local_users_get_userprofile_fields($mform, $this->_ajaxformdata, $this->_customdata, false, 'local_classroom', $categorycontext, $multiple = false);
            $functionname = 'globaltargetaudience_elementlist';

            if(function_exists($functionname)) {
                $costcenterfields = local_costcenter_get_fields();
                $firstdepth = current($costcenterfields);
                $mform->modulecostcenterpath = $this->_customdata[$firstdepth];

                $functionname($mform,array('group','designation'));
            }
        }
        $mform->disable_form_change_checker();
    }
    public function validation($data, $files) {
        global $CFG, $DB, $USER;

        $errors = parent::validation($data, $files);

         $data_db = $DB->get_record_sql('SELECT id,startdate,enddate,capacity FROM {local_classroom}
                WHERE id = ' . $data['id']);
        if (isset($data['startdate']) && $data['startdate'] &&
                isset($data['enddate']) && $data['enddate']) {
            if ($data['enddate'] <= $data['startdate']) {
                $errors['enddate'] = get_string('enddateerror', 'local_classroom');
            }
        }
        // print_r($data['trainers']);
        // print_r($data['id']);
        if(isset($data['trainers']) && !empty(($data['trainers']))){
            $trainernames = $DB->get_records_sql_menu("SELECT u.id, concat(u.firstname,' ',u.lastname) AS trainername
                FROM {local_classroom} AS lc 
                JOIN {local_classroom_trainers} AS lct ON lct.classroomid = lc.id
                JOIN {user} as u ON u.id = lct.trainerid
                WHERE u.id IN (:trainerids) AND lc.id != :classroomid AND (lc.startdate BETWEEN :startdate AND :enddate OR lc.enddate BETWEEN :startdate1 AND :enddate1) ", array('trainerids' => implode(',', $data['trainers']), 'classroomid' => $data['id'], 'startdate' => $data['startdate'], 'enddate' => $data['enddate'], 'startdate1' => $data['startdate'], 'enddate1' => $data['enddate']));
            // print_r($trainernames);
            if(count($trainernames) > 0)
                $errors['trainers'] = get_string('trainersoccupiedrequired','local_classroom', implode(',', $trainernames));
        }

        if(isset($data['name']) &&empty(trim($data['name']))){
            $errors['name'] = get_string('valnamerequired','local_classroom');
        }
        
        if(isset($data['institute_type'])&&$data['institute_type']!=0&&$data['instituteid']==0){
            $errors['instituteid'] = get_string('vallocationrequired','local_classroom');
        }elseif(isset($data['institute_type'])&&$data['institute_type']!=0&&$data['instituteid']!=0){
            $institutessql = "SELECT id
                                FROM {local_location_institutes}
                               WHERE institute_type = :institute_type and id=:instituteid";

            $params['institute_type'] = $data['institute_type'];           
            $params['instituteid'] = $data['instituteid'];  

            $institutes = $DB->record_exists_sql($institutessql, $params);
            if(!$institutes){
                $errors['instituteid'] = get_string('vallocation','local_classroom');
            }
        }
        
	    if(isset($data['capacity']) &&!empty(trim($data['capacity']))){
	    		if(!is_numeric(trim($data['capacity']))){
	    			$errors['capacity'] = get_string('numeric','local_classroom');
	    		}
                if(is_numeric(trim($data['capacity']))&&trim($data['capacity'])<0){
	    			$errors['capacity'] = get_string('positive_numeric','local_classroom');
	    		}
		}
        
		
        if($data['id']>0){
            $countfields = "SELECT COUNT(DISTINCT u.id) ";
            $params['classroomid'] = $data['id'];
            $params['confirmed'] = 1;
            $params['suspended'] = 0;
            $params['deleted'] = 0;
            $sql = " FROM {user} AS u
                    JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                     WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                        AND u.deleted = :deleted AND cu.classroomid = :classroomid";
            $allocatedseats     =$DB->count_records_sql($countfields . $sql, $params);

            //$allocatedseats=$DB->count_records('local_classroom_users',array('classroomid'=>$data['id'])) ;
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
            elseif ($data['nomination_enddate'] <= $data['nomination_startdate']&&$data['nomination_enddate']>0&&$data['nomination_startdate']>0) {
                $errors['nomination_enddate'] = get_string('nomination_error', 'local_classroom');
            }
        }
        if($data['allow_waitinglistusers']>0 && empty(trim($data['capacity']))){
             $errors['capacity'] = get_string('capacity_waiting_check','local_classroom');
        }

        if ($data['map_certificate'] == 1 && empty($data['certificateid'])){
            $errors['certificateid'] = get_string('err_certificate', 'local_classroom');
        }

         if (isset($data['costcenter']) && $data['form_status'] == 0){
            if($data['costcenter'] == 0){
                $errors['costcenter'] = get_string('pleaseselectorganization', 'local_classroom');
            }
        }
        // if(isset($data['open_points']) && $data['open_points']){
        //     $value = $data['open_points'];
        //     $intvalue = (int)$value;
  
        //     if(!("$intvalue" === "$value") || $intvalue < 0){
        //       $errors['open_points'] = get_string('numeric', 'local_classroom'); 
        //     }
            
        //   }

        return $errors;
    }

    public function set_data($components) {
        global $DB;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($components->id);
        if ($components->form_status == 0) {
            $data = $DB->get_record('local_classroom', array('id' => $components->id));
            //populate tags
            $data->tags = \local_tags_tag::get_item_tags_array('local_classroom', 'classroom', $components->id);
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
                nomination_startdate, nomination_enddate,startdate,enddate,certificateid FROM {local_classroom}
                WHERE id = ' . $components->id);
            if($data->instituteid==0){
                $data->instituteid=null;
            }
            if(!empty($data->certificateid)){
                $data->map_certificate = 1;
            }

        } else if ($components->form_status == 2) {
            $data = $DB->get_record_sql('SELECT id, description, approvalreqd,
             classroomlogo, nomination_startdate, nomination_enddate
             FROM {local_classroom} WHERE id = ' . $components->id);
            $data->cr_description['text'] = $data->description;
            $draftitemid = file_get_submitted_draft_itemid('classroomlogo');
            file_prepare_draft_area($draftitemid, $categorycontext->id, 'local_classroom', 'classroomlogo', $data->classroomlogo, null);
            $data->classroomlogo = $draftitemid;
        }else if ($components->form_status == 3) {
             // OL-1042 Add Target Audience to Classrooms//
            $data = $DB->get_record_sql('SELECT id, open_group, subdepartment,open_hrmsrole,
             open_designation, open_location,department, open_path
             FROM {local_classroom} WHERE id = ' . $components->id);
            $data =  (array)$data;
            local_costcenter_set_costcenter_path($data);
            $data = (object)$data;
            
            $data->open_group =(!empty($data->open_group)) ? array_diff(explode(',',$data->open_group), array('')) :array(NULL=>NULL);

            $data->open_designation =(!empty($data->open_designation)) ? array_diff(explode(',',$data->open_designation), array('')) :array(NULL=>NULL);

             // OL-1042 Add Target Audience to Classrooms//
        }
        
        parent::set_data($data);
    }
}
