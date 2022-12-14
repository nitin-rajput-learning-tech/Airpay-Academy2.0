<?php
/*
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
 * @subpackage local_users
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/local/users/lib.php');

global $OUTPUT, $CFG, $PAGE;

require_login();

// for pluginchecking calling core_component
$corecomponent = new core_component();

// systemcontest defining
$categorycontext = (new \local_users\lib\accesslib())::get_module_context();

$PAGE->set_context($categorycontext);

// amd js calling
$PAGE->requires->js_call_amd('local_users/newuser', 'load', array());
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'downloadtrigger', array());

// set url and layout and title
$pageurl = new moodle_url('/local/users/index.php');

$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('manage_users', 'local_users'));
$status = optional_param('status', '', PARAM_RAW);
$costcenterid = optional_param('costcenterid', '', PARAM_INT);
$departmentid = optional_param('departmentid', '', PARAM_INT);
$subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
$formattype = optional_param('formattype', 'card', PARAM_TEXT);
if ($formattype == 'card') {
    $formattype_url = 'table';
    $display_text = get_string('listtype', 'local_users');
    $display_icon = get_string('listicon', 'local_courses');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype', 'local_users');
    $display_icon = get_string('cardicon', 'local_courses');
}
// Header and the navigation bar
$heading = get_string('manage_users', 'local_users');
$PAGE->set_heading($heading);
$PAGE->navbar->add($heading);


echo $OUTPUT->header();

// user has capibilaty for manage users
if (!is_siteadmin() && (!has_capability('local/users:manage', $categorycontext)&&!
    has_capability('local/users:view', $categorycontext))) {
    echo print_error('nopermissions');
}

$userrenderer = $PAGE->get_renderer('local_users');

$collapse = true;
$show = '';

if (has_capability('local/users:create', $categorycontext) || is_siteadmin()) {
    // create user, uploadusers and sync errors icons
    echo $userrenderer->user_page_top_action_buttons();
}

// pluginexist checking
$coursespluginexist = $corecomponent::get_plugin_directory('local', 'courses');

if (!empty($coursespluginexist)) {

    // passing options and dataoptions in filter
    $filterparams = $userrenderer->manageusers_content(true, $formattype);

    // for filtering users we are providing form
    $mform = users_filters_form($filterparams);
    if ($mform->is_cancelled()) {
        redirect('index.php');
    }
    if (!empty($costcenterid) || !empty($status) || !empty($departmentid) || !empty($subdepartmentid)) {
        $formdata = new stdClass();
        $formdata->organizations = $costcenterid;
        $formdata->departments = $departmentid;
        $formdata->subdepartment = $subdepartmentid;
        $formdata->status = $status;
        $mform->set_data($formdata);
    }
    echo '<a class="btn-link btn-sm" href="javascript:void(0);" data-toggle=
    "collapse" data-target="#local_users-filter_collapse" aria-expanded="false"
    aria-controls="local_users-filter_collapse">
            <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
          </a>';
    echo  '<div class="mt-2 mb-2 collapse '.$show.'" id="local_users-filter_collapse">
                <div id="filters_form" class="card card-body p-2">';
                    $mform->display();
    echo        '</div>
            </div>';
}
$filterparams['submitid'] = 'form#filteringform';
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);
   $display_url = new moodle_url('/local/users/index.php');
if ($costcenterid) {
    $display_url->param('costcenterid', $costcenterid);
}
if ($departmentid) {
    $display_url->param('departmentid', $departmentid);
}
if ($subdepartmentid) {
    $display_url->param('subdepartmentid', $subdepartmentid);
}
if ($status) {
    $display_url->param('status', $status);
}
if ($formattype_url) {
    $display_url->param('formattype', $formattype_url);
}
   $displaytype_div = '<div class="col-12 d-inline-block">';
   $displaytype_div .= '<a class="btn btn-outline-secondary pull-right" href="' . $display_url . '">';
   $displaytype_div .= '<span class="'.$display_icon.'"></span>' . $display_text;
   $displaytype_div .= '</a>';
   $displaytype_div .= '</div>';  
     echo $displaytype_div;
echo $userrenderer->manageusers_content(false, $formattype);

echo $OUTPUT->footer();
