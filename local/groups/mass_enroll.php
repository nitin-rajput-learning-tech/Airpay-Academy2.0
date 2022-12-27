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

set_time_limit(0);
ini_set('memory_limit', '-1');
require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_once ('lib.php');
require_once($CFG->dirroot.'/local/lib.php');

/// Get params

$id = required_param('id', PARAM_INT);

$groups = $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);
if (empty($groups)) {
   print_error("Groups not found");
}

/// Security and access check

require_login();
$context =   (new \local_groups\lib\accesslib())::get_module_context();
require_capability('moodle/cohort:assign', $context);


$groupsdetails = $DB->get_record('local_groups', array('cohortid'=>$id));
//i.e other than admin eg:Org.Head



/// Start making page
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/groups/mass_enroll.php', array('id'=>$id));

$strinscriptions = get_string('mass_enroll', 'local_courses');

$PAGE->set_title($groups->name . ': ' . $strinscriptions);
$PAGE->set_heading($groups->name . ': '.get_string('assignments','local_groups'));

echo $OUTPUT->header();

$mform = new local_courses\form\mass_enroll_form($CFG->wwwroot . '/local/groups/mass_enroll.php', array (
	'course' => $groups,
    'context' => $context,
	'type' => 'groups'
));

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/groups/index.php'));
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
   
    $result = groups_mass_enroll($cir, $groups, $context, $data);
    
    $cir->close();
    $cir->cleanup(false); // only currently uploaded CSV file 
	/** The code has been disbaled to stop sending auto maila and make loading issues **/
    
    echo $OUTPUT->box(nl2br($result), 'center');

    echo $OUTPUT->continue_button(new moodle_url('/local/groups/index.php')); // Back to course page
    echo $OUTPUT->footer($groups);
    die();
}
echo $OUTPUT->box (get_string('mass_enroll_info', 'local_courses'), 'center');
echo html_writer::link(new moodle_url('/local/groups/index.php'),get_string('back', 'local_courses'),array('id'=>'back_tp_course'));
$sample = html_writer::link(new moodle_url('/local/courses/sample.php',array('format'=>'csv')),get_string('sample', 'local_courses'),array('id'=>'download_users'));
echo $sample;
$mform->display();
echo $OUTPUT->footer($groups);
