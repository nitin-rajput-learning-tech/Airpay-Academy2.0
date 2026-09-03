<?php
// finish_install.php - replay the tail of Moodle's install_cli_database() (lib/installlib.php)
// when install_database.php aborted inside a plugin and the site was completed via upgrade.php.
// Symptoms it fixes: admin password still the literal 'adminsetuppending', empty admin email,
// $CFG->rolesactive = 0, empty site name -> every page 303s to admin/index.php and dies with
// "Installation must be finished from the original IP address".
//
// Run AS THE WEB USER with the values in the environment:
//   sudo -u www-data env ADMINPASS=... ADMINEMAIL=... FULLNAME=... SHORTNAME=... MOODLE_CONFIG=/path/config.php php finish_install.php
define('CLI_SCRIPT', true);

$configfile = getenv('MOODLE_CONFIG') ?: '/var/www/html/moodle5.2/config.php';
require($configfile);
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/upgradelib.php');

$adminpass  = (string) getenv('ADMINPASS');
$adminemail = (string) getenv('ADMINEMAIL');
$fullname   = (string) getenv('FULLNAME');
$shortname  = (string) getenv('SHORTNAME');
if ($adminpass === '') {
    fwrite(STDERR, "ADMINPASS is required\n");
    exit(1);
}

$admin = $DB->get_record('user', ['username' => 'admin'], '*', MUST_EXIST);
$placeholder = ($admin->password === 'adminsetuppending');
echo "Before: rolesactive=" . var_export(get_config('core', 'rolesactive'), true)
   . " admin-pw-state:" . ($placeholder ? 'PLACEHOLDER' : 'set')
   . " admin.email=" . var_export($admin->email, true) . "\n";

// --- lib/installlib.php install_cli_database() tail, faithfully ---
// set up admin user password
$DB->set_field('user', 'password', hash_internal_user_password($adminpass), ['username' => 'admin']);
// Set the admin email address if specified.
if ($adminemail !== '') {
    $DB->set_field('user', 'email', $adminemail, ['username' => 'admin']);
    if (empty($CFG->supportemail)) {
        set_config('supportemail', $adminemail);
    }
}
// indicate that this site is fully configured
set_config('rolesactive', 1);
upgrade_finished();
// log in as admin - we need do anything when applying defaults
\core\session\manager::set_user(get_admin());
// Apply all default settings.
admin_apply_default_settings(null, true);
set_config('registerauth', '');
// set the site name
if ($shortname !== '') {
    $DB->set_field('course', 'shortname', $shortname, ['format' => 'site']);
}
if ($fullname !== '') {
    $DB->set_field('course', 'fullname', $fullname, ['format' => 'site']);
}
// Redirect to site registration on first login.
set_config('registrationpending', 1);
// Never leave the web installer's "finish here" state behind.
unset_config('adminsetuppending');
purge_all_caches();

$admin = $DB->get_record('user', ['username' => 'admin'], '*', MUST_EXIST);
echo "After:  rolesactive=" . var_export(get_config('core', 'rolesactive'), true)
   . " admin-pw-state:" . ($admin->password === 'adminsetuppending' ? 'PLACEHOLDER' : 'set')
   . " admin.email=" . var_export($admin->email, true)
   . " site=" . var_export($DB->get_field('course', 'fullname', ['format' => 'site']), true) . "\n";
echo "finish_install: done\n";
