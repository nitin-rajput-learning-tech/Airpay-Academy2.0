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


require_once("../../config.php");
require_once("lib.php");

$current_tab = 'templates';

$id = required_param('id', PARAM_INT);
$deletetempl = optional_param('deletetempl', false, PARAM_INT);

$baseurl = new moodle_url('/local/evaluation/delete_template.php', array('id' => $id));
$PAGE->set_url($baseurl);

$context = (new \local_evaluation\lib\accesslib())::get_module_context();

require_login();
require_capability('local/evaluation:deletetemplate', $context);

$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));
$systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context($id);

// Process template deletion.
if ($deletetempl) {
    require_sesskey();
    $template = $DB->get_record('local_evaluation_template', array('id' => $deletetempl), '*', MUST_EXIST);

    if ($template->ispublic) {
        require_capability('local/evaluation:createpublictemplate', $systemcontext);
        require_capability('local/evaluation:deletetemplate', $systemcontext);
    }

    evaluation_delete_template($template);
    redirect($baseurl, get_string('template_deleted', 'local_evaluation'));
}

/// Print the page header
$strevaluations = get_string("name", "local_evaluation");
$strevaluation  = get_string("name", "local_evaluation");
$strdeleteevaluation = get_string('delete_template', 'local_evaluation');

navigation_node::override_active_url(new moodle_url('/local/evaluation/edit.php',
        array('id' => $id, 'do_show' => 'templates')));
$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);
$PAGE->navbar->add(get_string("manageevaluation", 'local_evaluation'), new moodle_url('index.php'));
$PAGE->navbar->add($evaluation->name, new moodle_url('eval_view.php#templates', array('id' => $id )));
echo $OUTPUT->header();

// Print the main part of the page.
echo $OUTPUT->heading($strdeleteevaluation, 3);

// Now we get the public templates if it is permitted.
if (has_capability('local/evaluation:createpublictemplate', $systemcontext) AND
    has_capability('local/evaluation:deletetemplate', $systemcontext)) {
    $templates = evaluation_get_template_list('all');
    echo $OUTPUT->box_start('publictemplates');
    $tablepublic = new local_evaluation_templates_table('evaluation_template_public_table', $baseurl);
    $tablepublic->display($templates);
    echo $OUTPUT->box_end();
}

$url = new moodle_url('/local/evaluation/eval_view.php#templates', array('id' => $id, 'do_show' => 'templates'));
echo $OUTPUT->single_button($url, get_string('back'), 'post');

echo $OUTPUT->footer();

