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
 * @subpackage local_program
 */

require_once(dirname(__FILE__) . '/../../config.php');
use local_program\program;
$programid = required_param('bcid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$status = optional_param('status', 0, PARAM_INT);

$categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
require_login();
$PAGE->set_url('/local/program/view.php', array('bcid' => $programid));
$PAGE->set_context($categorycontext);
$PAGE->set_title(get_string('programs', 'local_program'));

$renderer = $PAGE->get_renderer('local_program');
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'load', array());
$program=$renderer->programview_check($programid);

$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('index.php'));
$PAGE->navbar->add($program->name);
$PAGE->set_heading($program->name);

$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/local/program/css/jquery.dataTables.min.css', true);
$PAGE->requires->js_call_amd('local_program/ajaxforms', 'load');
$PAGE->requires->js_call_amd('local_request/requestconfirm', 'load');
$PAGE->requires->js_call_amd('theme_epsilon/quickactions', 'quickactionsCall');
$PAGE->requires->js_call_amd('local_classroom/classroom', 'load', array());
$renderer = $PAGE->get_renderer('local_program');

$content = $renderer->viewprogram($programid);
echo $OUTPUT->header();
echo $content;
echo $OUTPUT->footer();