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
 * @subpackage local_assigroles
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->dirroot . '/' . $CFG->admin . '/roles/lib.php');
require_once($CFG->dirroot . '/local/lib.php');
require_once($CFG->libdir.'/adminlib.php');
global $PAGE,$USER;



$context =  (new \local_assignroles\lib\accesslib())::get_module_context();

require_login();

// admin_externalpage_setup('assignroles', '', array('contextid' => $contextid, 'roleid' => $roleid));
$heading = get_string('assignroles', 'local_assignroles');
$PAGE->set_context($context);
$PAGE->set_heading($heading);
$pageurl = new moodle_url('/local/assignroles/index.php', array());
$PAGE->set_url($pageurl);
$PAGE->set_title($heading);

$PAGE->navbar->add($heading);
$PAGE->requires->js_call_amd('local_assignroles/newassignrole', 'load', array());
$PAGE->requires->js_call_amd('local_assignroles/popup', 'Datatable', array());

if(!(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context) || has_capability('local/assignroles:manageassignroles', $context))){
	throw new moodle_exception(get_string('errornopermission', 'local_assignroles'));
}
echo $OUTPUT->header();

$PAGE->requires->js_call_amd('local_assignroles/popup', 'init',array(array('contextid' => $context->id, 'selector' => '.rolesuserpopup')));

$assignrolesclass=$PAGE->get_renderer('local_assignroles');

echo $assignrolesclass->display_roles($context);

echo $OUTPUT->footer();
