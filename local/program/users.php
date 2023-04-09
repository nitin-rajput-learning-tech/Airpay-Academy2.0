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
$programid = required_param('bcid', PARAM_INT);
$download = optional_param('download', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_RAW);
$search = optional_param_array('search',  array(), PARAM_RAW);
require_login();
$categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
$costcenterpathconcatsql = (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path');

$programsql = "SELECT lp.*
                    FROM {local_program} AS lp WHERE lp.id = $programid $costcenterpathconcatsql ";

$program = $DB->get_record_sql($programsql);

if (empty($program)) {
    print_error('program not found!');
}

$PAGE->set_context($categorycontext);
$url = new moodle_url($CFG->wwwroot . '/local/program/users.php', array('bcid' => $programid));
$PAGE->requires->js_call_amd('local_program/program', 'UsersDatatable',
                    array(array('programid' => $programid)));
$renderer = $PAGE->get_renderer('local_program');
$PAGE->set_url($url);
$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('/local/program/index.php'));
$PAGE->navbar->add($program->name, new moodle_url('/local/program/view.php', array('bcid' => $programid)));
$PAGE->navbar->add(get_string("users", 'local_program'));
$PAGE->set_heading(get_string('programusers', 'local_program', $program->name));
$PAGE->set_pagelayout('standard');
if(!$download) {
    echo $OUTPUT->header();
    $stable = new stdClass();
    $stable->thead = true;
    $stable->start = 0;
    $stable->length = -1;
    $stable->search = '';
    $stable->programid = $programid;
    echo $renderer->viewprogramusers($stable);
    echo $OUTPUT->footer();
} else {
     // $search = optional_param('search', '', PARAM_RAW);exit;
     $exportplugin = $CFG->dirroot . '/local/program/export_xls.php';
     if (file_exists($exportplugin)) {
         require_once($exportplugin);
         if(!empty($programid)){
            $stable = new stdClass();
            $stable->thead = true;
            $stable->start = 0;
            $stable->length = -1;
            $stable->search = '';
            $stable->programid = $programid;
            export_report($programid, $stable, $type);
         }
     }
     die;
}
