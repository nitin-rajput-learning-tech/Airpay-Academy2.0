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
defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/user/selector/lib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');

define('program', 4);

use \local_program\form\program_form as program_form;
use local_program\local\querylib;
use local_program\program;
// use \local_program\notifications_emails as programnotifications_emails;

function local_program_pluginfile($course, $cm, $categorycontext, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'programlogo') {
        return false;
    }

    $itemid = array_shift($args);

    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($categorycontext->id, 'local_program', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_file($file, $filename, 0, $forcedownload, $options);
}

/**
 * Serve the new group form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_program_output_fragment_program_form($args) {
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $renderer = $PAGE->get_renderer('local_program');
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $data = $DB->get_record('local_program', ['id' => $args->id]);
    $formdata['id'] = $args->id;
    $customdata = array(
        'id' => $args->id,
        'form_status' => $args->form_status
    );
    if($args->id){
        $customdata['open_path'] = $data->open_path;
    }

    local_costcenter_set_costcenter_path($customdata);
    local_users_set_userprofile_datafields($customdata,$data);

    $mform = new program_form(null, $customdata, 'post', '', null, true, $formdata);
    $programdata = new stdClass();
    $programdata->id = $args->id;
    $programdata->form_status = $args->form_status;
    $mform->set_data($programdata);

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    ob_start();
    $formstatus = array();
    foreach (array_values($mform->formstatus) as $k => $mformstatus) {
        $activeclass = $k == $args->form_status ? 'active' : '';
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass, 'form-status' => $k);
    }
    $formstatusview = new \local_program\output\form_status($formstatus);
    $return .= $renderer->render($formstatusview);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_output_fragment_level_completion_settings($args) {
    global $CFG, $DB;
    $args = (object) $args;
    $context = $args->context;
    $return = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['pid'] = $args->pid;
    $formdata['levelid'] = $args->levelid;
    $mform = new \local_program\form\level_completion_form(null, array('id' => $args->id, 'pid' => $args->pid,'levelid' => $args->levelid), 'post', '', null, true, $formdata);
    if ($args->id > 0) {
        $section_completiondata = $DB->get_record('local_bcl_cmplt_criteria', array('id' => $args->id));
        $section_completiondata->form_status = $args->form_status;
        if($section_completiondata->courseids=="NULL"){
            $section_completiondata->courseids=null;
        }
        $mform->set_data($section_completiondata);
    }
    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    if(!empty($mform->formstatus)){

        $formheaders = array_keys($mform->formstatus);
        $nextform = array_key_exists($args->form_status, $formheaders);
        if ($nextform === false) {
            return false;
        }
    }
    ob_start();
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_output_fragment_program_completion_settings($args){
    global $CFG, $DB;
    $args = (object) $args;
    $context = $args->context;
    $return = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['pid'] = $args->pid;
    $formdata['levelid'] = $args->levelid;
    $mform = new \local_program\form\program_completion_form(null, array('id' => $args->id, 'pid' => $args->pid, 'form_status' => $args->form_status), 'post', '', null, true, $formdata);
    if ($args->id > 0) {
        $program_completiondata = $DB->get_record('local_bc_completion_criteria', array('id' => $args->id));
        $program_completiondata->form_status = $args->form_status;
        if($program_completiondata->levelids=="NULL"){
            $program_completiondata->levelids=null;
        }
        $mform->set_data($program_completiondata);
    }

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    if(!empty($mform->formstatus)){

        $formheaders = array_keys($mform->formstatus);
        $nextform = array_key_exists($args->form_status, $formheaders);
        if ($nextform === false) {
            return false;
        }
    }
    ob_start();
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_output_fragment_program_completion_form($args) {
    global $CFG, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['bcid'] = $args->bcid;
    $mform = new \local_program\form\program_completion_form(null, array('id' => $args->id,
        'bcid' => $args->cid, 'form_status' => $args->form_status), 'post', '', null, true, $formdata);

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    ob_start();
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_output_fragment_course_form($args) {
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $renderer = $PAGE->get_renderer('local_program');
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['bcid'] = $args->id;
    $formdata['levelid'] = $args->levelid;
    $mform = new programcourse_form(null, array('bcid' => $args->bcid, 'levelid' => $args->levelid,
        'form_status' => $args->form_status), 'post', '', null, true, $formdata);
    $programdata = new stdClass();
    $programdata->id = $args->id;
    $programdata->form_status = $args->form_status;
    $mform->set_data($programdata);

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();

    if(!empty($mform->formstatus)){
        $formheaders = array_keys($mform->formstatus);
        $nextform = array_key_exists($args->form_status, $formheaders);
        if ($nextform === false) {
            return false;
        }
        $formstatus = new \local_program\output\form_status(array_values($mform->formstatus));
        $return .= $renderer->render($formstatus);
    }
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}

class programcourse_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $bcid = $this->_customdata['bcid'];
        $levelid = $this->_customdata['levelid'];
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($bcid);

        //$mform->addElement('header', 'general', get_string('addcourses', 'local_program'));

        $mform->addElement('hidden', 'programid', $bcid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('hidden', 'levelid', $levelid);
        $mform->setType('levelid', PARAM_INT);

        $classroom_plugin_exist = \core_component::get_plugin_directory('local', 'classroom');

        if($classroom_plugin_exist){

            $checkboxes = array();
            $checkboxes[] = $mform->createElement('advcheckbox', 'map_classroom_'.$bcid.'_'.$levelid, null, '', array('class'=>'map_classroom_'.$bcid.'_'.$levelid),array(0,1));
            $mform->addGroup($checkboxes, 'map_classroom_'.$bcid.'_'.$levelid, get_string('add_classroom', 'local_program'), array(' '), false);
            $mform->addHelpButton('map_classroom_'.$bcid.'_'.$levelid, 'add_classroom', 'local_program');

        }

        $courses = array();
        $params = array();
        $course = $this->_ajaxformdata['course'];
        if (!empty($course)) {

            $coursessql = "SELECT c.id, c.fullname
                              FROM {course} AS c
                             WHERE c.visible = 1  AND c.id <> " . SITEID;

            list($csql, $courseparam) = $DB->get_in_or_equal($course, SQL_PARAMS_NAMED);
            $coursessql .= " AND c.id $csql ";
            $params = $params + $courseparam;
            $courses = $DB->get_records_sql_menu($coursessql, $params);

        } else if ($id > 0) {

            $coursessql = "SELECT c.id, c.fullname
                              FROM {course} AS c

                              JOIN {local_program_level_courses} AS cc ON cc.courseid = c.id
                             WHERE cc.programid = :programid AND c.visible = 1 ";
            $courses = $DB->get_records_sql_menu($coursessql, array('programid' => $cid));
        }

        $options = array(
            'ajax' => 'local_program/form-course-selector',
            'multiple' => true,
            'data-contextid' => $categorycontext->id,
            'data-programid' => $bcid,
            'data-levelid' => $levelid,
        );
        $mform->addElement('autocomplete', 'course', get_string('course', 'local_program'), $courses,
            $options);
        $mform->addRule('course', null, 'required', null, 'client');

        $mform->disable_form_change_checker();
    }
    public function validation($data, $files) {
        global $CFG, $DB, $USER;
        $errors = parent::validation($data, $files);
        if (empty($data['course'])){
                $errors['course'] = get_string('no_courses_assigned', 'local_program');

        }
        return $errors;
    }
}
function local_program_completion_form_flag($mform, $programid, $levelid){
    global $DB;
    if($levelid > 0){
        $completionsexist = $DB->record_exists('local_bc_level_completions', array('programid' => $programid, 'completion_status' => 1, 'levelid' => $levelid));
    }else{
        $completionsexist = $DB->record_exists('local_program_users', array('programid' => $programid, 'completion_status' => 1));
    }
    if($completionsexist){
        $mform->addElement('static', '', '', get_string('err_settingslocked', 'local_program'));
        $mform->addElement('button', 'settingsunlock', get_string('unlockcompletiondelete', 'local_program'), array('id' => 'reset_program_completions', 'data-programid' => $programid, 'data-levelid' => $levelid));
    }
    return $completionsexist;
}
function local_program_output_fragment_new_catform($args) {
    global $CFG, $DB;

    $args = (object) $args;
    $categorycontext = $args->context;
    $categoryid = $args->categoryid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }

    if ($args->categoryid > 0) {
        $heading = 'Update category';
        $collapse = false;
        $data = $DB->get_record('local_program_categories', array('id' => $categoryid));
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $categorycontext,
        'noclean' => true,
        'subdirs' => false,
    ];
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $categorycontext, 'group', 'description', null);

    $mform = new local_program\form\catform(null, array('editoroptions' => $editoroptions), 'post', '', null, true, $formdata);

    $mform->set_data($data);

    if (!empty($formdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
function program_filter($mform){
    global $DB,$USER;
    $stable = new stdClass();
    $stable->thead = false;
    $stable->start = 0;
    $stable->length = -1;
    $stable->search = '';
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

    $program_sql = "SELECT bc.id  FROM {local_program} AS bc ";

    $concatsql = '';
    if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {

            $concatsql.= (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='bc.open_path');

        }
        $program_sql .= " WHERE 1 = 1 ";
        $program_sql .= $concatsql;
        $programids = $DB->get_fieldset_sql($program_sql);
        $componentid = implode(',', $programids);
        if (!empty($componentid)) {
            $courseslist = $DB->get_records_sql_menu("SELECT id, name FROM {local_program}
                WHERE id IN ($componentid)");
        } else {
            $courseslist = $DB->get_records_sql_menu("SELECT id, name FROM {local_program} ");
        }

    $select = $mform->addElement('autocomplete', 'program', get_string('program', 'local_program'), $courseslist,
        array('placeholder' => get_string('program', 'local_program')));
    $mform->setType('program', PARAM_RAW);
    $select->setMultiple(true);
}
function get_user_program($userid) {
    global $DB;
    $sql = "SELECT lc.id, lc.name, lc.description
                FROM {local_program} AS lc
                JOIN {local_program_users} AS lcu ON lcu.programid = lc.id
                WHERE userid = :userid AND lc.status IN (1, 4)";
    $programs = $DB->get_records_sql($sql, array('userid' => $userid));
    return $programs;
}

class program_managelevel_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $id = $this->_customdata['id'];
        $programid = $this->_customdata['programid'];
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);

        //$mform->addElement('header', 'general', get_string('addcourses', 'local_program'));

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('text', 'level', get_string('level', 'local_program'));
        $mform->addRule('level', null, 'required', null, 'client');

        $mform->addElement('editor', 'level_description', get_string('description', 'local_program'));
        $mform->setType('level_description', PARAM_RAW);
        // $mform->addRule('description', null, 'required', null, 'client');

        $mform->disable_form_change_checker();
    }
}

function local_program_output_fragment_program_managelevel_form($args) {
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $renderer = $PAGE->get_renderer('local_program');
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['programid'] = $args->programid;

    $mform = new program_managelevel_form(null, array('id' => $args->id,
        'programid' => $args->programid, 'form_status' => $args->form_status), 'post', '', null,
        true, $formdata);
    $bclevel = new stdClass();
    $bclevel->programid = $args->programid;
    if ($args->id > 0) {
        $bclevel = $DB->get_record('local_program_levels', array('id' => $args->id));
    }

    $bclevel->form_status = $args->form_status;
    $bclevel->level_description['text'] = $bclevel->description;
    $mform->set_data($bclevel);

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();

    if(!empty($mform->formstatus)){
        $formheaders = array_keys($mform->formstatus);
        $nextform = array_key_exists($args->form_status, $formheaders);
        if ($nextform === false) {
            return false;
        }
        $formstatus = array();
        foreach (array_values($mform->formstatus) as $k => $mformstatus) {
            $activeclass = $k == $args->form_status ? 'active' : '';
            $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass);
        }
    }

    $formstatusview = new \local_program\output\form_status($formstatus);
    $return .= $renderer->render($formstatusview);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_leftmenunode(){
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
    $programnode = '';
    if(((has_capability('local/program:manageprogram', $categorycontext)) &&
        (!has_capability('local/program:trainer_viewprogram', $categorycontext))) ||
        (is_siteadmin())) {
        $programnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browseprograms', 'class'=>'pull-left user_nav_div browseprograms'));
            $programs_url = new moodle_url('/local/program/index.php');
            $program_icon = '<i class="fa fa-graduation-cap" aria-hidden="true"></i>';
            $programs = html_writer::link($programs_url, $program_icon.'<span class="user_navigation_link_text">'.get_string('browse_programs','local_program').'</span>',array('class'=>'user_navigation_link'));
            $programnode .= $programs;
        $programnode .= html_writer::end_tag('li');
    }

    return array('10' => $programnode);
}
function local_program_quicklink_node(){
    global $CFG, $PAGE, $OUTPUT;
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
    $stable = new stdClass();
    if(has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin()){
            
        // $stable->thead = false;
        // $stable->start = 0;
        // $stable->length = 1;
        // $stable->programstatus = -1;
        // $programs = (new program)->programs($stable);
        
        // $count_cr = $programs['programscount'];
        
        // $stable->programstatus = 1;
        // $programs = (new program)->programs($stable);
        
        // $count_activecr = $programs['programscount'];
        
        // $stable->programstatus = 3;
        // $programs = (new program)->programs($stable);
        
        // $count_cancelledcr = $programs['programscount'];
        
        // //local programs content
        $PAGE->requires->js_call_amd('local_program/ajaxforms', 'load');
        // $local_programs_content = $PAGE->requires->js_call_amd('local_program/ajaxforms', 'load');
        // $local_programs_content .= "<span class='anch_span'><span class='bootcamp_icon_wrap'></span></span>";
        // $local_programs_content .= "<div class='quick_navigation_detail'>
        //                                 <div class='span_str'>".get_string('manage_br_programs', 'local_program')."</div>";
        //     $local_programs_content .= "<span class='span_createlink'>";
        //     if(has_capability('local/program:createprogram', $categorycontext) || is_siteadmin()){
        //         $local_programs_content .= "<a href='javascript:void(0);' class='quick_nav_link goto_local_program' title='".get_string('create_program', 'local_program')."' onclick='(function(e){ require(\"local_program/ajaxforms\").init({contextid: ".$categorycontext->id.", component:\"local_program\", callback:\"program_form\", form_status:0, plugintype: \"local\", pluginname: \"program\", id:0, title: \"createprogram\" }) })(event)' >".get_string('create')."</a> | ";
        //     }
            
        //     $local_programs_content .="<a href='".$CFG->wwwroot."/local/program/index.php' class='viewlink' title= '".get_string('view_programs', 'local_program')." '>".get_string('view')."</a>
        //                                 </span>";
        // $local_programs_content .= "</div>";
        // $local_programs = '<div class="quick_nav_list manage_programs one_of_three_columns" >'.$local_programs_content.'</div>';

        $programs = array();
        $programs['node_header_string'] = get_string('manage_br_programs', 'local_program');
        $programs['pluginname'] = 'bootcamp';
        $programs['plugin_icon_class'] = 'fa fa-graduation-cap';
        if(has_capability('local/program:createprogram', $categorycontext) || is_siteadmin()){
            $programs['create'] = TRUE;
            $programs['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('class' => 'quick_nav_link goto_local_program', 'title' => get_string('create_program', 'local_program'), 'onclick' => '(function(e){ require("local_program/ajaxforms").init({contextid: '.$categorycontext->id.', component:"local_program", callback:"program_form", form_status:0, plugintype: "local", pluginname: "program", id:0, title: "createprogram" }) })(event)'));
        }
        // if(has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext)){
        $programs['viewlink_url'] = $CFG->wwwroot.'/local/program/index.php';
        $programs['view'] = TRUE;
        $programs['viewlink_title'] = get_string('view_programs', 'local_program');
        // }
        $programs['space_count'] = 'one';
        $content = $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $programs);
    }
    
    return array('8' => $content);
}

/**
 * process the bootcamp_mass_enroll
 * @param csv_import_reader $cir  an import reader created by caller
 * @param Object $bootcamp  a bootcamp record from table mdl_local_bootcamp
 * @param Object $categorycontext  course context instance
 * @param Object $data    data from a moodleform
 * @return string  log of operations
 */
function program_mass_enroll($cir, $program, $categorycontext, $data) {
    global $CFG,$DB, $USER;
    require_once ($CFG->dirroot . '/group/lib.php');
    // require_once($CFG->dirroot . '/local/program/notifications_emails.php');
    // $emaillogs = new programnotifications_emails();
    $emaillogs = new \local_program\notification();
    // init csv import helper
    $useridfield = $data->firstcolumn;
    $cir->init();
    $enrollablecount = 0;
    while ($fields = $cir->next()) {
        $a = new stdClass();
        if (empty ($fields))
            continue;
        $fields[0]= str_replace('"', '', trim($fields[0]));
        /*First Condition To validate users*/
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        $sql="SELECT u.* from {user} u where u.deleted=0 and u.suspended=0 and u.$useridfield LIKE '{$fields[0]}' ";

        if(is_siteadmin()){

            $sql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path',$open_path=$program->open_path,'lowerandsamepath');

        }else{

            $sql .= (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

        }

        $sql .= (new \local_users\lib\accesslib())::get_userprofilematch_concatsql($program);

        if (!$user = $DB->get_record_sql($sql)) {
            $result .= '<div class="alert alert-error">'.get_string('im:user_unknown', 'local_courses', $fields[0] ). '</div>';
            continue;
        } else {
            // if (file_exists($CFG->dirroot . '/local/lib.php')) {
            //     require_once($CFG->dirroot . '/local/lib.php');
            // }
            $allow = true;
            $type = 'program_enrol';
            $dataobj = $program->id;
            $fromuserid = $USER->id;
            if ($allow) {
                // foreach ($userstoassign as $key => $adduser) {
                    if (true) {
                        $programuser = new stdClass();
                        $programuser->programid = $program->id;
                        $programuser->courseid = 0;
                        $programuser->userid = $user->id;
                        $programuser->supervisorid = 0;
                        $programuser->prefeedback = 0;
                        $programuser->postfeedback = 0;
                        $programuser->trainingfeedback = 0;
                        $programuser->confirmation = 0;
                        $programuser->completion_status = 0;
                        $programuser->completiondate = 0;
                        $programuser->usercreated = $USER->id;
                        $programuser->timecreated = time();
                        $programuser->usermodified = $USER->id;
                        $programuser->timemodified = time();
                        try {
                            $programuser->id = $DB->insert_record('local_program_users',
                            $programuser);
                            // $local_program = $DB->get_record_sql("SELECT * FROM {local_program} where id = $program->id");
                            $local_program = $DB->get_record('local_program', array('id' => $program->id));

                            $params = array(
                                'context' => $categorycontext,
                                'objectid' => $programuser->id,
                                'other' => array('programid' => $program->id)
                            );

                            $event = \local_program\event\program_users_enrol::create($params);
                            $event->add_record_snapshot('local_program_users', $programuser);
                            $event->trigger();

                            if ($local_program->status == 0) {
                                // $email_logs = $emaillogs->program_emaillogs($type, $dataobj, $programuser->userid, $fromuserid);
                                $touser = \core_user::get_user($programuser->userid);
                                $email_logs = $emaillogs->program_notification($type, $touser, $USER, $local_program);
                            }
                            $result .= '<div class="alert alert-success">'.get_string('im:enrolled_ok', 'local_courses', fullname($user)).'</div>';
                            $enrollablecount ++;
                        } catch (dml_exception $ex) {
                            print_error($ex);
                        }
                    } else {
                        break;
                    }
                // }
                $programid = $program->id;
                $program = new stdClass();
                $program->id = $programid;
                $program->totalusers = $DB->count_records('local_program_users',
                    array('programid' => $programid));
                $DB->update_record('local_program', $program);
            }
        }
    }
    $result .= '<br />';//exit;
    $result .= get_string('im:stats_i', 'local_program', $enrollablecount) . "";
    return $result;
}

/*
* Author Sarath
* return count of programs under selected costcenter
* @return  [type] int count of programs
*/
function costcenterwise_program_count($costcenter, $department = false, $subdepartment = false, $l4department=false, $l5department=false){
    global $USER, $DB,$CFG;
        $params = array();
        $params['costcenterpath'] = '%/'.$costcenter.'/%';

        $countprogramql = "SELECT count(id) FROM {local_program} WHERE concat('/',open_path,'/') LIKE :costcenterpath";

        if ($department) {
            $countprogramql .= "  AND concat('/',open_path,'/') LIKE :departmentpath  ";
            $params['departmentpath'] = '%/'.$department.'/%';
        }
        if ($subdepartment) {
            $countprogramql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
            $params['subdepartmentpath'] = '%/'.$subdepartment.'/%';
        }
        if ($l4department) {
            $countprogramql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
            $params['l4departmentpath'] = '%/'.$l4department.'/%';
        }
        if ($l5department) {
            $countprogramql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
            $params['l5departmentpath'] = '%/'.$l5department.'/%';
        }

        $activesql = " AND visible = 1 ";
        $inactivesql = " AND visible = 0 ";

        $countprograms = $DB->count_records_sql($countprogramql, $params);
        $activeprograms = $DB->count_records_sql($countprogramql.$activesql, $params);
        $inactiveprograms = $DB->count_records_sql($countprogramql.$inactivesql, $params);
        if($countprograms >= 0){

            if($costcenter){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter;
            }
            if($department){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }
        if($activeprograms >= 0){

            if($costcenter){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter;
            }
            if($department){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }
        if($inactiveprograms >= 0){

            if($costcenter){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter;
            }
            if($department){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }
    return array('program_plugin_exist' => true,'allprogramcount' => $countprograms,'activeprogramcount' => $activeprograms,'inactiveprogramcount' => $inactiveprograms,'viewprogramlink_url'=>$viewprogramlink_url,'count_programactivelink_url' => $count_programactivelink_url,'count_programinactivelink_url' => $count_programinactivelink_url);
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_program_list(){
    return 'Program';
}

/**
 * Returns programs tagged with a specified tag.
 *
 * @param local_tags_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \local_tags\output\tagindex
 */
function local_program_get_tagged_programs($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0, $sort = '') {
    global $CFG, $PAGE;
    // prepare for display of tags related to evaluations
    $perpage = $exclusivemode ? 10 : 5;
    $displayoptions = array(
        'limit' => $perpage,
        'offset' => $page * $perpage,
        'viewmoreurl' => null,
    );
    $renderer = $PAGE->get_renderer('local_program');
    $totalcount = $renderer->tagged_programs($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, $count = 1, $sort);
    $content = $renderer->tagged_programs($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, 0, $sort);
    $totalpages = ceil($totalcount / $perpage);
    if ($totalcount)
    return new local_tags\output\tagindex($tag, 'local_program', 'program', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    else
    return '';
}
/**
* todo sql query departmentwise
* @param  $categorycontext object
* @return array
**/
function orgdep_sql($categorycontext){
    global $DB, $USER;
    $sql = '';
    $params =array();

    $sql = (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');

    if (has_capability('local/program:trainer_viewprogram', $categorycontext)){
        $myprograms = $DB->get_records_menu('local_program_trainers', array(
                    'trainerid' => $USER->id), 'id', 'id, programid');
        if (!empty($myprograms)) {
            list($relatedprogramsql, $params) = $DB->get_in_or_equal($myprograms, SQL_PARAMS_NAMED, 'myprograms');
            $sql = " AND c.id $relatedprogramsql";
        } else {
            return compact('sql', 'params');
        }
    }
    return compact('sql', 'params'); 
}

/**
* todo sql query departmentwise
* @param  $categorycontext object
* @return array
**/

function get_program_details($classid) { 
    global $USER, $DB, $PAGE;
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
    $PAGE->requires->js_call_amd('local_program/program','load', array());
    $PAGE->requires->js_call_amd('local_request/requestconfirm','load', array());
    $details = array();
    $joinsql = " ";
    if(has_capability('local/program:manageprogram',$categorycontext)){
        $selectsql = "select c.*  ";
        $fromsql = " from  {local_program} c ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_program' ";
        }
        $wheresql = " where c.id = ? ";

        $adminrecord = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$classid]);
        $details['manage'] = 1;
        $completedcount = $DB->count_records_sql("select count(cu.id) from {local_program_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.programid=? AND cu.completion_status=?", array($classid, 1));
        $enrolledcount = $DB->count_records_sql("select count(cu.id) from {local_program_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.programid=? ", array($classid));

        $details['completed'] = $completedcount;
        $details['enrolled'] = $enrolledcount;
    } else {
        $selectsql = "select cu.*, c.id as cid ";

        $fromsql = " from {local_program_users} cu 
        JOIN {local_program} c ON c.id = cu.programid ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_program' ";
        }
        $wheresql = " where 1 = 1 AND cu.userid = ? AND c.id = ? ";

        $record = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$USER->id, $classid]);

        $classsql = "select c.* from {local_program} c where c.id = ?";
        $programinfo = $DB->get_record_sql($classsql, [$classid]);
        
        if ($programinfo->selfenrol == 1 && $programinfo->approvalreqd == 0) {
              $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('enroll','local_program'). ' title = ' .get_string('enroll','local_program'). ' onclick="(function(e){ require(\'local_program/program\').ManageprogramStatus({action:\'selfenrol\', id: '.$programinfo->id.', programid:'.$programinfo->id.',actionstatusmsg:\'program_self_enrolment\',programname:\''.$programinfo->name.'\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('enroll','local_program').'</button></a>';
        } elseif ($programinfo->selfenrol == 1 && $programinfo->approvalreqd == 1) {
              $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('requestforenroll','local_program'). ' title = ' .get_string('requestforenroll','local_program'). ' onclick="(function(e){ require(\'local_request/requestconfirm\').init({action:\'add\', componentid: '.$programinfo->id.', component:\'program\',componentname:\''.$programinfo->name.'\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('requestforenroll','local_program').'</button></a>';
        }
        else {
            $enrollmentbtn ='-';
        }
        $details['manage'] = 0;
        $details['status'] = ($record->completion_status == 1) ? get_string('completed', 'local_onlinetests'):get_string('pending', 'local_onlinetests');
        $details['enrolled'] = ($record->timecreated) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timecreated): $enrollmentbtn;
        $details['completed'] = ($record->completiondate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->completiondate): '-';
    }
    return $details;
}
function local_program_request_dependent_query($aliasname){
    $returnquery = " WHEN ({$aliasname}.compname LIKE 'program') THEN (SELECT name from {local_program} WHERE id = {$aliasname}.componentid) ";
    return $returnquery;
}
function check_programenrol_pluginstatus($value){
    global $DB ,$OUTPUT ,$CFG;
    $enabled_plugins = $DB->get_field('config', 'value', array('name' => 'enrol_plugins_enabled'));
    $enabled_plugins =  explode(',',$enabled_plugins);
    $enabled_plugins = in_array('program',$enabled_plugins);

if(!$enabled_plugins){

    if(is_siteadmin()){
        $url = $CFG->wwwroot.'/admin/settings.php?section=manageenrols';
        $enable = get_string('enableplugin','local_program',$url);
        echo $OUTPUT->notification($enable,'notifyerror');
    }
    else{
        $enable = get_string('manageplugincapability','local_program');
        echo $OUTPUT->notification($enable,'notifyerror');
     }
   }    
}
function local_program_search_page_js(){
    global $PAGE;
    $PAGE->requires->js_call_amd('local_program/program','load', array());
}
function local_program_search_page_filter_element(&$filterelements){
    global $CFG;
    if(file_exists($CFG->dirroot.'/local/search/lib.php')){
        require_once($CFG->dirroot.'/local/search/lib.php');
        $filterelements['program'] = ['code' => 'program', 'name' => 'Programs', 'tagitemshortname' => 'program', 'count' => local_search_get_coursecount_for_modules([['type' => 'moduletype', 'values' => ['program']]])];
    }
}
function local_program_enabled_search(){
    return ['pluginname' => 'local_program', 'templatename' => 'local_program/searchpagecontent', 'type' => program];
}
function  local_program_applicable_filters_for_search_page(&$filterapplicable){
    $filterapplicable[program] = [/*'learningtype',*/ 'status', 'categories'/*, 'level', 'skill'*/];
}
function local_program_output_fragment_course_classroom_info($args){
    global $CFG,$DB, $OUTPUT;
    $args = (object) $args;
    $program = new local_program\program();
    $classrooms = $program->get_classrooms_count($args->courseid);
    if($classrooms > 0){
        return $OUTPUT->render_from_template('local_program/classroom_table', array());
    }else{
        return html_writer::div(get_string('noclassroomsavailiable', 'local_classroom'), 'alert alert-info text-center');
    }
}