<?php
// Temporary password reset for browser automation testing.
// User: academy@airpay.co.in (id=2, siteadmin)

define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
require_once($CFG->dirroot . '/user/lib.php');
global $DB;

$userid = 2;
$newpassword = 'Airpay@Test2026!';

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
update_internal_user_password($user, $newpassword);

echo "Password reset for user id={$userid} (username={$user->username})\n";
echo "New password: {$newpassword}\n";
echo "(For automated testing only — change in production via UI)\n";
