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
 * @subpackage local_forum
 */

namespace local_forum\form;
use local_users\functions\userlibfunctions as userlib;
use core;
use moodleform;
use context_system;
use context_course;
use context_coursecat;
use core_component;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/forum/mod_form.php');
require_once($CFG->dirroot . '/mod/forum/locallib.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
//require_once($CFG->libdir. '/coursecatlib.php');

class custom_forum_form extends moodleform {
    protected $forum;
    protected $context;
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true, $formdata = null) {

        global $USER;

        $this->formstatus = array(
            'manage_forum' => get_string('manage_forum', 'local_forum'),
            'other_details' => get_string('forumother_details', 'local_forum'),
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
        global $DB,$OUTPUT,$CFG, $PAGE, $USER,$COURSE;

        $mform    = $this->_form;
        $forum        = $this->_customdata['course']; // this contains the data of this form
        $forum_id        = $this->_customdata['courseid']; // this contains the data of this form
        $category      = $this->_customdata['category'];
        $formstatus = $this->_customdata['form_status'];
        $get_forumdetails = $this->_customdata['get_coursedetails'];
        $editoroptions = $this->_customdata['editoroptions'];
        $returnto = $this->_customdata['returnto'];
        $returnurl = $this->_customdata['returnurl'];
        $forumtype =  $forum->open_identifiedas;
        $categorycontext = (new \local_forum\lib\accesslib())::get_module_context();
        $formheaders = array_keys($this->formstatus);
        $formheader = $formheaders[$formstatus];

        if(empty($category)){
          $category = $CFG->defaultrequestcategory;
        }

        if (!empty($forum->id)) {
          $forumcontext = context_course::instance($forum->id);
          $context = $forumcontext;
          $categorycontext = context_coursecat::instance($category->id);
        } else {
          $forumcontext = null;
          $categorycontext = context_coursecat::instance($category);
          $context = $categorycontext;
        }

        $courseconfig = get_config('moodlecourse');

        $this->forum  = $forum;
        $this->context = $context;

        // Form definition with new forum defaults.
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

        if(empty($forum->id)){
          $forumid = 0;
        }else{
          $forumid = $id = $forum->id;
        }

        //For Announcements activity
        $mform->addElement('hidden', 'newsitems',$courseconfig->newsitems);

        $mform->addElement('hidden', 'id', $forumid, array('id' => 'forumid'));
        $mform->setType('id', PARAM_INT);
		
        $categorycontext = (new \local_forum\lib\accesslib())::get_module_context($forumid);
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

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_forum', $categorycontext, $multiple = false);

            // $mform->addElement('hidden','category', null);
            // $mform->setConstant('category', $category);


            $parents[0] = 'Select Category';
            ksort($parents);
            $categoryinfo = array(
                'ajax' => 'local_costcenter/form-options-selector',
                'data-contextid' => (\local_costcenter\lib\accesslib::get_module_context())->id,
                'data-action' => 'custom_category_selector',
                'data-options' => json_encode(array('id' => $forumid,'type'=>'category_selector')),
                'class' => 'idparentselect',
                'data-parentclass' => 'open_costcenterid_select',
                'data-class' => 'idparentselect',
                'multiple' => false,
            );

            $mform->addElement('autocomplete', 'open_categoryid', get_string('category'), $parents, $categoryinfo);
            $mform->setType('open_categoryid', PARAM_INT);


            $mform->addElement('text','fullname', get_string('forum_name','local_forum'),'maxlength="254" size="50"');
            $mform->addHelpButton('fullname', 'forum_name','local_forum');


            if (!empty($forum->id) and !has_capability('moodle/course:changefullname', $categorycontext)) {
                $mform->hardFreeze('fullname');
                $mform->setConstant('fullname', $forum->fullname);

            }elseif(has_capability('moodle/course:changefullname', $categorycontext)) {

                $mform->addRule('fullname', get_string('missingfullname','local_forum'), 'required', null, 'client');
                $mform->setType('fullname', PARAM_TEXT);

            }
            if (!empty($forum->id)) {
                $mform->addElement('static', 'shortname_static', get_string('shortname', 'local_costcenter'), 'maxlength="100" size="20"');
    
                // $mform->addRule('shortname', get_string('shortnamecannotbeempty', 'local_costcenter'), 'required', null, 'client');
                $mform->addElement('hidden', 'shortname');
                $mform->setType('shortname', PARAM_TEXT);
                $mform->hardFreeze('shortname');
                $mform->setConstant('shortname', $forum->shortname);
            } else {
                $shortnamestatic = 'fo';
                $shortname = array();
                $shortname[] = $mform->createElement('hidden',  'concatshortname', $shortnamestatic);
                $shortname[] = $mform->createElement('static',  'shortnamestatic', '', '<span class="shortnamestatic">' . $shortnamestatic . '</span>_');
                $shortname[] = $mform->createElement('text', 'shortname', 'local_costcenter', 'maxlength="100" size="20"');
                $mform->addGroup($shortname,  'groupshortname',  get_string('shortname', 'local_costcenter'),  array(''),  false);
                $mform->addRule('groupshortname', get_string('missingshortname', 'local_forums'), 'required', null, 'client');
            }

            // $mform->addElement('text', 'shortname', get_string('forumcode','local_forum'), 'maxlength="100" size="20"');
            // $mform->addHelpButton('shortname', 'forumcode','local_forum');


            // if (!empty($forum->id) and !has_capability('moodle/course:changeshortname', $categorycontext)) {
            //     $mform->hardFreeze('shortname');
            //     $mform->setConstant('shortname', $forum->shortname);
            // }elseif(has_capability('moodle/course:changefullname', $categorycontext)) {

            //     $mform->addRule('shortname', get_string('missingshortname','local_forum'), 'required', null, 'client');
            //     $mform->setType('shortname', PARAM_TEXT);

            // }
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
            // $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'courses', 'component' => 'local_forum'));

            $mform->addElement('editor','summary_editor', get_string('forumummary','local_forum'), null, $editoroptions);
            $mform->addHelpButton('summary_editor', 'forumummary','local_forum');
            $mform->setType('summary_editor', PARAM_RAW);
            $summaryfields = 'summary_editor';

            if ($overviewfilesoptions = course_overviewfiles_options($forum)) {
              $mform->addElement('filemanager', 'overviewfiles_filemanager', get_string('forumoverviewfiles','local_forum'), null, $overviewfilesoptions);
              $mform->addHelpButton('overviewfiles_filemanager', 'forumoverviewfiles','local_forum');
              $summaryfields .= ',overviewfiles_filemanager';
            }        


            $mform->addElement('hidden',  'activitytype',  'forum');
            

        } elseif($formstatus == 1){
            // $this->standard_intro_elements(get_string('forumintro', 'forum'));

            $forumtypes = forum_get_forum_types();
            \core_collator::asort($forumtypes, \core_collator::SORT_STRING);
            $mform->addElement('select', 'type', get_string('forumtype', 'forum'), $forumtypes);
            $mform->addHelpButton('type', 'forumtype', 'forum');
            $mform->setDefault('type', 'general');
    
            $mform->addElement('header', 'availability', get_string('availability', 'forum'));
    
            $name = get_string('duedate', 'forum');
            $mform->addElement('date_time_selector', 'duedate', $name, array('optional' => true));
            $mform->addHelpButton('duedate', 'duedate', 'forum');
    
            $name = get_string('cutoffdate', 'forum');
            $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional' => true));
            $mform->addHelpButton('cutoffdate', 'cutoffdate', 'forum');
    
            // Attachments and word count.
            $mform->addElement('header', 'attachmentswordcounthdr', get_string('attachmentswordcount', 'forum'));
    
            $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, $CFG->forum_maxbytes);
            $choices[1] = get_string('uploadnotallowed');
            $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'forum'), $choices);
            $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'forum');
            $mform->setDefault('maxbytes', $CFG->forum_maxbytes);
    
            $choices = array(
                0 => 0,
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5,
                6 => 6,
                7 => 7,
                8 => 8,
                9 => 9,
                10 => 10,
                20 => 20,
                50 => 50,
                100 => 100
            );
            $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'forum'), $choices);
            $mform->addHelpButton('maxattachments', 'maxattachments', 'forum');
            $mform->setDefault('maxattachments', $CFG->forum_maxattachments);
    
            $mform->addElement('selectyesno', 'displaywordcount', get_string('displaywordcount', 'forum'));
            $mform->addHelpButton('displaywordcount', 'displaywordcount', 'forum');
            $mform->setDefault('displaywordcount', 0);
    
            // Subscription and tracking.
            $mform->addElement('header', 'subscriptionandtrackinghdr', get_string('subscriptionandtracking', 'forum'));
    
            $options = forum_get_subscriptionmode_options();
            $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'forum'), $options);
            $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'forum');
            if (isset($CFG->forum_subscription)) {
                $defaultforumsubscription = $CFG->forum_subscription;
            } else {
                $defaultforumsubscription = FORUM_CHOOSESUBSCRIBE;
            }
            $mform->setDefault('forcesubscribe', $defaultforumsubscription);
    
            $options = array();
            $options[FORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'forum');
            $options[FORUM_TRACKING_OFF] = get_string('trackingoff', 'forum');
            if ($CFG->forum_allowforcedreadtracking) {
                $options[FORUM_TRACKING_FORCED] = get_string('trackingon', 'forum');
            }
            $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'forum'), $options);
            $mform->addHelpButton('trackingtype', 'trackingtype', 'forum');
            $default = $CFG->forum_trackingtype;
            if ((!$CFG->forum_allowforcedreadtracking) && ($default == FORUM_TRACKING_FORCED)) {
                $default = FORUM_TRACKING_OPTIONAL;
            }
            $mform->setDefault('trackingtype', $default);
    
            if ($CFG->enablerssfeeds && isset($CFG->forum_enablerssfeeds) && $CFG->forum_enablerssfeeds) {
    //-------------------------------------------------------------------------------
                $mform->addElement('header', 'rssheader', get_string('rss'));
                $choices = array();
                $choices[0] = get_string('none');
                $choices[1] = get_string('discussions', 'forum');
                $choices[2] = get_string('posts', 'forum');
                $mform->addElement('select', 'rsstype', get_string('rsstype', 'forum'), $choices);
                $mform->addHelpButton('rsstype', 'rsstype', 'forum');
                if (isset($CFG->forum_rsstype)) {
                    $mform->setDefault('rsstype', $CFG->forum_rsstype);
                }
    
                $choices = array();
                $choices[0] = '0';
                $choices[1] = '1';
                $choices[2] = '2';
                $choices[3] = '3';
                $choices[4] = '4';
                $choices[5] = '5';
                $choices[10] = '10';
                $choices[15] = '15';
                $choices[20] = '20';
                $choices[25] = '25';
                $choices[30] = '30';
                $choices[40] = '40';
                $choices[50] = '50';
                $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
                $mform->addHelpButton('rssarticles', 'rssarticles', 'forum');
                $mform->hideIf('rssarticles', 'rsstype', 'eq', '0');
                if (isset($CFG->forum_rssarticles)) {
                    $mform->setDefault('rssarticles', $CFG->forum_rssarticles);
                }
            }
    
            $mform->addElement('header', 'discussionlocking', get_string('discussionlockingheader', 'forum'));
            $options = [
                0               => get_string('discussionlockingdisabled', 'forum'),
                1   * DAYSECS   => get_string('numday', 'core', 1),
                1   * WEEKSECS  => get_string('numweek', 'core', 1),
                2   * WEEKSECS  => get_string('numweeks', 'core', 2),
                30  * DAYSECS   => get_string('nummonth', 'core', 1),
                60  * DAYSECS   => get_string('nummonths', 'core', 2),
                90  * DAYSECS   => get_string('nummonths', 'core', 3),
                180 * DAYSECS   => get_string('nummonths', 'core', 6),
                1   * YEARSECS  => get_string('numyear', 'core', 1),
            ];
            $mform->addElement('select', 'lockdiscussionafter', get_string('lockdiscussionafter', 'forum'), $options);
            $mform->addHelpButton('lockdiscussionafter', 'lockdiscussionafter', 'forum');
            $mform->disabledIf('lockdiscussionafter', 'type', 'eq', 'single');
    
    //-------------------------------------------------------------------------------
            $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'forum'));
            $options = array();
            $options[0] = get_string('blockperioddisabled','forum');
            $options[60*60*24]   = '1 '.get_string('day');
            $options[60*60*24*2] = '2 '.get_string('days');
            $options[60*60*24*3] = '3 '.get_string('days');
            $options[60*60*24*4] = '4 '.get_string('days');
            $options[60*60*24*5] = '5 '.get_string('days');
            $options[60*60*24*6] = '6 '.get_string('days');
            $options[60*60*24*7] = '1 '.get_string('week');
            $mform->addElement('select', 'blockperiod', get_string('blockperiod', 'forum'), $options);
            $mform->addHelpButton('blockperiod', 'blockperiod', 'forum');
    
            $mform->addElement('text', 'blockafter', get_string('blockafter', 'forum'));
            $mform->setType('blockafter', PARAM_INT);
            $mform->setDefault('blockafter', '0');
            $mform->addRule('blockafter', null, 'numeric', null, 'client');
            $mform->addHelpButton('blockafter', 'blockafter', 'forum');
            $mform->hideIf('blockafter', 'blockperiod', 'eq', 0);
    
            $mform->addElement('text', 'warnafter', get_string('warnafter', 'forum'));
            $mform->setType('warnafter', PARAM_INT);
            $mform->setDefault('warnafter', '0');
            $mform->addRule('warnafter', null, 'numeric', null, 'client');
            $mform->addHelpButton('warnafter', 'warnafter', 'forum');
            $mform->hideIf('warnafter', 'blockperiod', 'eq', 0);
    
            $coursecontext = context_course::instance($COURSE->id);
            // To be removed (deprecated) with MDL-67526.
            plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_forum');
    
    //-------------------------------------------------------------------------------
    
            // Add the whole forum grading options.
            // $this->add_forum_grade_settings($mform, 'forum');
    
            // $this->standard_coursemodule_elements();

        }else if ($formstatus == 2) {
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$this->forum->open_path);
            $mform->addElement('hidden', 'open_costcenterid');
            $mform->setConstant('open_costcenterid', $org);

            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(2,HIERARCHY_LEVELS), true, 'local_forum', $categorycontext, $multiple = false);
        }
        $mform->closeHeaderBefore('buttonar');
		$mform->disable_form_change_checker();
        // Finally set the current form data
        if(empty($forum)&&$forum_id>0){
             $forum = get_course($forum_id);
        }
        if(!empty($this->_ajaxformdata['open_certificateid'])){
            $forum->open_certificateid = $this->_ajaxformdata['open_certificateid'];
        }
        if(!empty($forum->open_certificateid)){
            $forum->map_certificate = 1;
        }

        if(!empty($this->_ajaxformdata['open_categoryid'])){
            $forum->open_categoryid = $this->_ajaxformdata['open_categoryid'];
        }else{
            $forum->open_categoryid =0;
        }

        if(empty($this->_ajaxformdata['open_identifiedas'])&&!empty($this->_ajaxformdata['identifiedtype'])){
            $forum->identifiedtype = $this->_ajaxformdata['identifiedtype'];
        }elseif(empty($this->_ajaxformdata['open_identifiedas'])&&empty($this->_ajaxformdata['identifiedtype'])){
            $forum->identifiedtype ='';
        }
        $this->set_data($forum);
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
        $shortname = !empty(trim($data['concatshortname'])) ? trim($data['concatshortname']) . '_' . trim($data['shortname']) : trim($data['shortname']);
        if ($forum = $DB->get_record('course', array('shortname' => $shortname), '*', IGNORE_MULTIPLE)) {
            if (empty($data['id']) || $forum->id != $data['id']) {
                $errors['groupshortname'] = get_string('shortnametaken', 'local_forum', $forum->fullname);
            }
        }  

        if (empty(trim($data['shortname'])) && $data['id'] == 0) {
            $errors['groupshortname'] = get_string('shortnamecannotbeempty', 'local_costcenter');
        }
        if (empty(trim($data['fullname']))&& $data['form_status'] == 0) {
            $errors['fullname'] = get_string('missingfullname','local_forum');
        }
		 if (isset($data['duedate']) && $data['duedate']
                && isset($data['cutoffdate']) && $data['cutoffdate']) {
            if ($data['cutoffdate'] <= $data['duedate']) {
                $errors['cutoffdate'] = get_string('nosameenddate', 'local_forum');
            }
        }
        if ($data['map_certificate'] == 1 && empty($this->_ajaxformdata['open_certificateid'])){
            $errors['open_certificateid'] = get_string('err_certificate', 'local_forum');
        }
        if (isset($data['open_path']) && $data['form_status'] == 0){
            if($data['open_path'] == 0){
                $errors['open_path'] = get_string('pleaseselectorganization', 'local_forum');
            }
        }
        if(isset($data['open_forumcompletiondays']) && $data['open_forumcompletiondays']){
            $value = $data['open_forumcompletiondays'];
            $intvalue = (int)$value;
  
            if(!("$intvalue" === "$value") || $intvalue < 0){
              $errors['open_forumcompletiondays'] = get_string('numeric', 'local_classroom'); 
            }
            
          }
          if (isset($data['gradepass']) && $data['form_status'] == 0){
            
            if (array_key_exists('maxgrade', $data) AND array_key_exists('gradepass', $data)) {
                if ($data['gradepass'] > $data['maxgrade']) {
                    $errors['gradepass'] = get_string('shouldbeless','local_forum',$data['maxgrade']);
                }
            }
            $value = $data['gradepass'];
            $intvalue = (int)$value;
  
            if(!("$intvalue" === "$value") || $intvalue < 0){
              $errors['gradepass'] = get_string('numeric', 'local_forum');
            }
            
        }
        if(isset($data['timelimit'])){
            $value = $data['timelimit'];
            $intvalue = (int)$value;  
            if(!("$intvalue" === "$value") || $intvalue < 0){
              $errors['timelimit'] = get_string('numeric', 'local_forum');
            }
        }
        
        $errors = array_merge($errors, enrol_course_edit_validation($data, $this->context));
        return $errors;
    }
}
