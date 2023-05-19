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
 * user signup page.
 *
 * @package    core
 * @subpackage auth
 * @copyright  1999 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');


global $OUTPUT, $DB, $CFG, $PAGE;

$PAGE->set_url('/local/users/signup.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('registrationtitle', 'local_users'));
$PAGE->set_pagelayout('frontpage');
$PAGE->set_pagetype('site-index');
$PAGE->requires->jquery();
$success = optional_param('success', 0, PARAM_INT);
// If wantsurl is empty or /login/signup.php, override wanted URL.
// We do not want to end up here again if user clicks "Login".

$userslib = new local_users\functions\users();
$formdata = [];
$mform = new local_users\forms\registration_form();

if(isloggedin())
{
    redirect($CFG->wwwroot.'/my/dashboard.php');
}
if ($mform->is_cancelled()) {
    redirect(get_login_url());

} else if ($user = $mform->get_data()) {

$organization_shortname = get_config('local_users','organization_shortname');

$company = $DB->get_field('local_costcenter', 'id', array('parentid' => '0','shortname'=>$organization_shortname));
    if($company)
    {
        $user->open_costcenterid = $company;
    }

    $obj = (object) array('open_costcenterid'=>$user->open_costcenterid);

    local_costcenter_get_costcenter_path($obj);    
    $user->open_path = $obj->open_path;

    $res = $userslib->insert_newuser($user);
    if($res)
    {
        $a = new \stdClass();
        $a->username = $user->username;        
        $messagetext = get_string('regisemailbody', 'local_users', $a);
        $messagehtml = text_to_html($messagetext, null, false, true);
        $subject = get_string('emailsubject', 'local_users');
        $from = new stdClass();
        $from = get_admin();
        $baseurl = new moodle_url('/local/users/signup.php', array('success' => '1'));

        email_to_user($user, $from, $subject, $messagetext, $messagehtml);

        redirect($baseurl, get_string('registraionsuccess', 'local_users'), null, \core\output\notification::NOTIFY_SUCCESS);
        exit;

    }


}

echo $OUTPUT->header();
echo '<div class="container">';
if($success==0){
$mform->display();
}else
{   echo '<div class="float-right">';
    echo $OUTPUT->single_button($CFG->wwwroot , get_string('backtohome', 'local_users'));
    echo '</div>';
}
echo '</div>';
echo $OUTPUT->footer();
