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
require_once('use_templ_form.php');

$id = required_param('id', PARAM_INT);
$templateid = optional_param('templateid', false, PARAM_INT);

if (!$templateid) {
    redirect('edit.php?id='.$id);
}

$url = new moodle_url('/local/evaluation/use_templ.php', array('id'=>$id, 'templateid'=>$templateid));
$PAGE->set_url($url);

$context = (new \local_evaluation\lib\accesslib())::get_module_context();

require_login();

$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));
$evaluationstructure = new local_evaluation_structure($evaluation, $templateid);

require_capability('local/evaluation:edititems', $context);

$mform = new local_evaluation_use_templ_form();
$mform->set_data(array('id' => $id, 'templateid' => $templateid));

if ($mform->is_cancelled()) {
    redirect('eval_view.php?id='.$id.'&do_show=templates');
} else if ($formdata = $mform->get_data()) {
    evaluation_items_from_template($evaluation, $templateid, $formdata->deleteolditems);
    redirect('eval_view.php?id=' . $id);
}


