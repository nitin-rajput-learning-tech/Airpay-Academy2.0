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
 * @subpackage local_costcenter
 */

defined('MOODLE_INTERNAL') or die;
define('ACTIVE',0);
define('IN_ACTIVE',1);
define('TOTAL',2);
define('HIERARCHY_LEVELS', 4);
//use core_component;
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/message/lib.php');

class costcenter {
    
    /*
     * @method get_costcenter_parent Get parent of the costcenter
     * @param object $costcenters costcenter data object
     * @param array $selected Costcenter position
     * @param boolean $inctop Include default value/not
     * @param boolean $all All option to select all values/not
     * @return array List of values
     */
    function get_costcenter_parent($costcenters, $selected = array(), $inctop = true, $all = false) {
        $out = array();

        //if an integer has been sent, convert to an array
        if (!is_array($selected)) {
            $selected = ($selected) ? array(intval($selected)) : array();
        }
        if ($inctop) {
            $out[null] = '---Select---';
        }
        if ($all) {
            $out[0] = get_string('all');
        }
        if (is_array($costcenters)) {
            foreach ($costcenters as $parent) {
                // An item cannot be its own parent and cannot be moved inside itself or one of its own children
                // what we have in $selected is an array of the ids of the parent nodes of selected branches
                // so we must exclude these parents and all their children
                //add using same spacing style as the bulkitems->move available & selected multiselects
                foreach ($selected as $key => $selectedid) {
                    if (preg_match("@/$selectedid(/|$)@", $parent->path)) {
                        continue 2;
                    }
                }
                if ($parent->id != null) {
                    $out[$parent->id] = format_string($parent->fullname);
                }
            }
        }

        return $out;
    }


    /*
     * @method get_costcenter_items Get costcenter list
     * @param boolean $fromcostcenter used to indicate called from costcenter plugin,using while error handling
     * @return list of costcenters
     * */
    function get_costcenter_items($fromcostcenter = NULL) {

        global $DB, $USER;
        $activecostcenterlist = $DB->get_records('local_costcenter', array('visible' => 1), 'sortorder, fullname');

        if (empty($fromcostcenter)) {
            if (empty($activecostcenterlist))
                print_error('notassignedcostcenter', 'local_costcenter');
        }
        
        $assigned_costcenters = costcenter_items();
        
        if (empty($fromcostcenter)) {
            if (empty($assigned_costcenters)) {
                print_error('notassignedcostcenter', 'local_costcenter');
            } else
                return $assigned_costcenters;
        } else
            return $assigned_costcenters;
    }
    /*
     * @method get_next_child_sortthread Get costcenter child list
     * @param  int $parentid which is id of a parent costcenter
     * @param  [string] $table is a table name 
     * @return list of costcenter children
     * */
    function get_next_child_sortthread($parentid, $table) {
        global $DB, $CFG;
        $maxthread = $DB->get_record_sql("SELECT MAX(sortorder) AS sortorder FROM {$CFG->prefix}{$table} WHERE parentid = :parentid", array('parentid' => $parentid));
        
        if (!$maxthread || strlen($maxthread->sortorder) == 0) {
            if ($parentid == 0) {
                // first top level item
                return $this->inttovancode(1);
            } else {
                // parent has no children yet
                return $DB->get_field('local_costcenter', 'sortorder', array('id' => $parentid)) . '.' . $this->inttovancode(1);
            }
        }
        return $this->increment_sortorder($maxthread->sortorder);
    }

    /**
     * Convert an integer to a vancode
     * @param int $int integer to convert.
     * @return vancode The vancode representation of the specified integer
     */
    function inttovancode($int = 0) {
        $num = base_convert((int) $int, 10, 36);
        $length = strlen($num);
        return chr($length + ord('0') - 1) . $num;
    }

    /**
     * Convert a vancode to an integer
     * @param string $char Vancode to convert. Must be <= '9zzzzzzzzzz'
     * @return integer The integer representation of the specified vancode
     */
    function vancodetoint($char = '00') {
        return base_convert(substr($char, 1), 36, 10);
    }

    /**
     * Increment a vancode by N (or decrement if negative)
     *
     */
    function increment_vancode($char, $inc = 1) {
        return $this->inttovancode($this->vancodetoint($char) + (int) $inc);
    }
    /**
     * Increment a sortorder by N (or decrement if negative)
     *
     */
    function increment_sortorder($sortorder, $inc = 1) {
        if (!$lastdot = strrpos($sortorder, '.')) {
            // root level, just increment the whole thing
            return $this->increment_vancode($sortorder, $inc);
        }
        $start = substr($sortorder, 0, $lastdot + 1);
        $last = substr($sortorder, $lastdot + 1);
        // increment the last vancode in the sequence
        return $start . $this->increment_vancode($last, $inc);
    }
    
    /*Get uploaded course summary uploaded file
     * @param $course is an obj Moodle course
     * @return course summary file(img) src url if exists else return default course img url
     * */
    function get_course_summary_file($course){  
        global $DB, $CFG, $OUTPUT;
        if ($course instanceof stdClass) {
            //require_once($CFG->libdir . '/coursecatlib.php');
            $course = new core_course_list_element($course);
        }
        
        // set default course image
        $url = $OUTPUT->image_url('/course_images/courseimg', 'local_costcenter');
        foreach ($course->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            if($isimage)
            $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
            $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
        }
        return $url;
    }
    function get_costcenter_icons(){
        global $USER, $DB;

        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path',$costcenterpath=null,$datatype='lowerandsamepath');

        $costcentersql = "SELECT lc.shell
                    FROM {local_costcenter} AS lc WHERE lc.visible = 1 $costcenterpathconcatsql ";
        if(!empty($costcentershell = $DB->get_field_sql($costcentersql))){
            return $costcentershell;
        }else{
            return false;
        }
    }
    function get_costcenter_theme(){
        global $USER, $DB;
        if(!is_siteadmin()){
            $path = (new \local_costcenter\lib\accesslib())::get_user_role_switch_path();
            $orgid = ($path) ? explode('/',$path[0])[1] : null;
            if($orgid==NULL) {
                $orgid=(empty($path)) ? explode('/',$USER->open_path)[1] : null;
            }
            if($orgid){
                $costcentersql = "SELECT lc.theme,lc.button_color,lc.brand_color,lc.hover_color
                FROM {local_costcenter} AS lc WHERE lc.visible = 1 AND lc.id = {$orgid}";
            }else{
                return false;
            }
            if(!empty($costcentertheme = $DB->get_record_sql($costcentersql))){
                return $costcentertheme;
            }
        }else{
            return false;
        }
    }
}
/**
 * Description: local_costcenter_pluginfile for fetching images in costcenter plugin
 * @param  [INT] $course        [course id]
 * @param  [INT] $cm            [course module id]
 * @param  [context] $context       [context of the file]
 * @param  [string] $filearea      [description]
 * @param  [array] $args          [array of ]
 * @param  [boolean] $forcedownload [to download or only view]
 * @param  array  $options       [description]
 * @return [file]                [description]
 */
function local_costcenter_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
        // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.

        // Make sure the filearea is one of those used by the plugin.
        if ($filearea !== 'costcenter_logo') {
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
        $file = $fs->get_file($context->id, 'local_costcenter', $filearea, $itemid, $filepath, $filename);
        if (!$file) {
            return false;
        }
        send_file($file, $filename, 0, $forcedownload, $options);
    }
/**
 * Description: get the logo specified to the organization.
 * @param  [INT] $costcenter_logo [item id of the logo]
 * @return [URL]                  [path of the logo]
 */
function costcenter_logo($costcenter_logo) {
    global $DB;
    $costcenter_logourl = false;

    $sql = "SELECT * FROM {files} WHERE itemid = :logo  AND filename != '.' ORDER BY id DESC";
    $costcenterlogorecord = $DB->get_record_sql($sql,array('logo' => $costcenter_logo),1);

    if (!empty($costcenterlogorecord)){
        if($costcenterlogorecord->filearea=="costcenter_logo"){
            $costcenter_logourl = moodle_url::make_pluginfile_url($costcenterlogorecord->contextid, $costcenterlogorecord->component, $costcenterlogorecord->filearea, $costcenterlogorecord->itemid, $costcenterlogorecord->filepath, $costcenterlogorecord->filename);
        }
    }
    return $costcenter_logourl;
}
/**
     * @method local_costcenter_output_fragment_new_costcenterform
     * @param  $args is an array   
     */
function local_costcenter_output_fragment_new_costcenterform($args){
 global $CFG,$DB;
 
    $args = (object) $args;
    $context = $args->context;
    // $costcenterid = $args->costcenterid;
    // $parentid = $args->parentid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }

    $formparams=array('id' => $args->id, 'formtype' => $args->formtype);
    if($args->id){

        $data = $DB->get_record('local_costcenter', array('id'=>$args->id));

        $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context($data->path);

        $data->shortname_static = $data->shortname;
        $draftitemid = file_get_submitted_draft_itemid('costcenter_logo');
        file_prepare_draft_area($draftitemid, $categorycontext->id, 'local_costcenter', 'costcenter_logo', $data->costcenter_logo, null);
        $data->costcenter_logo = $draftitemid;

        $formparams['open_path'] = $data->path;
    }
    local_costcenter_set_costcenter_path($formparams);

    $mform = new local_costcenter\form\organization_form(null,$formparams, 'post', '', null, true, $formdata);

    $mform->set_data($data);
    if (!empty($args->jsonformdata)&& strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
 
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

/**
 * Description: [organizations_filter code]
 * @param  [mform]  $mform          [form where the filetr is initiated]
 * @param  string  $query          [description]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [description]
 * @param  integer $perpage        [description]
 * @return [type]                  [description]
 */
function organizations_filter($mform,$query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB,$USER;
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    $organizationlist = array();
    $data = data_submitted();
    $costcenterid = optional_param('costcenterid', '', PARAM_INT);
    // print_r($costcenterid);
    // exit;
    $userparam = array();
    $organizationparam = array();
    $params = array();
    
    if(is_siteadmin()){
        $organizationlist_sql="SELECT id, fullname FROM {local_costcenter} WHERE depth =1";
    }else{

        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

        $organizationlist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth=1 $costcenterpathconcatsql ";

    }
    if(!empty($query)){ 
        if ($searchanywhere) {
            $organizationlist_sql.=" AND fullname LIKE '%$query%' ";
        } else {
            $organizationlist_sql.=" AND fullname LIKE '$query%' ";
        }
    }
    if(isset($data->organizations)&&!empty(($data->organizations))){
        list($organizationparamsql, $organizationparam) = $DB->get_in_or_equal($data->organizations, SQL_PARAMS_NAMED, 'param', true, false);        
        $organizationlist_sql.=" AND id $organizationparamsql";
    }

    $params = array_merge($userparam, $organizationparam);

    if(!empty($query)||empty($mform)){
        $organizationlist = $DB->get_records_sql($organizationlist_sql, $params, $page, $perpage);
        return $organizationlist;
    }
    if((isset($data->organizations)&&!empty($data->organizations)) || !empty($costcenterid) ){
        $organizationlist = $DB->get_records_sql_menu($organizationlist_sql, $params, $page, $perpage);
    }
    
    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'organizations',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('organisations','local_costcenter')
    );
    $select = $mform->addElement('autocomplete', 'organizations', '', $organizationlist,$options);
    $mform->setType('organizations', PARAM_RAW);
}
/**
  * Description: [departments_filter code]
 * @param  [mform]  $mform          [form where the filetr is initiated]
 * @param  string  $query          [description]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [description]
 * @param  integer $perpage        [description]
 * @return [type]                  [description]
 */
function departments_filter($mform,$query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB,$USER;
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    $departmentslist=array();
    $data=data_submitted();
    $departmentid = optional_param('departmentid', '', PARAM_INT);
    $userparam = array();
    $organizationparam = array();
    $params = array();
    
    if(is_siteadmin()){
        $departmentslist_sql="SELECT id, fullname FROM {local_costcenter} WHERE depth = 2";
    }else{
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

        $departmentslist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth=2 $costcenterpathconcatsql ";

    }
    if(!empty($query)){ 
        if ($searchanywhere) {
            $departmentslist_sql.=" AND fullname LIKE '%$query%' ";
        } else {
            $departmentslist_sql.=" AND fullname LIKE '$query%' ";
        }
    }
    if(isset($data->departments)&&!empty(($data->departments))){
        list($organizationparamsql, $organizationparam) = $DB->get_in_or_equal($data->departments, SQL_PARAMS_NAMED, 'param', true, false);
        if($organizationparamsql){
            $departmentslist_sql.=" AND id {$organizationparamsql} ";
        }
    }
    $params = array_merge($userparam, $organizationparam);

    if(!empty($query)||empty($mform)){ 
        $departmentslist = $DB->get_records_sql($departmentslist_sql, $params, $page, $perpage);
        return $departmentslist;
    }
    if((isset($data->departments)&&!empty($data->departments))  || !empty($departmentid)){ 
        $departmentslist = $DB->get_records_sql_menu($departmentslist_sql, $params, $page, $perpage);
    }
    
    $options = array(
            'ajax' => 'local_courses/form-options-selector',
            'multiple' => true,
            'data-action' => 'departments',
            'data-options' => json_encode(array('id' => 0)),
            'placeholder' => get_string('department','local_costcenter')
    );
        
    $select = $mform->addElement('autocomplete', 'departments', '', $departmentslist,$options);
    $mform->setType('departments', PARAM_RAW);
}

/**
  * Description: [subdepartment_filter code]
 * @param  [mform]  $mform          [form where the filetr is initiated]
 * @param  string  $query          [description]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [description]
 * @param  integer $perpage        [description]
 * @return [type]                  [description]
 */
function subdepartment_filter($mform,$query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB,$USER;
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    $subdepartmentslist=array();
    $data=data_submitted();
    $subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
    $userparam = array();
    $departmentparam = array();
    $params = array();
    if(is_siteadmin()){
        $subdepartmentslist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth = 3 ";
    }else{
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

        $subdepartmentslist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth=3 $costcenterpathconcatsql ";

        ;
    }
    if(!empty($query)){ 
        if ($searchanywhere) {
            $subdepartmentslist_sql .= " AND fullname LIKE '%$query%' ";
        } else {
            $subdepartmentslist_sql .= " AND fullname LIKE '$query%' ";
        }
    }
    if(isset($data->subdepartment)&&!empty(($data->subdepartment))){
        list($departmentparamsql, $departmentparam) = $DB->get_in_or_equal($data->subdepartment, SQL_PARAMS_NAMED, 'param', true, false);        
        $subdepartmentslist_sql.=" AND id $departmentparamsql";
    }
    $params = array_merge($userparam, $departmentparam);

    if(!empty($query)||empty($mform)){ 
        $subdepartmentslist = $DB->get_records_sql($subdepartmentslist_sql, $params, $page, $perpage);
        return $subdepartmentslist;
    }
    if((isset($data->subdepartment)&&!empty($data->subdepartment)) || !empty($subdepartmentid)){ 
        $subdepartmentslist = $DB->get_records_sql_menu($subdepartmentslist_sql, $params, $page, $perpage);
    }
    
    $options = array(
            'ajax' => 'local_courses/form-options-selector',
            'multiple' => true,
            'data-action' => 'subdepartment',
            'data-options' => json_encode(array('id' => 0)),
            'placeholder' => get_string('subdepartment','local_costcenter'),
            'id' => 'subdepartment_filter_element'
    );
        
    $select = $mform->addElement('autocomplete', 'subdepartment', '', $subdepartmentslist, $options);
    $mform->setType('subdepartment', PARAM_RAW);
}

/**
  * Description: [department4level_filter code]
 * @param  [mform]  $mform          [form where the filetr is initiated]
 * @param  string  $query          [description]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [description]
 * @param  integer $perpage        [description]
 * @return [type]                  [description]
 */
function department4level_filter($mform,$query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB,$USER;
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    $department4levelslist=array();
    $data=data_submitted();
    $department4levelid = optional_param('department4level', '', PARAM_INT);
    $userparam = array();
    $departmentparam = array();
    $params = array();
    if(is_siteadmin()){
        $department4levelslist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth = 4 ";
    }else{

        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

        $department4levelslist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth=4 $costcenterpathconcatsql ";

    }
    if(!empty($query)){
        if ($searchanywhere) {
            $department4levelslist_sql .= " AND fullname LIKE '%$query%' ";
        } else {
            $department4levelslist_sql .= " AND fullname LIKE '$query%' ";
        }
    }
    if(isset($data->department4level)&&!empty(($data->department4level))){
        list($departmentparamsql, $departmentparam) = $DB->get_in_or_equal($data->department4level, SQL_PARAMS_NAMED, 'param', true, false);
        $department4levelslist_sql.=" AND id $departmentparamsql";
    }
    $params = array_merge($userparam, $departmentparam);

    if(!empty($query)||empty($mform)){
        $department4levelslist = $DB->get_records_sql($department4levelslist_sql, $params, $page, $perpage);
        return $department4levelslist;
    }
    if((isset($data->department4level)&&!empty($data->department4level)) || !empty($department4levelid)){
        $department4levelslist = $DB->get_records_sql_menu($department4levelslist_sql, $params, $page, $perpage);
    }

    $options = array(
            'ajax' => 'local_courses/form-options-selector',
            'multiple' => true,
            'data-action' => 'department4level',
            'data-options' => json_encode(array('id' => 0)),
            'placeholder' => get_string('department4levelt','local_costcenter'),
            'id' => 'department4level_filter_element'
    );

    $select = $mform->addElement('autocomplete', 'department4level', '', $department4levelslist, $options);
    $mform->setType('department4level', PARAM_RAW);
}
/**
  * Description: [department5level_filter code]
 * @param  [mform]  $mform          [form where the filetr is initiated]
 * @param  string  $query          [description]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [description]
 * @param  integer $perpage        [description]
 * @return [type]                  [description]
 */
function department5level_filter($mform,$query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB,$USER;
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    $department5levelslist=array();
    $data=data_submitted();
    $department5levelid = optional_param('department5level', '', PARAM_INT);
    $userparam = array();
    $departmentparam = array();
    $params = array();
    if(is_siteadmin()){

        $department5levelslist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth = 5 ";
    }else{

       $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

        $department5levelslist_sql = "SELECT id, fullname FROM {local_costcenter} WHERE depth=5 $costcenterpathconcatsql ";

    }
    if(!empty($query)){
        if ($searchanywhere) {
            $department5levelslist_sql .= " AND fullname LIKE '%$query%' ";
        } else {
            $department5levelslist_sql .= " AND fullname LIKE '$query%' ";
        }
    }
    if(isset($data->department5level)&&!empty(($data->department5level))){
        list($departmentparamsql, $departmentparam) = $DB->get_in_or_equal($data->department5level, SQL_PARAMS_NAMED, 'param', true, false);
        $department5levelslist_sql.=" AND id $departmentparamsql";
    }
    $params = array_merge($userparam, $departmentparam);

    if(!empty($query)||empty($mform)){
        $department5levelslist = $DB->get_records_sql($department5levelslist_sql, $params, $page, $perpage);
        return $department5levelslist;
    }
    if((isset($data->department5level)&&!empty($data->department5level)) || !empty($department5levelid)){
        $department5levelslist = $DB->get_records_sql_menu($department5levelslist_sql, $params, $page, $perpage);
    }

    $options = array(
            'ajax' => 'local_courses/form-options-selector',
            'multiple' => true,
            'data-action' => 'department5level',
            'data-options' => json_encode(array('id' => 0)),
            'placeholder' => get_string('department5levelt','local_costcenter'),
            'id' => 'department5level_filter_element'
    );

    $select = $mform->addElement('autocomplete', 'department5level', '', $department5levelslist, $options);
    $mform->setType('department5level', PARAM_RAW);
}

/**
 * Description: [insert costcenter instance ]
 * @param  [OBJECT] $costcenter [costcenter object]
 * @return [INT]             [created costcenter id]
 */
function costcenter_insert_instance($costcenter){
        global $DB, $CFG, $USER;
       // require_once("$CFG->libdir/coursecatlib.php");


        if($costcenter->formtype == 'department'){

            $costcenter->parentid=$costcenter->open_costcenterid;
            $costcenter->depth = 1;

        }else if($costcenter->formtype == 'subdepartment'){

            $costcenter->parentid=$costcenter->open_department;
            $costcenter->depth = 2;

        }else if($costcenter->formtype == 'subsubdepartment'){

            $costcenter->parentid=$costcenter->open_subdepartment;
            $costcenter->depth = 3;

        }else if($costcenter->formtype == 'subsubsubdepartment'){

            $costcenter->parentid=$costcenter->open_level4department;
            $costcenter->depth = 4;

        }else{

            $costcenter->depth = 1;
            $costcenter->path = '';

        }

        if ($costcenter->parentid > 0) {
            /* ---parent item must exist--- */
            $parent = $DB->get_record('local_costcenter', array('id' => $costcenter->parentid));
            $costcenter->depth = $parent->depth + 1;
            $costcenter->path = $parent->path;
        }
        /* ---get next child item that need to provide--- */
        $custom = new costcenter();
        if (!$sortorder = $custom->get_next_child_sortthread($costcenter->parentid, 'local_costcenter')) {
            return false;
        }
        
        $costcenter->sortorder = $sortorder;
        $parentid = $costcenter->parentid ?  $costcenter->parentid:0;
        $costcenter->costcenter_logo = $costcenter->costcenter_logo;
        $costcenter->shell = $costcenter->shell;

        if(isset($costcenter->concatshortname) && !empty($costcenter->concatshortname)){

            $costcenter->shortname = $costcenter->concatshortname.'_'.$costcenter->shortname;
        }

        $costcenter->id = $DB->insert_record('local_costcenter', $costcenter);
        if($costcenter->id) {
            $parentpath = $DB->get_field('local_costcenter', 'path', array('id'=>$parentid));
            $path = $parentpath.'/'.$costcenter->id;
            $datarecord = new stdClass();
            $datarecord->id = $costcenter->id;
            $datarecord->path = $path;
            $DB->update_record('local_costcenter',  $datarecord);
            
            $record = new stdClass();
            $record->name = $costcenter->fullname;
            $record->parent = $DB->get_field('local_costcenter', 'category', array('id'=>$parentid));
            $record->idnumber = $costcenter->shortname;
           $category = core_course_category::create($record);
            
            if($category ){
                $DB->execute("UPDATE {local_costcenter} SET multipleorg = ? WHERE id = ?", [$costcenter->id, $costcenter->id]);
                $DB->execute("UPDATE {local_costcenter} SET category= ? WHERE id = ? ", [$category->id, $costcenter->id]);
            }

            if ($costcenter->costcenter_logo > 0 && $costcenter->id) {


                $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context($datarecord->path);

                file_save_draft_area_files($costcenter->costcenter_logo, $categorycontext->id, 'local_costcenter', 'costcenter_logo', $costcenter->costcenter_logo);

            }
            $costcenter_depth = $DB->get_field('local_costcenter', 'depth', array('id'=>$costcenter->id));
            if($costcenter_depth==1){
            blocks_add_default_org_blocks($costcenter->id);
            }
        }
       return $costcenter->id;


     
    }
/**
 * Description: [edit costcenter instance ]
 * @param  [INT] $costcenterid  [id of the costcenter]
 * @param  [object] $newcostcenter [update content]
 * @return [BOOLEAN]                [true if updated ]
 */
function costcenter_edit_instance($costcenterid, $newcostcenter){
    global $DB,$CFG;

    $oldcostcenter = $DB->get_record('local_costcenter', array('id' => $costcenterid));

    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context($oldcostcenter->path);

    $category = $DB->get_field('local_costcenter','category',array('id' => $newcostcenter->id));
    /* ---check if the parentid is the same as that of new parentid--- */
    if ($newcostcenter->parentid != $oldcostcenter->parentid) {
        $newparentid = $newcostcenter->parentid;
        $newcostcenter->parentid = $oldcostcenter->parentid;
    }
    // $today = time();
    // $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);
    $today = strtotime(date('d/m/Y', time()));
    $newcostcenter->timemodified = $today;
    $newcostcenter->costcenter_logo = $newcostcenter->costcenter_logo;
 if($newcostcenter->parentid ==0)
      file_save_draft_area_files($newcostcenter->costcenter_logo, $categorycontext->id, 'local_costcenter', 'costcenter_logo', $newcostcenter->costcenter_logo);


    $costercenter = $DB->update_record('local_costcenter', $newcostcenter);
    $course_categories=$DB->record_exists('course_categories',array('id'=>$category));
    if($costercenter && $course_categories){
        $record = new stdClass();
        $record->id = $category;
        $record->name = $newcostcenter->fullname;
        $record->idnumber = $newcostcenter->shortname;
        $DB->update_record('course_categories', $record);
    }
    return true;

}
/**
 * [costcenter_items description]
 * @return [type] [description]
 */
function costcenter_items(){

    global $DB, $USER;

    $assigned_costcenters = '';

    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();

    if (is_siteadmin()) {

        $sql="SELECT * from {local_costcenter} where visible=1 AND depth <5 ORDER by sortorder,fullname ";
        $assigned_costcenters = $DB->get_records_sql($sql);

    } else {

         $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

         $sql = "SELECT * FROM {local_costcenter} WHERE visible=1 $costcenterpathconcatsql   ORDER by sortorder,fullname";

        $assigned_costcenters = $DB->get_records_sql($sql);
    }
    return $assigned_costcenters;
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_costcenter_leftmenunode(){
    global $USER,$DB;
    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    $costcenternode = '';
    if(has_capability('local/costcenter:view', $categorycontext) || is_siteadmin()) {
    $costcenternode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_departments', 'class'=>'pull-left user_nav_div departments'));
    if(is_siteadmin()) {
            $organization_url = new moodle_url('/local/costcenter/index.php');
            $organization_string = get_string('orgStructure','local_costcenter');
        }
    else{
            $depth=($categorycontext->depth-1);

            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path');

            $costcentersql = "SELECT lc.id
                                FROM {local_costcenter} AS lc WHERE lc.depth=$depth $costcenterpathconcatsql ";
            $costcenterid = $DB->get_field_sql($costcentersql);
            $organization_url = new moodle_url('/local/costcenter/costcenterview.php',array('id' => $costcenterid));
            $organization_string = get_string('orgStructure','local_costcenter');
        }
        $department = html_writer::link($organization_url, '<i class="fa fa-sitemap" aria-hidden="true" aria-label=""></i><span class="user_navigation_link_text">'.$organization_string.'</span>',array('class'=>'user_navigation_link'));
        $costcenternode .= $department;
        $costcenternode .= html_writer::end_tag('li');
    }

    return array('2' => $costcenternode);
}

/*
* Author sarath
* @return  plugins count with all modules
*/
function local_costcenter_plugins_count($costcenterid, $departmentid=false, $subdepartmentid=false, $l4departmentid=false, $l5departmentid=false){
    global $CFG;
    $core_component = new core_component();
    $local_pluginlist = $core_component::get_plugin_list('local');
    $deparray = array();
    $deparray['datacount']=0;
    foreach($local_pluginlist as $key => $local_pluginname){
        if(file_exists($CFG->dirroot.'/local/'.$key.'/lib.php')){
            require_once($CFG->dirroot.'/local/'.$key.'/lib.php');
            $functionname = 'costcenterwise_'.$key.'_count';
            if(function_exists($functionname)){
                // if($subdepartmentid){
                    // if($key === 'users')
                $data = $functionname($costcenterid,$departmentid, $subdepartmentid, $l4departmentid, $l5departmentid);
                // }else{
                //     $data = $functionname($costcenterid,$departmentid, $subdepartmentid);
                // }
                foreach($data as  $key => $val){
                    $deparray[$key] = $val;

                    if (gettype($val) != 'string' && preg_match("/count/", $key)){

                       $deparray['datacount']=$deparray['datacount']+$val;


                    }
                }
            }
        }
    }
    return $deparray;
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_costcenter_list(){
    return 'Costcenter';
}


function local_costcenter_output_fragment_departmentview($args){
   
    global $CFG,$DB;
    $args = (object) $args;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {

        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }

    $mform = new local_costcenter\functions\costcenter(null, array(), 'post', '', null, true, $formdata);
 
    if (!empty($args->jsonformdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
 
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}


function local_costcenter_output_fragment_roleusers_display($args)
{

    global $DB, $CFG, $PAGE, $OUTPUT, $USER;

         $rolestring = $args['roleid'];

      $templatedata =  array();
      $rowdata = array();
   
  $query1 = $DB->get_records_sql("SELECT ro.shortname,us.firstname,us.email,cat.name FROM {role_assignments} as ra JOIN {context} as cc ON cc.id=ra.contextid JOIN {user} as us ON us.id=ra.userid join {role} as ro on ro.id=ra.roleid JOIN {course_categories} as cat on cat.id= cc.instanceid WHERE ro.id IN ($rolestring)");
 
 $templatedata['enabletable'] = true;
          foreach($query1 as $a)
          { 

             $rowdata['fullname'] = $a->firstname;
             $rowdata['employeeid'] = $a->shortname;
             $rowdata['email'] =  $a->email;
             $rowdata['orgname'] = $a->name;
             $templatedata['rowdata'][] = $rowdata;
          }
 
 
    $output = $OUTPUT->render_from_template('local_costcenter/popupcontent', $templatedata);

    return $output;
}
/*
* Author sachin
* @default blocks for created organization
*/
function blocks_add_default_org_blocks($costcenterid) {
    global $DB;
    $page = new moodle_page();
    $sql = "SELECT cc.path FROM {local_costcenter} AS cc WHERE cc.id=:organisationid ";
    $costcenterpath = $DB->get_field_sql($sql,array('organisationid'=>$costcenterid));
    $page->set_context((new \local_costcenter\lib\accesslib())::get_module_context($costcenterpath));
        $subpagepattern = null;
    $page->blocks->add_blocks([
        'layerone_full' => [
            'userdashboard',
            'quick_navigation',
        ]],
        'my-dashboard',
        $subpagepattern
    );
}
function local_costcenter_get_hierarchy_fields($mform, $ajaxformdata, $customdata, $elements = null,$allenable = false, $pluginname='local_costcenter',$context= CONTEXT_SYSTEM, $multiple = false, $prefix = ''){
    global $DB, $USER;

    $depth = (isset($USER->useraccess)) ? $USER->useraccess['currentroleinfo']['depth'] : 0;
    $contextinfo = (isset($USER->useraccess)) ? $USER->useraccess['currentroleinfo']['contextinfo'] : array() ;
    $count = $contextinfo ? count($contextinfo) : 0;
    if($count > 1){
        $depth--;
    }
    if(is_siteadmin()){
        $depth = 0;
    }
    $total_fields = 4;
    $fields = local_costcenter_get_fields();
    $prev_element = '';
    if(empty($elements) || !is_array($elements)){
        $elements = range(1, $total_fields);
    }
    $firstelement = true;
    foreach($elements as $level){
        $levelelementoptions = array(
            'class' => $prefix.$fields[$level].'_select custom_form_field',
            'id' => 'id_'.$prefix.$fields[$level].'_select',
            'data-parentclass' => $prev_element,
            'data-selectstring' => get_string('select'.$fields[$level], 'local_costcenter'),
            'placeholder' => get_string('select'.$fields[$level], 'local_costcenter'),
            'data-depth' => $level,
            'data-class' => $prefix.$fields[$level].'_select',
            'onchange' => '(function(e){ require("local_costcenter/newcostcenter").changeElement(event) })(event)',
        );
        $prev_element = $prefix.$fields[$level].'_select';
        $fieldvalue = (!empty($ajaxformdata) && $ajaxformdata[$prefix.$fields[$level]]) ? $ajaxformdata[$prefix.$fields[$level]]  ?? null : $customdata[$prefix.$fields[$level]]  ?? null;
        if($depth > $level){
            $mform->addElement('hidden', $prefix.$fields[$level], null, $levelelementoptions);
            $mform->setConstant($prefix.$fields[$level], $fieldvalue);
        }else{
            $enableallfield = (is_siteadmin() && $level == 1) || (!is_siteadmin() && ($USER->useraccess['currentroleinfo']['depth'] > $level))  ? false : $allenable;
            $levelelementoptions['multiple'] = ($firstelement && $prefix == '') ? false : $multiple;
            $levelelementoptions['ajax'] = 'local_costcenter/form-options-selector';
            $levelelementoptions['data-contextid'] = $context->id;
            $levelelementoptions['data-action'] = 'costcenter_element_selector';
            $prevfield = $prefix.$fields[$level-1];
            $parentid = (!empty($ajaxformdata) && $ajaxformdata[$prevfield]) ? $ajaxformdata[$prevfield] ?? null : $customdata[$prevfield]  ?? null;
            $levelelementoptions['data-options'] = json_encode(array('depth' => $level, 'parentid' => $parentid, 'enableallfield' => $enableallfield, 'prefix' => $prefix));
            if($enableallfield){
                $levelelements = [0 => get_string('all')];
            }else{
                $levelelements = [];
            }
            if($fieldvalue){
                $levelelementids = is_array($fieldvalue) ? $fieldvalue : explode(',', $fieldvalue);
                $levelelementids = array_filter($levelelementids);
                $levelelements = [];
                if($levelelementids){
                    list($idsql, $idparams) = $DB->get_in_or_equal($levelelementids, SQL_PARAMS_NAMED, 'levelelements');
                    $levelsql = "SELECT id, fullname FROM {local_costcenter} WHERE id {$idsql} ";
                    $levelelements += $DB->get_records_sql_menu($levelsql, $idparams);
                }
            }
            $mform->addElement('autocomplete', $prefix.$fields[$level], get_string($fields[$level], 'local_costcenter'), $levelelements, $levelelementoptions);
            $mform->addHelpButton($prefix.$fields[$level], $fields[$level].$pluginname, $pluginname);
            if($level == 1 && $prefix != 'filter'){
                $mform->addRule($prefix.$fields[$level], get_string('required'.$fields[$level], 'local_costcenter'),  'required',  '', 'client');
            }

            $firstelement = false;
        }
        $mform->setType($prefix.$fields[$level], PARAM_RAW);
    }
}
function local_costcenter_get_costcenter_path(&$data){
    global $DB, $USER;
    $fields = local_costcenter_get_fields();
    $path = '';
    foreach($fields AS $field){
        if(isset($data->$field) && $data->$field > 0){
            $value = $data->$field;
            // $path .= '/'.$value;
        }
    }
    if($value > 0){
        // finding the path mapped for the last element in the form to meet the data requirements for all head roles.
        $path = $DB->get_field('local_costcenter', 'path', array('id' => $value));
        // updating the user path if the user belongs to the chlid path for the selected costcenter path.
        if($USER->useraccess['currentroleinfo']['contextinfo']){
            $updatepath = true;
            foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){
                if(strpos($path, $contextinfo['costcenterpath']) === 0){
                    $updatepath = false;
                    break;
                }
                $rolepath = $contextinfo['costcenterpath'];
            }
            if($updatepath){
                $path = $rolepath;
            }
        }
        $data->open_path = $path;
    }
}
function local_costcenter_set_costcenter_path(&$data, $prefix = ''){
    global $USER;
    $fields = local_costcenter_get_fields();
    $contextinfo = (isset($USER->useraccess)) ? $USER->useraccess['currentroleinfo']['contextinfo'] : array();
    $pathnottracked = true;
    if($contextinfo){
        foreach($contextinfo AS $contextdata){
            if(isset($data['open_path']) && (strpos($data['open_path'], $contextdata['costcenterpath']) === 0)){
                $pathnottracked = false;
                $recordedpathids = explode('/', $data['open_path']);
                foreach($fields as $levelid => $field){
                    if(isset($recordedpathids[$levelid]) && $recordedpathids[$levelid] > 0){
                        $data[$prefix.$field] = $recordedpathids[$levelid];
                    }
                }
                break;
            }
        }
    }else if(isset($data['open_path'])){
        $pathnottracked = false;
        $recordedpathids = explode('/', $data['open_path']);
        foreach($fields as $levelid => $field){
            if(isset($recordedpathids[$levelid]) && $recordedpathids[$levelid] > 0){
                $data[$prefix.$field] = $recordedpathids[$levelid];
            }
        }
    }
    if($pathnottracked && $contextinfo){
        $rolecontext = \local_costcenter\lib\accesslib::get_costcenterpath_context($contextinfo[0]['context']);
        $rolecontextids = explode('/',$rolecontext);
        if(count($contextinfo) > 1){
            $depth = $USER->useraccess['currentroleinfo']['depth'];
        }else{
            $depth = $USER->useraccess['currentroleinfo']['depth'] - 1;
        }
        for($i = 1; $i <= $depth; $i++){
            $data[$prefix.$fields[$i]] = $rolecontextids[$i];
        }
    }
}
function local_costcenter_get_fields(){
    $level = HIERARCHY_LEVELS;
    $fields = [ 1 => 'open_costcenterid', 2 => 'open_department', 3 => 'open_subdepartment', 4 => 'open_level4department', 5 => 'open_level5department'];
    for($i=1; $i<= $level; $i++){
        if(isset($fields[$i])){
            $return[$i] = $fields[$i];
        }else{
            // Never occuring but for test case verification.
            $return[$i] = 'open_level'.$i.'department';
        }
    }
    return $return;
}

function local_costcenter_masterinfo(){
    global $CFG, $PAGE, $OUTPUT, $DB;
    $costcenterid = explode('/',$USER->open_path)[1];
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $content = '';
    if (is_siteadmin()){
        $organisation = "SELECT count(id) FROM {local_costcenter} WHERE parentid = 0";
        $totalorgnisation = $DB->count_records_sql($organisation);

        if($totalorgnisation > 0) {
            $org = '('.$totalorgnisation.')';
        }

        $templatedata = array();
        $templatedata['show'] = true;
        $templatedata['count'] = $org;
        $templatedata['link'] = $CFG->wwwroot.'/local/costcenter/index.php';
        $templatedata['stringname'] = get_string('originator','block_masterinfo');
        $templatedata['icon'] = '<i class="fa fa-sitemap" aria-hidden="true" aria-label=""></i>';

        $content = $OUTPUT->render_from_template('block_masterinfo/masterinfo', $templatedata);
    }
    return array('1' => $content);
}
function local_costcenter_organization_hierarchy_fields($mform, $ajaxformdata, $customdata, $elements = null,$allenable = false, $pluginname='local_costcenter',$context=CONTEXT_SYSTEM, $multiple = false, $prefix = '',$editmode=0){
    global $DB, $USER;
    $depth = $USER->useraccess['currentroleinfo']['depth'];
    $contextinfo = $USER->useraccess['currentroleinfo']['contextinfo'];

    if($contextinfo){

        if(count($contextinfo) > 1){
            $depth--;
        }
    }

    if(is_siteadmin()){
        $depth = 0;
    }
    $total_fields = 4;
    $fields = local_costcenter_get_fields();
    $prev_element = '';
    if(empty($elements) || !is_array($elements)){
        $elements = range(1, $total_fields);
    }
    $firstelement = true;

    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();


    foreach($elements as $level){
        $levelelementoptions = array(
            'class' => $prefix.$fields[$level].'_select custom_form_field',
            'id' => 'id_'.$prefix.$fields[$level].'_select',
            'data-parentclass' => $prev_element,
            'data-selectstring' => get_string('select'.$fields[$level], 'local_costcenter'),
            'placeholder' => get_string('select'.$fields[$level], 'local_costcenter'),
            'data-depth' => $level,
            'data-class' => $prefix.$fields[$level].'_select',
            'data-contextid' => $categorycontext->id,
            'onchange' => '(function(e){ require("local_costcenter/newcostcenter").changeElement(event) })(event)',
        );
        $prev_element = $prefix.$fields[$level].'_select';
        $fieldvalue = $ajaxformdata[$prefix.$fields[$level]] ? $ajaxformdata[$prefix.$fields[$level]] : $customdata[$prefix.$fields[$level]];
        if($depth > $level){
            $mform->addElement('hidden', $prefix.$fields[$level], null, $levelelementoptions);
            $mform->setConstant($prefix.$fields[$level], $fieldvalue);
        }else{
            $enableallfield = ($USER->useraccess['currentroleinfo']['depth'] > $level) || (is_siteadmin()) ? false : $allenable;
            $levelelementoptions['multiple'] = ($firstelement && $prefix == '') ? false : $multiple;
            $levelelementoptions['ajax'] = 'local_costcenter/form-options-selector';
            $levelelementoptions['data-contextid'] = $context->id;
            $levelelementoptions['data-action'] = 'costcenter_element_selector';
            $prevfield = $prefix.$fields[$level-1];
            $parentid = $ajaxformdata[$prevfield] ? $ajaxformdata[$prevfield] : $customdata[$prevfield];
            $levelelementoptions['data-options'] = json_encode(array('depth' => $level, 'parentid' => $parentid, 'enableallfield' => $enableallfield, 'prefix' => $prefix));
            if($enableallfield){
                $levelelements = [0 => get_string('all')];
            }else{
                $levelelements = [];
            }
            if($fieldvalue){
                $levelelementids = is_array($fieldvalue) ? $fieldvalue : explode(',', $fieldvalue);
                $levelelementids = array_filter($levelelementids);
                $levelelements = [];
                if($levelelementids){
                    list($idsql, $idparams) = $DB->get_in_or_equal($levelelementids, SQL_PARAMS_NAMED, 'levelelements');
                    $levelsql = "SELECT id, fullname FROM {local_costcenter} WHERE id {$idsql} ";
                    $levelelements += $DB->get_records_sql_menu($levelsql, $idparams);
                }
            }

            if($editmode > 0){

                $mform->addElement('static', 'static'.$prefix.$fields[$level], get_string($fields[$level], 'local_costcenter'));

                $mform->setConstant('static'.$prefix.$fields[$level], $levelelements[$fieldvalue]);

                $mform->addElement('hidden', $prefix.$fields[$level], null, $levelelementoptions);
                $mform->setConstant($prefix.$fields[$level], $fieldvalue);

            }else{

                $mform->addElement('autocomplete', $prefix.$fields[$level], get_string($fields[$level], 'local_costcenter'), $levelelements, $levelelementoptions);
                $mform->addHelpButton($prefix.$fields[$level], $fields[$level].$pluginname, $pluginname);
                $mform->addRule($prefix.$fields[$level], get_string('required'.$fields[$level], 'local_costcenter'),  'required',  '', 'client');
            }

            $firstelement = false;
        }
        $mform->setType($prefix.$fields[$level], PARAM_RAW);
    }
}
