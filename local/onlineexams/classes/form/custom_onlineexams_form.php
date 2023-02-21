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
    protected $course;
    protected $context;
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {

        global $USER;

        $this->formstatus = array(
            'manage_onlineexam' => get_string('manage_onlineexam', 'local_onlineexams'),
            'other_details' => get_string('courseother_details', 'local_onlineexams'),
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
        $course        = $this->_customdata['course']; // this contains the data of this form
        $course_id        = $this->_customdata['courseid']; // this contains the data of this form
        $category      = $this->_customdata['category'];
        $formstatus = $this->_customdata['form_status'];
        $get_coursedetails = $this->_customdata['get_coursedetails'];
        $editoroptions = $this->_customdata['editoroptions'];
        $returnto = $this->_customdata['returnto'];
        $returnurl = $this->_customdata['returnurl'];
        $coursetype =  $course->open_identifiedas;
        $categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context();
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
		
        $categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context($courseid);
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
                'data-options' => json_encode(array('id' => $courseid,'type'=>'category_selector')),
                'class' => 'idparentselect',
                'data-parentclass' => 'open_costcenterid_select',
                'data-class' => 'idparentselect',
                'multiple' => false,
            );

            $mform->addElement('autocomplete', 'open_categoryid', get_string('category'), $parents, $categoryinfo);
            $mform->setType('open_categoryid', PARAM_INT);


            $mform->addElement('text','fullname', get_string('onlineexam_name','local_onlineexams'),'maxlength="254" size="50"');
            $mform->addHelpButton('fullname', 'onlineexam_name','local_onlineexams');


            if (!empty($course->id) and !has_capability('moodle/course:changefullname', $categorycontext)) {
                $mform->hardFreeze('fullname');
                $mform->setConstant('fullname', $course->fullname);

            }elseif(has_capability('moodle/course:changefullname', $categorycontext)) {

                $mform->addRule('fullname', get_string('missingfullname','local_onlineexams'), 'required', null, 'client');
                $mform->setType('fullname', PARAM_TEXT);

            }

            $mform->addElement('text', 'shortname', get_string('onlineexamcode','local_onlineexams'), 'maxlength="100" size="20"');
            $mform->addHelpButton('shortname', 'onlineexamcode','local_onlineexams');


            if (!empty($course->id) and !has_capability('moodle/course:changeshortname', $categorycontext)) {
                $mform->hardFreeze('shortname');
                $mform->setConstant('shortname', $course->shortname);
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
          

            // if(!empty($this->_ajaxformdata['open_identifiedas'])){
            //     $identifiedtype  = $this->_ajaxformdata['open_identifiedas'];
            // }elseif(!empty($this->_ajaxformdata['identifiedtype'])){
            //      $identifiedtype  = $this->_ajaxformdata['identifiedtype'];
            // }
            // if($identifiedtype){

            //     $identifiedtype = is_array($identifiedtype) ? $identifiedtype : explode(',', $identifiedtype);
            //     list($coursetypesql, $coursetypeparams) = $DB->get_in_or_equal($identifiedtype, SQL_PARAMS_NAMED, 'name');
            //     $coursetypeql = "SELECT id, name FROM {local_course_types} WHERE id {$coursetypesql} ";
            //     $coursetypes =  $DB->get_records_sql_menu($coursetypeql, $coursetypeparams);
            // }

            // $coursetype = array(
            //     'ajax' => 'local_costcenter/form-options-selector',
            //     'data-contextid' => $categorycontext->id,
            //     'data-action' => 'costecenter_coursetype_selector',
            //     'data-options' => json_encode(array('id' => $identifiedtype)),
            //     'class' => 'identifiedasselect',
            //     'data-parentclass' => 'open_costcenterid_select',
            //     'data-class' => 'identifiedasselect',
            //     'multiple' => false,
            // );
            // $mform->addElement('autocomplete', 'identifiedtype', get_string('type','local_onlineexams'), $coursetypes,$coursetype);
            // $mform->addRule('identifiedtype', get_string('missingtype','local_onlineexams'), 'required', null, 'client');
            // $mform->addHelpButton('identifiedtype', 'open_identifiedascourse', 'local_onlineexams');
            // $mform->setType('identifiedtype',PARAM_RAW);
            
            // //for course format
            // $courseformats = get_sorted_course_formats(true);
            // $formcourseformats = array();
            // foreach ($courseformats as $courseformat) {
            //   $formcourseformats[$courseformat] = get_string('pluginname', "format_$courseformat");
            // }

            // Completion tracking.
  			$mform->addElement('hidden', 'enablecompletion');
  			$mform->setType('enablecompletion', PARAM_INT);
  			$mform->setDefault('enablecompletion', 1);

            // Custom Course type .
  			$mform->addElement('hidden', 'custom_coursetype');
  			$mform->setType('custom_coursetype', PARAM_INT);
  			$mform->setDefault('custom_coursetype', 1);

            // tags
            // $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'courses', 'component' => 'local_onlineexams'));

            $mform->addElement('editor','summary_editor', get_string('onlineexamsummary','local_onlineexams'), null, $editoroptions);
            $mform->addHelpButton('summary_editor', 'onlineexamsummary');
            $mform->setType('summary_editor', PARAM_RAW);
            $summaryfields = 'summary_editor';

            if ($overviewfilesoptions = course_overviewfiles_options($course)) {
              $mform->addElement('filemanager', 'overviewfiles_filemanager', get_string('onlineexamoverviewfiles','local_onlineexams'), null, $overviewfilesoptions);
              $mform->addHelpButton('overviewfiles_filemanager', 'onlineexamoverviewfiles');
              $summaryfields .= ',overviewfiles_filemanager';
            }
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


            $mform->addElement('hidden',  'activitytype',  'quiz');
            $attemptnumbers = range(0,10);
            $attemptnumbers[0] = get_string('unlimited'); 
            $mform->addElement('select',  'attempts', get_string('attempts', 'mod_quiz'), $attemptnumbers);
            $mform->setType('attempts', PARAM_INT);
            $mform->hideIf('attempts', 'examtype', 'eq', 1);

			
			
	        // $mform->addElement('duration', 'sndtimelimit', get_string('reviewtimelimit', 'quiz'),array('optional' => false));
            // $mform->addHelpButton('sndtimelimit', 'timelimit', 'quiz');
            // $mform->hideIf('sndtimelimit', 'examtype', 'eq', 0);
          



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
        //\quiz_access_manager::add_settings_form_fields($this, $mform);
        // $element = $mform->createElement('header', 'seb', get_string('seb', 'quizaccess_seb'));
        // insert_element($mform, $mform, $element);
         // Grade settings.
        //  (new \mod_quiz_mod_form($current, $section, $cm, $course))->standard_grading_coursemodule_elements();

        //  $mform->removeElement('grade');
        //  if (property_exists($this->current, 'grade')) {
        //      $currentgrade = $this->current->grade;
        //  } else {
        //      $currentgrade = $quizconfig->maximumgrade;
        //  }
        //  $mform->addElement('hidden', 'grade', $currentgrade);
        //  $mform->setType('grade', PARAM_FLOAT);
 
        //  // Number of attempts.
        //  $attemptoptions = array('0' => get_string('unlimited'));
        //  for ($i = 1; $i <= QUIZ_MAX_ATTEMPT_OPTION; $i++) {
        //      $attemptoptions[$i] = $i;
        //  }
        //  $mform->addElement('select', 'attempts', get_string('attemptsallowed', 'quiz'),
        //          $attemptoptions);
 
         // Grading method.
      
        //  if (\mod_quiz_mod_form::get_max_attempts_for_any_override() < 2) {
        //      $mform->hideIf('grademethod', 'attempts', 'eq', 1);
        //  }
 
        //add_seb_header_element(\mod_quiz_mod_form $quizform, \MoodleQuickForm $mform);
        //-------------------------------------------------------------------------
        // TODO Formslib does OR logic on disableif, and we need AND logic here.
        // $mform->disabledIf('overduehandling', 'timelimit', 'eq', 0);
        // $mform->disabledIf('overduehandling', 'timeclose', 'eq', 0);

            // $pointsArr = array();
            // $pointsArr[] = $mform->createElement('text',  'open_points',  '',  get_string('points','local_courses'));
            // $pointsArr[] = $mform->createElement('advcheckbox', 'open_enablepoints',  '',  '', 0);
            // $mform->hideIf('open_points', 'open_enablepoints', 'neq', 1);
            // $mform->addGroup($pointsArr, 'pointsArr',
            //     get_string('points','local_courses'),
            //     array('&nbsp;&nbsp;'), false);
            // $mform->addHelpButton('pointsArr', 'open_pointscourse', 'local_courses');
            // $mform->setType('open_points', PARAM_INT);
            // $mform->addRule('open_points', get_string('numeric','local_classroom'), 'numeric', null, 'client');

            // $mform->addElement('text',  'open_cost', get_string('open_costcourse','local_courses'));
            // $mform->addHelpButton('open_cost', 'open_costcourse', 'local_courses');
            // $mform->setType('open_cost', PARAM_INT);
            // $mform->addRule('open_cost', get_string('numeric','local_users'), 'numeric', null, 'client');
            $skillselect = array(0 => get_string('select_skill','local_courses'));

         $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path',$costcenterpath=$this->course->open_path);

            $skillcostcentersql = "SELECT id,name FROM {local_skill}
                                WHERE 1=1 $costcenterpathconcatsql ";


            $skills = $DB->get_records_sql_menu($skillcostcentersql);

       
            if(!empty($skills)){
                $skillselect = $skillselect+$skills;
            }

            $mform->addElement('select',  'open_skill', get_string('open_skillcourse','local_onlineexams'), $skillselect);
            $mform->addHelpButton('open_skill', 'open_skillcourse', 'local_onlineexams');
            $mform->setType('open_skill', PARAM_INT);

            $levelselect = array(0 => get_string('select_level','local_onlineexams'));

            $levelsql = "SELECT id,name FROM {local_course_levels}
                                WHERE 1=1 $costcenterpathconcatsql ";

            $levels = $DB->get_records_sql_menu($levelsql);

            if(!empty($levels)){
                $levelselect = $levelselect+$levels;
            }
            $mform->addElement('select',  'open_level', get_string('open_levelcourse','local_onlineexams'), $levelselect);
            $mform->addHelpButton('open_level', 'open_levelcourse', 'local_onlineexams');
            $mform->setType('open_level', PARAM_INT);

            // $mform->addElement('date_time_selector', 'startdate', get_string('startdate','local_courses'),
            //  array());
            // $mform->addHelpButton('startdate', 'startdate');
		
			// $mform->addElement('date_time_selector', 'enddate', get_string('enddate','local_courses'), array('optional' => false));
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
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$this->course->open_path);
            $mform->addElement('hidden', 'open_costcenterid');
            $mform->setConstant('open_costcenterid', $org);

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(2,HIERARCHY_LEVELS), true, 'local_onlineexams', $categorycontext, $multiple = false);
        }
        $mform->closeHeaderBefore('buttonar');
		$mform->disable_form_change_checker();
        // Finally set the current form data
        if(empty($course)&&$course_id>0){
             $course = get_course($course_id);
        }
        if(!empty($this->_ajaxformdata['open_certificateid'])){
            $course->open_certificateid = $this->_ajaxformdata['open_certificateid'];
        }
        if(!empty($course->open_certificateid)){
            $course->map_certificate = 1;
        }

        if(!empty($this->_ajaxformdata['open_categoryid'])){
            $course->open_categoryid = $this->_ajaxformdata['open_categoryid'];
        }else{
            $course->open_categoryid =0;
        }

        if(empty($this->_ajaxformdata['open_identifiedas'])&&!empty($this->_ajaxformdata['identifiedtype'])){
            $course->identifiedtype = $this->_ajaxformdata['identifiedtype'];
        }elseif(empty($this->_ajaxformdata['open_identifiedas'])&&empty($this->_ajaxformdata['identifiedtype'])){
            $course->identifiedtype ='';
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
                $errors['enddate'] = get_string('nosameenddate', 'local_onlineexams');
            }
        }

        // if (isset($data['category']) && $data['form_status'] == 0){
        //     if(empty($data['category'])){
        //         $errors['category'] = get_string('err_category', 'local_onlineexams');
        //     }
        // }

        if ($data['map_certificate'] == 1 && empty($this->_ajaxformdata['open_certificateid'])){
            $errors['open_certificateid'] = get_string('err_certificate', 'local_onlineexams');
        }
        
        // if ($data['open_enablepoints'] == 1){
            
        //     if(isset($data['open_points']) && $data['open_points']){
        //         $value = $data['open_points'];
        //         $intvalue = (int)$value;
      
        //         if(!("$intvalue" === "$value") || $intvalue < 0){
        //           $errors['pointsArr'] = get_string('numeric', 'local_classroom');
        //         }
                
        //       }else{
        //         $errors['pointsArr'] = get_string('err_points', 'local_onlineexams');
        //       }
        // }
        if (isset($data['open_path']) && $data['form_status'] == 0){
            if($data['open_path'] == 0){
                $errors['open_path'] = get_string('pleaseselectorganization', 'local_onlineexams');
            }
        }
        // if (isset($data['identifiedtype']) && $data['form_status'] == 0){
        //     if($data['identifiedtype'] == 0){
        //         $errors['identifiedtype'] = get_string('pleaseselectidentifiedtype', 'local_onlineexams');
        //     }
        // }
        if(isset($data['open_coursecompletiondays']) && $data['open_coursecompletiondays']){
            $value = $data['open_coursecompletiondays'];
            $intvalue = (int)$value;
  
            if(!("$intvalue" === "$value") || $intvalue < 0){
              $errors['open_coursecompletiondays'] = get_string('numeric', 'local_classroom'); 
            }
            
          }
          if (isset($data['gradepass']) && $data['form_status'] == 0){
            if($data['gradepass'] == 0){
                $errors['gradepass'] = get_string('entergradepass', 'local_onlineexams');
            }
        }

        // if(isset($data['open_cost']) && $data['open_cost']){
        //     $value = $data['open_cost'];
        //     $intvalue = (int)$value;
  
        //     if(!("$intvalue" === "$value") || $intvalue < 0){
        //       $errors['open_cost'] = get_string('numeric', 'local_classroom');
        //     }
            
        //   }
        $errors = array_merge($errors, enrol_course_edit_validation($data, $this->context));
        return $errors;
    }
}
