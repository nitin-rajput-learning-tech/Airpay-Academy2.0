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
/* Learning Plan Block
 * This plugin serves as a database and plan for all learning activities in the organziation, 
 * where such activities are organized for a more structured learning program.
 * @package local
 * @sub package learning plan
 * @author: Syed HameedUllah
 * @copyright  Copyrights © 2016
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_learningplan\forms;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
use moodleform;
use context_system;
use costcenter;
use events;
use context_user;
use local_users\functions\userlibfunctions as userlib;
use core_component;
// Add Learning Plans.
class learningplan extends moodleform {
 
	public $formstatus;
	public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {

	 	$this->formstatus = array(
	 		'generaldetails' => get_string('generaldetails', 'local_learningplan'),
			'otherdetails' => get_string('otherdetails', 'local_learningplan')
			);
	 	parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
	}
    public function definition() {
        global $USER, $DB, $CFG;
        $mform = $this->_form;
		
        $id = $this->_customdata['id'];
		$org = $this->_customdata['costcenterid'];
		$dept = $this->_customdata['department'];
		$sub_dept = $this->_customdata['subdepartment'];
		$sub_sub_dept = $this->_customdata['sub_sub_department'];
		$editoroptions = $this->customdata['editoroptions'];
		$form_status = $this->_customdata['form_status'];
		$open_costcenterpath = $this->_customdata['open_costcenterpath'];
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
		
		$mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'form_status', $form_status);
        $mform->setType('form_status', PARAM_INT);

        if (!isset($errors)){
            $errors = array();
        }

        $core_component = new core_component();

		if($form_status == 0){
			if (is_siteadmin($USER->id) || has_capability('local/users:manage',$categorycontext)) {
				$sql="select id,fullname from {local_costcenter} where visible =1 and parentid=0 ";
	            $costcenters = $DB->get_records_sql($sql);
	        }
			// if (is_siteadmin($USER)) {
			// 	$organizationlist=array(null=>'--Select Organization--');
			// 	foreach ($costcenters as $scl) {
			// 		$organizationlist[$scl->id]=$scl->fullname;
			// 	}
			// 	$mform->addElement('select', 'costcenter', get_string('organization', 'local_users'), $organizationlist);
			// 	//$mform->addRule('costcenter', null, 'required', null, 'client');	 
			// 	$mform->addRule('costcenter', get_string('errororganization', 'local_users'), 'required', null, 'client');
			// } else{
			// 	$user_dept=$DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
			// 	$mform->addElement('hidden', 'costcenter', null);
			// 	$mform->setType('costcenter', PARAM_ALPHANUM);
			// 	$mform->setConstant('costcenter', $user_dept);
			// }

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_learningplan', $categorycontext, $multiple = false);

	        $mform->addElement('text', 'name', get_string('learning_plan_name', 'local_learningplan'));
	        $mform->addRule('name', null, 'required', null, 'client');
	        $mform->setType('name', PARAM_TEXT);
			
			$mform->addElement('text', 'shortname', get_string('learningplan','local_learningplan'), 'maxlength="100" size="20"');
			if($id < 0 || empty($id)){
			$mform->addRule('shortname', get_string('missing_plan_learningplan', 'local_learningplan'), 'required', null, 'client');
			}
			// if($id > 0){
			// 	$mform->disabledIf('shortname','id');
			// 	// $mform->disabledIf('costcenter','id');
			// }
	        $mform->setType('shortname', PARAM_TEXT);
			
			/*$options = array();
			$options[null] = 'Select Type';
			$options['1'] = 'Core Courses';
			$options['2'] = 'Elective Courses';
		    $mform->addElement('select', 'learning_type', get_string('learning_plan_type', 'local_learningplan'), $options);
	        $mform->addRule('learning_type', null, 'required', null, 'client');
	        $mform->setType('learning_type', PARAM_TEXT);
			*/
	       	$sequence = array();
			$sequence[] = $mform->createElement('radio', 'lpsequence', '', get_string('yes'), 1, $attributes);
			$sequence[] = $mform->createElement('radio', 'lpsequence', '', get_string('no'), 0, $attributes);
			$mform->addGroup($sequence, 'lpsequence',get_string('lp_sequence', 'local_learningplan'),
				array('&nbsp;&nbsp;'), false);
	        $mform->addHelpButton('lpsequence','sequence','local_learningplan');

			$manageselfenrol = array();
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('yes'), 1, $attributes);
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('no'), 0, $attributes);
            $mform->addGroup($manageselfenrol, 'selfenrol',
                get_string('need_self_enrol', 'local_courses'),
                array('&nbsp;&nbsp;'), false);
            $mform->addHelpButton('selfenrol', 'selfenrolcourse', 'local_learningplan');
			
			$manageapproval = array();
			$manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('yes'), 1, $attributes);
			$manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('no'), 0, $attributes);
			$mform->addGroup($manageapproval, 'approvalreqd',get_string('need_manage_approval', 'local_learningplan'),
				array('&nbsp;&nbsp;'), false);
			$mform->addHelpButton('approvalreqd','need_manager_approval','local_learningplan');
			// $credits[] = $mform->createElement('text', 'credits', '');
			// $credits[] = $mform->createElement('html', get_string('le_credits_defaultzero', 'local_learningplan'));
			// $mform->addGroup($credits, 'creditslink',get_string('credits','local_learningplan'),
			// 	array('&nbsp;&nbsp;'), false);
			// $mform->addHelpButton('creditslink','points','local_learningplan');

			$mform->addElement('text', 'open_points', get_string('points','local_learningplan'));
	        $mform->addHelpButton('open_points', 'open_pointslearningpath', 'local_learningplan');
	        $mform->setType('open_points', PARAM_INT);

			// $mform->addElement('text', 'credits', get_string('credits','local_learningplan'));
			// $mform->addRule('credits', get_string('numeric','local_learningplan'), 'numeric', null, 'client');
	        $mform->setType('credits', PARAM_RAW);

	        // tags
	        $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'learningplan', 'component' => 'local_learningplan'));

	        $editoroption = [
	        'maxfiles' => EDITOR_UNLIMITED_FILES,
	        'trust' => false,
	        'context' => (new \local_learningplan\lib\accesslib())::get_module_context(),
	        'noclean' => true,
	        'subdirs' => false,
	        'autosave'=>false
	    	];
			$mform->addElement('editor','description', get_string('description'), null, $editoroption);
	        $mform->setType('description', PARAM_RAW);
	        $mform->addHelpButton('description','descript','local_learningplan');
			
			$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
			if (is_siteadmin($USER->id) || has_capability('local/costcenter:assign_multiple_departments_manage', $categorycontext)) {
				$sql = "select id,fullname from {local_costcenter} where visible =1 and parentid IN(0,1)";
				$costcenters = $DB->get_records_sql($sql);
	        } else {
				
	        }
			
			$mform->addElement('filemanager', 'summaryfile', get_string('learning_path_summary_file', 'local_learningplan'), null,array('maxbytes' => $maxbytes, 'accepted_types' => ['.jpg','.jpeg','.png','.gif']));
			$mform->addHelpButton('summaryfile','learningpaths','local_learningplan');

			//certificate
            $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
            if($certificate_plugin_exist){
                $checkboxes = array();
                $checkboxes[] = $mform->createElement('advcheckbox', 'map_certificate', null, '', array(),array(0,1));
                $mform->addGroup($checkboxes, 'map_certificate', get_string('add_certificate', 'local_learningplan'), array(' '), false);
                $mform->addHelpButton('map_certificate', 'add_certificate', 'local_learningplan');


                $select = array(null => get_string('select_certificate','local_learningplan'));

                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)){
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array(),'name', 'id,name');
                }else{
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array('costcenter'=>$USER->open_costcenterid),'name', 'id,name');
                }
                $certificateslist = $select + $cert_templates;

                $mform->addElement('select',  'certificateid', get_string('certificate_template','local_learningplan'), $certificateslist);
                $mform->addHelpButton('certificateid', 'certificate_template', 'local_learningplan');
                $mform->setType('certificateid', PARAM_INT);
                $mform->hideIf('certificateid', 'map_certificate', 'neq', 1);
            }

		}else if($form_status == 1){
			// if ((!is_siteadmin() && (((! has_capability('local/costcenter:manage_multiorganizations', (new \local_learningplan\lib\accesslib())::get_module_context()))) &&(!has_capability('local/costcenter:manage_owndepartments',$categorycontext))))) {
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(2,5), true, 'local_learningplan', $categorycontext, $multiple = false);

			if (is_siteadmin() || 
                (has_capability('local/learningplan:manage', $categorycontext) 
                	&& has_capability('local/costcenter:manage_ownorganization', $categorycontext)
                    && !has_capability('local/costcenter:manage_owndepartments', $categorycontext))) {
				$departmentslist[-1]=get_string('all');
				if($id > 0 ){
					$costcenter = $DB->get_field('local_learningplan','costcenter',array('id'=>$id));
					$departments = userlib::find_departments_list($costcenter);
					foreach($departments as $depart){
						$departmentslist[$depart->id]=$depart->fullname;
					}
				}
				else if(isset($org)){
					$departments = userlib::find_departments_list($org);
					foreach($departments as $depart){
						$departmentslist[$depart->id]=$depart->fullname;
					}
				}
				else if(!is_siteadmin() && has_capability('local/costcenter:assign_multiple_departments_manage', $categorycontext)){
					$departments = userlib::find_departments_list($USER->open_costcenterid);
					foreach($departments as $depart){
						$departmentslist[$depart->id]=$depart->fullname;
					}
				}
				
				$options = array(
					'multiple' => true,
				);
				$mform->addElement('autocomplete', 'department', get_string('department','local_learningplan'),$departmentslist,$options);
				$mform->addHelpButton('department', 'department','local_users');
			// }elseif (is_siteadmin() || ((! has_capability('local/costcenter:manage_multiorganizations', (new \local_learningplan\lib\accesslib())::get_module_context()))&&(has_capability('local/costcenter:manage_owndepartments',$categorycontext)))) {
          
					/*$options = array(
						// 'multiple' => true,
						'class' => 'departmentselect'
					);
					$costcenter = $DB->get_field('local_learningplan','costcenter',array('id'=>$id));
					$costcenter_department = $DB->get_field('local_learningplan','department',array('id'=>$id));
					if(empty($costcenter_department)){
						$departmentslist=array($USER->open_departmentid=>$USER->open_departmentid);
					}else{
						if($costcenter_department!=-1){
							$departmentslist=explode(',',$costcenter_department);
							$departmentslist=array_combine($departmentslist,$departmentslist);
						}*/
					
			// 		}
					
				/*	$mform->addElement('autocomplete', 'department', get_string('department'),$departmentslist,$options);
				    $mform->addHelpButton('department', 'department','local_users');
					if(empty($costcenter_department)&&$costcenter_department!=-1)
					 $mform->setDefault('department', $USER->open_departmentid);
					}
				*/
            }else{
            	$department = $DB->get_field('local_learningplan', 'department', array('id' => $id));
            	$plan_department = $department ? $department : $USER->open_departmentid;
            	$mform->addElement('hidden', 'department', $plan_department, array('id' => 'id_department'));
            	$mform->setType('department', PARAM_RAW);
            }
            if (is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext)){
            	$subdepartmentslist[-1]=get_string('all');
            	$subdepartment = $this->_ajaxformdata['subdepartment'];

                $params = array();
                if (!empty($subdepartment)) {
                    $subdepartmentslist = $subdepartment;
                } else if ($id > 0) {
                    $subdepartmentlist = $DB->get_field('local_learningplan', 'subdepartment', array('id' => $id));
                    $subdepartmentslist = explode(', ', $subdepartmentlist);
                }
                if (!empty($subdepartmentslist)) {
                    if (is_array($subdepartmentslist)){
                        $subdepartmentslist=implode(',',$subdepartmentslist);
                    }
                    // $organisation = $DB->get_field('local_classroom', 'costcenter', array('id' => $id));
                    $departments = $DB->get_field('local_learningplan', 'department', array('id' => $id));
                    
                    $subdepartmentlistsql = "SELECT id, fullname
                                            FROM {local_costcenter}
                                           WHERE 1 = 1 ";
                    if(!empty($subdepartmentslist)) {
                        $arr_subdepartmentslist = explode(',', $subdepartmentslist);
                        list($subsql, $subparam) = $DB->get_in_or_equal($arr_subdepartmentslist, SQL_PARAMS_NAMED);
                        $subdepartmentlistsql .= " AND id $subsql ";
                        $params = $params + $subparam;
                    }else{
                        $subdepartmentlistsql .= " AND visible = :visible AND depth = :depth 
                        AND parentid IN (:parentid)";
                        $params['visible'] = 1;
                        $params['depth'] = 3;
                        $params['parentid'] = $departments;
                    }
                    
                    $subdepartmentlist = $DB->get_records_sql_menu($subdepartmentlistsql, $params);
                    $subdepartments = array(-1 => get_string('all')) + $subdepartmentlist;
                }
				// if($id > 0 ){
					// $departments = $DB->get_field('local_learningplan','department',array('id'=>$id));
					// $departments = explode(',', $departments);
					// $subdepartments = userlib::find_subdepartments_list($departments);
					// foreach($subdepartments as $subdepart){
					// 	$subdepartmentslist[$subdepart->id]=$subdepart->fullname;
					// }
				// }
				
				// $options = array(
				// 	'multiple' => true,
				// );
				$options = array(
                    'ajax' => 'local_learningplan/form-options-selector',
                    'multiple' => true,
                    'data-contextid' => $categorycontext->id,
                    'data-action' => 'learningplan_subdepartment_selector',
                    'data-options' => json_encode(array('id' => $id, 'depth' => 3,
                        'organizationselect' => '.organizationselect', 'subdepartment' => true)),
                    'class' => 'subdepartmentselect'
                );
					// js_call_amd
				$mform->addElement('autocomplete', 'subdepartment', get_string('subdepartment', 'local_costcenter'), $subdepartments, $options);
				$mform->addHelpButton('subdepartment', 'subdepartment','local_users');
            }
			
			$users_plugin_exist = $core_component::get_plugin_directory('local','users');
			if ($users_plugin_exist) {
				require_once($CFG->dirroot . '/local/users/lib.php');
				$functionname ='globaltargetaudience_elementlist';
				if(function_exists($functionname)) {
				$modulecostcenter = $DB->get_field('local_learningplan', 'costcenter',array('id' => $id));

                    $mform->modulecostcenter = $modulecostcenter;

                    $functionname($mform,array('group','hrmsrole','designation','location'));
                }
			}

			local_users_get_userprofile_fields($mform, $this->_ajaxformdata, $this->_customdata,'local_learningplan',true, $categorycontext, $multiple = false);
    	}
        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
       
        $errors = array();
		global $DB;
	    $errors = parent::validation($data, $files);
		if($data['enddate'] < $data['startdate']){
	        $errors['enddate'] = get_string('startdategreaterenddate','local_learningplan');
		}
		if($data['form_status']==0){
			if(empty(trim($data['name']))){
				$errors['name'] = get_string('provide_valid_name', 'local_learningplan');
			}
			if(empty(trim($data['shortname']))&&$data['id']=='0'){
                $errors['shortname'] = get_string('provide_valid_shortname','local_learningplan');
			}
        	if ($lplan = $DB->get_record('local_learningplan', array('shortname' => $data['shortname']), '*', IGNORE_MULTIPLE)) {
		    	if (($data['id']=='0') || $lplan->id != $data['id']) {
				 	$errors['shortname'] = get_string('unameexists','local_learningplan');
            	}
			}
			if($data['map_certificate'] == 1 && empty($data['certificateid'])){
                $errors['certificateid'] = get_string('err_certificate','local_learningplan');
			}
		}
		if(isset($data['open_points']) && $data['open_points']){
          $value = $data['open_points'];
          $intvalue = (int)$value;

          if(!("$intvalue" === "$value") || $intvalue < 0){
            $errors['open_points'] = get_string('numeric', 'local_learningplan'); 
          }
          
        }
	
		return $errors;
    }
}
