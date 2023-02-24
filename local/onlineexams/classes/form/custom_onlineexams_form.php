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
 * @subpackage local_onlineexams
 */

namespace local_onlineexams\form;
use local_users\functions\userlibfunctions as userlib;
use core;
use moodleform;
use context_system;
use context_course;
use context_coursecat;
use core_component;

defined('MOODLE_INTERNAL') || die;
// require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/quiz/mod_form.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
//require_once($CFG->libdir. '/coursecatlib.php');

class custom_onlineexams_form extends moodleform {
    protected $onlineexam;
    protected $context;
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {

        global $USER;

        $this->formstatus = array(
            'manage_onlineexam' => get_string('manage_onlineexam', 'local_onlineexams'),
            'other_details' => get_string('onlineexamother_details', 'local_onlineexams'),
        );
        $costcenterdepth=local_costcenter_get_fields();

        $depth=count($costcenterdepth);

        if($USER->useraccess['currentroleinfo']['depth'] < $depth){

            $this->formstatus['target_audience']=get_string('target_audience', 'local_users');

        }
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }
    /**
     * Form definition.
     */
    function definition() {
        global $DB,$OUTPUT,$CFG, $PAGE, $USER;

        $mform    = $this->_form;
        $onlineexam        = $this->_customdata['course']; // this contains the data of this form
        $onlineexam_id        = $this->_customdata['courseid']; // this contains the data of this form
        $category      = $this->_customdata['category'];
        $formstatus = $this->_customdata['form_status'];
        $get_onlineexamdetails = $this->_customdata['get_coursedetails'];
        $editoroptions = $this->_customdata['editoroptions'];
        $returnto = $this->_customdata['returnto'];
        $returnurl = $this->_customdata['returnurl'];
        $onlineexamtype =  $onlineexam->open_identifiedas;
        $categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context();
        $formheaders = array_keys($this->formstatus);
        $formheader = $formheaders[$formstatus];

        if(empty($category)){
          $category = $CFG->defaultrequestcategory;
        }

        if (!empty($onlineexam->id)) {
          $onlineexamcontext = context_course::instance($onlineexam->id);
          $context = $onlineexamcontext;
          $categorycontext = context_coursecat::instance($category->id);
        } else {
          $onlineexamcontext = null;
          $categorycontext = context_coursecat::instance($category);
          $context = $categorycontext;
        }

        $courseconfig = get_config('moodlecourse');

        $this->onlineexam  = $onlineexam;
        $this->context = $context;

        // Form definition with new onlineexam defaults.
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

        $mform->addElement('hidden', 'enablecompletion');
        $mform->setType('enablecompletion', PARAM_INT);
        $mform->setConstant('enablecompletion', 1);

        $defaultformat = $courseconfig->format;

        if(empty($onlineexam->id)){
          $onlineexamid = 0;
        }else{
          $onlineexamid = $id = $onlineexam->id;
        }

        //For Announcements activity
        $mform->addElement('hidden', 'newsitems',$courseconfig->newsitems);

        $mform->addElement('hidden', 'id', $onlineexamid, array('id' => 'onlineexamid'));
        $mform->setType('id', PARAM_INT);
		
        $categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context($onlineexamid);
        $core_component = new core_component();
        if($formstatus == 0){

            $opencategoryid = $this->_ajaxformdata['open_categoryid'];

            if($opencategoryid){

                $costcentersql = "SELECT lcc.id,lcc.fullname,lcc.parentid
                                FROM {local_custom_category} AS lcc
                                WHERE lcc.id=:id ";

                $customcat = $DB->get_records_sql($costcentersql,array('id'=>$opencategoryid));
                $parents = [];
                foreach($customcat as $cat){
                    $parentname = '';
                    if($cat->parentid > 0){
                        $parentname = $DB->get_field('local_custom_category', 'fullname', ['id' => $cat->parentid]);
                    }
                    if($parentname){
                        $cat->fullname = $parentname . ' / '. $cat->fullname;
                    }
                    $parents[$cat->id] = $cat->fullname;
                }

            }else{

                $parents = array();
            }

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_onlineexams', $categorycontext, $multiple = false);

            $mform->addElement('hidden','category', null);
            $mform->setConstant('category', $category);


            $parents[0] = 'Select Category';
            ksort($parents);
            $categoryinfo = array(
                'ajax' => 'local_costcenter/form-options-selector',
                'data-contextid' => (\local_costcenter\lib\accesslib::get_module_context())->id,
                'data-action' => 'custom_category_selector',
                'data-options' => json_encode(array('id' => $onlineexamid,'type'=>'category_selector')),
                'class' => 'idparentselect',
                'data-parentclass' => 'open_costcenterid_select',
                'data-class' => 'idparentselect',
                'multiple' => false,
            );

            $mform->addElement('autocomplete', 'open_categoryid', get_string('category'), $parents, $categoryinfo);
            $mform->setType('open_categoryid', PARAM_INT);


            $mform->addElement('text','fullname', get_string('onlineexam_name','local_onlineexams'),'maxlength="254" size="50"');
            $mform->addHelpButton('fullname', 'onlineexam_name','local_onlineexams');


            if (!empty($onlineexam->id) and !has_capability('moodle/course:changefullname', $categorycontext)) {
                $mform->hardFreeze('fullname');
                $mform->setConstant('fullname', $onlineexam->fullname);

            }elseif(has_capability('moodle/course:changefullname', $categorycontext)) {

                $mform->addRule('fullname', get_string('missingfullname','local_onlineexams'), 'required', null, 'client');
                $mform->setType('fullname', PARAM_TEXT);

            }

            $mform->addElement('text', 'shortname', get_string('onlineexamcode','local_onlineexams'), 'maxlength="100" size="20"');
            $mform->addHelpButton('shortname', 'onlineexamcode','local_onlineexams');


            if (!empty($onlineexam->id) and !has_capability('moodle/course:changeshortname', $categorycontext)) {
                $mform->hardFreeze('shortname');
                $mform->setConstant('shortname', $onlineexam->shortname);
            }elseif(has_capability('moodle/course:changefullname', $categorycontext)) {

                $mform->addRule('shortname', get_string('missingshortname','local_onlineexams'), 'required', null, 'client');
                $mform->setType('shortname', PARAM_TEXT);

            }
            $identify = array();
            $identifyone = array();
            $identifytwo = array();
            $classroom_plugin_exist = $core_component::get_plugin_directory('local', 'classroom');
            $learningplan_plugin_exist = $core_component::get_plugin_directory('local', 'learningplan');
            $program_plugin_exist = $core_component::get_plugin_directory('local', 'program');
            $certification_plugin_exist = $core_component::get_plugin_directory('local', 'certification');
          

  			$mform->addElement('hidden', 'open_coursetype');
  			$mform->setType('open_coursetype', PARAM_INT);
  			$mform->setDefault('open_coursetype', 1);

            // tags
            // $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'courses', 'component' => 'local_onlineexams'));

            $mform->addElement('editor','summary_editor', get_string('onlineexamsummary','local_onlineexams'), null, $editoroptions);
            $mform->addHelpButton('summary_editor', 'onlineexamsummary','local_onlineexams');
            $mform->setType('summary_editor', PARAM_RAW);
            $summaryfields = 'summary_editor';

            if ($overviewfilesoptions = course_overviewfiles_options($onlineexam)) {
              $mform->addElement('filemanager', 'overviewfiles_filemanager', get_string('onlineexamoverviewfiles','local_onlineexams'), null, $overviewfilesoptions);
              $mform->addHelpButton('overviewfiles_filemanager', 'onlineexamoverviewfiles','local_onlineexams');
              $summaryfields .= ',overviewfiles_filemanager';
            }
            $onlineexamformats = get_sorted_course_formats(true);
            $formonlineexamformats = array();
            foreach ($onlineexamformats as $onlineexamformat) {
              $formonlineexamformats[$onlineexamformat] = get_string('pluginname', "format_$onlineexamformat");
            }

            if (isset($onlineexam->format)) {
              $onlineexam->format = course_get_format($onlineexam)->get_format(); // replace with default if not found
              if (!in_array($onlineexam->format, $onlineexamformats)) {
                  // this format is disabled. Still display it in the dropdown
                  $formonlineexamformats[$onlineexam->format] = get_string('withdisablednote', 'moodle',
                          get_string('pluginname', 'format_'.$onlineexam->format));
              }
            }


            $mform->addElement('hidden',  'activitytype',  'quiz');
            $attemptnumbers = range(0,10);
            $attemptnumbers[0] = get_string('unlimited'); 
            $mform->addElement('select',  'attempts', get_string('attempts', 'mod_quiz'), $attemptnumbers);
            $mform->setType('attempts', PARAM_INT);
            $mform->hideIf('attempts', 'examtype', 'eq', 1);

			
			
	        // $mform->addElement('duration', 'sndtimelimit', get_string('reviewtimelimit', 'quiz'),array('optional' => false));
            // $mform->addHelpButton('sndtimelimit', 'timelimit', 'quiz');
            // $mform->hideIf('sndtimelimit', 'examtype', 'eq', 0);
          

            $mform->addElement('hidden', 'maxgrade');
            $mform->setType('maxgrade', PARAM_INT);
            $mform->setDefault('maxgrade', 10);

            // $mform->addElement('text', 'maxgrade',get_string('maxgrade','local_onlineexams'), array('size'=>'20'));
            // $mform->addRule('maxgrade', get_string('missinggrade', 'local_onlineexams'), 'required', null, 'client');
            // $mform->setType('maxgrade', PARAM_FLOAT);
            
            $mform->addElement('text', 'gradepass', get_string('gradepass', 'local_onlineexams'));
            $mform->addRule('gradepass', get_string('entergradepass', 'local_onlineexams'), 'required', null, 'client');
            $mform->setType('gradepass', PARAM_FLOAT);

            $mform->addElement('select', 'grademethod', get_string('grademethod', 'quiz'),
            quiz_get_grading_options());
            $mform->addHelpButton('grademethod', 'grademethod', 'quiz');

        } elseif($formstatus == 1){
          // core quiz fields 
            $datefieldoptions = array('optional' => true);
           // $mform->addElement('header', 'timing', get_string('timing', 'quiz'));

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('quizopen', 'quiz'),
                $datefieldoptions);
        $mform->addHelpButton('timeopen', 'quizopenclose', 'quiz');

        $mform->addElement('date_time_selector', 'timeclose', get_string('quizclose', 'quiz'),
                $datefieldoptions);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'quiz'),
                array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'quiz');

        // What to do with overdue attempts.
        $mform->addElement('select', 'overduehandling', get_string('overduehandling', 'quiz'),
                quiz_get_overdue_handling_options());
        $mform->addHelpButton('overduehandling', 'overduehandling', 'quiz');
        //---------------------------------------------------------------------------
        // Browser security choices.
        $mform->addElement('select', 'browsersecurity', get_string('browsersecurity', 'quiz'),
                \quiz_access_manager::get_browser_security_choices());
        $mform->addHelpButton('browsersecurity', 'browsersecurity', 'quiz');
        
            $skillselect = array(0 => get_string('select_skill','local_onlineexams'));

         $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path',$costcenterpath=$this->onlineexam->open_path);

            $skillcostcentersql = "SELECT id,name FROM {local_skill}
                                WHERE 1=1 $costcenterpathconcatsql ";


            $skills = $DB->get_records_sql_menu($skillcostcentersql);

       
            if(!empty($skills)){
                $skillselect = $skillselect+$skills;
            }

            $mform->addElement('select',  'open_skill', get_string('open_skillonlineexam','local_onlineexams'), $skillselect);
            $mform->addHelpButton('open_skill', 'open_skillonlineexam', 'local_onlineexams');
            $mform->setType('open_skill', PARAM_INT);

            $levelselect = array(0 => get_string('select_level','local_onlineexams'));

            $levelsql = "SELECT id,name FROM {local_course_levels}
                                WHERE 1=1 $costcenterpathconcatsql ";

            $levels = $DB->get_records_sql_menu($levelsql);

            if(!empty($levels)){
                $levelselect = $levelselect+$levels;
            }
            $mform->addElement('select',  'open_level', get_string('open_levelonlineexam','local_onlineexams'), $levelselect);
            $mform->addHelpButton('open_level', 'open_levelonlineexam', 'local_onlineexams');
            $mform->setType('open_level', PARAM_INT);

            // $mform->addElement('date_time_selector', 'startdate', get_string('startdate','local_onlineexams'),
            //  array());
            // $mform->addHelpButton('startdate', 'startdate');
		
			// $mform->addElement('date_time_selector', 'enddate', get_string('enddate','local_onlineexams'), array('optional' => false));
            // $mform->addHelpButton('enddate', 'enddate');

            $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
            if($certificate_plugin_exist){
                $checkboxes = array();
                $checkboxes[] = $mform->createElement('advcheckbox', 'map_certificate', null, '', array(),array(0,1));
                $mform->addGroup($checkboxes, 'map_certificate', get_string('add_certificate', 'local_onlineexams'), array(' '), false);
                $mform->addHelpButton('map_certificate', 'add_certificate', 'local_onlineexams');


                $select = array(null => get_string('select_certificate','local_onlineexams'));

                $certificatesql = "SELECT id,name FROM {tool_certificate_templates}
                                    WHERE 1=1 $costcenterpathconcatsql ";

                $cert_templates = $DB->get_records_sql_menu($certificatesql);
                $certificateslist = $select + $cert_templates;

                $mform->addElement('select',  'open_certificateid', get_string('certificate_template','local_onlineexams'), $certificateslist);
                $mform->addHelpButton('open_certificateid', 'certificate_template', 'local_onlineexams');
                $mform->setType('open_certificateid', PARAM_INT);
                $mform->hideIf('open_certificateid', 'map_certificate', 'neq', 1);
            }

        }else if ($formstatus == 2) {
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$this->onlineexam->open_path);
            $mform->addElement('hidden', 'open_costcenterid');
            $mform->setConstant('open_costcenterid', $org);

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(2,HIERARCHY_LEVELS), true, 'local_onlineexams', $categorycontext, $multiple = false);
        }
        $mform->closeHeaderBefore('buttonar');
		$mform->disable_form_change_checker();
        // Finally set the current form data
        if(empty($onlineexam)&&$onlineexam_id>0){
             $onlineexam = get_course($onlineexam_id);
        }
        if(!empty($this->_ajaxformdata['open_certificateid'])){
            $onlineexam->open_certificateid = $this->_ajaxformdata['open_certificateid'];
        }
        if(!empty($onlineexam->open_certificateid)){
            $onlineexam->map_certificate = 1;
        }

        if(!empty($this->_ajaxformdata['open_categoryid'])){
            $onlineexam->open_categoryid = $this->_ajaxformdata['open_categoryid'];
        }else{
            $onlineexam->open_categoryid =0;
        }

        if(empty($this->_ajaxformdata['open_identifiedas'])&&!empty($this->_ajaxformdata['identifiedtype'])){
            $onlineexam->identifiedtype = $this->_ajaxformdata['identifiedtype'];
        }elseif(empty($this->_ajaxformdata['open_identifiedas'])&&empty($this->_ajaxformdata['identifiedtype'])){
            $onlineexam->identifiedtype ='';
        }
        $this->set_data($onlineexam);
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
        if ($onlineexam = $DB->get_record('course', array('shortname' => $data['shortname']), '*', IGNORE_MULTIPLE)) {
            if (empty($data['id']) || $onlineexam->id != $data['id']) {
                $errors['shortname'] = get_string('shortnametaken', 'local_onlineexams', $onlineexam->fullname);
            }
        }  
		 if (isset($data['timeopen']) && $data['timeopen']
                && isset($data['timeclose']) && $data['timeclose']) {
            if ($data['timeclose'] < $data['timeopen']) {
                $errors['timeclose'] = get_string('nosameenddate', 'local_onlineexams');
            }
        }
        if ($data['map_certificate'] == 1 && empty($this->_ajaxformdata['open_certificateid'])){
            $errors['open_certificateid'] = get_string('err_certificate', 'local_onlineexams');
        }
        if (isset($data['open_path']) && $data['form_status'] == 0){
            if($data['open_path'] == 0){
                $errors['open_path'] = get_string('pleaseselectorganization', 'local_onlineexams');
            }
        }
        if(isset($data['open_onlineexamcompletiondays']) && $data['open_onlineexamcompletiondays']){
            $value = $data['open_onlineexamcompletiondays'];
            $intvalue = (int)$value;
  
            if(!("$intvalue" === "$value") || $intvalue < 0){
              $errors['open_onlineexamcompletiondays'] = get_string('numeric', 'local_classroom'); 
            }
            
          }
          if (isset($data['gradepass']) && $data['form_status'] == 0){
            
            if (array_key_exists('maxgrade', $data) AND array_key_exists('gradepass', $data)) {
                if ($data['gradepass'] > $data['maxgrade']) {
                    $errors['gradepass'] = get_string('shouldbeless','local_onlineexams',$data['maxgrade']);
                }
            }
            $value = $data['gradepass'];
            $intvalue = (int)$value;
  
            if(!("$intvalue" === "$value") || $intvalue < 0){
              $errors['gradepass'] = get_string('numeric', 'local_onlineexams');
            }
            
        }
        if(isset($data['timelimit'])){
            $value = $data['timelimit'];
            $intvalue = (int)$value;  
            if(!("$intvalue" === "$value") || $intvalue < 0){
              $errors['timelimit'] = get_string('numeric', 'local_onlineexams');
            }
        }
        
        $errors = array_merge($errors, enrol_course_edit_validation($data, $this->context));
        return $errors;
    }
}
