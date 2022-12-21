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

evaluation_init_evaluation_session();

$itemid = optional_param('id', false, PARAM_INT); 
$typ = optional_param('typ', '', PARAM_ALPHA);

$id = optional_param('cmid', -1, PARAM_INT); // this is not coursemodule id

if ($id == -1 OR $id == 0) {
    $existingitem = $DB->get_record('local_evaluation_item', array('id' => $itemid), '*', MUST_EXIST);
    $id = $existingitem->evaluation;
}

$url = new moodle_url('/local/evaluation/edit_item.php', array('id' => $itemid, 'typ' => $typ));
$item = (object)['id' => null, 'position' => -1, 'typ' => $typ, 'options' => ''];
require_login();
$context = (new \local_evaluation\lib\accesslib())::get_module_context($id);
require_capability('local/evaluation:edititems', $context);
$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));

$editurl = new moodle_url('/local/evaluation/eval_view.php', array('id' => $evaluation->id));

$PAGE->set_url($url);

// If the typ is pagebreak so the item will be saved directly.
if (!$item->id && $typ === 'pagebreak') {
    require_sesskey();
    evaluation_create_pagebreak($evaluation->id);
    redirect($editurl->out(false));
    exit;
}

//get the existing item or create it
if (!$typ || !file_exists($CFG->dirroot.'/local/evaluation/item/'.$typ.'/lib.php')) {
    print_error('typemissing', 'evaluation', $editurl->out(false));
}

require_once($CFG->dirroot.'/local/evaluation/item/'.$typ.'/lib.php');

$itemobj = evaluation_get_item_class($typ);

$itemobj->build_editform($item, $evaluation);
if ($itemobj->is_cancelled()) {
    redirect($editurl);
    exit;
}
if ($itemobj->get_data()) {
    if ($item = $itemobj->save_item()) {
        evaluation_move_item($item, $item->position);
        redirect($editurl);
    }
}

////////////////////////////////////////////////////////////////////////////////////
/// Print the page header

navigation_node::override_active_url(new moodle_url('/local/evaluation/edit.php',
        array('id' => $evaluation->id, 'do_show' => 'edit')));
if ($item->id) {
    $PAGE->navbar->add(get_string('edit_item', 'local_evaluation'));
} else {
    $PAGE->navbar->add(get_string('add_item', 'local_evaluation'));
}
$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);
echo $OUTPUT->header();

// Print the main part of the page.
echo $OUTPUT->heading(format_string($evaluation->name));

$id = $evaluation->id;

//print errormsg
if (isset($error)) {
    echo $error;
}
$itemobj->show_editform(); // form is diasabled

echo $OUTPUT->footer();
