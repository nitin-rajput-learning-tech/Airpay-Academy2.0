<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Send a test push notification to a specific user — Phase B.2.5 ALPHA.
 *
 * IMPORTANT — Phase B.2.5 ALPHA: this exercises the hand-rolled web-push
 * crypto. Before relying on push delivery in production, run
 * cli/test_crypto.php to verify the encryption pipeline is self-consistent.
 *
 * Usage (cwd = moodle public/):
 *   php local/sentientia_pwa/cli/test_push.php --userid=12345 --title="Test" --body="Hello"
 *   php local/sentientia_pwa/cli/test_push.php --userid=12345 --dry-run
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'    => false,
    'userid'  => 0,
    'title'   => 'Sentientia LMS — test push',
    'body'    => 'If you see this, your push pipeline is working.',
    'url'     => '',
    'dry-run' => false,
], [
    'h' => 'help',
    'u' => 'userid',
    't' => 'title',
    'b' => 'body',
]);

if ($options['help'] || empty($options['userid'])) {
    echo <<<EOT

Send a test push notification — Sentientia LMS Phase B.2.5 ALPHA.

Usage:
  php local/sentientia_pwa/cli/test_push.php --userid=N [--title=...] [--body=...] [--url=...] [--dry-run]

Required:
  --userid=N     Numeric user ID. The push goes to EVERY subscription this user has.

Optional:
  --title=...    Notification title (default: "Sentientia LMS — test push")
  --body=...     Notification body
  --url=...      URL to open when user clicks the notification
  --dry-run      Don't actually POST — just print what WOULD be sent

EOT;
    exit($options['help'] ? 0 : 1);
}

$userid = (int) $options['userid'];

global $DB;
$user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, deleted, suspended');
if (!$user) {
    cli_writeln("User #{$userid} not found.");
    exit(1);
}
if ($user->deleted || $user->suspended) {
    cli_writeln("User #{$userid} is deleted or suspended.");
    exit(1);
}

cli_writeln('User:       ' . $user->firstname . ' ' . $user->lastname . ' (id=' . $userid . ')');

$subs = \local_sentientia_pwa\subscription_manager::for_user($userid);
cli_writeln('Subs found: ' . count($subs));

if (count($subs) === 0) {
    cli_writeln('');
    cli_writeln('User has no push subscriptions. Ask them to visit:');
    cli_writeln('  ' . $CFG->wwwroot . '/local/sentientia_pwa/preferences.php');
    cli_writeln('and click "Enable browser notifications" — then re-run this CLI.');
    exit(1);
}

foreach ($subs as $sub) {
    cli_writeln('  - ' . \local_sentientia_pwa\subscription_manager::endpoint_host($sub->endpoint)
        . ' (created ' . userdate((int) $sub->timecreated, '%Y-%m-%d') . ')');
}

cli_writeln('');

// Verify VAPID keypair is set up.
if (!\local_sentientia_pwa\vapid_key_manager::exists()) {
    cli_writeln('!! No VAPID keypair. Run cli/generate_vapid_keys.php first.');
    exit(1);
}
cli_writeln('VAPID:      ' . substr(\local_sentientia_pwa\vapid_key_manager::get_public_key(), 0, 16) . '...');
cli_writeln('Subject:    ' . \local_sentientia_pwa\vapid_key_manager::get_subject());

// Check feature flag.
$flag_on = \local_sentientia_pwa\push_sender::is_enabled();
cli_writeln('Flag on?:   ' . ($flag_on ? 'YES' : 'NO'));

if (!$flag_on && !$options['dry-run']) {
    cli_writeln('');
    cli_writeln('!! sentientia.pwa.push.enabled is OFF. Turn it on in the Switchboard');
    cli_writeln('!! (Site administration → Sentientia → Switchboard) or rerun with --dry-run.');
    exit(1);
}

cli_writeln('');
cli_writeln('Payload:');
cli_writeln('  title: ' . $options['title']);
cli_writeln('  body:  ' . $options['body']);
if ($options['url'] !== '') {
    cli_writeln('  url:   ' . $options['url']);
}
cli_writeln('');

if ($options['dry-run']) {
    cli_writeln('--dry-run: skipping actual delivery.');
    cli_writeln('');
    cli_writeln('To send for real, re-run without --dry-run AND ensure the push flag is ON.');
    exit(0);
}

cli_writeln('Sending...');
$delivered = \local_sentientia_pwa\push_sender::send(
    $userid,
    $options['title'],
    $options['body'],
    $options['url']
);

cli_writeln('');
cli_writeln('Delivered to ' . $delivered . ' / ' . count($subs) . ' subscription(s).');

if ($delivered === 0) {
    cli_writeln('');
    cli_writeln('!! Nothing was delivered. Possible causes:');
    cli_writeln('   - The push provider returned an error (check moodle/site logs / debug.log)');
    cli_writeln('   - The subscribers browser is offline AND TTL=0');
    cli_writeln('   - sentientia.pwa.push.enabled is OFF (re-check Switchboard)');
    exit(1);
}

exit(0);
