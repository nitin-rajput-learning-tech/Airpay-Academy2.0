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

evaluation_init_evaluation_session();

$id = required_param('id', PARAM_INT);

if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

$do_show = optional_param('do_show', 'edit', PARAM_ALPHA);
$switchitemrequired = optional_param('switchitemrequired', false, PARAM_INT);
$deleteitem = optional_param('deleteitem', false, PARAM_INT);
$url = new moodle_url('/local/evaluation/eval_view.php', array('id'=>$id, 'do_show'=>$do_show));

$PAGE->set_pagelayout('standard');
$context = (new \local_evaluation\lib\accesslib())::get_module_context($id);
require_login();
$PAGE->set_context($context);
require_capability('local/evaluation:edititems', $context);
$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));
$evaluationstructure = new local_evaluation_structure($evaluation);

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
        exit;
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


//The use_template-form
$use_template_form = new evaluation_edit_use_template_form('use_templ.php', array('course' => 0, 'id' => $id));

//Print the page header.
$strevaluations = get_string('name', 'local_evaluation');
$strevaluation  = get_string('name', 'local_evaluation');

$PAGE->set_url('/local/evaluation/edit.php', array('id'=>$evaluation->id, 'do_show'=>$do_show));
$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);


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

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($evaluation->name));

// Print the main part of the page.

if ($do_show == 'templates') {
    // Print the template-section.
    $use_template_form->display();

    if ($cancreatetemplates) {
        $deleteurl = new moodle_url('/local/evaluation/delete_template.php', array('id' => $id));
        $create_template_form->display();
        echo '<p><a href="'.$deleteurl->out().'">'.
             get_string('delete_templates', 'local_evaluation').
             '</a></p>';
    } else {
        echo '&nbsp;';
    }

    if (has_capability('local/evaluation:edititems', $context)) {
        $urlparams = array('action'=>'exportfile', 'id'=>$id);
        $exporturl = new moodle_url('/local/evaluation/export.php', $urlparams);
        $importurl = new moodle_url('/local/evaluation/import.php', array('id'=>$id));
        echo '<p>
            <a href="'.$exporturl->out().'">'.get_string('export_questions', 'local_evaluation').'</a>/
            <a href="'.$importurl->out().'">'.get_string('import_questions', 'local_evaluation').'</a>
        </p>';
    }
}

if ($do_show == 'edit') {
    // Print the Item-Edit-section.
    $select = new single_select(new moodle_url('/local/evaluation/edit_item.php',
            array('id' => $id, 'position' => $lastposition, 'sesskey' => sesskey())),
        'typ', evaluation_load_evaluation_items_options());
    $select->label = get_string('add_item', 'local_evaluation');
    echo $OUTPUT->render($select);

    $form = new local_evaluation_complete_form(local_evaluation_complete_form::MODE_EDIT, $evaluationstructure, 'evaluation_edit_form');
    echo '<div id="evaluation_dragarea">'; // The container for the dragging area.
    $form->display();
    echo '</div>';
}

echo $OUTPUT->footer();
