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
define('elearning', 1);
define('courses', 1);

if(file_exists($CFG->dirroot.'/local/costcenter/lib.php')){
    require_once($CFG->dirroot.'/local/costcenter/lib.php');                  
}
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/completion/completion_completion.php');
use \local_courses\form\custom_course_form as custom_course_form;
use \local_courses\form\custom_courseevidence_form as custom_courseevidence_form;


defined('MOODLE_INTERNAL') || die();

/**
 * process the mass enrolment
 * @param csv_import_reader $cir  an import reader created by caller
 * @param Object $course  a course record from table mdl_course
 * @param Object $context  course context instance
 * @param Object $data    data from a moodleform
 * @return string  log of operations
 */
function mass_enroll($cir, $course, $context, $data) {
    global $CFG, $DB, $USER;
    require_once ($CFG->dirroot . '/group/lib.php');

    $result = '';

    $courseid=$course->id;
    $roleid = $data->roleassign;
    $useridfield = $data->firstcolumn;

    $enrollablecount = 0;
    $createdgroupscount = 0;
    $createdgroupingscount = 0;
    $createdgroups = '';
    $createdgroupings = '';


    $plugin = enrol_get_plugin('manual');
    //Moodle 2.x enrolment and role assignment are different
    // make sure couse DO have a manual enrolment plugin instance in that course
    //that we are going to use (only one instance is allowed @see enrol/manual/lib.php get_new_instance)
    // thus call to get_record is safe
    $instance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'));
    if (empty($instance)) {
        // Only add an enrol instance to the course if non-existent
        $enrolid = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', array('id' => $enrolid));
    }


    // init csv import helper
    $notification = new \local_courses\notification();
    $type = 'course_enrol';
    $notificationdata = $notification->get_existing_notification($course, $type);
    
    $cir->init();

    while ($fields = $cir->next()) {
        $a = new stdClass();
        if (empty ($fields))
        continue;
        $coscenter=$DB->get_field('course','open_path',array('id'=>$course->id));
        $coscenter_name=$DB->get_field('local_costcenter','shortname',array('id'=>$coscenter));

        $string=strtolower($coscenter_name);

        // 1st column = id Moodle (idnumber,username or email)
        // get rid on eventual double quotes unfortunately not done by Moodle CSV importer
        /*****Checking with all costcenters*****/

        $fields[0]= str_replace('"', '', trim($fields[0]));
        $fieldcontcat=$string.$fields[0];
        /******The below code is for the AH checking condtion if AH any user can be enrolled else if OH only his costcenter users enrol*****/
        // $id=$DB->get_field('course','open_path',array('id'=>$course->id));
		$categorycontext = (new \local_courses\lib\accesslib())::get_module_context($course->id);

        // if(!is_siteadmin()  && has_capability('local/costcenter:assign_multiple_departments_manage', $categorycontext)){
        //     $sql=" ";
        // }else{
        //     $sql=" and u.open_path=$id ";
        // }

        /*First Condition To validate users*/
        $sql="SELECT u.* from {user} u where u.deleted=0 and u.suspended=0 and u.$useridfield='$fields[0]' ";
        //$sql
        if(!(is_siteadmin())){

            $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path',$costcenterpath = null, $datatype = 'lowerandsamepath');
        }
        $sql .= " and u.id <> {$USER->id} ";
        if (!$user = $DB->get_record_sql($sql)) {
            $result .= '<div class="alert alert-error">'.get_string('im:user_unknown', 'local_courses', $fields[0] ). '</div>';
            continue;
        }

        $id=$DB->get_field('course','open_path',array('id'=>$course->id));
        /** The below code is for the AH checking condtion if AH any user can be enrolled else if OH only his costcenter users enrol **/
        if(!is_siteadmin()  && has_capability('local/costcenter:assign_multiple_departments_manage', $categorycontext)){

            $pathlike = $id.'/%';
            $sql=" open_path like '{$pathlike}' AND ";
        }else{

            $sql=" ";
        }
        /*Second Condition To validate users*/
        if(!$DB->record_exists_sql("select id from {user} where $sql  id=$user->id")){

            $costcentername = $DB->get_field('local_costcenter','fullname',array('id'=>$course->costcenter));
            $cs_object = new stdClass();
            $cs_object->csname = $costcentername;
            $cs_object->user   = fullname($user);
            $result .= '<div class="alert alert-error">'.get_string('im:user_notcostcenter', 'local_courses',$cs_object ). '</div>';
            continue;
        }

        //already enroled ?

        $instance_auto = $DB->get_field('enrol', 'id',array('courseid' => $course->id, 'enrol' => 'auto'));
        $instance_self = $DB->get_field('enrol', 'id',array('courseid' => $course->id, 'enrol' => 'self'));

        if(!$instance_auto){
         $instance_auto=0;

        }
        if(!$instance_self){
            $instance_self=0;
        }

        $enrol_ids=$instance_auto.",".$instance_self.",".$instance->id;

        $sql="select id from {user_enrolments} where enrolid IN ($enrol_ids) and userid=$user->id";
        $enrolormnot=$DB->get_field_sql($sql);

        if (user_has_role_assignment($user->id, $roleid, $context->id)) {
            $result .= '<div class="alert alert-error">'.get_string('im:already_in', 'local_courses', fullname($user)). '</div>';

        } elseif($enrolormnot){
         $result .= '<div class="alert alert-error">'.get_string('im:already_in', 'local_courses', fullname($user)). '</div>';
         continue;
        }else {
            //TODO take care of timestart/timeend in course settings
            // done in rev 1.1
            $timestart=$DB->get_field('course','startdate',array('id'=>$course->id));
            $timeend=0;
            // not anymore so easy in Moodle 2.x
            // Enrol the user with this plugin instance (unfortunately return void, no more status )
            $plugin->enrol_user($instance, $user->id,$roleid,$timestart,$timeend);

            if($notificationdata){
                $notification->send_course_email($course, $user, $type, $notificationdata);
            }
            $result .= '<div class="alert alert-success">'.get_string('im:enrolled_ok', 'local_courses', fullname($user)).'</div>';
            $enrollablecount++;
        }

        $group = str_replace('"','',trim($fields[1]));
        // 2nd column ?
        if (empty ($group)) {
            $result .= "";
            continue; // no group for this one
        }

        // create group if needed
        if (!($gid = mass_enroll_group_exists($group, $courseid))) {
            if ($data->creategroups) {
                if (!($gid = mass_enroll_add_group($group, $courseid))) {
                    $a->group = $group;
                    $a->courseid = $courseid;
                    $result .= '<div class="alert alert-error">'.get_string('im:error_addg', 'local_courses', $a) . '</div>';
                    continue;
                }
                $createdgroupscount++;
                $createdgroups .= " $group";
            } else {
                $result .= '<div class="alert alert-error">'.get_string('im:error_g_unknown', 'local_courses', $group) . '</div>';
                continue;
            }
        }

        // if groupings are enabled on the site (should be ?)
        // if ($CFG->enablegroupings) { // not anymore in Moodle 2.x
        if (!($gpid = mass_enroll_grouping_exists($group, $courseid))) {
            if ($data->creategroupings) {
                if (!($gpid = mass_enroll_add_grouping($group, $courseid))) {
                    $a->group = $group;
                    $a->courseid = $courseid;
                    $result .= '<div class="alert alert-error">'.get_string('im:error_add_grp', 'local_courses', $a) . '</div>';
                    continue;
                }
                $createdgroupingscount++;
                $createdgroupings .= " $group";
            } else {
                // don't complains,
                // just do the enrolment to group
            }
        }
        // if grouping existed or has just been created
        if ($gpid && !(mass_enroll_group_in_grouping($gid, $gpid))) {
            if (!(mass_enroll_add_group_grouping($gid, $gpid))) {
                $a->group = $group;
                $result .= '<div class="alert alert-error">'.get_string('im:error_add_g_grp', 'local_courses', $a) . '</div>';
                continue;
            }
        }
        //}

        // finally add to group if needed
        if (!groups_is_member($gid, $user->id)) {
            $ok = groups_add_member($gid, $user->id);
            if ($ok) {
                $result .= '<div class="alert alert-success">'.get_string('im:and_added_g', 'local_courses', $group) . '</div>';
            } else {
                $result .= '<div class="alert alert-error">'.get_string('im:error_adding_u_g', 'local_courses', $group) . '</div>';
            }
        } else {
            $result .= '<div class="alert alert-notice">'.get_string('im:already_in_g', 'local_courses', $group) . '</div>';
        }

    }
    $result .= '<br />';
    //recap final
    $result .= get_string('im:stats_i', 'local_courses', $enrollablecount) . "";
    // $a->nb = $createdgroupscount;
    // if(!isset($createdgroups) || empty($createdgroups)||$createdgroups='')
    // $a->what = '-';
    // else
    // $a->what = $createdgroups;
    // $result .= get_string('im:stats_g', 'local_courses', $a) . "";
    // $a->nb = $createdgroupingscount;
    // if(!isset($createdgroupings) || empty($createdgroupings)||$createdgroupings='')
    // $a->what = '-';
    // else
    // $a->what = $createdgroupings;
    // $result .= get_string('im:stats_grp', 'local_courses', $a) . "";

    return $result;
}


/**
 * Enter description here ...
 * @param string $newgroupname
 * @param int $courseid
 * @return int id   Moodle id of inserted record
 */
function mass_enroll_add_group($newgroupname, $courseid) {
    $newgroup = new stdClass();
    $newgroup->name = $newgroupname;
    $newgroup->courseid = $courseid;
    $newgroup->lang = current_language();
    return groups_create_group($newgroup);
}


/**
 * Enter description here ...
 * @param string $newgroupingname
 * @param int $courseid
 * @return int id Moodle id of inserted record
 */
function mass_enroll_add_grouping($newgroupingname, $courseid) {
    $newgrouping = new StdClass();
    $newgrouping->name = $newgroupingname;
    $newgrouping->courseid = $courseid;
    return groups_create_grouping($newgrouping);
}

/**
 * @param string $name group name
 * @param int $courseid course
 * @return string or false
 */
function mass_enroll_group_exists($name, $courseid) {
    return groups_get_group_by_name($courseid, $name);
}

/**
 * @param string $name group name
 * @param int $courseid course
 * @return string or false
 */
function mass_enroll_grouping_exists($name, $courseid) {
    return groups_get_grouping_by_name($courseid, $name);

}

/**
 * @param int $gid group ID
 * @param int $gpid grouping ID
 * @return mixed a fieldset object containing the first matching record or false
 */
function mass_enroll_group_in_grouping($gid, $gpid) {
     global $DB;
    $sql ="SELECT * from {groupings_groups}
   where groupingid = ?
   and groupid = ?";
    $params = array($gpid, $gid);
    return $DB->get_record_sql($sql,$params,IGNORE_MISSING);
}

/**
 * @param int $gid group ID
 * @param int $gpid grouping ID
 * @return bool|int true or new id
 * @throws dml_exception A DML specific exception is thrown for any errors.
 */
function mass_enroll_add_group_grouping($gid, $gpid) {
     global $DB;
    $new = new stdClass();
    $new->groupid = $gid;
    $new->groupingid = $gpid;
    $new->timeadded = time();
    return $DB->insert_record('groupings_groups', $new);
}
/**
* todo displays the categories
* @param string $requiredcapability
* @param int $excludeid
* @param string $separator
* @param int $departmentcat
* @param int $orgcat
* @param array $args List of named arguments for the fragment loader.
* @return string
*/
function categorylist($requiredcapability = '', $excludeid = 0, $separator = ' / ',$departmentcat = 0,$orgcat=0) {
    global $DB, $USER;
    $coursecatcache = cache::make('core', 'coursecat');

    // Check if we cached the complete list of user-accessible category names ($baselist) or list of ids
    // with requried cap ($thislist).
    $currentlang = current_language();
    $basecachekey = $currentlang . '_catlist';
    $baselist = $coursecatcache->get($basecachekey);
    $thislist = false;
    $thiscachekey = null;
    if (!empty($requiredcapability)) {
        $requiredcapability = (array)$requiredcapability;
        $thiscachekey = 'catlist:'. serialize($requiredcapability);
        if ($baselist !== false && ($thislist = $coursecatcache->get($thiscachekey)) !== false) {
            $thislist = preg_split('|,|', $thislist, -1, PREG_SPLIT_NO_EMPTY);
        }
    } else if ($baselist !== false) {
        $thislist = array_keys($baselist);
    }

    if ($baselist === false) {
        // We don't have $baselist cached, retrieve it. Retrieve $thislist again in any case.
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent, cc.path, $ctxselect
                FROM {course_categories} cc
                JOIN {context} ctx ON cc.id = ctx.instanceid AND ctx.contextlevel = :contextcoursecat AND cc.visible = :value
                WHERE cc.depth<=2
                ORDER BY cc.sortorder";
        $rs = $DB->get_recordset_sql($sql, array('contextcoursecat' => CONTEXT_COURSECAT,'value' => 1));
        $baselist = array();
        $thislist = array();
        foreach ($rs as $record) {
            // If the category's parent is not visible to the user, it is not visible as well.
            if (!$record->parent || isset($baselist[$record->parent])) {
                context_helper::preload_from_record($record);
                $context = context_coursecat::instance($record->id);
                if (!$record->visible && !has_capability('moodle/category:viewhiddencategories', $context)) {
                    // No cap to view category, added to neither $baselist nor $thislist.
                    continue;
                }
                $baselist[$record->id] = array(
                    'name' => format_string($record->name, true, array('context' => $context)),
                    'path' => $record->path,
                );
                if (!empty($requiredcapability) && !has_all_capabilities($requiredcapability, $context)) {
                    // No required capability, added to $baselist but not to $thislist.
                    continue;
                }
                $thislist[] = $record->id;
            }
        }
        $rs->close();
        $coursecatcache->set($basecachekey, $baselist);
        if (!empty($requiredcapability)) {
            $coursecatcache->set($thiscachekey, join(',', $thislist));
        }
    } else if ($thislist === false) {
        // We have $baselist cached but not $thislist. Simplier query is used to retrieve.
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $sql = "SELECT ctx.instanceid AS id, $ctxselect
                FROM {context} ctx WHERE ctx.contextlevel = :contextcoursecat ";
        $contexts = $DB->get_records_sql($sql, array('contextcoursecat' => CONTEXT_COURSECAT));
        $thislist = array();
        foreach (array_keys($baselist) as $id) {
            context_helper::preload_from_record($contexts[$id]);
            if (has_all_capabilities($requiredcapability, context_coursecat::instance($id))) {
                $thislist[] = $id;
            }
        }
        $coursecatcache->set($thiscachekey, join(',', $thislist));
    }

    // Now build the array of strings to return, mind $separator and $excludeid.
    $names = array();
    foreach ($thislist as $id) {

        $path = preg_split('|/|', $baselist[$id]['path'], -1, PREG_SPLIT_NO_EMPTY);
        if($departmentcat){
            if($path[1] == $departmentcat){
                if (!$excludeid || !in_array($excludeid, $path)) {
                    $namechunks = array();
                    foreach ($path as $parentid) {
                        $namechunks[] = $baselist[$parentid]['name'];
                    }
                    $names[$id] = join($separator, $namechunks);
                }
            }
        }else if($orgcat){
            if($path[0] == $orgcat){
                if (!$excludeid || !in_array($excludeid, $path)) {
                    $namechunks = array();
                    foreach ($path as $parentid) {
                        $namechunks[] = $baselist[$parentid]['name'];
                    }
                    $names[$id] = join($separator, $namechunks);
                }
            }
        }
        else{
                if (!$excludeid || !in_array($excludeid, $path)) {
                    $namechunks = array();
                    foreach ($path as $parentid) {
                        $namechunks[] = $baselist[$parentid]['name'];
                    }
                    $names[$id] = join($separator, $namechunks);
                }
        }
    }
    return $names;
}

/**
 * Serve the new course form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_custom_course_form($args){
    global $DB,$CFG,$PAGE;
    $args = (object) $args;
    $context = $args->context;
    $renderer = $PAGE->get_renderer('local_courses');
    $courseid = $args->courseid;
    $o = '';
    if ($courseid) {
        $course = get_course($courseid);
        $course = course_get_format($course)->get_course();
        $category = $DB->get_record('course_categories', array('id'=>$course->category), '*', MUST_EXIST);
        $coursecontext = context_course::instance($course->id);
        //require_capability('moodle/course:update', $coursecontext);
    }else{
        $category = $CFG->defaultrequestcategory;
    }
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    if(!empty($course) && empty($formdata)){
        $formdata = clone $course;
        $formdata = (array)$formdata;

    }

    if ($courseid > 0) {
        $heading = get_string('updatecourse', 'local_courses');
        $collapse = false;
        $data = $DB->get_record('course', array('id'=>$courseid));
    }
    // Populate course tags.
    $course->tags = local_tags_tag::get_item_tags_array('local_courses', 'courses', $course->id);
    $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes'=>$CFG->maxbytes, 'trusttext'=>false, 'noclean'=>true,'autosave'=>false);
    $overviewfilesoptions = course_overviewfiles_options($course);
    if ($courseid) {
        // Add context for editor.
        $editoroptions['context'] = $coursecontext;
        $editoroptions['subdirs'] = file_area_contains_subdirs($coursecontext, 'course', 'summary', 0);
        $course = file_prepare_standard_editor($course, 'summary', $editoroptions, $coursecontext, 'course', 'summary', 0);
        if ($overviewfilesoptions) {
            file_prepare_standard_filemanager($course, 'overviewfiles', $overviewfilesoptions, $coursecontext, 'course', 'overviewfiles', 0);
        }
        $get_coursedetails=$DB->get_record('course',array('id'=>$course->id));
    } else {
        // Editor should respect category context if course context is not set.
        $editoroptions['context'] = $catcontext;
        $editoroptions['subdirs'] = 0;
        $course = file_prepare_standard_editor($course, 'summary', $editoroptions, null, 'course', 'summary', null);
        if ($overviewfilesoptions) {
            file_prepare_standard_filemanager($course, 'overviewfiles', $overviewfilesoptions, null, 'course', 'overviewfiles', 0);
        }
    }
    if($formdata['open_points'] > 0){
        $formdata['open_enablepoints'] = true;
    }

    $params = array(
        'course' => $course,
        'category' => $category,
        'editoroptions' => $editoroptions,
        'returnto' => $returnto,
        'get_coursedetails'=>$get_coursedetails,
        'form_status' => $args->form_status,
        'costcenterid' => $data->open_path,
    );
    local_costcenter_set_costcenter_path($formdata);
    $mform = new custom_course_form(null, $params, 'post', '', null, true, $formdata);
    // Used to set the courseid.

    $mform->set_data($formdata);

    if (!empty($args->jsonformdata) && strlen($args->jsonformdata)>2) {
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
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass);
    }
    $formstatusview = new \local_courses\output\form_status($formstatus);
    $o .= $renderer->render($formstatusview);
    // $o = $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'getCatlist');
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

/**
 * Serve the delete category form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_deletecategory_form($args){
 global $DB,$CFG,$PAGE;
   // require_once($CFG->libdir.'/coursecatlib.php');
    require_once($CFG->libdir . '/questionlib.php');

    $args = (object) $args;
    $context = $args->context;
    $categoryid = $args->categoryid;
    $o = '';

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    if ($categoryid) {
        $category = core_course_category::get($categoryid);
        $context = context_coursecat::instance($category->id);
    }else {
        $category = core_course_category::get_default();
        $categoryid = $category->id;
        $context = context_coursecat::instance($category->id);
    }

    $mform = new local_courses\form\deletecategory_form(null, $category, 'post', '', null, true, $formdata);
    // Used to set the courseid.

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

/**
 * Serve the new course category form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_coursecategory_form($args){
 global $DB,$CFG,$PAGE;
   // require_once($CFG->libdir.'/coursecatlib.php');

    $args = (object) $args;
    $context = $args->context;
    $categoryid = $args->categoryid;

    $o = '';

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    if (empty($formdata) && $categoryid > 0) {
        // $heading = get_string('updatecourse', 'local_courses');
        // $collapse = false;
        $data = $DB->get_record('course_categories', array('id'=>$categoryid));
        $formdata = new stdClass();
        $formdata->id = $data->id;
        $formdata->parent = $data->parent;
        $formdata->name = $data->name;
        $formdata->idnumber = $data->idnumber;
        $formdata->description_editor['text'] = $data->description;
    }

    if($categoryid){
        $coursecat = core_course_category::get($categoryid, MUST_EXIST, true);
        $category = $coursecat->get_db_record();
        $context = context_coursecat::instance($categoryid);

         $itemid = 0;
    }else{
        $parent = optional_param('parent', 0, PARAM_INT);

        if ($parent) {
            $DB->record_exists('course_categories', array('id' => $parent), '*', MUST_EXIST);
            $context = context_coursecat::instance($parent);
        } else {
            $context = context_system::instance();
        }
        $category = new stdClass();
        $category->id = 0;
        $category->parent = $parent;
    }

    // if ($categoryid > 0) {
    //     // $heading = get_string('updatecourse', 'local_courses');
    //     // $collapse = false;
    //     $data = $DB->get_record('course_categories', array('id'=>$categoryid));
    //     $formdata = new stdClass();
    //     $formdata->id = $data->id;
    //     $formdata->parent = $data->parent;
    //     $formdata->name = $data->name;
    //     $formdata->idnumber = $data->idnumber;
    //     $formdata->description_editor['text'] = $data->description;
    // }

    $params = array(
    'categoryid' => $categoryid,
    'parent' => $category->parent,
    'context' => $context,
    'itemid' => $itemid
    );

    $mform = new local_courses\form\coursecategory_form(null, $params, 'post', '', null, true, $formdata);
    // Used to set the courseid.
    $mform->set_data($formdata);

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
/**
 * Serve the table for course categories
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string 
 */
function local_courses_output_fragment_coursecategory_display($args){
    global $DB,$CFG,$PAGE,$OUTPUT;

    $args = (object) $args;
    $context = $args->context;
    $categoryid = $args->categoryid;

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    $table = new html_table();
    $table->id = 'popup_category';
    $table->align = ['left','center','center','center','center'];
    $table->head = array(get_string('course_name', 'local_courses'),get_string('enrolledusers', 'local_courses'),get_string('completed_users', 'local_courses'),get_string('type', 'local_courses'),get_string('actions', 'local_courses'));
    $courses = $DB->get_records_sql("SELECT c.id,c.category,c.fullname FROM {course} c WHERE c.id > 1
                                     AND c.category = ?", [$categoryid]);
    if($courses){
    $data=array();
    foreach($courses as $course){
        $row = array();
        $row[] = html_writer::link(new moodle_url('/course/view.php',array('id'=>$course->id)),$course->fullname);
        $course_sql = "SELECT count(ue.userid) as enrolled,count(cc.course) as completed
                            FROM {user_enrolments} as ue
                            JOIN {enrol} as e ON e.id=ue.enrolid
                            JOIN {user} as u ON u.id = ue.userid
                            RIGHT JOIN {course} as c ON c.id =e.courseid
                            LEFT JOIN {course_completions} cc ON cc.course=e.courseid and ue.userid=cc.userid and cc.timecompleted IS NOT NULL
                            WHERE c.id = ? AND u.deleted = 0 AND u.suspended = 0 
                                group by e.courseid";
        $course_stats = $DB->get_record_sql($course_sql, [$course->id]);
       if($course_stats->enrolled){
            $row[] = $course_stats->enrolled;
        }else{
             $row[] = "N/A";
        }
        if($course_stats->completed){
            $row[] = $course_stats->completed;
        }else{
             $row[] = "N/A";
        }
        $ilt_sql = "SELECT open_identifiedas from {course}  WHERE id = ? " ;  
        $ilt_stats = $DB->get_record_sql($ilt_sql, [$course->id]);
        $types = explode(',',$ilt_stats->open_identifiedas);
        $classtype = array();
        foreach($types as $type){

            if($type == 2){
              $classtype[0]= get_string('classroom','local_courses');
            }
            if($type == 3){
             $classtype[2]= get_string('elearning','local_courses');
            }
            if($type == 4){
             $classtype[3]= get_string('learningplan','local_courses');
            }
            if($type == 5){
             $classtype[5]= get_string('program','local_courses');
            }
            if($type == 6){
             $classtype[6]= get_string('certification','local_courses');
            }
        }
        $ctype = implode(',',$classtype);

        if($ctype){

            $row[] = $ctype;
        }else{
             $row[] = "N/A";
        }



        $enrolid = $DB->get_field('enrol','id', array('courseid'=>$course->id, 'enrol'=>'manual'));

        $enrolicon = html_writer::link(new moodle_url('/local/courses/courseenrol.php',array('id'=>$course->id,'enrolid' => $enrolid)),html_writer::tag('i','',array('class'=>'fa fa-user-plus icon text-muted', 'title' => get_string('enrol','local_courses'), 'alt' => get_string('enrol'))));
        $actions = $enrolicon.' '.$editicon;
        $row[] = $actions;

        $data[] = $row;
    }
    $table->data = $data;
    $output = html_writer::table($table);
    $output .= html_writer::script("$('#popup_category').DataTable({
        'language': {
            paginate: {
            'previous': '<',
            'next': '>'
            }
        },
        'bInfo' : false,
        lengthMenu: [
            [5, 10, 25, 50, 100, -1],
            [5, 10, 25, 50, 100, ".get_string('all')."]
        ]
    });");
    }else{
        $output = get_string('nocourseavailiable', 'local_courses');
    }

    return $output;
}

/**
 * Serve the table for course status
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_coursestatus_display($args){
    global $DB,$CFG,$PAGE,$OUTPUT,$USER;
    $args = (object) $args;
    $course = $DB->get_record('course', array('id' => $args->courseid));
    $info = new completion_info($course);
        
    // Is course complete?
    $coursecomplete = $info->is_course_complete($USER->id);

    // Has this user completed any criteria?
    $criteriacomplete = $info->count_course_user_data($USER->id);
    $params = array(
        'userid' => $USER->id,
        'course' => $course->id,
    );
    $completions = $info->get_completions($USER->id);
    $ccompletion = new completion_completion($params);

    $rows = array();
    // Loop through course criteria.
    foreach ($completions as $completion) {
        $criteria = $completion->get_criteria();
        $row = array();
            $row['type'] = $criteria->criteriatype;
            $row['title'] = $criteria->get_title();
            $row['complete'] = $completion->is_complete();
            $row['timecompleted'] = $completion->timecompleted;
            $row['details'] = $criteria->get_details($completion);
            $rows[] = $row;

        }
    // Print table.
    $last_type = '';
    $agg_type = false;
    // $oddeven = 0;

    $table = new html_table();
    $table->head = array(get_string('criteriagroup','format_tabtopics'),get_string('criteria','format_tabtopics'),get_string('requirement','format_tabtopics'),get_string('complete','format_tabtopics'),get_string('completiondate','format_tabtopics'));
    $table->size=array('20%','20%','25%','5%','30%');
    $table->align=array('left','left','left','center','center');
    $table->id = 'scrolltable';
    foreach ($rows as $row) {
        if ($last_type !== $row['details']['type']) {
        $last_type = $row['details']['type'];
        $agg_type = true;
        }else {
        // Display aggregation type.
            if ($agg_type) {
                $agg = $info->get_aggregation_method($row['type']);
                $last_type .= '('. html_writer::start_tag('i');
                if ($agg == COMPLETION_AGGREGATION_ALL) {
                    $last_type .= core_text::strtolower(get_string('all', 'completion'));
                } else {
                    $last_type .= core_text::strtolower(get_string('any', 'completion'));
                }
                $last_type .= html_writer::end_tag('i') .core_text::strtolower(get_string('required')).')';
                $agg_type = false;
            }
        }
        if ($row['timecompleted']) {
            $timecompleted=userdate($row['timecompleted'], get_string('strftimedate', 'langconfig'));
        } else {
            $timecompleted = '-';
        }
        $table->data[] = new html_table_row(array($last_type,$row['details']['criteria'],$row['details']['requirement'],$row['complete'] ? get_string('yes') : get_string('no'),$timecompleted));
    }
    $output = html_writer::table($table);
    $output .= html_writer::script("
         $(document).ready(function(){
            var table_rows = $('#scrolltable tr');
            // if(table_rows.length>6){
                $('#scrolltable').dataTable({
                    'searching': false,
                    'language': {
                        'paginate': {
                            'next': '>',
                            'previous': '<'
                        }
                    },
                    'pageLength': 5,
                });
            // }
        });
    ");
    return $output;
}

/*
* todo provides form element - courses
* @param $mform formobject
* return void
*/
function courses_filter($mform){
    global $DB,$USER;
	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $sql = "SELECT id, fullname FROM {course} WHERE id > 1 ";

    if(is_siteadmin()){
       $courseslist = $DB->get_records_sql_menu($sql);
    }else{
      $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    }
    $courseslist = $DB->get_records_sql_menu($sql);

    $select = $mform->addElement('autocomplete', 'courses', get_string('course'), $courseslist, array('placeholder' => get_string('course')));
    $mform->setType('courses', PARAM_RAW);
    $select->setMultiple(true);
}
function status_filter($mform){
    $statusarray = array('active' => get_string('active'), 'inactive' => get_string('inactive'));
    $select = $mform->addElement('autocomplete', 'status', get_string('user_status','local_users'), $statusarray, array('placeholder' => get_string('user_status','local_users')));
    $mform->setType('status', PARAM_RAW);
    $select->setMultiple(true);
} 
function coursetype_filter($mform){
    global $DB,$USER;
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $coursetypeql = "SELECT id, name FROM {local_course_types} ";
    $coursetypes =  $DB->get_records_sql_menu($coursetypeql, $coursetypeparams=null);
    $select = $mform->addElement('autocomplete', 'coursetype', get_string('type','local_courses'), $coursetypes);
    $mform->setType('coursetype',PARAM_RAW);
    $select->setMultiple(true);
}
/*
* todo provides form element - courses
* @param $mform formobject
* return void
*/
function elearning_filter($mform){
    global $DB,$USER;
	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
        $courseslist = $DB->get_records_sql_menu("SELECT id, fullname FROM {course} WHERE visible = 1");
    }
    $select = $mform->addElement('autocomplete', 'elearning', '', $courseslist, array('placeholder' => get_string('course_name', 'local_courses')));
    $mform->setType('elearning', PARAM_RAW);
    $select->setMultiple(true);
}

/*
* todo provides form element - categories
* @param $mform formobject
* return void
*/
function categories_filter($mform){
    global $DB,$USER;
	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $catslib = new local_courses\catslib();
    if(is_siteadmin()){
        $categorylist = $DB->get_records_sql_menu("SELECT id, fullname FROM {local_custom_category} ");
    } else {

       $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname="CONCAT('/',cc.costcenterid,'')");

        $categorylist = $DB->get_records_sql_menu("SELECT cc.id, cc.fullname FROM {local_custom_category} AS cc WHERE 1=1 $costcenterpathconcatsql");
    }


    $select = $mform->addElement('autocomplete', 'categories', get_string('category'), $categorylist, array('placeholder' => get_string('category')));
    $mform->setType('categories', PARAM_RAW);
    $select->setMultiple(true);
}
/*
* todo prints the filter form
*/
function print_filterform(){
    global $DB, $CFG;
    require_once($CFG->dirroot . '/local/courses/filters_form.php');
    $mform = new filters_form(null, array('filterlist'=>array('courses', 'costcenter', 'categories')));
    if ($mform->is_cancelled()) {
        redirect($CFG->wwwroot . '/local/courses/courses.php');
    } else{
        $filterdata =  $mform->get_data();
        if($filterdata){
            $collapse = false;
        } else{
            $collapse = true;
        }
    }
    $heading = '<button >'.get_string('course_filters', 'local_courses').'</button>';
    print_collapsible_region_start(' ', 'filters_form', ' '.' '.$heading, false, $collapse);
    $mform->display();
    print_collapsible_region_end();
    return $filterdata;
}

/**
* [course_enrolled_users description]
* @param  string  $type       [description]
* @param  integer $evaluationid [description]
* @param  [type]  $params     [description]
* @param  integer $total      [description]
* @param  integer $offset    [description]
* @param  integer $perpage    [description]
* @param  integer $lastitem   [description]
* @return [type]              [description]
*/
function course_enrolled_users($type = null, $course_id = 0, $params, $total=0, $offset=-1, $perpage=-1, $lastitem=0){

    global $DB, $USER;
	$context = (new \local_courses\lib\accesslib())::get_module_context($course_id);
    $course = $DB->get_record('course', array('id' => $course_id));
    $condition = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
    $params['suspended'] = 0;
    $params['deleted'] = 0;
 
    if($total==0){
         $sql = "SELECT u.id,concat(u.firstname,' ',u.lastname,' ','(',u.idnumber,')') as fullname";
    }else{
        $sql = "SELECT count(u.id) as total";
    }
    $sql.=" FROM {user} AS u WHERE  u.id > 2 AND u.suspended = :suspended AND u.deleted = :deleted ";
    if($lastitem!=0){
       $sql.=" AND u.id > $lastitem";
    }
    if (!is_siteadmin()) {
        $sql.=$condition;
    }
    $sql .=" AND u.id <> $USER->id";
    if (!empty($params['email'])) {
         $sql.=" AND u.id IN ({$params['email']})";
    }
    if (!empty($params['uname'])) {
         $sql .=" AND u.id IN ({$params['uname']})";
    }
    if (!empty($params['organization'])) {
        $organizations = explode(',', $params['organization']);
        $orgsql = [];
        foreach($organizations AS $organisation){
            $orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%'.$organisation.'%';
        }
        if(!empty($orgsql)){
            $sql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
        }
    }
    if (!empty($params['department'])) {
        $departments = explode(',', $params['department']);
        $deptsql = [];
        foreach($departments AS $department){
            $deptsql[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%'.$department.'%';
        }
        if(!empty($deptsql)){
            $sql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
        }
    }
    if (!empty($params['subdepartment'])) {
        $subdepartments = explode(',', $params['subdepartment']);
        $subdeptsql = [];
        foreach($subdepartments AS $subdepartment){
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%'.$subdepartment.'%';
        }
        if(!empty($subdeptsql)){
            $sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
        }
    }
    if (!empty($params['department4level'])) {
        $subdepartments = explode(',', $params['department4level']);
        $subdeptsql = [];
        foreach($subdepartments AS $department4level){
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%'.$department4level.'%';
        }
        if(!empty($subdeptsql)){
            $sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
        }
    }
    if (!empty($params['department5level'])) {
        $subdepartments = explode(',', $params['department5level']);
        $subdeptsql = [];
        foreach($subdepartments AS $department5level){
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :department5levelparam_{$department5level}";
            $params["department5levelparam_{$department5level}"] = '%'.$department5level.'%';
        }
        if(!empty($subdeptsql)){
            $sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
        }
    }
    if (!empty($params['idnumber'])) {
         $sql .=" AND u.id IN ({$params['idnumber']})";
    }
    if (!empty($params['village'])) {
        $villages = explode(',', $params['village']);
        list($villagesql, $villageparam) = $DB->get_in_or_equal($villages, SQL_PARAMS_NAMED, 'village');
        $params = array_merge($params, $villageparam);
        $sql .= " AND u.open_village {$villagesql} ";
    }
    if (!empty($params['subdistrict'])) {
        $subdistricts = explode(',', $params['subdistrict']);
        list($subdistrictsql, $subdistrictparam) = $DB->get_in_or_equal($subdistricts, SQL_PARAMS_NAMED, 'subdistrict');
        $params = array_merge($params, $subdistrictparam);
        $sql .= " AND u.open_subdistrict {$subdistrictsql} ";
    }
    if (!empty($params['district'])) {
        $districts = explode(',', $params['district']);
        list($districtsql, $districtparam) = $DB->get_in_or_equal($districts, SQL_PARAMS_NAMED, 'district');
        $params = array_merge($params, $districtparam);
        $sql .= " AND u.open_district {$districtsql} ";
    }
    if (!empty($params['states'])) {
        $state = explode(',', $params['states']);
        list($statessql, $statesparam) = $DB->get_in_or_equal($state, SQL_PARAMS_NAMED, 'states');
        $params = array_merge($params, $statesparam);
        $sql .= " AND u.open_states {$statessql} ";
    }

    if (!empty($params['location'])) {

        $locations = explode(',',$params['location']);
        list($locationsql, $locationparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
        $params = array_merge($params,$locationparams);            
        $sql .= " AND u.open_location {$locationsql} ";
    }

    if (!empty($params['hrmsrole'])) {

        $hrmsroles = explode(',',$params['hrmsrole']);
        list($hrmsrolesql, $hrmsroleparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
        $params = array_merge($params,$hrmsroleparams);            
        $sql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
    }
    if (!empty($params['groups'])) {
         $group_list = $DB->get_records_sql_menu("select cm.id, cm.userid from {cohort_members} cm, {user} u where u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0 AND cm.cohortid IN ({$params['groups']})");
         
         $groups_members = implode(',', $group_list);
         if (!empty($groups_members))
         $sql .=" AND u.id IN ({$groups_members})";
         else
         $sql .=" AND u.id =0";
    }
    if ($type=='add') {
        $sql .= " AND u.id NOT IN (SELECT ue.userid
                             FROM {user_enrolments} AS ue 
                             JOIN {enrol} e ON (e.id = ue.enrolid and e.courseid=$course_id and (e.enrol='manual' OR e.enrol='self')))";
    }elseif ($type=='remove') {
        $sql .= " AND u.id IN (SELECT ue.userid
                             FROM {user_enrolments} AS ue 
                             JOIN {enrol} e ON (e.id = ue.enrolid and e.courseid=$course_id and (e.enrol='manual' OR e.enrol='self')))";
    }

    $order = " ORDER BY concat(u.firstname,' ',u.lastname) ASC ";

    if($total==0){
        $availableusers = $DB->get_records_sql_menu($sql.$order, $params, $offset, $perpage);
    }else{
        $availableusers = $DB->count_records_sql($sql, $params);
    }
    return $availableusers;
}

/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_courses_leftmenunode(){
    global $DB, $USER;
	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $coursecatnodes = '';
    if(has_capability('local/custom_category:view_custom_category', $categorycontext) || is_siteadmin()) {
        $coursecatnodes .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_categories', 'class'=>'pull-left user_nav_div categories usernavdep'));
        $categories_url = new moodle_url('/local/custom_category/index.php');
        $categories = html_writer::link($categories_url, '<i class="fa fa-book" aria-hidden="true" aria-label=""></i><i class="fa fa-book secbook" aria-hidden="true" aria-label=""></i><span class="user_navigation_link_text">'.get_string('leftmenu_browsecategories','local_courses').'</span>',array('class'=>'user_navigation_link'));
        $coursecatnodes .= $categories;
        $coursecatnodes .= html_writer::end_tag('li');
    }

    if(has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext) || is_siteadmin()) {
        $coursecatnodes .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browsecourses', 'class'=>'pull-left user_nav_div browsecourses'));
            $courses_url = new moodle_url('/local/courses/courses.php');
            $courses = html_writer::link($courses_url, '<i class="fa fa-book"></i><span class="user_navigation_link_text">'.get_string('manage_courses','local_courses').'</span>',array('class'=>'user_navigation_link'));
            $coursecatnodes .= $courses;
        $coursecatnodes .= html_writer::end_tag('li');
    }

    return array('6' => $coursecatnodes);
}

function local_courses_quicklink_node(){
    global $CFG, $PAGE, $OUTPUT;
	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $content = '';
    if (has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext) || is_siteadmin()){
        //local courses content
        // $local_courses_content = $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'load');
        $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'load');
        // $local_courses_content = "<span class='anch_span'><i class='fa fa-book' aria-hidden='true'></i></span>";
        // $local_courses_content .= "<div class='quick_navigation_detail'>
        //                                 <div class='span_str'>".get_string('manage_br_courses', 'local_courses')."</div>";
        // $local_courses_content .= "<span class='span_createlink'>";
        // if(is_siteadmin() ||( has_capability('moodle/course:create', $categorycontext)&& has_capability('moodle/course:update', $categorycontext)&&has_capability('local/courses:manage', $categorycontext))){
        //     $local_courses_content .="<a href='javascript:void(0);' class='quick_nav_link goto_local_courses' title = '".get_string('create_newcourse', 'local_courses')."' data-action='createcoursemodal' onclick='(function(e){ require(\"local_courses/courseAjaxform\").init({contextid:".$categorycontext->id.", component:\"local_courses\", callback:\"custom_course_form\", form_status:0, plugintype: \"local\", pluginname: \"courses\"}) })(event)'>".get_string('create')."</a> | ";
            
        // }
        // if(has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext) ){
        //     $local_courses_content .=" <a href='".$CFG->wwwroot."/local/courses/courses.php' class='viewlink' title= ' ".get_string('view_courses','local_courses')." '>".get_string('view')."</a>";
        // }
        // $local_courses_content .=" </span>";
        // $local_courses_content .= "</div>";
        // $local_courses = '<div class="quick_nav_list manage_courses one_of_three_columns" >'.$local_courses_content.'</div>';
        $coursedata = array();
        $coursedata['node_header_string'] = get_string('manage_br_courses', 'local_courses');
        $coursedata['pluginname'] = 'courses';
        $coursedata['plugin_icon_class'] = 'fa fa-book';
        if(is_siteadmin() ||( has_capability('moodle/course:create', $categorycontext)&& has_capability('moodle/course:update', $categorycontext)&&has_capability('local/courses:manage', $categorycontext))){
            $coursedata['create'] = TRUE;
            $coursedata['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('onclick' => '(function(e){ require("local_courses/courseAjaxform").init({contextid:'.$categorycontext->id.', component:"local_courses", callback:"custom_course_form", form_status:0, plugintype: "local", pluginname: "courses"}) })(event)'));
        }
        if(has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext)){
            $coursedata['viewlink_url'] = $CFG->wwwroot.'/local/courses/courses.php';
            $coursedata['view'] = TRUE;
            $coursedata['viewlink_title'] = get_string('view_courses','local_courses');
        }
        $coursedata['space_count'] = 'one';
        $content = $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $coursedata);
    }
    return array('3' => $content);
}

/**
    * function costcenterwise_courses_count
    * @todo count of courses under selected costcenter
    * @param int $costcenter costcenter
    * @param int $department department
    * @return  array courses count of each type
*/
function costcenterwise_courses_count($costcenter,$department = false,$subdepartment = false, $l4department=false, $l5department=false){
    global $USER, $DB,$CFG;
    $params = array();
    $params['costcenterpath'] = '%/'.$costcenter.'/%';
    $countcoursesql = "SELECT count(id) FROM {course} WHERE concat('/',open_path,'/') LIKE :costcenterpath";
    if ($department) {
        $countcoursesql .= "  AND concat('/',open_path,'/') LIKE :departmentpath  ";
        $params['departmentpath'] = '%/'.$department.'/%';
    }
    if ($subdepartment) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/'.$subdepartment.'/%';
    }
    if ($l4department) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
        $params['l4departmentpath'] = '%/'.$l4department.'/%';
    }
    if ($l5department) {
        $countcoursesql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
        $params['l5departmentpath'] = '%/'.$l5department.'/%';
    }
    $activesql = " AND visible = 1 ";
    $inactivesql = " AND visible = 0 ";

    $countcourses = $DB->count_records_sql($countcoursesql, $params);
    $activecourses = $DB->count_records_sql($countcoursesql.$activesql, $params);
    $inactivecourses = $DB->count_records_sql($countcoursesql.$inactivesql, $params);
    if($countcourses >= 0){
        if($costcenter){
            $viewcourselink_url = $CFG->wwwroot.'/local/courses/courses.php?costcenterid='.$costcenter; 
        }
        if($department){
            $viewcourselink_url = $CFG->wwwroot.'/local/courses/courses.php?costcenterid='.$costcenter.'&departmentid='.$department;
        }
        if($subdepartment){
            $viewcourselink_url = $CFG->wwwroot.'/local/courses/courses.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
        }
        if($l4department){
            $viewcourselink_url = $CFG->wwwroot.'/local/courses/courses.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
        }
        if($l5department){
            $viewcourselink_url = $CFG->wwwroot.'/local/courses/courses.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
        }
    }

    if($activecourses >= 0){
        if($costcenter){
            $count_courseactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=active&costcenterid='.$costcenter; 
        }
        if($department){
            $count_courseactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department;
        }
        if($subdepartment){
            $count_courseactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
        }
        if($l4department){
            $count_courseactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
        }
        if($l5department){
            $count_courseactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
        }
    }
    if($inactivecourses >= 0){
        if($costcenter){
            $count_courseinactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=inactive&costcenterid='.$costcenter; 
        }
        if($department){
            $count_courseinactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department;
        }
        if($subdepartment){
            $count_courseinactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
        }
        if($l4department){
            $count_courseinactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
        }
        if($l5department){
            $count_courseinactivelink_url = $CFG->wwwroot.'/local/courses/courses.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
        }
    }

    return array('coursecount' => $countcourses,'activecoursecount' => $activecourses,'inactivecoursecount' => $inactivecourses,'viewcourselink_url'=>$viewcourselink_url,'count_courseactivelink_url' =>$count_courseactivelink_url,'count_courseinactivelink_url' =>$count_courseinactivelink_url);
}

/**
    * function get_listof_courses
    * @todo all courses based  on costcenter / department
    * @param object $stable limit values
    * @param object $filterdata filterdata
    * @return  array courses
*/

function get_listof_courses($stable, $filterdata) {
    global $CFG,$DB,$OUTPUT,$USER;
    $core_component = new core_component();
     //require_once($CFG->libdir. '/coursecatlib.php');
    require_once($CFG->dirroot.'/course/renderer.php');
    require_once($CFG->dirroot . '/local/costcenter/lib.php');
    require_once($CFG->dirroot . '/enrol/locallib.php');
    $autoenroll_plugin_exist = $core_component::get_plugin_directory('enrol','auto');
    if(!empty($autoenroll_plugin_exist)){
      require_once($CFG->dirroot . '/enrol/auto/lib.php');
    }
	$maincheckcontext=$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $statustype=$stable->status;
    $totalcostcentercount=$stable->costcenterid;
    $totaldepartmentcount=$stable->departmentid;
    $departmentsparams = array();
    $subdepartmentsparams = array();
    $organizationsparams = array();
    $userorg = array();
    $userdep = array();
    $locationsparams = $hrmsrolessparams = [];
    $filtercategoriesparams= array();
    $filtercoursesparams = array();
    $chelper = new coursecat_helper();
    $selectsql = "SELECT c.id, ct.name as coursetype ,c.fullname, c.shortname, c.category, c.summary, c.format ,c.selfenrol,c.open_points,c.open_path, c.open_identifiedas, c.visible, c.open_skill,c.open_categoryid FROM {course} AS c";
    $countsql  = "SELECT count(c.id) FROM {course} AS c ";
        $open_path=(new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        $formsql = " JOIN {local_costcenter} AS co ON co.path = c.open_path
                     JOIN {course_categories} AS cc ON cc.id = c.category
                     JOIN {local_course_types} As ct ON ct.id = c.open_identifiedas ";
        
    $formsql .= " AND c.id > 1  $open_path";
    
    $params=array();
    if(isset($filterdata->search_query) && trim($filterdata->search_query) != ''){
        $formsql .= " AND c.fullname LIKE :search";
        // $searchparams = array('search' => '%'.trim($filterdata->search_query).'%');
        $params['search'] = '%'.trim($filterdata->search_query).'%';
    }else{
        $searchparams = array();
    }
    if(!empty($filterdata->categories)){
        $filtercategories = explode(',', $filterdata->categories);
        list($filtercategoriessql, $filtercategoriesparams) = $DB->get_in_or_equal($filtercategories, SQL_PARAMS_NAMED, 'categories', true, false);
        $params = array_merge($params, $filtercategoriesparams);
        $formsql .= " AND c.open_categoryid $filtercategoriessql";
    }
    if(!empty($filterdata->courses)){
        $filtercourses = explode(',', $filterdata->courses);
        list($filtercoursessql, $filtercoursesparams) = $DB->get_in_or_equal($filtercourses, SQL_PARAMS_NAMED, 'courses', true, false);
        $params = array_merge($params, $filtercoursesparams);
        $formsql .= " AND c.id $filtercoursessql";
    }
    if (!empty($filterdata->filteropen_costcenterid)) {

        $filteropen_costcenterid = explode(',', $filterdata->filteropen_costcenterid);
        $orgsql = [];
        foreach($filteropen_costcenterid AS $organisation){
            $orgsql[] = " concat('/',c.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%/'.$organisation.'/%';
        }
        if(!empty($orgsql)){
            $formsql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
        }
    }
    if (!empty($filterdata->filteropen_department)) {
        $filteropen_department = explode(',', $filterdata->filteropen_department);

        $deptsql = [];
        foreach($filteropen_department AS $department){
            $deptsql[] = " concat('/',c.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%/'.$department.'/%';
        }
        if(!empty($deptsql)){
            $formsql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
        }
    }
    if (!empty($filterdata->filteropen_subdepartment)) {
        $subdepartments = explode(',', $filterdata->filteropen_subdepartment);

        $subdeptsql = [];
        foreach($subdepartments AS $subdepartment){
            $subdeptsql[] = " concat('/',c.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%/'.$subdepartment.'/%';
        }
        if(!empty($subdeptsql)){
            $formsql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
        }
    }
    if (!empty($filterdata->filteropen_level4department)) {
        $subsubdepartments = explode(',', $filterdata->filteropen_level4department);

        $subsubdeptsql = [];
        foreach($subsubdepartments AS $department4level){
            $subsubdeptsql[] = " concat('/',c.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%/'.$department4level.'/%';
        }
        if(!empty($subsubdeptsql)){
            $formsql .= " AND ( ".implode(' OR ', $subsubdeptsql)." ) ";
        }
    }
    if (!empty($filterdata->filteropen_level5department)) {
        $subsubsubdepartments = explode(',', $filterdata->filteropen_level5department);
        $subsubsubdeptsql = [];
        foreach($subsubsubdepartments AS $department5level){
            $subsubsubdeptsql[] = " concat('/',c.open_path,'/') LIKE :department5levelparam_{$department5level}";
            $params["department5levelparam_{$department5level}"] = '%/'.$department5level.'/%';
        }
        if(!empty($subsubsubdeptsql)){
            $formsql .= " AND ( ".implode(' OR ', $subsubsubdeptsql)." ) ";
        }
    }
    if(!empty($filterdata->hrmsrole)){
        $hrmsroles = explode(',', $filterdata->hrmsrole);
        list($hrmsrolessql, $hrmsrolessparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole', true, false);
        $params = array_merge($params, $hrmsrolessparams);
        $formsql .= " AND c.open_hrmsrole {$hrmsrolessql} ";
    }
    if(!empty($filterdata->location)){
        $locations = explode(',', $filterdata->location);
        list($locationsql, $locationsparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location', true, false);
        $params = array_merge($params, $locationsparams);
        $formsql .= " AND c.open_location {$locationsql} ";
    }
    

    if(!empty($filterdata->status)){
        $status = explode(',',$filterdata->status);
        if(!(in_array('active',$status) && in_array('inactive',$status))){
            if(in_array('active' ,$status)){
                $formsql .= " AND c.visible = 1 ";           
            }else if(in_array('inactive' ,$status)){
                $formsql .= " AND c.visible = 0 ";
            }
        }
    }

    if(!empty($filterdata->coursetype)){
        $filtercoursetype = explode(',', $filterdata->coursetype);
        list($filtercoursetypesql, $filtercoursetypeparams) = $DB->get_in_or_equal($filtercoursetype, SQL_PARAMS_NAMED, 'coursetype', true, false);
        $params = array_merge($params, $filtercoursetypeparams);
        $formsql .= " AND c.open_identifiedas $filtercoursetypesql";
    }
    // $params = array_merge($searchparams, $userorg, $userdep, $filtercategoriesparams,$filtercoursesparams, $departmentsparams, $subdepartmentsparams, $organizationsparams, $hrmsrolessparams, $locationsparams,$filterparams,$filtercoursetypeparams);

    $totalcourses = $DB->count_records_sql($countsql.$formsql, $params);

    $formsql .=" ORDER BY c.id DESC";
    $courses = $DB->get_records_sql($selectsql.$formsql, $params, $stable->start,$stable->length);
    $ratings_plugin_exist = $core_component::get_plugin_directory('local', 'ratings');
    $courseslist = array();
    $employeerole = $DB->get_field('role', 'id', array('shortname' => 'employee'));
    if(!empty($courses)){
        $count = 0;
        foreach ($courses as $key => $course) {

            $course=(array)$course;

            local_costcenter_set_costcenter_path($course);

            $course=(object)$course;

            $course_in_list = new core_course_list_element($course);
            $context =  \context_course::instance($course->id);
            $categoryname = $DB->get_field('local_custom_category','fullname',array('id'=>$course->open_categoryid));
            $departmentcount = 1;
            $subdepartmentcount = 1;
          
            $params = array('courseid'=>$course->id, 'employeerole' => $employeerole);

            $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

            $enrolledusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                WHERE c.id = :courseid AND ra.roleid = :employeerole $costcenterpathconcatsql";

            $enrolled_count =  $DB->count_records_sql($enrolledusersssql, $params);


            $completedusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid
                                WHERE c.id = :courseid AND ra.roleid = :employeerole AND cc.timecompleted IS NOT NULL $costcenterpathconcatsql";

            $completed_count = $DB->count_records_sql($completedusersssql,$params);

            $coursename = $course->fullname;
            $shortname = $course->shortname;
          
            $format = $course->format;

            if (strlen($coursename)>35){
                $coursenameCut = substr($coursename, 0, 35)."...";
                $courseslist[$count]["coursenameCut"] = \local_costcenter\lib::strip_tags_custom($coursenameCut);
            }
            $catname = $categoryname;
            $catnamestring = strlen($catname) > 12 ? substr($catname, 0, 12)."..." : $catname;
            $displayed_names = '<span class="pl-10 '.$course->coursetype.'">'.$course->coursetype.'</span>';

            $courestypes_names = array('2'=>get_string('classroom','local_courses'),'3'=>get_string('elearning','local_courses'), '4'=> get_string('learningplan','local_courses'), '5' => get_string('program','local_courses'), '6' => get_string('certification','local_courses'));
            if($ratings_plugin_exist){
                require_once($CFG->dirroot.'/local/ratings/lib.php');
                $ratingenable = True;
                $avgratings = get_rating($course->id, 'local_courses');
                $rating_value = $avgratings->avg == 0 ? 'N/A' : $avgratings->avg/*/2*/ ;
            }else{
                $ratingenable = False;
                $rating_value = 'N/A';
            }
            $classname = '\local_tags\tags';
            if(class_exists($classname)){
                $tags = new $classname;

                $tagstring = $tags->get_item_tags($component = 'local_courses', $itemtype='courses', $itemid=$course->id, $contextid=context_course::instance($course->id)->id, $arrayflag = 0,$more = 0);
                $tagstringtotal = $tagstring;
                if($tagstring == ""){
                    $tagstring = 'N/A';
                }else{
                    $tagstring = strlen($tagstring) > 35 ? substr($tagstring, 0, 35).'...' : $tagstring;
                }
                $tagenable = True;
            }else{
                $tagenable = False;
                $tagstring = '';
                $tagstringtotal = $tagstring;
            }

            if($course->open_skill > 0){
                $skill = $DB->get_field('local_skill', 'name', array('id' => $course->open_skill));
                if($skill){
                    $skillname = $skill;
                } else {
                    $skillname = 'N/A';
                }
            } else {
                $skillname = 'N/A';                
            }
            $courseslist[$count]["coursename"] = \local_costcenter\lib::strip_tags_custom($coursename);
            $courseslist[$count]["shortname"] =  \local_costcenter\lib::strip_tags_custom($shortname);
            $courseslist[$count]["skillname"] = \local_costcenter\lib::strip_tags_custom($skillname);
            $courseslist[$count]["ratings_value"] = $rating_value;
            $courseslist[$count]["ratingenable"] = $ratingenable;
            $courseslist[$count]["tagstring"] = \local_costcenter\lib::strip_tags_custom($tagstring);
            $courseslist[$count]["tagstringtotal"] = $tagstringtotal;
            $courseslist[$count]["tagenable"] = $tagenable;
            $courseslist[$count]["catname"] = \local_costcenter\lib::strip_tags_custom($catname);
            $courseslist[$count]["catnamestring"] = \local_costcenter\lib::strip_tags_custom($catnamestring);
            $courseslist[$count]["enrolled_count"] = $enrolled_count;
            $courseslist[$count]["courseid"] = $course->id;
            $courseslist[$count]["completed_count"] = $completed_count;
            $courseslist[$count]["points"] = $course->open_points != NULL ? $course->open_points: 0;
            $courseslist[$count]["coursetype"] = \local_costcenter\lib::strip_tags_custom($displayed_names);
            $courseslist[$count]["course_class"] = $course->visible ? 'active' : 'inactive';
            $courseslist[$count]["grade_view"] = ((has_capability('local/courses:grade_view',
            $context) || is_siteadmin())&&has_capability('local/courses:manage', $context)) ? true : false;
            $courseslist[$count]["request_view"] = ((has_capability('local/request:approverecord', $maincheckcontext)) || is_siteadmin()) ? true : false;
            
            $coursesummary = \local_costcenter\lib::strip_tags_custom($chelper->get_course_formatted_summary($course_in_list,
                    array('overflowdiv' => false, 'noclean' => false, 'para' => false)));
            $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100)."..." : $coursesummary;
            $courseslist[$count]["coursesummary"] = \local_costcenter\lib::strip_tags_custom($summarystring);
            $courseslist[$count]["fullcoursesummary"] = $coursesummary;
            $courseslist[$count]["format"] = $format;
            
            
            $course =  (array)$course;
            local_costcenter_set_costcenter_path($course);
            $course = (object)$course;
             if($course->open_department > 0){
                 $courseslist[$count]["open_department"] = $DB->get_records_sql_menu('SELECT id,fullname 
                FROM {local_costcenter}
                WHERE id IN('.$course->open_department.')');
             } else {
                 $courseslist[$count]["open_department"] = get_string('all');
             }
            if($course->open_subdepartment > 0) {
                 $courseslist[$count]["open_subdepartment"] = $DB->get_records_sql_menu('SELECT id,fullname 
                FROM {local_costcenter}
                WHERE id IN('.$course->open_subdepartment.')');
             } else {
                 $courseslist[$count]["open_subdepartment"] = get_string('all');
             }
             if($course->open_level4department > 0) {
                $courseslist[$count]["open_level4department"] = $DB->get_records_sql_menu('SELECT id,fullname 
               FROM {local_costcenter}
               WHERE id IN('.$course->open_level4department.')');
            } else {
                $courseslist[$count]["open_level4department"] = get_string('all');
            }
            if($course->open_level5department > 0) {
                $courseslist[$count]["open_level5department"] = $DB->get_records_sql_menu('SELECT id,fullname 
               FROM {local_costcenter}
               WHERE id IN('.$course->open_level5department.')');
            } else {
                $courseslist[$count]["open_level5department"] = get_string('all');
            }

             if($course->selfenrol == 1){
                $courseslist[$count]["selfenrol"] = get_string('yes');
             }else{
                 $courseslist[$count]["selfenrol"] = get_string('no');
             }
           
            //course image
            if(file_exists($CFG->dirroot.'/local/includes.php')){
                require_once($CFG->dirroot.'/local/includes.php');
                $includes = new user_course_details();
                $courseimage = $includes->course_summary_files($course);                
                if(is_object($courseimage)){
                    $courseslist[$count]["courseimage"] = $courseimage->out();                    
                }else{
                    $courseslist[$count]["courseimage"] = $courseimage;
                }                
            }            

            $enrolid = $DB->get_field('enrol','id',array('enrol'=>'manual','courseid'=>$course->id));
            
            if(has_capability('local/courses:enrol',$maincheckcontext)&&has_capability('local/courses:manage', $maincheckcontext)){
                $courseslist[$count]["enrollusers"] = $CFG->wwwroot."/local/courses/courseenrol.php?id=".$course->id."&enrolid=".$enrolid;
            }
            if(has_capability('moodle/course:view', $context) || is_enrolled($context)){
                $courseslist[$count]["courseurl"] = $CFG->wwwroot."/course/view.php?id=".$course->id;
            }else{
                $courseslist[$count]["courseurl"] = $CFG->wwwroot."/local/search/coursedetails.php?id=".$course->id;
            }

            if($departmentcount > 1 && !(is_siteadmin())) {
                  $courseslist[$count]["grade_view"] = false;
                  $courseslist[$count]["request_view"] = false;
             }   


            if(has_capability('local/courses:update',$context)&&has_capability('local/courses:manage', $context)&&has_capability('moodle/course:update', $context)){
                $courseedit = html_writer::link('javascript:void(0)', html_writer::tag('i', '', array('class' => 'fa fa-pencil ')).get_string('edit') , array('title' => get_string('edit'), 'alt' => get_string('edit'),'data-action' => 'createcoursemodal', 'class'=>'createcoursemodal dropdown-item', 'data-value'=>$course->id, 'onclick' =>'(function(e){ require("local_courses/courseAjaxform").init({contextid:'.$context->id.', component:"local_courses", callback:"custom_course_form", form_status:0, plugintype: "local", pluginname: "courses", courseid: ' . $course->id . ' }) })(event)'));
                $courseslist[$count]["editcourse"] = $courseedit;
                if($course->visible){
                    $icon = 't/hide';
                    $string = get_string('make_active','local_courses');
                    $title = get_string('make_inactive','local_courses');
                }else{
                    $icon = 't/show';
                    $string = get_string('make_inactive','local_courses');
                    $title = get_string('make_active','local_courses');
                }
                $image = $OUTPUT->pix_icon($icon, $title, 'moodle', array('class' => 'iconsmall', 'title' => '')).$title;
                $params = json_encode(array('coursename' => $coursename, 'coursestatus' => $course->visible));
                $courseslist[$count]["update_status"] .= html_writer::link("javascript:void(0)", $image  , array('class'=>' make_inactive dropdown-item','data-fg'=>"d", 'data-method' => 'course_update_status','data-plugin' => 'local_courses', 'data-params' => $params, 'data-id'=>$course->id));
                if(!empty($autoenroll_plugin_exist)){
                    $autoplugin = enrol_get_plugin('auto');
                    $instance = $autoplugin->get_instance_for_course($course->id);
                    if($instance){
                        if ($instance->status == ENROL_INSTANCE_DISABLED) {
                            
                        $courseslist[$count]["auto_enrol"] = $CFG->wwwroot."/enrol/auto/edit.php?courseid=".$course->id."&id=".$instance->id;
                        }
                    }
                }
            }

             if($departmentcount > 1 && !(is_siteadmin() )) {
                  $courseslist[$count]["editcourse"] = ''; 
                  $courseslist[$count]["update_status"] = '';
                  $courseslist[$count]["auto_enrol"] = '';
             }   

           if(has_capability('local/courses:delete',$context)&&has_capability('local/courses:manage', $context)&&has_capability('moodle/course:delete', $context)){
                $deleteactionshtml = html_writer::link('javascript:void(0)', $OUTPUT->pix_icon('t/delete', get_string('delete'), 'moodle', array('')).get_string('delete'), array('class'=>"dropdown-item delete_icon" ,'title' => get_string('delete'), 'id' => "courses_delete_confirm_".$course->id,'onclick'=>'(function(e){ require(\'local_courses/courseAjaxform\').deleteConfirm({action:\'deletecourse\' , id: ' . $course->id . ', name:"'.$coursename.'" }) })(event)'));
                $courseslist[$count]["deleteaction"] = $deleteactionshtml;
           
            }
            
            if($departmentcount > 1 && !(is_siteadmin())) {
                 $courseslist[$count]["deleteaction"] = '';
             }   

            if(has_capability('local/courses:grade_view',$context) && has_capability('local/courses:manage', $context)){
                  $courseslist[$count]["grader"] =  $CFG->wwwroot."/grade/report/grader/index.php?id=".$course->id;
            }

            if($departmentcount > 1 && !(is_siteadmin() )) {
                    unset($courseslist[$count]["grader"]);
            }   
            
            if(has_capability('local/courses:report_view',$context)&&has_capability('local/courses:manage', $context)){
                $courseslist[$count]["activity"] = $CFG->wwwroot."/report/outline/index.php?id=".$course->id;
           
            }
            if($departmentcount > 1 && !(is_siteadmin() )) {
                 unset($courseslist[$count]["activity"]);
             }   

           
            if ((has_capability('local/request:approverecord', $maincheckcontext) || is_siteadmin())) {
                $courseslist[$count]["requestlink"] = $CFG->wwwroot."/local/request/index.php?courseid=".$course->id;
            }

            if($departmentcount > 1 && !(is_siteadmin() )) {
                  unset($courseslist[$count]["requestlink"]);
             }   


        $courseslist[$count]=array_merge($courseslist[$count],array(
                                    "actions"=>(((has_capability('local/courses:enrol',
                                $maincheckcontext)|| has_capability('local/courses:update',
                                $context)||has_capability('local/courses:delete',
                                $context) || has_capability('local/courses:grade_view',
                                $context)|| has_capability('local/courses:report_view',
                                $context)) || is_siteadmin())&&has_capability('local/courses:manage', $maincheckcontext)) ? true : false,
                                "enrol"=>((has_capability('local/courses:enrol',
                                $maincheckcontext)  || is_siteadmin())&&has_capability('local/courses:manage', $maincheckcontext)) ? true : false,
                                "update"=>((has_capability('local/courses:update',
                                $context) || is_siteadmin())&&has_capability('local/courses:manage', $context)&&has_capability('moodle/course:update', $context)) ? true : false,
                                "delete"=>((has_capability('local/courses:delete',
                                $context) || is_siteadmin())&&has_capability('local/courses:manage', $context)&&has_capability('moodle/course:delete', $context)) ? true : false,
                                "report_view"=>((has_capability('local/courses:report_view', $context) || is_siteadmin())&&has_capability('local/courses:manage', $context)) ? true : false
                            ));


            $count++;
        }
        $nocourse = false;
        $pagination = false;
    }else{
        $nocourse = true;
        $pagination = false;
    }
    // check the course instance is not used in any plugin
    $candelete = true;
    $core_component = new core_component();
    $classroom_plugin_exist = $core_component::get_plugin_directory('local', 'classroom');
    if ($classroom_plugin_exist) {
        $exist_sql = "Select id from {local_classroom_courses} where courseid = ?";
        if ($DB->record_exists_sql($exist_sql, array($course->id)))
        $candelete = false;
    }
    
    $program_plugin_exist = $core_component::get_plugin_directory('local', 'program');
    if ($program_plugin_exist) {
        $exist_sql = "Select id from {local_program_level_courses} where courseid = ?";
        if ($DB->record_exists_sql($exist_sql, array($course->id)))
        $candelete = false;
    }
    $certification_plugin_exist = $core_component::get_plugin_directory('local', 'certification');
    if ($certification_plugin_exist) {
        $exist_sql = "Select id from {local_certification_courses} where courseid = ?";
        if ($DB->record_exists_sql($exist_sql, array($course->id)))
        $candelete = false;
    }
    $coursesContext = array(
        "hascourses" => $courseslist,
        "nocourses" => $nocourse,
        "totalcourses" => $totalcourses,
        "length" => count($courseslist),

    );

    return $coursesContext;

}

/**
    * function get_listof_categories
    * @todo all courses based  on costcenter / department
    * @param object $stable limit values
    * @param object $filterdata filterdata
    * @return  array courses
*/
function get_listof_categories($stable, $filterdata) {
    global $DB, $CFG, $OUTPUT, $PAGE ,$USER;
    require_once($CFG->dirroot.'/course/lib.php');
   //require_once($CFG->libdir.'/coursecatlib.php');
    $categorylib = new local_courses\catslib();

    $organizationsparams = array();
    $deptcategoryparams = array();
    $categoryparams = array();
    $filtercategoriesparams= array();
    $table = new html_table();
    $table->id = 'category_tbl';
    $table->head = array('','','','');

	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    
    $countsql = "select count(c.id) ";
    $sql = "SELECT c.id, c.name, c.parent, c.visible, c.coursecount, c.idnumber ";
    $fromsql = "FROM {course_categories} as  c WHERE id > 1 ";
        
    if(!empty($filterdata->parentid)){
        $fromsql .= " AND c.parent = $filterdata->parentid ";
        
    } else{ 
        if(is_siteadmin()) {
            $fromsql .= " AND c.parent =0 ";
        }else{
            $fromsql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        }
    }

   
    if(isset($filterdata->search_query) && trim($filterdata->search_query) != ''){
        $fromsql .= " AND c.name LIKE :search ";
        $searchparams = array('search' => '%'.$filterdata->search_query.'%');
    }else{
        $searchparams = array();
    }
    $ordersql = " ORDER BY c.id DESC ";

    $params = array_merge($searchparams,$organizationsparams, $deptcategoryparams, $categoryparams);

    $allcategories = $DB->get_records_sql($sql.$fromsql.$ordersql, $params, $stable->start, $stable->length);      
    $categoriescount = $DB->count_records_sql($countsql.$fromsql, $params);
    $data = array();
    $totalrecords = count($allcategories);
    $org_categories = $DB->get_records_menu('local_costcenter', array(),'', 'id, category');
    foreach($allcategories as $categories){
        $row = array();
        $result = $categories->name;
        $cate= $categories->id;
        $sql = $DB->get_records_sql("SELECT c.name FROM {course_categories} as  c
                                    WHERE c.parent=$cate");
        $categorynames =  count($sql);
        $categoryidnumber = $categories->idnumber;
        $categorycontext = context_coursecat::instance($categories->id);
        if($categories->visible ==0){
            $count =  $categories->coursecount;
        }

        if($categorynames > 0){
            $linkurl = new moodle_url("/local/custom_category/index.php?id=".$categories->id."");
        }else{
            $linkurl = null;
        }

        $counts =html_writer::link($linkurl, $categorynames, array());

        $count = html_writer::link('javascript:void(0)', $categories->coursecount, array('title' => '', 'alt' => '', 'class'=>'createcoursemodal course_count_popup', 'onclick' =>'(function(e){ require("local_courses/newcategory").courselist({contextid:'.$categorycontext->id.', categoryname: "'.$categories->name.'", categoryid: "' . $categories->id . '" }) })(event)'));

        $actions = '';
        if(has_capability('local/courses:manage',$categorycontext)){
            $actions = true;
            if(!empty($categories->visible)){
                $visible_value = 0;
                $show = true;
            }else{
                $visible_value = 1;
                $show =  false;
            }
        }
        if($result  != ''){
            $parentname_str = strlen($result) > 20 ? substr($result, 0, 20)."..." : $result;

        }else{
            $parentname_str = 'N/A';
        }

        if(!empty($categories->visible)) {
            $line['parentname_str'] = $parentname_str;
            $line['result'] = $result;
        } else {
            $line['parentname_str'] = $parentname_str;
            $line['result'] = $result;
        }
        if($categoryidnumber != ''){
        $categoryidnumber_idnumber = strlen($categoryidnumber) > 13 ? substr($categoryidnumber, 0, 13)."..." : $categoryidnumber;

        }else{
            $categoryidnumber_idnumber = 'N/A';
        }
        if(!empty($categories->visible)) {
            $line['categoryidnumber_idnumber'] = $categoryidnumber_idnumber;
            $line['categoryidnumber'] = $categoryidnumber;
        } else {
            $line['categoryidnumber_idnumber'] = $categoryidnumber_idnumber;
            $line['categoryidnumber'] = $categoryidnumber;
        }

        if(!empty($categories->visible)){
            $line['catcount'] = $count;
        }else {
            $line['catcount'] = $count;
        }

        if(!empty($categories->visible)) {
            $line['categoryname_str'] = $counts;
        } else {
            $line['categoryname_str'] = $counts;
        }
        $catdepth = $DB->get_field('course_categories','depth',array('id'=>$filterdata->parentid));
        if($catdepth < 2){
            $depth = true;
        }else{
             $depth = false;
        }
        $line['showsubcategory'] =  $depth;

        $catimage = $OUTPUT->image_url('catlist', 'local_courses');
        if(is_object($catimage)){
            $line['catlisticon'] = $catimage->out_as_local_url();
        }else{
            $line['catlisticon'] = $catimage;
        }
        $line['catgoryid'] = $categories->id;
        $line['actions'] = $actions;
        $line['contextid'] = $categorycontext->id;
        $line['show'] = $show;
        $line['visible_value'] = $visible_value;
        $line['sesskey'] = sesskey();

        $coursesexists = $DB->record_exists('course', array('category'=>$categories->id));
        $subcatexists = $DB->record_exists('course_categories', array('parent'=>$categories->id));

        if(in_array($categories->id, $org_categories)){
            $line['delete_enable'] = FALSE;
            $line['unabletodelete_reason'] = get_string('reason_linkedtocostcenter','local_courses');
        }elseif($subcatexists){
            $line['delete_enable'] = FALSE;
            $line['unabletodelete_reason'] = get_string('reason_subcategoriesexists','local_courses');
        }elseif($coursesexists){
            $line['delete_enable'] = FALSE;
            $line['unabletodelete_reason'] = get_string('reason_coursesexists','local_courses');
        }else{
            $line['delete_enable'] = TRUE;
        }
        $data[] = $line;
    }
    return array('totalrecords' => $categoriescount,'records' => $data);
}
function courses_filters_form($filterparams, $ajaxformdata = null){

    global $CFG, $PAGE,$USER;

    require_once($CFG->dirroot . '/local/courses/filters_form.php');

    $action = isset($filterparams['action']) ? $filterparams['action'] : '';


     $fields =array(/*'organizations', 'departments',
            'subdepartment', 'department4level','department5level',*/'hierarchy_fields','courses','categories','status','coursetype');

    /*if(!is_siteadmin()) {

        $fields =array('courses','categories','status');

        $depth = $USER->useraccess['currentroleinfo']['depth'];
        if(count($USER->useraccess['currentroleinfo']['contextinfo']) > 1){
            $depth--;
        }
        if($depth < 6){
            array_unshift($fields, 'department5level');
        }
        if($depth < 5){
            array_unshift($fields, 'department4level');
        }
        if($depth < 4){
            array_unshift($fields, 'subdepartment');
        }
        if($depth < 3){
            array_unshift($fields, 'departments');
        }
        if($depth < 2){
            array_unshift($fields, 'organizations');
        }

    }*/

    $mform = new filters_form(null, array('filterlist'=> $fields, 'filterparams' => $filterparams, 'action' => $action), 'post', '', null, true, $ajaxformdata);
    return $mform;
}
/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_courses_list(){
    return 'Courses';
}

/**
 * Returns onlinetests tagged with a specified tag.
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
function local_courses_get_tagged_courses($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0, $sort = '') {
    global $CFG, $PAGE;
    // prepare for display of tags related to tests
    $perpage = $exclusivemode ? 10 : 5;
    $displayoptions = array(
        'limit' => $perpage,
        'offset' => $page * $perpage,
        'viewmoreurl' => null,
    );
    $renderer = $PAGE->get_renderer('local_courses');
    $totalcount = $renderer->tagged_courses($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, $count = 1, $sort);
    $content = $renderer->tagged_courses($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, 0, $sort);
    $totalpages = ceil($totalcount / $perpage);
    if ($totalcount)
    return new local_tags\output\tagindex($tag, 'local_courses', 'courses', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    else
    return '';
}
/**
* todo sql query departmentwise
* @param  $categorycontext object
* @return array
**/

function get_course_details($courseid) {
    global $USER, $DB, $PAGE;
	$context = (new \local_courses\lib\accesslib())::get_module_context($courseid);
    
    $PAGE->requires->js_call_amd('local_courses/courses','load', array());
    $PAGE->requires->js_call_amd('local_request/requestconfirm','load', array());
    $details = array();
    $joinsql = '';
    if(is_siteadmin()) {
        $sql = "select c.* from {course} c where c.id = ?";

        $selectsql = "select c.*  ";
        $fromsql = " from  {course} c";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
             $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
        }
        
        $wheresql = " where c.id = ? ";

        $adminrecord = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$courseid]);
        $enrolsql = "SELECT count(id) as ccount from {course_completions} where course = ? AND timecompleted IS NOT NULL";

        $completionsql="SELECT count(u.id) as total FROM {user} AS u WHERE u.id > 2 AND u.suspended =0 AND u.deleted = 0 AND u.id <> 3 AND u.id IN (
            SELECT ue.userid FROM {user_enrolments} ue
            JOIN {enrol} e ON (e.id = ue.enrolid and e.courseid = ? and (e.enrol='manual' OR e.enrol='self')))";
        $completedcount =  $DB->count_records_sql($completionsql, [$adminrecord->id]);
        $enrolledcount = $DB->count_records_sql($enrolsql,[$adminrecord->id]);
         $courestypes_names = array('2'=>get_string('classroom','local_courses'),'3'=>get_string('elearning','local_courses'), '4'=> get_string('learningplan','local_courses'), '5' => get_string('program','local_courses'), '6' => get_string('certification','local_courses'));
        $text_class = array('2'=>'classroom','3'=>'elearning', '4'=> 'learningpath', '5' => 'program', '6' => 'certification');
        $courestypes = explode(',', $adminrecord->open_identifiedas);
        $displayed_names = array();
        foreach ($courestypes as $key => $courestype){
            $displayed_names[] = $courestypes_names[$courestype];
        }
        $displayed_names = implode(',' ,$displayed_names);
        $details['manage'] = 1;
        $details['completed'] = $completedcount;
        $details['enrolled'] = $enrolledcount;
        $details['type'] = $displayed_names;
    } else {
        $ccsql = "SELECT * from {course_completions} where course = ? AND userid = ?";
        $userrecord = $DB->get_record_sql($ccsql, [$courseid, $USER->id]);
        // $courserecord = $DB->get_record('course', ['id'=>$courseid]);
        $selectsql = "select c.*, ra.timemodified ";

        $fromsql = " from {course} c ";
        
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
        }
        $joinsql .= " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
        JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ? ";
        $wheresql = " where 1 = 1 AND c.id = ? ";
        $courserecord = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$USER->id, $courseid], IGNORE_MULTIPLE);
        if ($courserecord->selfenrol == 1 && $courserecord->approvalreqd == 0) {
            $enrollmentbtn = '<a href="javascript:void(0);" data-action="courseselfenrol'.$courseid.'" class="courseselfenrol enrolled'.$courseid.'" onclick ="(function(e){ require(\'local_catalog/courseinfo\').test({selector:\'courseselfenrol'.$courseid.'\', courseid:'.$courseid.', enroll:1}) })(event)"><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('enroll','local_catalog').'</button></a>';
        } elseif ($courserecord->selfenrol == 1 && $courserecord->approvalreqd == 1) {
            $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('requestforenroll','local_classroom'). ' title = ' .get_string('requestforenroll','local_classroom'). ' onclick="(function(e){ require(\'local_request/requestconfirm\').init({action:\'add\', componentid: '.$courserecord->id.', component:\'elearning\',componentname:\''.$courserecord->fullname.'\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('requestforenroll','local_classroom').'</button></a>';
        } else {
            $enrollmentbtn ='-';
        }
        $details['manage'] = 0;
        $details['status'] = (!is_null($userrecord->timecompleted)) ? get_string('completed', 'local_onlinetests'):get_string('pending', 'local_onlinetests');
        $details['enrolled'] = ($courserecord->timemodified) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $courserecord->timemodified):$enrollmentbtn;
        $details['completed'] = ($courserecord->timecompleted) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $courserecord->timecompleted): '-';
    }
    
    return $details;
}
function local_courses_request_dependent_query($aliasname){
    $returnquery = " WHEN ({$aliasname}.compname LIKE 'elearning') THEN (SELECT fullname from {course} WHERE id = {$aliasname}.componentid) ";
    return $returnquery;
}

function get_enrolledusers($courseid){
    global $DB, $USER, $OUTPUT, $CFG;

    $sql = "SELECT ue.id, u.id as userid, u.firstname, u.lastname, u.email, u.open_employeeid, 
            cc.timecompleted
            FROM {course} c
            JOIN {course_categories} cat ON cat.id = c.category
            JOIN {enrol} e ON e.courseid = c.id AND 
                        (e.enrol = 'manual' OR e.enrol = 'self') 
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
            JOIN {local_costcenter} lc ON lc.id = u.open_path
            JOIN {role_assignments} as ra ON ra.userid = u.id
            JOIN {context} AS cxt ON cxt.id=ra.contextid AND cxt.contextlevel = 50 AND cxt.instanceid=c.id
            JOIN {role} as r ON r.id = ra.roleid AND r.shortname = 'employee'
            LEFT JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid 
            WHERE c.id = :courseid ";

    $params = array();
    $params['courseid'] = $courseid;

	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context($courseid);
    
    if(!is_siteadmin()){

      $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');

    }

    $courseusers = $DB->get_records_sql($sql , $params);

    $userslist = array();
    if($courseusers){
        $userslist['usersexists'] = true;
        $certificateid = $DB->get_field('course', 'open_certificateid', array('id'=>$courseid));
        if($certificateid){
            $certid = $DB->get_field('tool_certificate_issues', 'code', array('moduleid'=>$courseid->id,'userid'=>$enroluser->userid,'moduletype'=>'course'));
            $userslist['certid'] = $certid;
        }else{
            $userslist['certid'] = null;
        }
        $userslist['courseid'] = $courseid;
        $userslist['configpath'] = $CFG->wwwroot;
        foreach ($courseusers as $enroluser) {
            $userinfo = array();
            $userinfo['userid'] = $enroluser->userid;
            $userinfo['employeename'] = $enroluser->firstname.' '.$enroluser->lastname;
            $userinfo['employeeid'] = $enroluser->open_employeeid;
            $userinfo['email'] = $enroluser->email;
            if($enroluser->timecompleted){
                $userinfo['completiondate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i a', $enroluser->timecompleted);
            }else{
                $userinfo['completiondate'] = null;
            }
            $userslist['userdata'][] = $userinfo;
        }
    }else{
        $userslist['usersexists'] = false;
    }

    echo $OUTPUT->render_from_template('local_courses/enrolledusersview', $userslist);

}
/**
 * Serve the new course form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_custom_courseevidence_form($args){
    global $DB,$CFG,$PAGE;
    $args = (object) $args;
    $o = '';

    $params = array(
        'courseid' => $args->courseid,
        'userid' => $args->userid,
    );
    $serialiseddata = json_decode($args->jsonformdata);
    parse_str($serialiseddata, $formdata);
    $mform = new custom_courseevidence_form(null, $params, 'post', '', null, true,$formdata);
   
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
function local_courses_render_navbar_output() {
    global $PAGE;
    // $PAGE->requires->jquery();
    $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'load');
}
function local_courses_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'files_filemanager') {
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
    $file = $fs->get_file($context->id, 'local_courses', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_file($file, $filename, null, 0, false, 0, $options);
}
/**
 * Serve the new course form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_courses_output_fragment_custom_selfcompletion_form($args){
    global $DB,$CFG,$PAGE;
    $args = (object) $args;
    
    return get_string('selfcompletionconfirm', 'local_courses',$args->coursename);
}
function local_courses_output_fragment_course_type($args) {
    global $CFG, $DB;

    $args = (object) $args;
    $context = $args->context;
    $coursetypeid = $args->coursetypeid;
    $o = '';
    $formdata = [];

    $o = '';
    if (isset($args->jsonformdata) && (!empty($args->jsonformdata))) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    if (empty($formdata) && !empty($coursetypeid)) {
        $data = $DB->get_record('local_course_types', array('id'=>$coursetypeid));
        $formdata = new stdClass();
        $formdata->id = $data->id;
        $costcenterdata = $DB->get_record('local_costcenter', array('id'=>$data->orgid));
        $formdata->name = $data->name;
        $formdata->shortname = $data->shortname;
        $formdata->open_costcenterid=$data->orgid;
        $formdata->orgname = $costcenterdata->fullname;

    }

    $params = array(
        'orgid' => $formdata->open_costcenterid,
        'id' => $coursetypeid,
        'name' => $formdata->name,
        'shortname' => $formdata->shortname,
        'orgname' =>$formdata->orgname,
        'contextid' => $context
    );
    $mform = new local_courses\form\coursetype_form(null, $params, 'post', '', null, true, (array)$formdata);
    $mform->set_data($formdata);

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

/**
    * function get_listof_coursetypes
    * @return  array coursetypes
*/
function get_listof_coursetypes($stable, $filterdata) {
    global $DB, $CFG, $OUTPUT, $PAGE ,$USER;

    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $allcoursetypes=$DB->get_records('local_course_types');
    $coursesContext = array(
        "result" => $allcoursetypes );

    return $coursesContext;
}
function local_courses_search_page_filter_element(&$filterelements){
    global $CFG;
    if(file_exists($CFG->dirroot.'/local/search/lib.php')){
        require_once($CFG->dirroot.'/local/search/lib.php');
        $filterelements['elearning'] = ['code' => 'elearning', 'name' => 'Courses', 'tagitemshortname' => 'elearning', 'count' => local_search_get_coursecount_for_modules([['type' => 'moduletype', 'values' => ['elearning']]])];
    }
}
function local_courses_enabled_search(){
    return ['pluginname' => 'local_courses', 'templatename' => 'local_courses/searchpagecontent', 'type' => elearning];
}
function  local_courses_applicable_filters_for_search_page(&$filterapplicable){
    $filterapplicable[elearning] = ['learningtype', 'status', 'categories', 'level', 'skill'];
}

function course_thumbimage($course) {
    // print_r($course); die;
    global $DB,$CFG;
    if (file_exists($CFG->dirroot.'/local/includes.php')) {
        require_once($CFG->dirroot.'/local/includes.php');
        $includes = new \user_course_details();
    }

    if (file_exists($CFG->dirroot.'/local/includes.php')) {
        $courseimage = $includes->course_summary_files($course);
        if (is_object($courseimage)) {
            $imageurl = $courseimage->out();
        } else {
            $imageurl = $courseimage;
        }
    }
    return $imageurl;
}
