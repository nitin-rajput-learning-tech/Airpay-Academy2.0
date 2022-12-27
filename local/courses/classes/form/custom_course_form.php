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
 * @subpackage local_courses
 */

namespace local_courses\form;
use local_users\functions\userlibfunctions as userlib;
use core;
use moodleform;
use context_system;
use context_course;
use context_coursecat;
use core_component;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
//require_once($CFG->libdir. '/coursecatlib.php');

class custom_course_form extends moodleform {
    protected $course;
    protected $context;
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {
        $this->formstatus = array(
            'manage_course' => get_string('manage_course', 'local_courses'),
            'other_details' => get_string('courseother_details', 'local_courses')
        );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }
    /**
     * Form definition.
     */
    function definition() {
        global $DB,$OUTPUT,$CFG, $PAGE, $USER;

        $mform    = $this->_form;
        $course        = $this->_customdata['course']; // this contains the data of this form
        $course_id        = $this->_customdata['courseid']; // this contains the data of this form
        $category      = $this->_customdata['category'];
        $formstatus = $this->_customdata['form_status'];
        $get_coursedetails = $this->_customdata['get_coursedetails'];
        $editoroptions = $this->_customdata['editoroptions'];
        $returnto = $this->_customdata['returnto'];
        $returnurl = $this->_customdata['returnurl'];
        $costcenterid = $this->_customdata['costcenterid'];
        $coursetype =  $course->open_identifiedas;
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $formheaders = array_keys($this->formstatus);
        $formheader = $formheaders[$formstatus];

        if(empty($category)){
          $category = $CFG->defaultrequestcategory;
        }

        if (!empty($course->id)) {
          $coursecontext = context_course::instance($course->id);
          $context = $coursecontext;
          $categorycontext = context_coursecat::instance($category->id);
        } else {
          $coursecontext = null;
          $categorycontext = context_coursecat::instance($category);
          $context = $categorycontext;
        }

        $courseconfig = get_config('moodlecourse');

        $this->course  = $course;
        $this->context = $context;

        // Form definition with new course defaults.
        $mform->addElement('hidden', 'returnto', null);
        $mform->setType('returnto', PARAM_ALPHANUM);
        $mform->setConstant('returnto', $returnto);

        $mform->addElement('hidden', 'form_status', $formstatus);
        $mform->setType('form_status', PARAM_ALPHANUM);

        $mform->addElement('hidden', 'returnurl', null);
        $mform->setType('returnurl', PARAM_LOCALURL);
        $mform->setConstant('returnurl', $returnurl);

        $mform->addElement('hidden', 'getselectedclients');
        $mform->setType('getselectedclients', PARAM_BOOL);

        $defaultformat = $courseconfig->format;

        if(empty($course->id)){
          $courseid = 0;
        }else{
          $courseid = $id = $course->id;
        }

        //For Announcements activity
        $mform->addElement('hidden', 'newsitems',$courseconfig->newsitems);

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);
		
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($courseid);
        $core_component = new core_component();
        if($formstatus == 0){

            if(explode(',',(array)$this->_ajaxformdata['open_subdepartment'])){
               $parentid = (int)$this->_ajaxformdata['open_subdepartment'];
            }else if(explode(',',(array)$this->_ajaxformdata['open_departmentid'])){
               $parentid = (int)$this->_ajaxformdata['open_departmentid'];
            }else if((int)$this->_ajaxformdata['open_costcenterid']){
               $parentid = (int)$this->_ajaxformdata['open_costcenterid'];
            }
             
            if($parentid){
                $parentcategory = $DB->get_field('local_costcenter', 'category', array('id' => $parentid));
                $categorysql = "SELECT cc.id, cc.path FROM {course_categories} AS cc 
                  WHERE (cc.path LIKE '%/{$parentcategory}/%' OR cc.id = {$parentcategory}) ";
                $displaylist = $DB->get_records_sql_menu($categorysql);

             }

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,null, false, 'local_courses', $categorycontext, $multiple = false);

            $selectcatlist = array(null=>get_string('selectcat','local_courses'));
            if( isset($displaylist) && !empty($displaylist) ){
              $findisplaylist = array();
              foreach ($displaylist as $key => $categorywise) {
                $explodepaths = explode('/',$categorywise);
                $countcat = count($explodepaths);
                if($countcat > 0){
                    $catpathnames = array();
                    for ($i=0; $i < $countcat; $i++) { 
                        if($i != 0){
                            $catpathnames[$i] = $DB->get_field('course_categories','name',array('id' => $explodepaths[$i]));
                        }
                    }
                    if(count($catpathnames) > 1){
                        $findisplaylist[$key] = implode(' / ',$catpathnames);
                    }else{
                        $findisplaylist[$key] = $catpathnames[1];
                    }
                    
                }
              }
              $categories = $selectcatlist+$findisplaylist;
            }else {
              $categories = $selectcatlist;
            }
            
            $categoryoptions = array(
              'ajax' => 'local_costcenter/form-options-selector',
              'data-contextid' => $categorycontext->id,
              'data-action' => 'costcenter_category_selector',
              'data-options' => json_encode(array('id' => $parentcategory)),
              'class' => 'categoryselect',
              'data-parentclass' => 'subdepartmentselect',
              'data-class' => 'categoryselect',
              'multiple' => false,
            );
            $mform->addElement('autocomplete', 'category', get_string('coursecategory','local_courses'), $categories, $categoryoptions);
            $mform->addHelpButton('category', 'coursecategory');
            $mform->addRule('category', get_string('pleaseselectcategory','local_courses'), 'required', null, 'client');
            $mform->setType('category', PARAM_INT);


            $mform->addElement('text','fullname', get_string('course_name','local_courses'),'maxlength="254" size="50"');
            $mform->addHelpButton('fullname', 'course_name','local_courses');
            $mform->addRule('fullname', get_string('missingfullname','local_courses'), 'required', null, 'client');
            $mform->setType('fullname', PARAM_TEXT);
            if (!empty($course->id) and !has_capability('moodle/course:changefullname', $coursecontext)) {
                $mform->hardFreeze('fullname');
                $mform->setConstant('fullname', $course->fullname);
            }

            $mform->addElement('text', 'shortname', get_string('coursecode','local_courses'), 'maxlength="100" size="20"');
            $mform->addHelpButton('shortname', 'coursecode','local_courses');
            $mform->addRule('shortname', get_string('missingshortname','local_courses'), 'required', null, 'client');
            $mform->setType('shortname', PARAM_TEXT);
            if (!empty($course->id) and !has_capability('moodle/course:changeshortname', $coursecontext)) {
                $mform->hardFreeze('shortname');
                $mform->setConstant('shortname', $course->shortname);
            }
            $identify = array();
            $identifyone = array();
            $identifytwo = array();
            $classroom_plugin_exist = $core_component::get_plugin_directory('local', 'classroom');
            $learningplan_plugin_exist = $core_component::get_plugin_directory('local', 'learningplan');
            $program_plugin_exist = $core_component::get_plugin_directory('local', 'program');
            $certification_plugin_exist = $core_component::get_plugin_directory('local', 'certification');
          
            if($id || $this->_ajaxformdata['open_identifiedas']){
            $identifiedtype = $coursetype;  
            $identifiedtype = is_array($identifiedtype) ? $identifiedtype : explode(',', $identifiedtype);
            list($coursetypesql, $coursetypeparams) = $DB->get_in_or_equal($identifiedtype, SQL_PARAMS_NAMED, 'name');
            $coursetypeql = "SELECT id, name FROM {local_course_types} WHERE id {$coursetypesql} ";
            $coursetypes =  $DB->get_records_sql_menu($coursetypeql, $coursetypeparams);   
            }else{
                $open_subdepartment = 0;
            } 

            $coursetype = array(
                'ajax' => 'local_costcenter/form-options-selector',
                'data-contextid' => $categorycontext->id,
                'data-action' => 'costecenter_coursetype_selector',
                'data-options' => json_encode(array('id' => $identifiedtype)),
                'class' => 'identifiedasselect',
                'data-parentclass' => 'organisationselect',
                'data-class' => 'identifiedasselect',
                'multiple' => false,
            );
            $mform->addElement('autocomplete', 'identifiedtype', get_string('type','local_courses'), $coursetypes,$coursetype);
            $mform->addRule('identifiedtype', get_string('missingtype','local_courses'), 'required', null, 'client');
            $mform->addHelpButton('identifiedtype', 'open_identifiedascourse', 'local_courses');
            $mform->setType('identifiedtype',PARAM_INT);
            
            //for course format
            $courseformats = get_sorted_course_formats(true);
            $formcourseformats = array();
            foreach ($courseformats as $courseformat) {
              $formcourseformats[$courseformat] = get_string('pluginname', "format_$courseformat");
            }

            if (isset($course->format)) {
              $course->format = course_get_format($course)->get_format(); // replace with default if not found
              if (!in_array($course->format, $courseformats)) {
                  // this format is disabled. Still display it in the dropdown
                  $formcourseformats[$course->format] = get_string('withdisablednote', 'moodle',
                          get_string('pluginname', 'format_'.$course->format));
              }
            }
            $mform->addElement('select', 'format', get_string('format'), $formcourseformats);
            $mform->addHelpButton('format', 'format');
            $mform->setDefault('format', $defaultformat);

            $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
            if($certificate_plugin_exist){
                $checkboxes = array();
                $checkboxes[] = $mform->createElement('advcheckbox', 'map_certificate', null, '', array(),array(0,1));
                $mform->addGroup($checkboxes, 'map_certificate', get_string('add_certificate', 'local_courses'), array(' '), false);
                $mform->addHelpButton('map_certificate', 'add_certificate', 'local_courses');


                $select = array(null => get_string('select_certificate','local_courses'));

                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)){
                  if($this->_ajaxformdata['open_costcenterid'] > 0){
                    $costcenter = (int) $this->_ajaxformdata['open_costcenterid'];
                  }else{
                    $costcenter = $costcenterid;
                  }
                  $cert_templates = $DB->get_records_menu('tool_certificate_templates',array('costcenter' => $costcenter),'name', 'id,name');
                }else{
                  $cert_templates = $DB->get_records_menu('tool_certificate_templates',array('costcenter'=>$USER->open_costcenterid),'name', 'id,name');

               }
                $certificateslist = $select + $cert_templates;

                $mform->addElement('select',  'open_certificateid', get_string('certificate_template','local_courses'), $certificateslist);
                $mform->addHelpButton('open_certificateid', 'certificate_template', 'local_courses');
                $mform->setType('open_certificateid', PARAM_INT);
                $mform->hideIf('open_certificateid', 'map_certificate', 'neq', 1);
            }
            $mform->addElement('text', 'open_coursecompletiondays', get_string('coursecompday','local_courses'));
            $mform->setType('open_coursecompletiondays', PARAM_TEXT);
            $mform->addRule('open_coursecompletiondays', get_string('numeric','local_users'), 'numeric', 'numeric', 'client');
	    
            $manageselfenrol = array();
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('yes'), 1, $attributes);
            $manageselfenrol[] = $mform->createElement('radio', 'selfenrol', '', get_string('no'), 0, $attributes);
            $mform->addGroup($manageselfenrol, 'selfenrol',
                get_string('need_self_enrol', 'local_courses'),
                array('&nbsp;&nbsp;'), false);
            $mform->addHelpButton('selfenrol', 'selfenrolcourse', 'local_courses');

  			$manageapproval = array();
  			$manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('yes'), 1, $attributes);
  			$manageapproval[] = $mform->createElement('radio', 'approvalreqd', '', get_string('no'), 0, $attributes);
  			$mform->addGroup($manageapproval, 'approvalreqd',
  				get_string('need_manage_approval', 'local_courses'),
  				array('&nbsp;&nbsp;'), false);
            $mform->addHelpButton('approvalreqd', 'approvalreqdcourse', 'local_courses');
            $mform->hideIf('approvalreqd', 'selfenrol', 'neq', '1');

            // Completion tracking.
  			$mform->addElement('hidden', 'enablecompletion');
  			$mform->setType('enablecompletion', PARAM_INT);
  			$mform->setDefault('enablecompletion', 1);

            // tags
            $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'courses', 'component' => 'local_courses'));            

            $mform->addElement('editor','summary_editor', get_string('coursesummary','local_courses'), null, $editoroptions);
            $mform->addHelpButton('summary_editor', 'coursesummary');
            $mform->setType('summary_editor', PARAM_RAW);
            $summaryfields = 'summary_editor';

            if ($overviewfilesoptions = course_overviewfiles_options($course)) {
              $mform->addElement('filemanager', 'overviewfiles_filemanager', get_string('courseoverviewfiles','local_courses'), null, $overviewfilesoptions);
              $mform->addHelpButton('overviewfiles_filemanager', 'courseoverviewfiles');
              $summaryfields .= ',overviewfiles_filemanager';
            }

        } elseif($formstatus == 1){

            $pointsArr = array();
            $pointsArr[] = $mform->createElement('text',  'open_points',  '',  get_string('points','local_courses'));
            $pointsArr[] = $mform->createElement('advcheckbox', 'open_enablepoints',  '',  '', 0);
            $mform->hideIf('open_points', 'open_enablepoints', 'neq', 1);
            $mform->addGroup($pointsArr, 'pointsArr',
                get_string('points','local_courses'),
                array('&nbsp;&nbsp;'), false);
            $mform->addHelpButton('pointsArr', 'open_pointscourse', 'local_courses');
            $mform->setType('open_points', PARAM_INT);

            $mform->addElement('text',  'open_cost', get_string('open_costcourse','local_courses'));
            $mform->addHelpButton('open_cost', 'open_costcourse', 'local_courses');
            $mform->setType('open_cost', PARAM_INT);
            $mform->addRule('open_cost', get_string('numeric','local_users'), 'numeric', null, 'client');
            $skillselect = array(0 => get_string('select_skill','local_courses'));
            $skills = $DB->get_records_menu('local_skill',array('costcenterid' => $this->course->open_costcenterid),'','id,name');
       
            if(!empty($skills)){
                $skillselect = $skillselect+$skills;
            }

            $mform->addElement('select',  'open_skill', get_string('open_skillcourse','local_courses'), $skillselect);
            $mform->addHelpButton('open_skill', 'open_skillcourse', 'local_courses');
            $mform->setType('open_skill', PARAM_INT);

            $levelselect = array(0 => get_string('select_level','local_courses'));
            $level ="SELECT cl.name FROM {local_course_levels} as cl 
                    JOIN {local_costcenter} as c ON c.id = cl.costcenterid";
            $levels = $DB->get_records_menu('local_course_levels',  array('costcenterid' => $this->course->open_costcenterid),'sortorder', 'id,name');
            if(!empty($levels)){
                $levelselect = $levelselect+$levels;
            }
            $mform->addElement('select',  'open_level', get_string('open_levelcourse','local_courses'), $levelselect);
            $mform->addHelpButton('open_level', 'open_levelcourse', 'local_courses');
            $mform->setType('open_level', PARAM_INT);

            $mform->addElement('date_time_selector', 'startdate', get_string('startdate','local_courses'),
             array());
            $mform->addHelpButton('startdate', 'startdate');
		
			$mform->addElement('date_time_selector', 'enddate', get_string('enddate','local_courses'), array('optional' => false));
            $mform->addHelpButton('enddate', 'enddate');

            $users_plugin_exist = $core_component::get_plugin_directory('local','users');
            if ($users_plugin_exist) {
                require_once($CFG->dirroot . '/local/users/lib.php');
                $functionname ='globaltargetaudience_elementlist';
                 if(function_exists($functionname)) {
                    $modulecostcenter = $DB->get_field('course', 'open_costcenterid',array('id' => $courseid));

                    $mform->modulecostcenter = $modulecostcenter;
                    $functionname($mform,array('hrmsrole','location'));
                }
            }
        }
        $mform->closeHeaderBefore('buttonar');
		$mform->disable_form_change_checker();
        // Finally set the current form data
        if(empty($course)&&$course_id>0){
             $course = get_course($course_id);
        }
        if(!empty($course->open_certificateid)){
            $course->map_certificate = 1;
        }
        $this->set_data($course);
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
		$form_data = data_submitted();
        // Add field validation check for duplicate shortname.
        if ($course = $DB->get_record('course', array('shortname' => $data['shortname']), '*', IGNORE_MULTIPLE)) {
            if (empty($data['id']) || $course->id != $data['id']) {
                $errors['shortname'] = get_string('shortnametaken', '', $course->fullname);
            }
        }  
		 if (isset($data['startdate']) && $data['startdate']
                && isset($data['enddate']) && $data['enddate']) {
            if ($data['enddate'] < $data['startdate']) {
                $errors['enddate'] = get_string('nosameenddate', 'local_courses');
            }
        }

        if (isset($data['category']) && $data['form_status'] == 0){
            if(empty($data['category'])){
                $errors['category'] = get_string('err_category', 'local_courses');
            }
        }

        if ($data['map_certificate'] == 1 && empty($data['open_certificateid'])){
            $errors['open_certificateid'] = get_string('err_certificate', 'local_courses');
        }
        
        if ($data['open_enablepoints'] == 1 && empty($data['open_points'])){
            $errors['pointsArr'] = get_string('err_points', 'local_courses');
        }
        if (isset($data['open_costcenterid']) && $data['form_status'] == 0){
            if($data['open_costcenterid'] == 0){
                $errors['open_costcenterid'] = get_string('pleaseselectorganization', 'local_courses');
            }
        }
        $errors = array_merge($errors, enrol_course_edit_validation($data, $this->context));
        return $errors;
    }
}
