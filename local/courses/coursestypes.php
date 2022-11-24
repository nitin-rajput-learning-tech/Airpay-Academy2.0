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


require_once('../../config.php');
require_once($CFG->dirroot . '/local/courses/filters_form.php');

$id        = optional_param('id', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$jsonparam    = optional_param('jsonparam', '', PARAM_RAW);

$systemcontext = context_system::instance();
if (!has_capability('local/courses:view', $systemcontext) && !has_capability('local/courses:manage', $systemcontext)) {
    print_error("You don't have permissions to view this page.");
}
$PAGE->set_pagelayout('admin');

$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/courses/courses.php');
$PAGE->set_title(get_string('courses'));
$PAGE->set_heading(get_string('course_type', 'local_courses'));
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'load');
$PAGE->requires->js_call_amd('local_courses/featuredCourseform', 'load');
$PAGE->requires->js_call_amd('local_courses/createCoursetype', 'Datatable', array());
$PAGE->requires->js_call_amd('local_courses/createCourseProviders', 'Datatable', array());

$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
$PAGE->requires->js_call_amd('local_costcenter/fragment', 'init', array());
$PAGE->requires->js_call_amd('local_courses/courses', 'load', array());
$PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'getskills', array());
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('manage_courses', 'local_courses'));


$renderer = $PAGE->get_renderer('local_courses');

$extended_menu_links = '';
$extended_menu_links = '<div class="course_contextmenu_extended">
            <ul class="course_extended_menu_list">';

if ((has_capability('local/request:approverecord', context_system::instance()) || is_siteadmin())) {
    $extended_menu_links .= '<li><div class="courseedit course_extended_menu_itemcontainer">
                            <a id="extended_menu_createcourses" class="pull-right course_extended_menu_itemlink" title = "Request" href = ' . $CFG->wwwroot . '/local/request/index.php?component=elearning>
                                <i class="icon fa fa-share-square" aria-hidden="true"></i>
                            </a>
                        </div></li>';
}

if (is_siteadmin() || (has_capability('moodle/course:create', $systemcontext) && has_capability('moodle/course:update', $systemcontext) && has_capability('local/courses:manage', $systemcontext))) {
    $uploadurl = new moodle_url('/local/courses/upload/index.php', array());

    //$featured_courses = $DB->get_record('local_featured_courses', array(), $fields = 'id,count(*)', $strictness = IGNORE_MISSING);

    if ((!empty($featured_courses)) && $featured_courses->id > 0) {
        $featured_courseid = $featured_courses->id;
    } else {
        $featured_courseid = 0;
    }

    $coursetype_url = new moodle_url('/local/courses/coursestypes.php');
    $courseprov_url = new moodle_url('/local/courses/coursesproviders.php');

    $extended_menu_links .= '<li>
                                    <div class="courseedit course_extended_menu_itemcontainer">
                                        <a href="' . $courseprov_url . '" id="extended_menu_createcourseprov" class="pull-right course_extended_menu_itemlink" title = "' . get_string('viewcourse_prov', 'local_courses') . '">
                                        <span class="createicon"><i class="icon fa fa-book"></i><i class="fa fa-hand-paper-o createiconchild" aria-hidden="true"></i></span>
        
                                        </a>
                                    </div>
                                </li>
                                <li><div class="courseedit course_extended_menu_itemcontainer">';

    $extended_menu_links .= '<li>
                                    <div class="courseedit course_extended_menu_itemcontainer">
                                        <a href="' . $coursetype_url . '" id="extended_menu_createcoursetype" class="pull-right course_extended_menu_itemlink" title = "' . get_string('viewcourse_type', 'local_courses') . '">
                                        <i class="icon fa fa-list-alt"></i>
                                        </a>
                                    </div>
                                 </li>
                                <li><div class="courseedit course_extended_menu_itemcontainer">';

    $extended_menu_links .= '<li>
                                    <div class="courseedit course_extended_menu_itemcontainer">
                                        <a id="extended_menu_createfeaturedcourses" class="pull-right course_extended_menu_itemlink" title = "' . get_string('add_featuredcourse', 'local_courses') . '" data-action="featuredcoursemodal" onclick="(function(e){ require(\'local_courses/featuredCourseform\').init({contextid:' . $systemcontext->id . ', featured_courseid :' . $featured_courseid . ' }) })(event)">
                                        <!--<span class="createicon"><i class="icon fa fa-book"></i><i class="fa fa-star createiconchild" aria-hidden="true"></i></span>-->
                                        <i class="icon fa fa-star" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                 </li>
                                 <li><div class="courseedit course_extended_menu_itemcontainer">';

    $extended_menu_links .= '<li>
                                <div class="courseedit course_extended_menu_itemcontainer">
                                    <a class="pull-right course_extended_menu_itemlink" title = "' . get_string('uploadcourses', 'local_courses') . '" href="' . $uploadurl . '">
                                        <i class="icon fa fa-upload" aria-hidden="true"></i>
                                    </a>
                                </div>
                                </li>
                                <li><div class="courseedit course_extended_menu_itemcontainer">
                                    <a id="extended_menu_createcourses" class="pull-right course_extended_menu_itemlink" title = "' . get_string('create_newcourse', 'local_courses') . '" data-action="createcoursemodal" onclick="(function(e){ require(\'local_courses/courseAjaxform\').init({contextid:' . $systemcontext->id . ', component:\'local_courses\', callback:\'custom_course_form\', form_status:0, plugintype: \'local\', pluginname: \'courses\'}) })(event)">
                                        <span class="createicon"><i class="icon fa fa-book"></i><i class="fa fa-plus createiconchild" aria-hidden="true"></i></span>
                                    </a>
                                </div></li>';
}

$extended_menu_links .= '
        </ul>
    </div>';

echo $OUTPUT->header();
echo $extended_menu_links;

$result = $DB->get_records('local_course_types');
foreach($result as $res){
    if(in_array($res->id,array('1','2','3','4','5','6'))){
        $show = false;
    }else{
        $show = true;
    }
    $res->display = $show;
}
$data = (object)[
    'result' => array_values($result),
];

echo $OUTPUT->render_from_template('local_courses/coursetypes_table', $data);
echo $OUTPUT->footer();
