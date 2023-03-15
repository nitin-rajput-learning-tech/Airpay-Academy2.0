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

if (file_exists($CFG->dirroot . '/local/costcenter/lib.php')) {
    require_once($CFG->dirroot . '/local/costcenter/lib.php');
}
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/completion/completion_completion.php');

use \local_onlineexams\form\custom_onlineexams_form as custom_onlineexams_form;
use \local_courses\form\custom_courseevidence_form as custom_courseevidence_form;


defined('MOODLE_INTERNAL') || die();


/**
 * Serve the new course form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_onlineexams_output_fragment_custom_onlineexams_form($args)
{
    global $DB, $CFG, $PAGE;
    $args = (object) $args;
    $context = $args->context;
    $renderer = $PAGE->get_renderer('local_onlineexams');
    $courseid = $args->courseid;
    $o = '';
    if ($courseid) {
        $course = get_course($courseid);
        $course = course_get_format($course)->get_course();
        $category = $DB->get_record('course_categories', array('id' => $course->category), '*', MUST_EXIST);
        $coursecontext = context_course::instance($course->id);
        //require_capability('moodle/course:update', $coursecontext);
    } else {
        $category = $CFG->defaultrequestcategory;
    }
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $get_coursedetails = $DB->get_record('course', array('id' => $course->id));
        if ($get_coursedetails->format == 'singleactivity') {
            $moduleinfoSql = "SELECT q.id, q.attempts,q.timelimit,q.graceperiod,q.overduehandling,q.browsersecurity, gi.grademax, gi.gradepass ,q.timeopen,q.timeclose
                FROM {quiz} as q 
                JOIN {grade_items} as gi ON gi.iteminstance = q.id AND gi.itemtype ='mod' AND gi.itemmodule = 'quiz' 
                WHERE q.course=:courseid ";
            $moduleinfo = $DB->get_record_sql($moduleinfoSql, array('courseid' => $courseid));
            // print_r($moduleinfo);exit;
            $maxgrade = round($moduleinfo->grademax, 2);
            $gradepass = round($moduleinfo->gradepass, 2);
            $attempts = $moduleinfo->attempts;
            $course->gradepass = $gradepass;
            $course->attempts = $attempts;
            $course->timeopen = $moduleinfo->timeopen;
            $course->timeclose = $moduleinfo->timeclose;
            $course->timelimit = $moduleinfo->timelimit;
            $course->overduehandling =$moduleinfo->overduehandling;
            $course->graceperiod =$moduleinfo->graceperiod;
            $course->browsersecurity = $moduleinfo->browsersecurity;
            // }else{
        }
    if (!empty($course) && empty($formdata)) {
        $formdata = clone $course;
        $formdata = (array)$formdata;
    }
// print_r($formdata);die;
    if ($courseid > 0) {
        $heading = get_string('updatecourse', 'local_courses');
        $collapse = false;
        $data = $DB->get_record('course', array('id' => $courseid));
    }
    // Populate course tags.
    $course->tags = local_tags_tag::get_item_tags_array('local_courses', 'courses', $course->id);
    $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes, 'trusttext' => false, 'noclean' => true, 'autosave' => false);
    $overviewfilesoptions = course_overviewfiles_options($course);
    if ($courseid) {
        // Add context for editor.
        $editoroptions['context'] = $coursecontext;
        $editoroptions['subdirs'] = file_area_contains_subdirs($coursecontext, 'course', 'summary', 0);
        $course = file_prepare_standard_editor($course, 'summary', $editoroptions, $coursecontext, 'course', 'summary', 0);
        if ($overviewfilesoptions) {
            file_prepare_standard_filemanager($course, 'overviewfiles', $overviewfilesoptions, $coursecontext, 'course', 'overviewfiles', 0);
        }
        
    } else {
        // Editor should respect category context if course context is not set.
        $editoroptions['context'] = $catcontext;
        $editoroptions['subdirs'] = 0;
        $course = file_prepare_standard_editor($course, 'summary', $editoroptions, null, 'course', 'summary', null);
        if ($overviewfilesoptions) {
            file_prepare_standard_filemanager($course, 'overviewfiles', $overviewfilesoptions, null, 'course', 'overviewfiles', 0);
        }
    }
    if ($formdata['open_points'] > 0) {
        $formdata['open_enablepoints'] = true;
    }
    $params = array(
        'course' => $course,
        'category' => $category,
        'editoroptions' => $editoroptions,
        'returnto' => $returnto,
        'get_coursedetails' => $get_coursedetails,
        'form_status' => $args->form_status,
        'costcenterid' => $data->open_path,
    );
    local_costcenter_set_costcenter_path($formdata);
    $mform = new custom_onlineexams_form(null, $params, 'post', '', null, true, $formdata);
    // Used to set the courseid.
    $formdata['shortname_static'] = $formdata['shortname'];
    $mform->set_data($formdata);

    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) > 2) {
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
    $formstatusview = new \local_onlineexams\output\form_status($formstatus);
    $o .= $renderer->render($formstatusview);
    // $o = $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'getCatlist');
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

/**
 * function get_listof_courses
 * @todo all courses based  on costcenter / department
 * @param object $stable limit values
 * @param object $filterdata filterdata
 * @return  array courses
 */

function get_listof_onlineexams($stable, $filterdata,$options)
{
    global $CFG, $DB, $OUTPUT, $USER;
    $options=json_decode($options);
    $core_component = new core_component();
    //require_once($CFG->libdir. '/coursecatlib.php');
    require_once($CFG->dirroot . '/course/renderer.php');
    require_once($CFG->dirroot . '/local/costcenter/lib.php');
    require_once($CFG->dirroot . '/enrol/locallib.php');
    $autoenroll_plugin_exist = $core_component::get_plugin_directory('enrol', 'auto');
    if (!empty($autoenroll_plugin_exist)) {
        require_once($CFG->dirroot . '/enrol/auto/lib.php');
    }
    $maincheckcontext = $categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context();
    $statustype = $stable->status;
    $totalcostcentercount = $stable->costcenterid;
    $totaldepartmentcount = $stable->departmentid;
    $departmentsparams = array();
    $subdepartmentsparams = array();
    $organizationsparams = array();
    $userorg = array();
    $userdep = array();
    $locationsparams = $hrmsrolessparams = [];
    $filtercategoriesparams = array();
    $filtercoursesparams = array();
    $chelper = new coursecat_helper();
    $selectsql = "SELECT c.id ,c.fullname, c.shortname, c.category, c.summary, c.format ,c.selfenrol,c.open_points,c.open_path, c.open_identifiedas, c.visible, c.open_skill,c.open_categoryid FROM {course} AS c";
    $countsql  = "SELECT count(c.id) FROM {course} AS c ";
    $open_path = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'c.open_path');
    $formsql = " JOIN {local_costcenter} AS co ON co.path = c.open_path
                     JOIN {course_categories} AS cc ON cc.id = c.category
                     ";

    $formsql .= " AND c.id > 1  $open_path";
    $formsql .= " AND c.open_module ='online_exams'  $open_path AND open_coursetype = 1 ";
    $params = array();
    if (isset($filterdata->search_query) && trim($filterdata->search_query) != '') {
        $formsql .= " AND c.fullname LIKE :search";
        // $searchparams = array('search' => '%'.trim($filterdata->search_query).'%');
        $params['search'] = '%' . trim($filterdata->search_query) . '%';
    } else {
        $searchparams = array();
    }
    if (!empty($filterdata->categories)) {
        $filtercategories = explode(',', $filterdata->categories);
        list($filtercategoriessql, $filtercategoriesparams) = $DB->get_in_or_equal($filtercategories, SQL_PARAMS_NAMED, 'categories', true, false);
        $params = array_merge($params, $filtercategoriesparams);
        $formsql .= " AND c.open_categoryid $filtercategoriessql";
    }
    if (!empty($filterdata->onlineexams)) {
        $filteronlineexams = explode(',', $filterdata->onlineexams);
        list($filteronlineexamssql, $filteronlineexamsparams) = $DB->get_in_or_equal($filteronlineexams, SQL_PARAMS_NAMED, 'onlineexams', true, false);
        $params = array_merge($params, $filteronlineexamsparams);
        $formsql .= " AND c.id $filteronlineexamssql";
    }
    if (!empty($filterdata->filteropen_costcenterid)) {

        $filteropen_costcenterid = explode(',', $filterdata->filteropen_costcenterid);
        $orgsql = [];
        foreach ($filteropen_costcenterid as $organisation) {
            $orgsql[] = " concat('/',c.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
        }
        if (!empty($orgsql)) {
            $formsql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_department)) {
        $filteropen_department = explode(',', $filterdata->filteropen_department);

        $deptsql = [];
        foreach ($filteropen_department as $department) {
            $deptsql[] = " concat('/',c.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%/' . $department . '/%';
        }
        if (!empty($deptsql)) {
            $formsql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_subdepartment)) {
        $subdepartments = explode(',', $filterdata->filteropen_subdepartment);

        $subdeptsql = [];
        foreach ($subdepartments as $subdepartment) {
            $subdeptsql[] = " concat('/',c.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%/' . $subdepartment . '/%';
        }
        if (!empty($subdeptsql)) {
            $formsql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_level4department)) {
        $subsubdepartments = explode(',', $filterdata->filteropen_level4department);

        $subsubdeptsql = [];
        foreach ($subsubdepartments as $department4level) {
            $subsubdeptsql[] = " concat('/',c.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%/' . $department4level . '/%';
        }
        if (!empty($subsubdeptsql)) {
            $formsql .= " AND ( " . implode(' OR ', $subsubdeptsql) . " ) ";
        }
    }
    if (!empty($filterdata->filteropen_level5department)) {
        $subsubsubdepartments = explode(',', $filterdata->filteropen_level5department);
        $subsubsubdeptsql = [];
        foreach ($subsubsubdepartments as $department5level) {
            $subsubsubdeptsql[] = " concat('/',c.open_path,'/') LIKE :department5levelparam_{$department5level}";
            $params["department5levelparam_{$department5level}"] = '%/' . $department5level . '/%';
        }
        if (!empty($subsubsubdeptsql)) {
            $formsql .= " AND ( " . implode(' OR ', $subsubsubdeptsql) . " ) ";
        }
    }
    if (!empty($filterdata->hrmsrole)) {
        $hrmsroles = explode(',', $filterdata->hrmsrole);
        list($hrmsrolessql, $hrmsrolessparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole', true, false);
        $params = array_merge($params, $hrmsrolessparams);
        $formsql .= " AND c.open_hrmsrole {$hrmsrolessql} ";
    }
    if (!empty($filterdata->location)) {
        $locations = explode(',', $filterdata->location);
        list($locationsql, $locationsparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location', true, false);
        $params = array_merge($params, $locationsparams);
        $formsql .= " AND c.open_location {$locationsql} ";
    }


    if (!empty($filterdata->status)) {
        $status = explode(',', $filterdata->status);
        if (!(in_array('active', $status) && in_array('inactive', $status))) {
            if (in_array('active', $status)) {
                $formsql .= " AND c.visible = 1 ";
            } else if (in_array('inactive', $status)) {
                $formsql .= " AND c.visible = 0 ";
            }
        }
    }

    if (!empty($filterdata->coursetype)) {
        $filtercoursetype = explode(',', $filterdata->coursetype);
        list($filtercoursetypesql, $filtercoursetypeparams) = $DB->get_in_or_equal($filtercoursetype, SQL_PARAMS_NAMED, 'coursetype', true, false);
        $params = array_merge($params, $filtercoursetypeparams);
        $formsql .= " AND c.open_identifiedas $filtercoursetypesql";
    }
    // $params = array_merge($searchparams, $userorg, $userdep, $filtercategoriesparams,$filtercoursesparams, $departmentsparams, $subdepartmentsparams, $organizationsparams, $hrmsrolessparams, $locationsparams,$filterparams,$filtercoursetypeparams);

    $totalonlineexams = $DB->count_records_sql($countsql . $formsql, $params);

    $formsql .= " ORDER BY c.id DESC";
    $onlineexams = $DB->get_records_sql($selectsql . $formsql, $params, $stable->start, $stable->length);
    $ratings_plugin_exist = $core_component::get_plugin_directory('local', 'ratings');
    $onlineexamslist = array();
    $employeerole = $DB->get_field('role', 'id', array('shortname' => 'employee'));
    if (!empty($onlineexams)) {
        $count = 0;
        foreach ($onlineexams as $key => $course) {

            $course = (array)$course;

            local_costcenter_set_costcenter_path($course);

            $course = (object)$course;

            $course_in_list = new core_course_list_element($course);
            $context =  \context_course::instance($course->id);
            $categoryname = $DB->get_field('local_custom_category', 'fullname', array('id' => $course->open_categoryid));
            $departmentcount = 1;
            $subdepartmentcount = 1;

            $params = array('courseid' => $course->id, 'employeerole' => $employeerole);

            $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');

            $enrolledusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                WHERE c.id = :courseid AND ra.roleid = :employeerole $costcenterpathconcatsql AND c.open_module= 'online_exams' AND c.open_coursetype = 1 ";
            $enrolled_count =  $DB->count_records_sql($enrolledusersssql, $params);


            $completedusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                JOIN {course_modules} as cm ON cm.course = c.id 
                                JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id AND u.id = cmc.userid
                                WHERE c.id = :courseid AND ra.roleid = :employeerole AND cmc.completionstate > 0 $costcenterpathconcatsql AND c.open_module= 'online_exams' AND c.open_coursetype = 1 ";

            $completed_count = $DB->count_records_sql($completedusersssql, $params);

            $coursename = $course->fullname;
            $shortname = $course->shortname;

            $format = $course->format;

            if (strlen($coursename) > 35) {
                $coursenameCut = substr($coursename, 0, 35) . "...";
                $onlineexamslist[$count]["coursenameCut"] = \local_costcenter\lib::strip_tags_custom($coursenameCut);
            }
            $catname = $categoryname;
            $catnamestring = strlen($catname) > 12 ? substr($catname, 0, 12) . "..." : $catname;
            $displayed_names = '<span class="pl-10 ' . $course->coursetype . '">' . $course->coursetype . '</span>';

            $courestypes_names = array('2' => get_string('classroom', 'local_onlineexams'), '3' => get_string('elearning', 'local_onlineexams'), '4' => get_string('learningplan', 'local_onlineexams'), '5' => get_string('program', 'local_onlineexams'), '6' => get_string('certification', 'local_onlineexams'));
            if ($ratings_plugin_exist) {
                require_once($CFG->dirroot . '/local/ratings/lib.php');
                $ratingenable = True;
                $avgratings = get_rating($course->id, 'local_onlineexams');
                $rating_value = $avgratings->avg == 0 ? 'N/A' : $avgratings->avg/*/2*/;
            } else {
                $ratingenable = False;
                $rating_value = 'N/A';
            }
            $classname = '\local_tags\tags';
            if (class_exists($classname)) {
                $tags = new $classname;

                $tagstring = $tags->get_item_tags($component = 'local_onlineexams', $itemtype = 'onlineexams', $itemid = $course->id, $contextid = context_course::instance($course->id)->id, $arrayflag = 0, $more = 0);
                $tagstringtotal = $tagstring;
                if ($tagstring == "") {
                    $tagstring = 'N/A';
                } else {
                    $tagstring = strlen($tagstring) > 35 ? substr($tagstring, 0, 35) . '...' : $tagstring;
                }
                $tagenable = True;
            } else {
                $tagenable = False;
                $tagstring = '';
                $tagstringtotal = $tagstring;
            }

            if ($course->open_skill > 0) {
                $skill = $DB->get_field('local_skill', 'name', array('id' => $course->open_skill));
                if ($skill) {
                    $skillname = $skill;
                } else {
                    $skillname = 'N/A';
                }
            } else {
                $skillname = 'N/A';
            }
            $onlineexamslist[$count]["coursename"] = \local_costcenter\lib::strip_tags_custom($coursename);
            $onlineexamslist[$count]["shortname"] =  \local_costcenter\lib::strip_tags_custom($shortname);
            $onlineexamslist[$count]["skillname"] = \local_costcenter\lib::strip_tags_custom($skillname);
            $onlineexamslist[$count]["ratings_value"] = $rating_value;
            $onlineexamslist[$count]["ratingenable"] = $ratingenable;
            $onlineexamslist[$count]["tagstring"] = \local_costcenter\lib::strip_tags_custom($tagstring);
            $onlineexamslist[$count]["tagstringtotal"] = $tagstringtotal;
            $onlineexamslist[$count]["tagenable"] = $tagenable;
            $onlineexamslist[$count]["catname"] = \local_costcenter\lib::strip_tags_custom($catname);
            $onlineexamslist[$count]["catnamestring"] = \local_costcenter\lib::strip_tags_custom($catnamestring);
            $onlineexamslist[$count]["enrolled_count"] = $enrolled_count;
            $onlineexamslist[$count]["courseid"] = $course->id;
            $onlineexamslist[$count]["completed_count"] = $completed_count;
            $onlineexamslist[$count]["points"] = $course->open_points != NULL ? $course->open_points : 0;
            $onlineexamslist[$count]["coursetype"] = \local_costcenter\lib::strip_tags_custom($displayed_names);
            $onlineexamslist[$count]["course_class"] = $course->visible ? 'active' : 'inactive';
            $onlineexamslist[$count]["grade_view"] = ((has_capability(
                'local/onlineexams:grade_view',
                $context
            ) || is_siteadmin()) && has_capability('local/onlineexams:manage', $context)) ? true : false;
            $onlineexamslist[$count]["request_view"] = ((has_capability('local/request:approverecord', $maincheckcontext)) || is_siteadmin()) ? true : false;

            $onlineexamsummary = \local_costcenter\lib::strip_tags_custom($chelper->get_course_formatted_summary(
                $course_in_list,
                array('overflowdiv' => false, 'noclean' => false, 'para' => false)
            ));
            $summarystring = strlen($onlineexamsummary) > 100 ? substr($onlineexamsummary, 0, 100) . "..." : $onlineexamsummary;
            // $summarystring = strlen($onlineexamsummary) > 100 ? substr($onlineexamsummary, 0, 100)."<span id='dots'>...</span><span id='more' style='display: none;'>".substr($onlineexamsummary, 100,strlen($onlineexamsummary)).'</span> <a onclick="myFunction()" id="myBtn">Read more</a>' : $onlineexamsummary;
            $onlineexamslist[$count]["onlineexamsummary"] = \local_costcenter\lib::strip_tags_custom($summarystring);
            $onlineexamslist[$count]["fullonlineexamsummary"] = $onlineexamsummary;
            $onlineexamslist[$count]["format"] = $format;


            $course =  (array)$course;
            local_costcenter_set_costcenter_path($course);
            $course = (object)$course;
            if ($course->open_department > 0) {
                $onlineexamslist[$count]["open_department"] = $DB->get_records_sql_menu('SELECT id,fullname 
                FROM {local_costcenter}
                WHERE id IN(' . $course->open_department . ')');
            } else {
                $onlineexamslist[$count]["open_department"] = get_string('all');
            }
            if ($course->open_subdepartment > 0) {
                $onlineexamslist[$count]["open_subdepartment"] = $DB->get_records_sql_menu('SELECT id,fullname 
                FROM {local_costcenter}
                WHERE id IN(' . $course->open_subdepartment . ')');
            } else {
                $onlineexamslist[$count]["open_subdepartment"] = get_string('all');
            }
            if ($course->open_level4department > 0) {
                $onlineexamslist[$count]["open_level4department"] = $DB->get_records_sql_menu('SELECT id,fullname 
               FROM {local_costcenter}
               WHERE id IN(' . $course->open_level4department . ')');
            } else {
                $onlineexamslist[$count]["open_level4department"] = get_string('all');
            }
            if ($course->open_level5department > 0) {
                $onlineexamslist[$count]["open_level5department"] = $DB->get_records_sql_menu('SELECT id,fullname 
               FROM {local_costcenter}
               WHERE id IN(' . $course->open_level5department . ')');
            } else {
                $onlineexamslist[$count]["open_level5department"] = get_string('all');
            }

            if ($course->selfenrol == 1) {
                $onlineexamslist[$count]["selfenrol"] = get_string('yes');
            } else {
                $onlineexamslist[$count]["selfenrol"] = get_string('no');
            }

            //course image
            if (file_exists($CFG->dirroot . '/local/includes.php')) {
                require_once($CFG->dirroot . '/local/includes.php');
                $includes = new user_course_details();
                $courseimage = $includes->course_summary_files($course);
                if (is_object($courseimage)) {
                    $onlineexamslist[$count]["courseimage"] = $courseimage->out();
                } else {
                    $onlineexamslist[$count]["courseimage"] = $courseimage;
                }
            }

            $enrolid = $DB->get_field('enrol', 'id', array('enrol' => 'manual', 'courseid' => $course->id));

            if (has_capability('local/onlineexams:enrol', $maincheckcontext) && has_capability('local/onlineexams:manage', $maincheckcontext)) {
                $onlineexamslist[$count]["enrollusers"] = $CFG->wwwroot . "/local/onlineexams/onlineexamsenrol.php?id=" . $course->id . "&enrolid=" . $enrolid;
            }
            if (has_capability('local/onlineexams:view', $context) || is_enrolled($context)) {
                $onlineexamslist[$count]["courseurl"] = $CFG->wwwroot . "/course/view.php?id=" . $course->id;
            } else {
                $onlineexamslist[$count]["courseurl"] = "#";
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                $onlineexamslist[$count]["grade_view"] = false;
                $onlineexamslist[$count]["request_view"] = false;
            }


            if (has_capability('local/onlineexams:update', $context) && has_capability('local/onlineexams:manage', $context)) {
                if($options->viewType=='table'){
                $courseedit = html_writer::link('javascript:void(0)', html_writer::tag('i', '', array('class' => 'fa fa-pencil ')), array('title' => get_string('edit'), 'alt' => get_string('edit'), 'data-action' => 'createcoursemodal', 'class' => 'createcoursemodal dropdown-item', 'data-value' => $course->id, 'onclick' => '(function(e){ require("local_onlineexams/onlineexamsAjaxform").init({contextid:' . $context->id . ', component:"local_onlineexams", callback:"custom_onlineexams_form", form_status:0, plugintype: "local", pluginname: "onlineexams", courseid: ' . $course->id . ' }) })(event)'));
                }else{
                    $courseedit = html_writer::link('javascript:void(0)', html_writer::tag('i', '', array('class' => 'fa fa-pencil ')) . get_string('edit'), array('title' => get_string('edit'), 'alt' => get_string('edit'), 'data-action' => 'createcoursemodal', 'class' => 'createcoursemodal dropdown-item', 'data-value' => $course->id, 'onclick' => '(function(e){ require("local_onlineexams/onlineexamsAjaxform").init({contextid:' . $context->id . ', component:"local_onlineexams", callback:"custom_onlineexams_form", form_status:0, plugintype: "local", pluginname: "onlineexams", courseid: ' . $course->id . ' }) })(event)'));
                }
                $onlineexamslist[$count]["editcourse"] = $courseedit;
                if($options->viewType=='table'){
                if ($course->visible) {
                    $icon = 't/hide';
                    $string = get_string('make_active', 'local_onlineexams');
                   // $title = get_string('make_inactive', 'local_onlineexams');
                } else {
                    $icon = 't/show';
                    $string = get_string('make_inactive', 'local_onlineexams');
                    //$title = get_string('make_active', 'local_onlineexams');
                }
            }else{
                if ($course->visible) {
                    $icon = 't/hide';
                    $string = get_string('make_active', 'local_onlineexams');
                    $title = get_string('make_inactive', 'local_onlineexams');
                } else {
                    $icon = 't/show';
                    $string = get_string('make_inactive', 'local_onlineexams');
                    $title = get_string('make_active', 'local_onlineexams');
                }
            }
                $image = $OUTPUT->pix_icon($icon, $title, 'moodle', array('class' => 'iconsmall', 'title' => '')) . $title;
                $params = json_encode(array('coursename' => $coursename, 'onlineexamstatus' => $course->visible));
                $onlineexamslist[$count]["update_status"] .= html_writer::link("javascript:void(0)", $image, array('class' => ' make_inactive dropdown-item', 'data-fg' => "d", 'data-method' => 'course_update_status', 'data-plugin' => 'local_onlineexams', 'data-params' => $params, 'data-id' => $course->id));
                if (!empty($autoenroll_plugin_exist)) {
                    $autoplugin = enrol_get_plugin('auto');
                    $instance = $autoplugin->get_instance_for_course($course->id);
                    if ($instance) {
                        if ($instance->status == ENROL_INSTANCE_DISABLED) {

                            $onlineexamslist[$count]["auto_enrol"] = $CFG->wwwroot . "/enrol/auto/edit.php?courseid=" . $course->id . "&id=" . $instance->id;
                        }
                    }
                }
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                $onlineexamslist[$count]["editcourse"] = '';
                $onlineexamslist[$count]["update_status"] = '';
                $onlineexamslist[$count]["auto_enrol"] = '';
            }

            if (has_capability('local/onlineexams:delete', $context) && has_capability('local/onlineexams:manage', $context)) {
                if($options->viewType=='table'){
                $deleteactionshtml = html_writer::link('javascript:void(0)', $OUTPUT->pix_icon('t/delete', get_string('delete'), 'moodle', array('')), array('class' => "dropdown-item delete_icon", 'title' => get_string('delete'), 'id' => "onlineexams_delete_confirm_" . $course->id, 'onclick' => '(function(e){ require(\'local_onlineexams/onlineexamsAjaxform\').deleteConfirm({action:\'deleteonlineexams\' , id: ' . $course->id . ', name:"' . $coursename . '" }) })(event)'));
                }else{
                $deleteactionshtml = html_writer::link('javascript:void(0)', $OUTPUT->pix_icon('t/delete', get_string('delete'), 'moodle', array('')) . get_string('delete'), array('class' => "dropdown-item delete_icon", 'title' => get_string('delete'), 'id' => "onlineexams_delete_confirm_" . $course->id, 'onclick' => '(function(e){ require(\'local_onlineexams/onlineexamsAjaxform\').deleteConfirm({action:\'deleteonlineexams\' , id: ' . $course->id . ', name:"' . $coursename . '" }) })(event)'));
                }
                $onlineexamslist[$count]["deleteaction"] = $deleteactionshtml;
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                $onlineexamslist[$count]["deleteaction"] = '';
            }

            if (has_capability('local/onlineexams:grade_view', $context) && has_capability('local/onlineexams:manage', $context)) {
                $onlineexamslist[$count]["grader"] =  $CFG->wwwroot . "/grade/report/grader/index.php?id=" . $course->id;
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                unset($onlineexamslist[$count]["grader"]);
            }

            if (has_capability('local/onlineexams:report_view', $context) && has_capability('local/onlineexams:manage', $context)) {
                $onlineexamslist[$count]["activity"] = $CFG->wwwroot . "/report/outline/index.php?id=" . $course->id;
            }
            if ($departmentcount > 1 && !(is_siteadmin())) {
                unset($onlineexamslist[$count]["activity"]);
            }


            if ((has_capability('local/request:approverecord', $maincheckcontext) || is_siteadmin())) {
                $onlineexamslist[$count]["requestlink"] = $CFG->wwwroot . "/local/request/index.php?courseid=" . $course->id;
            }

            if ($departmentcount > 1 && !(is_siteadmin())) {
                unset($onlineexamslist[$count]["requestlink"]);
            }

            $quiz = $DB->get_record('quiz', array("course" => $course->id));
            $gradeitem = $DB->get_record('grade_items', array('iteminstance' => $quiz->id, 'itemmodule' => 'quiz', 'courseid' => $course->id));
				$gradepass = $gradeitem->gradepass;
                $grademax = $gradeitem->grademax;
            // echo $quiz->timeopen;
            // echo "<br/>";
            $onlineexamslist[$count]["examfromdate"] =($quiz->timeopen > 0) ?  date('d-m-Y h:i:s', ($quiz->timeopen)) : 'N/A';
            $onlineexamslist[$count]["examtodate"] = ($quiz->timeclose > 0) ? date('d-m-Y h:i:s', ($quiz->timeclose)) : 'N/A';
            $onlineexamslist[$count]["passgrade"] = ($gradepass) ? round($gradepass, 2) : 'N/A';
            $onlineexamslist[$count]["maxgrade"] = ($grademax > 0) ? round($grademax,2) : 'N/A';
            $onlineexamslist[$count] = array_merge($onlineexamslist[$count], array(
                "actions" => (((has_capability(
                    'local/onlineexams:enrol',
                    $maincheckcontext
                ) || has_capability(
                    'local/onlineexams:update',
                    $context
                ) || has_capability(
                    'local/onlineexams:delete',
                    $context
                ) || has_capability(
                    'local/onlineexams:grade_view',
                    $context
                ) || has_capability(
                    'local/onlineexams:report_view',
                    $context
                )) || is_siteadmin()) && has_capability('local/onlineexams:manage', $maincheckcontext)) ? true : false,
                "enrol" => ((has_capability(
                    'local/onlineexams:enrol',
                    $maincheckcontext
                )  || is_siteadmin()) && has_capability('local/onlineexams:manage', $maincheckcontext)) ? true : false,
                "update" => ((has_capability(
                    'local/onlineexams:update',
                    $context
                ) || is_siteadmin()) && has_capability('local/onlineexams:manage', $context) && has_capability('moodle/course:update', $context)) ? true : false,
                "delete" => ((has_capability(
                    'local/onlineexams:delete',
                    $context
                ) || is_siteadmin()) && has_capability('local/onlineexams:manage', $context) && has_capability('moodle/course:delete', $context)) ? true : false,
                "report_view" => ((has_capability('local/onlineexams:report_view', $context) || is_siteadmin()) && has_capability('local/onlineexams:manage', $context)) ? true : false,
                "actions" => 1
            ));


            $count++;
        }
        $nocourse = false;
        $pagination = false;
    } else {
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

    $onlineexam_plugin_exist = $core_component::get_plugin_directory('local', 'onlineexam');
    if ($onlineexam_plugin_exist) {
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
    $onlineexamsContext = array(
        "hascourses" => $onlineexamslist,
        "nocourses" => $nocourse,
        "totalcourses" => $totalonlineexams,
        "length" => count($onlineexamslist),

    );

    return $onlineexamsContext;
}
function local_onlineexams_leftmenunode()
{
    global $DB, $USER;
    $categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context();
    $coursecatnodes = '';
    if (has_capability('local/onlineexams:view', $categorycontext) || has_capability('local/onlineexams:manage', $categorycontext) || is_siteadmin()) {
        $coursecatnodes .= html_writer::start_tag('li', array('id' => 'id_leftmenu_browseonlineexams', 'class' => 'pull-left user_nav_div browseonlineexams'));
        $onlineexams_url = new moodle_url('/local/onlineexams/index.php');
        $onlineexams = html_writer::link($onlineexams_url, '<i class="fa fa-book"></i><span class="user_navigation_link_text">' . get_string('manage_onlineexams', 'local_onlineexams') . '</span>', array('class' => 'user_navigation_link'));
        $coursecatnodes .= $onlineexams;
        $coursecatnodes .= html_writer::end_tag('li');
    }
    return array('6' => $coursecatnodes);
}

function local_onlineexams_quicklink_node()
{
    global $CFG, $PAGE, $OUTPUT;
    $categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context();
    $content = '';
    if (has_capability('local/onlineexams:view', $categorycontext) || has_capability('local/onlineexams:manage', $categorycontext) || is_siteadmin()) {
        $PAGE->requires->js_call_amd('local_onlineexams/onlineexamsAjaxform', 'load');

        $coursedata = array();
        $coursedata['node_header_string'] = get_string('manage_br_onlineexams', 'local_onlineexams');
        $coursedata['pluginname'] = 'onlineexams';
        $coursedata['plugin_icon_class'] = 'fa fa-book';
        if (is_siteadmin() || (has_capability('moodle/course:create', $categorycontext) && has_capability('moodle/course:update', $categorycontext) && has_capability('local/onlineexams:manage', $categorycontext))) {
            $coursedata['create'] = TRUE;
            $coursedata['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('onclick' => '(function(e){ require("local_onlineexams/courseAjaxform").init({contextid:' . $categorycontext->id . ', component:"local_onlineexams", callback:"custom_course_form", form_status:0, plugintype: "local", pluginname: "onlineexams"}) })(event)'));
        }
        if (has_capability('local/onlineexams:view', $categorycontext) || has_capability('local/onlineexams:manage', $categorycontext)) {
            $coursedata['viewlink_url'] = $CFG->wwwroot . '/local/onlineexams/index.php';
            $coursedata['view'] = TRUE;
            $coursedata['viewlink_title'] = get_string('view_onlineexams', 'local_onlineexams');
        }
        $coursedata['space_count'] = 'one';
        $content = $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $coursedata);
    }
    return array('5' => $content);
}

function add_onlineexam_quiz($validateddata, $examid)
{
    global $DB;
    //quiz module
    $quiz = new stdClass();
    $quiz->modulename = 'quiz';
    $quiz->add = 'quiz';
    $quiz->module = $DB->get_field('modules', 'id', array('name' => 'quiz'));    
    $quiz->preferredbehaviour = 'deferredfeedback';
    $quiz->quizpassword = '';
    $quiz->subnet = '';
    $quiz->visible = 1;
    $quiz->section = 0;
    $quiz->course = $examid->id;
    $quiz->grademethod = $validateddata->grademethod;
    $quiz->grade = $validateddata->maxgrade;
    $quiz->gradepass = $validateddata->gradepass;
    $quiz->name = $validateddata->fullname;
    $quiz->attempts = $validateddata->attempts;
    $quiz->graceperiod = ($validateddata->graceperiod) ? $validateddata->graceperiod : 0;
    $quiz->attemptimmediately = 1;
    $quiz->correctnessimmediately = 1;
    $quiz->marksimmediately = 1;
    $quiz->specificfeedbackimmediately = 1;
    $quiz->generalfeedbackimmediately = 1;
    $quiz->rightanswerimmediately = 1;
    $quiz->overallfeedbackimmediately = 1;
    $quiz->attemptopen = 1;
    $quiz->correctnessopen = 1;
    $quiz->marksopen = 1;
    $quiz->specificfeedbackopen = 1;
    $quiz->generalfeedbackopen = 1;
    $quiz->rightansweropen = 1;
    $quiz->overallfeedbackopen = 1;
    if (!empty($validateddata->summary_editor['text']))
        $quiz->introeditor['text'] = $validateddata->summary_editor['text'];
    else
        $quiz->introeditor['text'] = $validateddata->fullname;

    $quiz->introeditor['format'] = $validateddata->summary_editor['format'];
    $quiz->completion = 2;
    $quiz->completionusegrade = 1;
    $quiz->completionpassgrade = 1;
    return $quiz;
}

function update_onlineexam_quiz($validateddata, $data, $formstatus)
{
    global $DB;
    //quiz module
    $quiz = new stdClass();
    $quiz->modulename = 'quiz';
    $quiz->add = 'quiz';
    $quiz->module = $DB->get_field('modules', 'id', array('name' => 'quiz'));
    $quiz->preferredbehaviour = 'deferredfeedback';
    $quiz->quizpassword = '';
    $quiz->subnet = '';
    $quiz->visible = 1;
    $quiz->section = 0;
    $courseid = is_object($data) ? $data->id  : $data['id'];
    $quiz->course = $courseid;
    $quizobject = $DB->get_record('quiz', array('course' => $courseid));
    $quiz->completion = 2;
    $quiz->completionusegrade = 1;
    $quiz->completionpassgrade = 1;
    $quiz->attemptimmediately = 1;
    $quiz->correctnessimmediately = 1;
    $quiz->marksimmediately = 1;
    $quiz->specificfeedbackimmediately = 1;
    $quiz->generalfeedbackimmediately = 1;
    $quiz->rightanswerimmediately = 1;
    $quiz->overallfeedbackimmediately = 1;
    $quiz->attemptopen = 1;
    $quiz->correctnessopen = 1;
    $quiz->marksopen = 1;
    $quiz->specificfeedbackopen = 1;
    $quiz->generalfeedbackopen = 1;
    $quiz->rightansweropen = 1;
    $quiz->overallfeedbackopen = 1;
    // print_r($validateddata);
    // print_r($quizobject);
    $quiz->id = $quizobject->id;
    $quiz->name = $validateddata->fullname;
    if ($formstatus == 0) {
        if (!empty($validateddata->summary_editor['text']))
            $quiz->introeditor['text'] = $validateddata->summary_editor['text'];
        else
            $quiz->introeditor['text'] = $validateddata->fullname;

        $quiz->introeditor['format'] = $validateddata->summary_editor['format'];
        $quiz->gradepass = $validateddata->gradepass;
        $quiz->grademethod = $validateddata->grademethod;
        $quiz->grade = $validateddata->maxgrade;
        $quiz->attempts = $validateddata->attempts;
    }
    if ($formstatus == 1) {
        $quiz->timeopen = $validateddata->timeopen;
        $quiz->timeclose = $validateddata->timeclose;
        $quiz->overduehandling = $validateddata->overduehandling;
        $quiz->browsersecurity = $validateddata->browsersecurity;
        $quiz->timelimit = $validateddata->timelimit;
        $quiz->introeditor['text'] = $quizobject->intro;
        $quiz->introeditor['format'] = $quizobject->introformat;
        $quiz->grademethod = $quizobject->grademethod;
        $quiz->graceperiod = ($validateddata->graceperiod) ? $validateddata->graceperiod : 0;
    }
    // print_r($quiz);
    return $quiz;
}

/**
    * function costcenterwise_onlineexams_count
    * @todo count of onlineexams under selected costcenter
    * @param int $costcenter costcenter
    * @param int $department department
    * @return  array onlineexams count of each type
*/
function costcenterwise_onlineexams_count($costcenter,$department = false,$subdepartment = false, $l4department=false, $l5department=false){
    global $USER, $DB,$CFG;
    $params = array();
    $params['costcenterpath'] = '%/'.$costcenter.'/%';
    $countonlineexamsql = "SELECT count(id) FROM {course} WHERE concat('/',open_path,'/') LIKE :costcenterpath AND open_module = 'online_exams' AND open_coursetype = 1 ";
    if ($department) {
        $countonlineexamsql .= "  AND concat('/',open_path,'/') LIKE :departmentpath  ";
        $params['departmentpath'] = '%/'.$department.'/%';
    }
    if ($subdepartment) {
        $countonlineexamsql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/'.$subdepartment.'/%';
    }
    if ($l4department) {
        $countonlineexamsql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
        $params['l4departmentpath'] = '%/'.$l4department.'/%';
    }
    if ($l5department) {
        $countonlineexamsql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
        $params['l5departmentpath'] = '%/'.$l5department.'/%';
    }
    $activesql = " AND visible = 1 ";
    $inactivesql = " AND visible = 0 ";

    $countonlineexams = $DB->count_records_sql($countonlineexamsql, $params);
    $activeonlineexams = $DB->count_records_sql($countonlineexamsql.$activesql, $params);
    $inactiveonlineexams = $DB->count_records_sql($countonlineexamsql.$inactivesql, $params);
    if($countonlineexams >= 0){
        if($costcenter){
            $viewonlineexamlink_url = $CFG->wwwroot.'/local/onlineexams/index.php?costcenterid='.$costcenter; 
        }
        if($department){
            $viewonlineexamlink_url = $CFG->wwwroot.'/local/onlineexams/index.php?costcenterid='.$costcenter.'&departmentid='.$department;
        }
        if($subdepartment){
            $viewonlineexamlink_url = $CFG->wwwroot.'/local/onlineexams/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
        }
        if($l4department){
            $viewonlineexamlink_url = $CFG->wwwroot.'/local/onlineexams/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
        }
        if($l5department){
            $viewonlineexamlink_url = $CFG->wwwroot.'/local/onlineexams/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
        }
    }

    if($activeonlineexams >= 0){
        if($costcenter){
            $count_onlineexamactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=active&costcenterid='.$costcenter; 
        }
        if($department){
            $count_onlineexamactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department;
        }
        if($subdepartment){
            $count_onlineexamactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
        }
        if($l4department){
            $count_onlineexamactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
        }
        if($l5department){
            $count_onlineexamactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
        }
    }
    if($inactiveonlineexams >= 0){
        if($costcenter){
            $count_onlineexaminactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=inactive&costcenterid='.$costcenter; 
        }
        if($department){
            $count_onlineexaminactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department;
        }
        if($subdepartment){
            $count_onlineexaminactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
        }
        if($l4department){
            $count_onlineexaminactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
        }
        if($l5department){
            $count_onlineexaminactivelink_url = $CFG->wwwroot.'/local/onlineexams/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
        }
    }

    return array('onlineexam_plugin_exist' => true,'onlineexamcount' => $countonlineexams,'activeonlineexamcount' => $activeonlineexams,'inactiveonlineexamcount' => $inactiveonlineexams,'viewonlineexamlink_url'=>$viewonlineexamlink_url,'count_onlineexamactivelink_url' =>$count_onlineexamactivelink_url,'count_onlineexaminactivelink_url' =>$count_onlineexaminactivelink_url);
}


function onlineexams_filters_form($filterparams, $ajaxformdata = null){

    global $CFG, $PAGE,$USER;

    require_once($CFG->dirroot . '/local/onlineexams/onlineexam_filters_form.php');

    $action = isset($filterparams['action']) ? $filterparams['action'] : '';


     $fields =array(/*'organizations', 'departments',
            'subdepartment', 'department4level','department5level',*/'hierarchy_fields','onlineexams','categories');
    $mform = new onlineexam_filters_form(null, array('filterlist'=> $fields, 'filterparams' => $filterparams, 'action' => $action), 'post', '', null, true, $ajaxformdata);
    return $mform;
}
function onlineexams_filter($mform){
    global $DB,$USER;
	$categorycontext = (new \local_onlineexams\lib\accesslib())::get_module_context();
    $sql = "SELECT id, fullname FROM {course} WHERE id > 1 AND open_module = 'online_exams'  AND open_coursetype = 1 ";

    if(is_siteadmin()){
       $onlineexamslist = $DB->get_records_sql_menu($sql);
    }else{
      $sql .= (new \local_onlineexams\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    }
    $onlineexamslist = $DB->get_records_sql_menu($sql);

    $select = $mform->addElement('autocomplete', 'onlineexams', get_string('onlineexam','local_onlineexams'), $onlineexamslist, array('placeholder' => get_string('onlineexam','local_onlineexams')));
    $mform->setType('onlineexams', PARAM_RAW);
    $select->setMultiple(true);
}
function category_filter($mform){
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
