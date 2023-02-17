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
$sessionid = required_param('id', PARAM_INT);
$levelid = optional_param('levelid', 0, PARAM_INT);
$bclcid = optional_param('bclcid', 0, PARAM_INT);
$enrol = optional_param('enrol', 0, PARAM_INT);
require_login();
$categorycontext = (new \local_program\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$url = new moodle_url($CFG->wwwroot . '/local/program/enrol.php', array('bcid' => $programid,
    'id' => $sessionid));

$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

if ($enrol > 0) {
    $enroldata = new stdClass();
    $enroldata->programid = $programid;
    $enroldata->levelid = $levelid;
    $enroldata->bclcid = $bclcid;
    $enroldata->sessionid = $sessionid;
    $enroldata->enrol = $enrol;
    $enroldata->userid = $USER->id;
    $signup = (new program)->bc_session_enrolments($enroldata);
    if ($signup) {
        redirect($CFG->wwwroot . '/local/program/view.php?bcid=' . $programid);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->footer();