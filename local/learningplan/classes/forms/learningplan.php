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
require_once($CFG->dirroot . '/local/users/lib.php');
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
		// $org = $this->_customdata['costcenterid'];
		// $dept = $this->_customdata['department'];
		// $sub_dept = $this->_customdata['subdepartment'];
		// $sub_sub_dept = $this->_customdata['sub_sub_department'];
		$editoroptions = $this->customdata['editoroptions'];
		$form_status = $this->_customdata['form_status'];
		$open_path = $this->_customdata['open_path'];
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
   		list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$open_path);
		$mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'form_status', $form_status);
        $mform->setType('form_status', PARAM_INT);

        if (!isset($errors)){
            $errors = array();
        }

        $core_component = new core_component();

		if($form_status == 0){
			// if (is_siteadmin($USER->id) || has_capability('local/users:manage',$categorycontext)) {
			// 	$sql="select id,fullname from {local_costcenter} where visible =1 and parentid=0 ";
	        //     $costcenters = $DB->get_records_sql($sql);
	        // }

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_learningplan', $categorycontext, $multiple = false);

	        $mform->addElement('text', 'name', get_string('learning_plan_name', 'local_learningplan'));
	        $mform->addRule('name', null, 'required', null, 'client');
	        $mform->setType('name', PARAM_TEXT);
			
			$mform->addElement('text', 'shortname', get_string('learningplan','local_learningplan'), 'maxlength="100" size="20"');
			if($id < 0 || empty($id)){
			$mform->addRule('shortname', get_string('missing_plan_learningplan', 'local_learningplan'), 'required', null, 'client');
			}
	        $mform->setType('shortname', PARAM_TEXT);
	        
        $parentsql = "SELECT lcc.id, lcc.fullname FROM {local_custom_category} AS lcc WHERE 1 = 1 AND lcc.depth = 1";
        if(!is_siteadmin()){
            $orgcond = [];
            foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $orgcond[] = " lcc.costcenterid = {$costcenterid} ";
            }
            if(!empty($orgcond)){
                $parentsql .= " AND".implode(' OR ', $orgcond);
            }else{
                $parentsql .= " AND 1 <> 1 ";
            }
        }
        $parents = $DB->get_records_sql_menu($parentsql);

        $parents[0] = 'Select Category';
        asort($parents);
        $coursetype = array(
            'ajax' => 'local_costcenter/form-options-selector',
            'data-contextid' => (\local_costcenter\lib\accesslib::get_module_context())->id,
            'data-action' => 'custom_category_selector',
            'data-options' => json_encode(array('id' => $identifiedtype)),
            'class' => 'idparentselect',
            'data-parentclass' => 'open_costcenterid_select',
            'data-class' => 'idparentselect',
            'multiple' => false,
            );

        $mform->addElement('autocomplete', 'open_categoryid', get_string('open_categoryid','local_learningplan'), $parents, $coursetype);
        $mform->setType('open_categoryid', PARAM_INT);
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

			// $mform->addElement('text', 'open_points', get_string('points','local_learningplan'));
	        // $mform->addHelpButton('open_points', 'open_pointslearningpath', 'local_learningplan');
	        // $mform->setType('open_points', PARAM_INT);

	        // $mform->setType('credits', PARAM_RAW);

	        // // tags
	        // $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'learningplan', 'component' => 'local_learningplan'));

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
			// if (is_siteadmin($USER->id) || has_capability('local/learningplan:manage', $categorycontext)) {
			// 	$sql = "select id,fullname from {local_costcenter} where visible =1 and parentid IN(0,1)";
			// 	$costcenters = $DB->get_records_sql($sql);
	        // } else {
				
	        // }
			
			$mform->addElement('filemanager', 'summaryfile', get_string('learning_path_summary_file', 'local_learningplan'), null,array('maxbytes' => $maxbytes, 'accepted_types' => ['.jpg','.jpeg','.png','.gif']));
			$mform->addHelpButton('summaryfile','learningpaths','local_learningplan');


		}else if($form_status == 1){
			//certificate
            $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
            if($certificate_plugin_exist){
                $checkboxes = array();
                $checkboxes[] = $mform->createElement('advcheckbox', 'map_certificate', null, '', array(),array(0,1));
                $mform->addGroup($checkboxes, 'map_certificate', get_string('add_certificate', 'local_learningplan'), array(' '), false);
                $mform->addHelpButton('map_certificate', 'add_certificate', 'local_learningplan');
                $select = array(null => get_string('select_certificate','local_learningplan'));
                if(is_siteadmin()){
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array(),'name', 'id,name');
                }else{
                    $cert_templates = $DB->get_records_menu('tool_certificate_templates',array('costcenter'=>$org),'name', 'id,name');
                }
                $certificateslist = $select + $cert_templates;

                $mform->addElement('select',  'certificateid', get_string('certificate_template','local_learningplan'), $certificateslist);
                $mform->addHelpButton('certificateid', 'certificate_template', 'local_learningplan');
                $mform->setType('certificateid', PARAM_INT);
                $mform->hideIf('certificateid', 'map_certificate', 'neq', 1);
            }
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(2,5), true, 'local_learningplan', $categorycontext, $multiple = false);
			// local_users_get_userprofile_fields($mform, $this->_ajaxformdata, $this->_customdata,'local_learningplan',true, $categorycontext, $multiple = false);
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
		// if(isset($data['open_points']) && $data['open_points']){
        //   $value = $data['open_points'];
        //   $intvalue = (int)$value;

        //   if(!("$intvalue" === "$value") || $intvalue < 0){
        //     $errors['open_points'] = get_string('numeric', 'local_learningplan'); 
        //   }
          
        // }
	
		return $errors;
    }
}
