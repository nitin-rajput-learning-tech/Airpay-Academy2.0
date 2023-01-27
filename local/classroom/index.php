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
 * @package Bizlms 
 * @subpackage local_classroom
 */

require_once(dirname(__FILE__) . '/../../config.php');
$categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
global $DB;
$value = '';
require_login();
$PAGE->set_url('/local/classroom/index.php', array());
$PAGE->set_context($categorycontext);
$formattype = optional_param('formattype', 'card', PARAM_TEXT);
if ($formattype == 'card') {
    $formattype_url = 'table';
    $display_text = get_string('listtype','local_classroom');
} else {
    $formattype_url = 'card';
    $display_text = get_string('cardtype','local_classroom');
}
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
if (!is_siteadmin() && !(has_capability('local/classroom:manageclassroom', $categorycontext))) {
	$PAGE->set_title(get_string('my_classrooms', 'local_classroom'));
	$PAGE->set_heading(get_string('my_classrooms', 'local_classroom'));
}else{
	$PAGE->set_title(get_string('browse_classrooms', 'local_classroom'));
	$PAGE->set_heading(get_string('browse_classrooms', 'local_classroom'));
}

$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('local_classroom/ajaxforms', 'load');
$PAGE->requires->js_call_amd('local_classroom/classroom', 'load');
$PAGE->requires->js_call_amd('local_classroom/classroom', 'classroomsData', array('view_type' => $formattype));

$core_component = new core_component();
$epsilon_plugin_exist = $core_component::get_plugin_directory('theme', 'epsilon');
if(!empty($epsilon_plugin_exist)){
	$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
}
$renderer = $PAGE->get_renderer('local_classroom');
$PAGE->navbar->add(get_string("pluginname", 'local_classroom'));
echo $OUTPUT->header();

$enabled = check_classroomenrol_pluginstatus($value);

echo $renderer->get_classroom_tabs($formattype);
echo $OUTPUT->footer();
