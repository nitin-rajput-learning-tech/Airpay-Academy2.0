<?php

require_once('../../config.php');
global $CFG,$DB,$OUTPUT,$PAGE,$USER;

require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/local/courses/course_form.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');


$id = optional_param('id', 0, PARAM_INT); // Course id.
$categoryid = optional_param('category', 0, PARAM_INT); // Course category - can be changed in edit form.
$returnto = optional_param('returnto', 0, PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL); // A return URL. returnto must also be set to 'url'.

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->js('/local/courses/js/custom.js');
$PAGE->requires->js('/local/courses/js/select2.full.js',true);
$PAGE->requires->css('/local/courses/css/select2.min.css');


if (!empty($id)) {
    $returnurl = new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $id));
} else {
    $returnurl = new moodle_url($CFG->wwwroot . '/course/');
}
$PAGE->set_pagelayout('standard');
if ($id) {
    $pageparams = array('id' => $id);
} else {
    $pageparams = array('category' => $categoryid);
}
if ($returnto !== 0) {
    $pageparams['returnto'] = $returnto;
    if ($returnto === 'url' && $returnurl) {
        $pageparams['returnurl'] = $returnurl;
    }
}
$PAGE->set_url('/local/courses/edit.php', $pageparams);
// Basic access control checks.
if ($id) {
    // Editing course.
    if ($id == SITEID){
        // Don't allow editing of  'site course' using this from.
        print_error('cannoteditsiteform');
    }

    // Login to the course and retrieve also all fields defined by course format.
    $course = get_course($id);
    require_login($course);
    $course = course_get_format($course)->get_course();

    $category = $DB->get_record('course_categories', array('id'=>$course->category), '*', MUST_EXIST);
    $coursecontext = context_course::instance($course->id);
    require_capability('moodle/course:update', $coursecontext);

} else if ($categoryid) {
    // Creating new course in this category.
    $course = null;
    require_login();
    $category = $DB->get_record('course_categories', array('id'=>$categoryid), '*', MUST_EXIST);
    $catcontext = context_coursecat::instance($category->id);
    require_capability('moodle/course:create', $catcontext);
    $PAGE->set_context($catcontext);

} else {
    require_login();
     $coursemanageurl = new moodle_url('/local/courses/courses.php',array('s'=>1));
        redirect($coursemanageurl);
}

// Prepare course and the editor.
$editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes'=>$CFG->maxbytes, 'trusttext'=>false, 'noclean'=>true);
$overviewfilesoptions = course_overviewfiles_options($course);
if (!empty($course)) {
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
// First create the form.
local_costcenter_set_costcenter_path((array)$course);
$args = array(
    'course' => $course,
    'category' => $category,
    'editoroptions' => $editoroptions,
    'returnto' => $returnto,
    'returnurl' => $returnurl,
    'get_coursedetails'=>$get_coursedetails
);

$editform = new custom_course_form(null, $args);
if ($editform->is_cancelled()) {
    // The form has been cancelled, take them back to what ever the return to is.
    $coursemanageurl = new moodle_url('/local/courses/courses.php',array('s'=>1));
    redirect($coursemanageurl);
} else if ($data = $editform->get_data()) {
    $cat=data_submitted();/** This Line is added to get the data which are submitted by ajax**/
    $category_id=$cat->category;
	global $USER,$DB;
    // Process data if submitted.
    if (empty($course->id)) {
        // In creating the course.
        $data->open_identifiedas=implode(',',$data->open_identifiedas);
        $data->category = $category_id;
        local_costcenter_get_costcenter_path($data);
        $course = create_course($data, $editoroptions);

        // Get the context of the newly created course.
        $context = context_course::instance($course->id, MUST_EXIST);
        $course_det = new stdClass();
        $sql = $DB->get_field('user','firstname', array('id' =>$USER->id));
        $course_det->userid = $sql;
        $sql1 = $DB->get_field('course','fullname', array('id' =>$course->id));
        $course_det->courseid = $sql1;
        $description=get_string('description','local_courses',$course_det);
        $logs = new local_courses\action\insert();
        $insert_logs = $logs->local_custom_logs('insert', 'course', $description, $course->id);
        // The URL to take them to if they chose save and display.
        $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
    }else {
        // Save any changes to the files used in the editor.
        $code=implode(',',$data->open_identifiedas);
        $data->open_identifiedas=implode(',',$data->open_identifiedas);
        $data->category = $category_id;
        local_costcenter_get_costcenter_path($data);
        update_course($data, $editoroptions);
        $course_detail = new stdClass();
        $sql = $DB->get_field('user','firstname', array('id' =>$USER->id));
        $course_detail->userid = $sql;
        $sql1 = $DB->get_field('course','fullname', array('id' =>$course->id));
        $course_detail->courseid = $sql1;
        $description=get_string('desc','local_courses',$course_detail);
        $logs = new local_courses\action\insert();
        $insert_logs = $logs->local_custom_logs('update', 'course', $description, $course->id);
          // Set the URL to take them too if they choose save and display.
        $courseurl = new moodle_url('/local/courses/courses.php', array('s'=>1));
    }
    if (isset($data->saveanddisplay)) {
        // Redirect user to newly created/updated course.
        redirect(new moodle_url('/local/courses/courses.php', array('s'=>1)));
    } else {
        // Save and return. Take them back to wherever.
        redirect(new moodle_url('/local/courses/courses.php', array('s'=>1)));
    }
}

// Print the form.

$site = get_site();

$streditcoursesettings = get_string("editcoursesettings");
$straddnewcourse = get_string("addnewcourse");
$stradministration = get_string("administration");
$strcategories = get_string("categories");

if (!empty($course->id)) {
    // Navigation note: The user is editing a course, the course will exist within the navigation and settings.
    // The navigation will automatically find the Edit settings page under course navigation.
    $pagedesc = $streditcoursesettings;
    $title = $streditcoursesettings;
    $fullname = $course->fullname;
} else {
    // The user is adding a course, this page isn't presented in the site navigation/admin.
    // Adding a new course is part of course category management territory.
    // We'd prefer to use the management interface URL without args.
    $managementurl = new moodle_url('/course/management.php');
    // These are the caps required in order to see the management interface.
    $managementcaps = array('local/courses:manage', 'moodle/course:create');
    if ($categoryid && !has_any_capability($managementcaps, (new \local_courses\lib\accesslib())::get_module_context())) {
        // If the user doesn't have either manage caps then they can only manage within the given category.
        $managementurl->param('categoryid', $categoryid);
    }
    // Because the course category management interfaces are buried in the admin tree and that is loaded by ajax
    // we need to manually tell the navigation we need it loaded. The second arg does this.
    navigation_node::override_active_url($managementurl, true);

    $pagedesc = $straddnewcourse;
    $title = "$site->shortname: $straddnewcourse";
    $fullname = $site->fullname;
    $PAGE->navbar->ignore_active();
    $PAGE->navbar->add(get_string('courses'),new moodle_url('/local/courses/courses.php'));
    $PAGE->navbar->add($pagedesc);
}

$PAGE->set_title($title);
$PAGE->set_heading($fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($pagedesc);

$editform->display();

echo $OUTPUT->footer();
