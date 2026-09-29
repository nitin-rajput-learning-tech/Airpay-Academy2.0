<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY diagnostic for the visual pass: does each vp_* persona's stored
 * test password authenticate, and what would stop a browser login (reCAPTCHA,
 * forced password change, policy acceptance)? Prints reasons only, never a
 * password. Refuses anything that is not a localhost wwwroot.
 *
 *   php check_local_login.php   (cwd = moodle5/public)
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: not a local development host.\n");
    exit(1);
}
global $DB;
$creds = json_decode(file_get_contents(__DIR__ . '/.personas.local.json'), true);
echo 'login reCAPTCHA (enableloginrecaptcha): ' . var_export(get_config('core', 'enableloginrecaptcha'), true)
    . ' | recaptcha keys set: ' . (get_config('core', 'recaptchapublickey') ? 'yes' : 'no')
    . ' | sitepolicy handler: ' . var_export(get_config('core', 'sitepolicyhandler'), true)
    . ' | lockout threshold: ' . var_export(get_config('core', 'lockoutthreshold'), true) . "\n";
foreach ($creds as $username => $c) {
    $reason = null;
    $user = authenticate_user_login($username, $c['password'], false, $reason);
    $u = $DB->get_record('user', ['username' => $username], 'id, auth, confirmed, suspended, policyagreed');
    $locked = get_user_preferences('login_lockout', null, $u->id);
    $force = get_user_preferences('auth_forcepasswordchange', null, $u->id);
    printf("%-15s auth=%s confirmed=%d suspended=%d policyagreed=%d locked=%s forcechange=%s -> %s (reason %s)\n",
        $username, $u->auth, $u->confirmed, $u->suspended, $u->policyagreed, var_export($locked, true),
        var_export($force, true), $user ? 'AUTH OK' : 'AUTH FAILED', var_export($reason, true));
}
