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
 * @subpackage local_skillrepository
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/local/skillrepository/lib.php');

global $CFG, $PAGE;

$systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
$id = optional_param('id', -1, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$delete_id = optional_param('delete_id', 0, PARAM_INT);
$submitbutton = optional_param('submitbutton', '', PARAM_RAW);
$returnurl = new moodle_url('/local/skillrepository/skill_category.php');

$PAGE->requires->jquery();
$PAGE->requires->js('/local/skillrepository/js/script.js');
$PAGE->requires->js('/local/skillrepository/js/jquery.dataTables.js',true);
$PAGE->requires->css('/local/skillrepository/css/jquery.dataTables.css');
$PAGE->set_pagelayout('standard');
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/skillrepository/skill_category.php');
require_login();

if (!has_capability('local/skillrepository:create_skill', (new \local_skillrepository\lib\accesslib())::get_module_context()) && !is_siteadmin()) {
    print_error('Sorry, You are not accessable to this page');
}
if ($id > 0){
    $string = get_string('skill_category', 'local_skillrepository') . ':' . get_string('edit_skill_category', 'local_skillrepository');
} else {
    $string = get_string('skill_category', 'local_skillrepository') . ':' . get_string('create_newskill_category', 'local_skillrepository');
}
$title="Skill Category";
$PAGE->set_title($title);
$PAGE->navbar->add(get_string('manage_skills', 'local_skillrepository'),new moodle_url('/local/skillrepository/index.php'));
$PAGE->navbar->add(get_string('manage_skill_category', 'local_skillrepository'));
$PAGE->set_heading(get_string('manage_skill_category', 'local_skillrepository'));
$PAGE->requires->js_call_amd('local_skillrepository/newcategory', 'load', array());
echo $OUTPUT->header();
$lib =  new local_skillrepository\event\insertcategory();
$repository = new local_skillrepository\event\insertrepository();
$renderer = $PAGE->get_renderer('local_skillrepository');
$filterparams = $renderer->manageskillscategory_content(true);
if ($id > 0) {
    $tool = $DB->get_record('local_skill_categories', array('id' => $id));
} else {
    $tool = new stdClass();
    $tool->id = -1;
}
if($id > 0){
    $collapse = false;
}else{
    $collapse = true;
}

//this is the return url
$editform =  new local_skillrepository\form\skill_category_form(null, array('id'=>$id));
$editform->set_data($tool);
if ($editform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $editform->get_data()) { 
    $skill_category = $lib->create_skill_category($data);
    redirect($returnurl);
}else{
    //we will get when ever we submit the form
    if($submitbutton){
        $collapse = false;
    }
}

$skill_categories = $repository->skillrepository_opertaions('local_skill_categories', 'fetch-multiple','','','');
if(empty($skill_categories)){
    $collapse = false;
}

//Added back button
echo "<ul class='course_extended_menu_list'>
    <li>
        <div class='coursebackup course_extended_menu_itemcontainer'>
            <a href='".$CFG->wwwroot."/local/skillrepository/index.php' title='".get_string("back")."' class='course_extended_menu_itemlink'>
                <i class='icon fa fa-reply'></i>
            </a>
        </div>
    </li>
    <li>
        <div class='coursebackup course_extended_menu_itemcontainer'>
            <a id='extended_menu_syncstats' title='".get_string('create_skillcategory', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_skillrepository/newcategory\").init({selector:\"createcategorymodal\", contextid:$systemcontext->id, categoryid:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true' aria-label=''></i>
            </a>
        </div>
    </li>
</ul>";
$filterparams['submitid'] = 'form#filteringform';
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);
echo $renderer->manageskillscategory_content();
echo $OUTPUT->footer();