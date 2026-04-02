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
 * @subpackage local_notifications
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot. "/local/notifications/classes/local/email_status_func.php");
// use local_courses\local\notification_master; //use this namespace for calling class
global $CFG, $USER, $PAGE, $OUTPUT;
$id = optional_param('id', 0, PARAM_INT);
require_login();
$PAGE->set_url('/local/notifications/email_status_details.php', array());
$PAGE->set_context($sitecontext);
$PAGE->set_title(get_string('email_status_details', 'local_notifications'));
$PAGE->set_heading(get_string('email_status_details', 'local_notifications'));

$PAGE->navbar->add(get_string('notification_link','local_notifications'), new moodle_url("/local/notifications/index.php"));
$PAGE->navbar->add(get_string('email_status','local_notifications'), new moodle_url("/local/notifications/email_status.php"));
$PAGE->navbar->add(get_string('email_status_link','local_notifications'));
$PAGE->navbar->ignore_active();

$renderer = $PAGE->get_renderer('local_notifications');
echo $OUTPUT->header();
echo $return='<div class="coursebackup course_extended_menu_itemcontainer 
				pull-right">
                        <a href="'.$CFG->wwwroot.'/local/notifications/email_status.php" title="'.get_string('back').'" class="course_extended_menu_itemlink">
                          <i class="icon fa fa-reply"></i>
                        </a>
                </div>';
echo $renderer->view_email_status_details($id);
echo $OUTPUT->footer();
