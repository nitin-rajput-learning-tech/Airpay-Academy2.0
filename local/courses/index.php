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
global $CFG, $OUTPUT,$PAGE;
require_once($CFG->dirroot.'/course/lib.php');
//require_once($CFG->libdir.'/coursecatlib.php');

require_login();
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$hide = optional_param('hide', '', PARAM_INT);
$visible = optional_param('visible', -1, PARAM_INT);
$url = new moodle_url('/local/custom_category/index.php');
$id = optional_param('id', 0, PARAM_INT);
$formattype = optional_param('formattype', 'card', PARAM_TEXT);
if ($formattype == 'card') {
    $formattype_url = 'table';
    $display_text = get_string('listtype','local_courses');
    $display_icon = get_string('listicon','local_courses');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype','local_courses');
    $display_icon = get_string('cardicon','local_courses');
}
$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();


if($categoryid > 0 && $visible != -1){

    $dataobject=new stdClass();
    $dataobject->id=$categoryid;
    $dataobject->visible=$visible;
    $DB->update_record('course_categories', $dataobject);
    $DB->execute('UPDATE {course} SET visible = ' .
                    $hide . ',visibleold= ' .
                    $visible . ' WHERE category = ' .
                $categoryid. '');

    redirect(new moodle_url('/local/custom_category/index.php'));
}
if ($categoryid) {
    $category = core_course_category::get($categoryid);
    $context = context_coursecat::instance($category->id);
    $url->param('categoryid', $category->id);
}else{
    $category = core_course_category::get_default();
    $categoryid = $category->id;
    $context = context_coursecat::instance($category->id);
    $url->param('categoryid', $category->id);
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('leftmenu_browsecategories','local_courses'));
if(empty($id)){
        $PAGE->set_heading(get_string('leftmenu_browsecategories','local_courses'));
 }else{
        $categories = $DB->get_field('course_categories','name',array('id'=>$id));
        $PAGE->set_heading($categories); 
}
$PAGE->requires->js_call_amd('local_courses/newcategory', 'load');
$PAGE->requires->js_call_amd('local_courses/deletecategory', 'load');
$capabilities = array(
    'moodle/site:config',
    'moodle/backup:backupcourse',
    'local/courses:manage',
    'moodle/course:create',
    'moodle/site:approvecourse'
);
if ($category && !has_any_capability($capabilities, $categorycontext)) {
    // If the user doesn't poses any of these system capabilities then we're going to mark the manage link in the settings block
    // as active, tell the page to ignore the active path and just build what the user would expect.
    // This will at least give the page some relevant navigation.
    navigation_node::override_active_url(new moodle_url('/local/custom_category/index.php', array('categoryid' => $category->id)));
    $PAGE->set_category_by_id($category->id);
    $PAGE->navbar->ignore_active(true);
    $PAGE->navbar->add(get_string('coursemgmt', 'admin'), $PAGE->url->out_omit_querystring());
} else {
    // If user has system capabilities, make sure the "Manage courses and categories" item in Administration block is active.
    navigation_node::require_admin_tree();
    navigation_node::override_active_url(new moodle_url('/local/custom_category/index.php'));
}
// if($category !== null){
//     $parents = coursecat::get_many($category->get_parents());
//     $parents[] = $category;
//     foreach ($parents as $parent) {
//         $PAGE->navbar->add(get_string('leftmenu_browsecategories','local_courses'), $PAGE->url->out_omit_querystring());
        // if(!empty($id)){
        //     $catdepth = $DB->get_field('course_categories','idnumber',array('id'=>$id));
        //     $catdepths = $DB->get_field('course_categories','name',array('id'=>$id));
        //     $catdepth = $catdepth ? $catdepth :  $catdepths;
        //     $PAGE->navbar->add(get_string('subcategories','local_courses'), $PAGE->url->out_omit_querystring());
        //     $PAGE->navbar->add($catdepth);
        // }
//     }
// }
$renderer = $PAGE->get_renderer('local_courses');

if($id == 0){
    $PAGE->navbar->add(get_string('leftmenu_browsecategories','local_courses'));
}else{
    if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)){
        $PAGE->navbar->add(get_string('leftmenu_browsecategories','local_courses'), $PAGE->url->out_omit_querystring());
    }
    $superparent_sql = "SELECT id,name FROM {course_categories} WHERE id = (SELECT parent FROM {course_categories} WHERE id = {$id}) ";
    if($superparent = $DB->get_record_sql($superparent_sql)){
        // if(has_capability('local/costcenter:manage_ownorganization', $categorycontext)){
        //     $PAGE->navbar->add($superparent->name, new moodle_url('/local/custom_category/index.php', array('id' => $superparent->id)));
        // }else{
            $PAGE->navbar->add($superparent->name);
        // }
    }
    $parentcategory = $DB->get_field('course_categories','name',array('id'=>$id));
    $PAGE->navbar->add($parentcategory);
    $content = $renderer->get_parent_category_data($id);
}

echo $OUTPUT->header();

/*if(!empty($id)){
    $parentcat = $DB->get_record('course_categories',array('id'=>$id),'id,name,parent');
    echo html_writer::start_tag('div',array('class' => 'categoryname'));
    if($parentcat->parent != 0){
        $topcatname = $DB->get_field('course_categories','name', array('id'=>$parentcat->parent));
    }else{
        $topcatname = null;
    }
    echo $topcatname.'/'.$parentcat->name;
    echo  html_writer::end_tag('div');

}*/

if(is_siteadmin() ||
    has_capability('local/courses:manage', $categorycontext)){
    echo '<ul class="course_extended_menu_list">
        <li>
            <div class="coursebackup course_extended_menu_itemcontainer">
                <a id="extended_menu_createcategories" data-action="createcategorymodal"
                class="course_extended_menu_itemlink"
                onclick = "(function(e){ require(\'local_courses/newcategory\').init({
                    contextid:1, categoryid:0}) })(event)"
                title="'.get_string('createcategory','local_courses').'"><span class="createicon"><i class="icon fa fa-book" aria-hidden="true" aria-label=""></i><i class="fa fa-book secbook catcreateicon" aria-hidden="true" aria-label=""></i><i class="fa fa-plus createiconchild" aria-hidden="true"></i></span></a>
            </div>
        </li>
    </ul>';
}else{
    redirect($CFG->wwwroot.'/my');
}
if($categoryid > 0 && $visible != -1){
    $dataobject=new stdClass();
    $dataobject->id=$categoryid;
    $dataobject->visible=$visible;
    $DB->update_record('course_categories', $dataobject);
    $DB->execute('UPDATE {course} SET visible = ' .
                    $visible . ',visibleold= ' .
                    $visible . ' WHERE category = ' .
                $categoryid. '');
    redirect(new moodle_url('/local/custom_category/index.php'));
}
// echo isset($content);
$filterparams = $renderer->get_categories_list(true,$formattype);
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);
if (is_siteadmin() || has_capability('local/courses:manage', $categorycontext))  {

    $display_url = new moodle_url('/local/custom_category/index.php?formattype=' . $formattype_url);
        $displaytype_div = '<div class="col-12 d-inline-block">';
    $displaytype_div .= '<a class="btn btn-outline-secondary pull-right" href="' . $display_url . '">';
    $displaytype_div .= '<span class="'.$display_icon.'"></span>' . $display_text;
    $displaytype_div .= '</a>';
    $displaytype_div .= '</div>';

        echo $displaytype_div;
}
echo $renderer->get_categories_list(false,$formattype);

echo $OUTPUT->footer();
