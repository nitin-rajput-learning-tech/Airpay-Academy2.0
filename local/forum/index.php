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


require_once('../../config.php');
require_once($CFG->dirroot . '/local/courses/filters_form.php');
 $id        = optional_param('id', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$jsonparam    = optional_param('jsonparam', '', PARAM_RAW);
$forum = optional_param('forum', '', PARAM_RAW);
$costcenterid = optional_param('costcenterid', '', PARAM_INT);
$departmentid = optional_param('departmentid', '', PARAM_INT);
$subdepartmentid = optional_param('subdepartmentid','',PARAM_INT);
$l4department = optional_param('l4department', '', PARAM_INT);
$l5department = optional_param('l5department', '', PARAM_INT);
$department4levelid = optional_param('department4levelid', '', PARAM_INT);
$department5levelid = optional_param('department5levelid','',PARAM_INT);
$formattype = optional_param('formattype', 'card', PARAM_TEXT);
if ($formattype == 'card') {
    $formattype_url = 'table';
    $display_text = get_string('listtype','local_forum');
    $display_icon = get_string('listicon','local_forum');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype','local_forum');
    $display_icon = get_string('cardicon','local_forum');
}

require_login();

$categorycontext = (new \local_forum\lib\accesslib())::get_module_context();
if(!has_capability('local/forum:view', $categorycontext) && !has_capability('local/forum:manage', $categorycontext) ){
    throw new moodle_exception("You don't have permissions to view this page.");
}
$PAGE->set_pagelayout('standard');

$PAGE->set_context($categorycontext);
$PAGE->set_url('/local/forum/index.php');
$PAGE->set_title(get_string('forum','local_forum'));
$PAGE->set_heading(get_string('manage_forum','local_forum'));
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('local_forum/forumAjaxform', 'load');
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
$PAGE->requires->js_call_amd('local_costcenter/fragment', 'init', array());
$PAGE->requires->js_call_amd('local_forum/forum', 'load', array());
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'downloadtrigger',array());
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('manage_forum','local_forum'));

if($deleteid && $confirm && confirm_sesskey()){
    $course=$DB->get_record('course',array('id'=>$deleteid));
    delete_course($course, false);
    if($course){
        $custom_delete = new \local_courses\action\delete();
        $delete = $custom_delete->delete_coursedetails($deleteid);
     }

    $course_detail = new stdClass();
    $sql = $DB->get_field('user','firstname', array('id' =>$USER->id));
    $course_detail->userid = $sql;
    $course_detail->courseid = $deleteid;
    $description = get_string('descptn','local_forum',$course_detail);
    $logs = new local_courses\action\insert();
    $insert_logs = $logs->local_custom_logs('delete', 'course', $description, $deleteid);
    redirect($CFG->wwwroot . '/local/forum/index.php'); 
}
$renderer = $PAGE->get_renderer('local_forum');

$extended_menu_links = '';  
$extended_menu_links = '<div class="course_contextmenu_extended">
            <ul class="course_extended_menu_list">';


           

if(is_siteadmin() ||(
        has_capability('moodle/course:create', $categorycontext)&& has_capability('moodle/course:update', $categorycontext)&&has_capability('local/courses:manage', $categorycontext))){
        $extended_menu_links .= '<li><div class="courseedit course_extended_menu_itemcontainer">
                                    <a id="extended_menu_createcourses" class="pull-right course_extended_menu_itemlink" title = "'.get_string('create_newforum','local_forum').'" data-action="createcoursemodal" onclick="(function(e){ require(\'local_forum/forumAjaxform\').init({contextid:'.$categorycontext->id.', component:\'local_forum\', callback:\'custom_forum_form\', form_status:0, plugintype: \'local\', pluginname: \'forum\'}) })(event)">
                                        <span class="createicon">
                                        <i class="icon fa fa-comments-o"></i>
                                        <i class="fa fa-plus createiconchild" aria-hidden="true"></i>
                                        </span>
                                    </a>
                                </div></li>';
}

$extended_menu_links .= '
        </ul>
    </div>';

echo $OUTPUT->header();
echo $extended_menu_links;

$filterparams = $renderer->get_catalog_forum(true,$formattype);
    // for filtering users we are providing form
    $formdata = new stdClass();
    $formdata->filteropen_costcenterid = $costcenterid;
    $formdata->filteropen_department = $departmentid;
    $formdata->filteropen_subdepartment = $subdepartmentid;
    $formdata->filteropen_level4department = $l4department;
    $formdata->filteropen_level5department = $l5department;

$mform = forum_filters_form($filterparams, (array)$formdata);
     
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/local/forum/index.php');
} else{
    $filterdata =  $mform->get_data();
    if($filterdata){
        $collapse = false;
    } else{
        $collapse = true;
    }
}
if(empty($filterdata) && !empty($jsonparam)){
    $filterdata = json_decode($jsonparam);
    foreach($thisfilters AS $filter){
        if(empty($filterdata->$filter)){
            unset($filterdata->$filter);
        }
    }
    $mform->set_data($filterdata);
}
if(!empty($costcenterid) || !empty($forum) || !empty($departmentid) || !empty($subdepartmentid) || !empty($department4levelid) || !empty($department5levelid)){
        // $formdata = new stdClass();
        // $formdata->organizations = $costcenterid;
        // $formdata->departments = $departmentid;
        // $formdata->subdepartment = $subdepartmentid;
        // $formdata->department4level = $department4levelid;
        // $formdata->department5level = $department5levelid;
        $formdata->forum = $forum;
        $mform->set_data($formdata);
}
if($filterdata){
    $collapse = false;
    $show = 'show';
} else{
    $collapse = true;
    $show = '';
}
echo '<a class="btn-link btn-sm" data-toggle="collapse" data-target="#local_forum-filter_collapse" aria-expanded="false" aria-controls="local_forum-filter_collapse">
        <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
      </a>';
echo  '<div class="collapse '.$show.'" id="local_forum-filter_collapse">
            <div id="filters_form" class="card card-body p-2">';
                $mform->display();
echo        '</div>
        </div>';
$filterparams['submitid'] = 'form#filteringform';
$filterparams['filterdata'] = json_encode($formdata);
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);
// if (is_siteadmin() || (
//         has_capability('moodle/course:create', $categorycontext) && has_capability('moodle/course:update', $categorycontext) && has_capability('local/courses:manage', $categorycontext))) {
   $display_url = new moodle_url('/local/forum/index.php');
   if($costcenterid){
    $display_url->param('costcenterid', $costcenterid);  
   }
   if($departmentid){
    $display_url->param('departmentid',$departmentid);
   }
   if($subdepartmentid){
    $display_url->param('subdepartmentid',$subdepartmentid);
   }
    if($department4levelid){
    $display_url->param('department4levelid',$department4levelid);
   }
   if($department5levelid){
    $display_url->param('department5levelid',$department5levelid);
   }
   if($forum){
    $display_url->param('forum',$forum);
   }
   if($formattype_url){
    $display_url->param('formattype', $formattype_url);      
   } 
    $displaytype_div = '<div class="col-12 d-inline-block">';
    $displaytype_div .= '<a class="btn btn-outline-secondary pull-right" href="' . $display_url . '">';
    $displaytype_div .= '<span class="'.$display_icon.'"></span>' . $display_text;
    $displaytype_div .= '</a>';
    $displaytype_div .= '</div>';

        echo $displaytype_div;
    // }
echo $renderer->get_catalog_forum(false,$formattype);

echo $OUTPUT->footer();
