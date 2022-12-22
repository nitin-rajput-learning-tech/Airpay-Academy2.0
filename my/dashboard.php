<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Lists the course categories
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package course
 */

require_once("../config.php");
require_once($CFG->dirroot. '/course/lib.php');
$orgid  = optional_param('orgid', 0, PARAM_INT);
$context =(new \local_costcenter\lib\accesslib())::get_module_context();
$site = get_site();
if ($CFG->forcelogin) {
    require_login();
}
$heading = $site->fullname;
if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){
    if($orgid == 0){
        $orgid = $DB->get_field_sql("SELECT min(id) FROM {local_costcenter} WHERE parentid = 0 AND visible = 1 ");
    }
}else if($USER->open_costcenterid){
    $orgid = $USER->open_costcenterid;
}
$context = (new \local_users\lib\accesslib())::get_module_context();
$PAGE->set_category_by_id($context->instanceid);
$PAGE->set_url(new moodle_url('/my/dashboard.php', array('orgid' => $orgid)));

$PAGE->set_pagelayout('mydashboard');
$PAGE->set_primary_active_tab('home');
$PAGE->add_body_class('limitedwidth');
$courserenderer = $PAGE->get_renderer('core', 'course');
$PAGE->set_heading($heading);
echo $OUTPUT->header();
echo $OUTPUT->skip_link_target();
echo $content;
// Trigger event, course category viewed.
$eventparams = array('context' => $PAGE->context, 'objectid' => $categoryid);
$event = \core\event\course_category_viewed::create($eventparams);
$event->trigger();


echo $OUTPUT->footer();
