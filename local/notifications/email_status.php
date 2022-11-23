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
global $CFG, $USER, $PAGE, $OUTPUT;
$id = optional_param('id', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$sitecontext =(new \local_notifications\lib\accesslib())::get_module_context();
require_login();
$PAGE->set_url('/local/notifications/email_status.php', array());
$PAGE->set_context($sitecontext);
$PAGE->set_title(get_string('email_status', 'local_notifications'));
$PAGE->set_heading(get_string('email_status', 'local_notifications'));

$PAGE->navbar->add(get_string('notification_link','local_notifications'), new moodle_url("/local/notifications/index.php"));
$PAGE->navbar->add(get_string('email_status','local_notifications'));
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/local/notifications/css/jquery.dataTables.min.css', true);
$PAGE->requires->js(new moodle_url('/local/notifications/js/jquery.dataTables.min.js'),true);
$renderer = $PAGE->get_renderer('local_notifications');
echo $OUTPUT->header();
if (is_siteadmin() || has_capability('local/costcenter:manage_ownorganization', $sitecontext)) {
    echo $renderer->view_email_status();
} else {
    echo "<h4>".get_string('dont_have_permission_view_page', 'local_notifications')."</h4>";
}
echo $OUTPUT->footer();
