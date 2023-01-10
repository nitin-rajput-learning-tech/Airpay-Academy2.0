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
 * This script triggers a full purging of system caches,
 * this is useful mostly for developers who did not disable the caching.
 *
 * @package    core
 * @copyright  2016 Sri Harsha
 */

require_once('../config.php');
require_once($CFG->libdir.'/adminlib.php');
global $USER,$PAGE, $OUTPUT;
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);
$roleid = optional_param('switchrole', 0, PARAM_INT);
$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$purge = optional_param('p', null, PARAM_BOOL);


$context = context::instance_by_id($contextid);
//check the context level of the user and check whether the user is login to the system or not
$PAGE->set_context($context);
require_login();
$PAGE->set_url('/my/switchrole.php');
$PAGE->set_title(get_string('switchrole'));
$PAGE->set_pagelayout('maintenance');

if ($returnurl) {
    redirect($returnurl, get_string('purgecachesfinished', 'admin'));
}
// If we have got here as a confirmed aciton, do it.
if ($confirm && isloggedin() && confirm_sesskey() && !empty($roleid)) {

    $OUTPUT->role_switch_basedon_userroles($roleid, $purge, $contextid);
    // Valid request. Purge, and redirect the user back to where they came from.
    //purge_all_caches();
    $returnurl = new moodle_url('/my/index.php');
    $role = $DB->get_record('role',  array('id' => $roleid), '*', MUST_EXIST);
    $displayname = !empty($role->name) ? $role->name : $role->shortname;
    $image = html_writer::empty_tag('image', array('src' => $OUTPUT->image_url('i/loading'),
            'alt' => 'loading', 'title' => 'loading'));
     redirect($returnurl);
}