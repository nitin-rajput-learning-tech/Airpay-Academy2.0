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
 * @subpackage local_evaluation
 */


require_once('../../config.php');
require_once('lib.php');
require_once('edit_form.php');
global $PAGE, $DB, $USER;
evaluation_init_evaluation_session();

$id = required_param('id', PARAM_INT);
$typ = optional_param('typ', '', PARAM_ALPHA);

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('local_costcenter/fragment', 'init', array());
$PAGE->requires->js_call_amd('local_evaluation/evaluation', 'load', array());
$PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'load', array());
if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

$do_show = optional_param('do_show', 'edit', PARAM_ALPHA);
$switchitemrequired = optional_param('switchitemrequired', false, PARAM_INT);
$deleteitem = optional_param('deleteitem', false, PARAM_INT);

$url = new moodle_url('/local/evaluation/eval_view.php', array('id'=>$id, 'do_show'=>$do_show));

$PAGE->set_pagelayout('standard');
$context = (new \local_evaluation\lib\accesslib())::get_module_context();
require_login();
$PAGE->set_context($context);
if (!has_capability('local/evaluation:edititems', $context) OR !has_capability('local/evaluation:createpublictemplate', $context) ) {
    print_error(get_string('no_permission_to_view_this_page', 'local_evaluation')); 
}

$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));

if (empty($evaluation)) {
  print_error(get_string('feedback_not_found', 'local_evaluation'));
}

if ($evaluation->plugin === "classroom"){
    $classroom = $DB->get_record('local_classroom', array('id' => $evaluation->instance));
    if (empty($classroom)) {
        print_error(get_string('classroom_not_found', 'local_evaluation'));
    }
    if ((has_capability('local/classroom:manageclassroom', (new \local_evaluation\lib\accesslib())::get_module_context())) && (!is_siteadmin()
    )) {
            if(explode('/',$classroom->open_path)[1] != (new \local_evaluation\lib\accesslib())::get_user_roleswitch_path(1)){
             print_error(get_string('no_permissions', 'local_evaluation'));
            }

    }
}elseif ($evaluation->plugin === "program"){
    $program = $DB->get_record('local_program', array('id' => $evaluation->instance));
    if (empty($program)) {
        print_error(get_string('program_not_found', 'local_evaluation'));
    }
    if ((has_capability('local/program:manageprogram', (new \local_evaluation\lib\accesslib())::get_module_context())) && (!is_siteadmin()
        )) {
            if(explode('/',$classroom->open_path)[1] != (new \local_evaluation\lib\accesslib())::get_user_roleswitch_path(1)){
             print_error(get_string('no_permissions', 'local_evaluation'));
            }

    }
}

else{
    $feedback = $DB->get_record('local_evaluations',array('id' => $id));
}




$PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'displayquestion', array(0,0));
$PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'displaytemplate');

$evaluationstructure = new local_evaluation_structure($evaluation);
if ($evaluation->instance == 0)
$PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'init', array('[data-action=createevaluationmodal]', $context->id, $id, $evaluation->instance, $evaluation->plugin));
if ($switchitemrequired) {
    require_sesskey();
    $items = $evaluationstructure->get_items();
    if (isset($items[$switchitemrequired])) {
        evaluation_switch_item_required($items[$switchitemrequired]);
    }
    redirect($url);
}

if ($deleteitem) {
    require_sesskey();
    $items = $evaluationstructure->get_items();
    if (isset($items[$deleteitem])) {
        evaluation_delete_item($deleteitem);
    }
    redirect($url);
}

// Process the create template form.
$cancreatetemplates = has_capability('local/evaluation:createprivatetemplate', $context) ||
            has_capability('local/evaluation:createpublictemplate', $context);
$create_template_form = new evaluation_edit_create_template_form(null, array('id' => $id));
if ($data = $create_template_form->get_data()) {
    // Check the capabilities to create templates.
    if (!$cancreatetemplates) {
        print_error('cannotsavetempl', 'local_evaluation', $url);
    }
    $ispublic = !empty($data->ispublic) ? 1 : 0;
    if (!evaluation_save_as_template($evaluation, $data->templatename, $ispublic, $data)) {
        redirect($url, get_string('saving_failed', 'local_evaluation'), null, \core\output\notification::NOTIFY_ERROR);
    } else {
        redirect($url, get_string('template_saved', 'local_evaluation'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

//Get the evaluationitems
$lastposition = 0;
$evaluationitems = $DB->get_records('local_evaluation_item', array('evaluation'=>$evaluation->id), 'position');
if (is_array($evaluationitems)) {
    $evaluationitems = array_values($evaluationitems);
    if (count($evaluationitems) > 0) {
        $lastitem = $evaluationitems[count($evaluationitems)-1];
        $lastposition = $lastitem->position;
    } else {
        $lastposition = 0;
    }
}
$lastposition++;

$PAGE->set_url('/local/evaluation/eval_view.php', array('id'=>$evaluation->id, 'do_show'=>$do_show));
$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);
if ($evaluation->instance == 0) {
    $PAGE->navbar->add(get_string("manageevaluation", 'local_evaluation'), new moodle_url('index.php'));
} else {
    if ($evaluation->plugin === "classroom")
    $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?cid='.$evaluation->instance.''));
    elseif ($evaluation->plugin === "program")
    $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?pid='.$evaluation->instance.''));
    elseif ($evaluation->plugin === "certification")
    $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?ctid='.$evaluation->instance.''));
    else
    $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?id='.$evaluation->instance.''));
}
$PAGE->navbar->add($evaluation->name);
//Adding the javascript module for the items dragdrop.
if (count($evaluationitems) > 1) {
    if ($do_show == 'edit') {
        $PAGE->requires->strings_for_js(array(
               'pluginname',
               'move_item',
               'position',
            ), 'local_evaluation');
        $PAGE->requires->yui_module('moodle-local_evaluation-dragdrop', 'M.local_evaluation.init_dragdrop',
                array(array('cmid' => $evaluation->id)));
    }
}
//Print the page header.
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_evaluation');
$renderable = new evaluations($id, $evaluationstructure);
echo $renderer->render($renderable);
$path = evaluation_return_url($evaluation->plugin, $evaluation);
echo $OUTPUT->footer();
