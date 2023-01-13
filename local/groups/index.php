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
 * @subpackage local_groups
 */

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/adminlib.php');

$PAGE;

$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');

$PAGE->requires->js_call_amd('local_groups/newgroup', 'load', array());

$PAGE->requires->js_call_amd('local_groups/newsubgroup', 'load', array());



$contextid = optional_param('contextid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$searchquery  = optional_param('search', '', PARAM_RAW);
$showall = optional_param('showall', false, PARAM_BOOL);
$formattype = optional_param('formattype', 'card', PARAM_TEXT);
if ($formattype == 'card') {
    $formattype_url = 'table';
    $display_text = get_string('listtype','local_groups');
    $display_icon = get_string('listicon','local_groups');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype','local_groups');
    $display_icon = get_string('cardicon','local_groups');
}


require_login();

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
} else {
    $context =  (new \local_groups\lib\accesslib())::get_module_context();
}
if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
    print_error('invalidcontext');
}

$category = null;
if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
}
$manager = has_capability('moodle/cohort:manage', $context);
$canassign = has_capability('moodle/cohort:assign', $context);
if (!$manager) {
    require_capability('moodle/cohort:view', $context);
}

$strcohorts = get_string('cohorts', 'local_groups');
$PAGE->set_url($CFG->wwwroot . '/local/groups/index.php');
$PAGE->set_context($context);
$PAGE->set_title($strcohorts);
$PAGE->set_heading(get_string('cohorts', 'local_groups'));
$PAGE->navbar->add(get_string("pluginname", 'local_groups'));
$PAGE->requires->js_call_amd('local_groups/renderselections', 'init');
echo $OUTPUT->header();


$renderer = $PAGE->get_renderer('local_groups');
echo $renderer->get_group_btns();

$filterparams = $renderer->managegroups_content(true,$formattype);

if ($showall || is_siteadmin()) {
    $cohorts = local_groups_get_all_groups($context->id,$page, 25, $searchquery);
} else {
    $cohorts = local_groups_get_groups($context->id, $page, 25, $searchquery);
}

$count = '';
if ($cohorts['allgroups'] > 0) {
    if ($searchquery === '') {
        $count = ' ('.$cohorts['allgroups'].')';
    } else {
        $count = ' ('.$cohorts['totalgroups'].'/'.$cohorts['allgroups'].')';
    }
}

$params = array('page' => $page);
if ($contextid) {
    $params['contextid'] = $contextid;
}
if ($searchquery) {
    $params['search'] = $searchquery;
}
if ($showall) {
    $params['showall'] = true;
}
$baseurl = new moodle_url('/local/groups/index.php', $params);

if ($editcontrols = local_groups_edit_controls($context, $baseurl)) {
}

// Add search form.
$search  = html_writer::start_tag('form', array('id'=>'searchcohortquery', 'method'=>'get', 'class' => 'form-inline search-cohort'));
$search .= html_writer::start_div('m-b-1 w-100 pull-left');
$search .= html_writer::label(get_string('search', 'local_groups'), 'cohort_search_q', true,
        array('class' => 'm-r-1 pull-left mt-1')); // No : in form labels!*/
$search .= html_writer::empty_tag('input', array('id' => 'cohort_search_q', 'type' => 'text', 'name' => 'search',
        'value' => $searchquery, 'class' => 'form-control m-r-1 d-inline-block w-auto'));
$search .= html_writer::start_span('grp_search');
$search .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('search', 'cohort'), 'class' => 'btn btn-secondary'));
$search .= html_writer::end_span();
$search .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'contextid', 'value'=>$contextid));
$search .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'showall', 'value'=>$showall));
$search .= html_writer::end_div();
$search .= html_writer::end_tag('form');

$filterparams['submitid'] = 'form#filteringform';

echo $OUTPUT->render_from_template('local_groups/global_filter', $filterparams);

    $display_url = new moodle_url('/local/groups/index.php?formattype=' . $formattype_url);
        $displaytype_div = '<div class="col-12 d-inline-block">';
    $displaytype_div .= '<a class="btn btn-outline-secondary pull-right" href="' . $display_url . '">';
    $displaytype_div .= '<span class="'.$display_icon.'"></span>' . $display_text;
    $displaytype_div .= '</a>';
    $displaytype_div .= '</div>';

        echo $displaytype_div;
echo $renderer->managegroups_content(false,$formattype);

echo $OUTPUT->footer();
