<?php
// One-shot verifier: does the password we set actually authenticate
// through Moodle's auth chain? Bypasses the web layer entirely so we
// know whether the issue is the hash or the login form.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$user = $DB->get_record('user', ['username' => 'fatma.khamis@airpay.tz'], '*', MUST_EXIST);
echo "User found: id={$user->id} username={$user->username} auth={$user->auth}\n";
echo "Password hash: " . substr($user->password, 0, 20) . "... (" . strlen($user->password) . " chars)\n";

$result = validate_internal_user_password($user, 'AcademyAudit2026!');
echo "validate_internal_user_password() result: " . var_export($result, true) . "\n";

$auth_result = authenticate_user_login($user->username, 'AcademyAudit2026!');
echo "authenticate_user_login() result: " . (is_object($auth_result) ? "user id={$auth_result->id}" : var_export($auth_result, true)) . "\n";
