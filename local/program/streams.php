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
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
global $CFG, $DB, $PAGE, $USER, $OUTPUT;
require_once($CFG->dirroot.'/local/program/lib.php');
require_login();
use local_program\program;
require_login();
$categorycontext = (new \local_program\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$url = new moodle_url($CFG->wwwroot . '/local/program/streams.php', array());
$PAGE->requires->js_call_amd('local_program/ajaxforms', 'load');
$PAGE->requires->js_call_amd('local_program/program', 'StreamsDatatable');
$renderer = $PAGE->get_renderer('local_program');
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('/local/program/index.php'));
$headingstr = get_string('view_streams', 'local_program');
$PAGE->navbar->add($headingstr);
$PAGE->set_heading($headingstr);
$PAGE->set_title($headingstr);
echo $OUTPUT->header();
$stable = new stdClass();
$stable->thead = true;
$stable->start = 0;
$stable->length = -1;
$stable->search = '';
echo $renderer->viewprogramstreams($stable);
echo $OUTPUT->footer();