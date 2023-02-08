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
 * @subpackage local_program
 */

set_time_limit(0);
ini_set('memory_limit', '-1');
require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_once ('lib.php');
require_once($CFG->dirroot.'/local/lib.php');

/// Get params

$id = required_param('id', PARAM_INT);

$program = $DB->get_record('local_program', array('id' => $id));
if (empty($program)) {
    print_error(get_string('program_not_found', 'local_program'));
}
if ((has_capability('local/program:manageprogram', context_system::instance())) && (!is_siteadmin()
    && (!has_capability('local/program:manage_multiorganizations', context_system::instance())
        && !has_capability('local/costcenter:manage_multiorganizations', context_system::instance())))) {
        if($program->costcenter!=$USER->open_costcenterid){
         print_error(get_string('dont_have_permissions', 'local_program'));
        }

        if ((has_capability('local/program:manage_owndepartments', context_system::instance())
         || has_capability('local/costcenter:manage_owndepartments', context_system::instance()))) {
            $programs = $DB->get_record('local_program',array('id'=>$programid),$fields = 'id,costcenter,department');
             if(!in_array(explode(',',$programs->department)) != $USER->open_departmentid){
                print_error(get_string('dont_have_permissions', 'local_program'));  
             }
        }
}


/// Security and access check

require_login();
$context =  context_system::instance();
require_capability('local/program:manageprogram', $context);
require_capability('local/program:manageusers', $context);
 
/// Start making page
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/program/mass_enroll.php', array('id'=>$id));

$strinscriptions = get_string('mass_enroll', 'local_courses');

$PAGE->set_title($program->name . ': ' . $strinscriptions);
$PAGE->set_heading($program->name . ': ' . $strinscriptions);
$PAGE->navbar->add(get_string("pluginname", 'local_program'), new moodle_url('/local/program/index.php'));
$PAGE->navbar->add($program->name);
$PAGE->navbar->add(get_string("bulkenrolments", 'local_program'));

echo $OUTPUT->header();
$mform = new local_courses\form\mass_enroll_form($CFG->wwwroot . '/local/program/mass_enroll.php', array (
	'course' => $program,
    'context' => $context,
	'type' => 'program'
));

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/program/index.php'));
} else
if ($data = $mform->get_data(false)) { // no magic quotes
    echo $OUTPUT->heading($strinscriptions);

    $iid = csv_import_reader::get_new_iid('uploaduser');
    $cir = new csv_import_reader($iid, 'uploaduser');

    $content = $mform->get_file_content('attachment');

    $readcount = $cir->load_csv_content($content, $data->encoding, $data->delimiter_name);
    unset($content);

    if ($readcount === false) {
        print_error('csvloaderror', '', $returnurl);
    } else if ($readcount == 0) {
        print_error('csvemptyfile', 'error', $returnurl);
    }
   
    $result = program_mass_enroll($cir, $program, $context, $data);
    
    $cir->close();
    $cir->cleanup(false); // only currently uploaded CSV file 
	/** The code has been disbaled to stop sending auto maila and make loading issues **/
    
    echo $OUTPUT->box(nl2br($result), 'center');

    echo $OUTPUT->continue_button(new moodle_url('/local/program/view.php?bcid='.$id)); // Back to course page
    echo $OUTPUT->footer($program);
    die();
}
echo $OUTPUT->heading_with_help($strinscriptions, 'mass_enroll', 'local_courses','',get_string('mass_enroll', 'local_courses'));
echo $OUTPUT->box (get_string('mass_enroll_info', 'local_courses'), 'center');
echo html_writer::link(new moodle_url('/local/program/index.php'),get_string('back', 'local_courses'),array('id'=>'back_tp_course'));
$sample = html_writer::link(new moodle_url('/local/courses/sample.php',array('format'=>'csv')),get_string('sample', 'local_courses'),array('id'=>'download_users'));
echo $sample;
$mform->display();
echo $OUTPUT->footer($program);
