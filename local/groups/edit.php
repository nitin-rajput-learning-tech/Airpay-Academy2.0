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

require($CFG->dirroot.'/course/lib.php');

require($CFG->dirroot.'/local/groups/lib.php');
require($CFG->dirroot.'/local/groups/classes/form/edit_form.php');

global $DB, $OUTPUT,$USER,$CFG;

$id        = optional_param('id', 0, PARAM_INT);
$contextid = optional_param('contextid', 1, PARAM_INT);
$delete    = optional_param('delete', 0, PARAM_BOOL);
$show      = optional_param('show', 0, PARAM_BOOL);
$hide      = optional_param('hide', 0, PARAM_BOOL);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

require_login();

$category = null;
if ($id) {
    $groups = $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);
    $context =  (new \local_groups\lib\accesslib())::get_module_context();
    $groupsdetails = $DB->get_record('local_groups', array('cohortid'=>$id));
    //i.e other than admin eg:Org.Head
} else {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
        print_error('invalidcontext');
    }
    $groups = new stdClass();
    $groups->id          = 0;
    $groups->contextid   = $context->id;
    $groups->name        = '';
    $groups->description = '';
}

require_capability('moodle/cohort:manage', $context);
$PAGE->requires->js_call_amd('local_groups/renderselections', 'init');
if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/local/groups/index.php', array('contextid'=>$context->id));
}

if (!empty($groups->component)) {
    // We can not manually edit groupss that were created by external systems, sorry.
    redirect($returnurl);
}

$PAGE->set_context($context);
$baseurl = new moodle_url('/local/groups/edit.php', array('contextid' => $context->id, 'id' => $groups->id));
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    navigation_node::override_active_url(new moodle_url('/groups/index.php', array('contextid'=>$groups->contextid)));
} else {
    navigation_node::override_active_url(new moodle_url('/local/groups/index.php', array()));
}

if ($delete and $groups->id) {
    $PAGE->url->param('delete', 1);
    if ($confirm and confirm_sesskey()) {
        local_groups_delete_groups($groups);
        redirect($returnurl);
    }
    $strheading = get_string('delgroups', 'local_groups');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    $PAGE->set_heading($COURSE->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/local/groups/edit.php', array('id' => $groups->id, 'delete' => 1,
        'confirm' => 1, 'sesskey' => sesskey(), 'returnurl' => $returnurl->out_as_local_url()));
    $message = get_string('delconfirm', 'local_groups', format_string($groups->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

if ($show && $groups->id && confirm_sesskey()) {
    if (!$groups->visible) {
        $record = (object)array('id' => $groups->id, 'visible' => 1, 'contextid' => $groups->contextid);
        local_groups_update_groups($record);
    }
    redirect($returnurl);
}

if ($hide && $groups->id && confirm_sesskey()) {
    if ($groups->visible) {
        $record = (object)array('id' => $groups->id, 'visible' => 0, 'contextid' => $groups->contextid);
        local_groups_update_groups($record);
    }
    redirect($returnurl);
}

$editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => $SITE->maxbytes, 'context' => $context);
if ($groups->id) {
    // Edit existing.
    $groups = file_prepare_standard_editor($groups, 'description', $editoroptions,
            $context, 'local_groups', 'description', $groups->id);
    $strheading = get_string('editcohort', 'local_groups');

} else {
    // Add new.
    $groups = file_prepare_standard_editor($groups, 'description', $editoroptions,
            $context, 'local_groups', 'description', null);
    $strheading = get_string('addcohort', 'local_groups');
}

$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->navbar->add($strheading);

$editform = new groups_edit_form(null, array('editoroptions'=>$editoroptions, 'data'=>$groups, 'returnurl'=>$returnurl));
if ($id) {
    $cohort_group = $DB->get_record('local_groups', array('cohortid'=>$id));
    $groups->departmentid = explode(',',$cohort_group->departmentid);
    $editform->set_data($groups);
}

if ($editform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $editform->get_data()) {
    $oldcontextid = $context->id;
    $editoroptions['context'] = $context = context::instance_by_id($data->contextid);

    if ($data->id) {
        if ($data->contextid != $oldcontextid) {
            // Cohort was moved to another context.
            get_file_storage()->move_area_files_to_new_context($oldcontextid, $context->id,
                    'local_groups', 'description', $data->id);
        }
        $data = file_postupdate_standard_editor($data, 'description', $editoroptions,
                $context, 'local_groups', 'description', $data->id);
        local_groups_update_groups($data);
    } else {
        $data->descriptionformat = $data->description_editor['format'];
        $data->description = $description = $data->description_editor['text'];
        $data->id = local_groups_add_groups($data);
        $editoroptions['context'] = $context = context::instance_by_id($data->contextid);
        $data = file_postupdate_standard_editor($data, 'description', $editoroptions,
                $context, 'groups', 'description', $data->id);
        if ($description != $data->description) {
            $updatedata = (object)array('id' => $data->id,
                'description' => $data->description, 'contextid' => $context->id);
            local_groups_update_groups($updatedata);
        }
    }

    if ($returnurl->get_param('showall') || $returnurl->get_param('contextid') == $data->contextid) {
        // Redirect to where we were before.
        redirect($returnurl);
    } else {
        // Use new context id, it has been changed.
        redirect(new moodle_url('/local/groups/index.php', array('contextid' => $data->contextid)));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strheading);

if (!$id && ($editcontrols = local_groups_edit_controls($context, $baseurl))) {
    echo $OUTPUT->render($editcontrols);
}

echo $editform->display();
echo $OUTPUT->footer();

