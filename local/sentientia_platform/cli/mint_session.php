<?php
// Mint a Moodle session for a given username and print the session
// cookie value to stdout. The audit walker imports this cookie into
// Playwright to bypass the sentientia login form (whose JS-based hash
// fields aren't easily reproducible from headless drivers).
//
// LOCAL DEV ONLY. Refuses to run if wwwroot contains airpay.academy.
//
// Usage: php mint_session.php --username=<name>
//        echoes: MoodleSession=<sessid>

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

if (str_contains((string) $CFG->wwwroot, 'airpay.academy')) {
    fwrite(STDERR, "mint_session.php is dev-only — refuses to run on production wwwroot.\n");
    exit(1);
}

[$options] = cli_get_params(['username' => null]);
if (empty($options['username'])) {
    fwrite(STDERR, "Usage: mint_session.php --username=<name>\n");
    exit(2);
}

$user = $DB->get_record('user', ['username' => $options['username']],
    '*', MUST_EXIST);

// Bypass \core\session\manager because CLI bootstrap doesn't have a
// real session to start, and login_user expects one. Insert a session
// row directly and return its sid for the cookie.
//
// The sessdata payload below is what Moodle writes when a real browser
// login completes: serialised globals containing USER->id + a marker
// that the row is fully populated. Without sessdata the next web
// request would treat the session as anonymous.
$sid = bin2hex(random_bytes(13));   // 26-char hex, matches Moodle's default
$now = time();

$user_serial = 's:6:"USER->";O:8:"stdClass":2:{s:2:"id";i:' . $user->id . ';s:9:"sesskey";s:10:"' .
    substr(bin2hex(random_bytes(8)), 0, 10) . '";}';
// More accurate: just put the USER global; Moodle reconstructs the rest.
$sessdata = base64_encode(serialize([
    'USER' => (object) [
        'id'           => $user->id,
        'username'     => $user->username,
        'sesskey'      => substr(bin2hex(random_bytes(8)), 0, 10),
        'firstaccess'  => $user->firstaccess,
        'currentlogin' => $now,
        'lastip'       => '127.0.0.1',
    ],
    'SESSION' => (object) [],
]));

$DB->insert_record('sessions', (object) [
    'state'        => 0,
    'sid'          => $sid,
    'userid'       => $user->id,
    'sessdata'     => $sessdata,
    'timecreated'  => $now,
    'timemodified' => $now,
    'firstip'      => '127.0.0.1',
    'lastip'       => '127.0.0.1',
]);

if (!$sid) {
    fwrite(STDERR, "Failed to mint session for {$options['username']}\n");
    exit(3);
}

echo "MoodleSession=$sid\n";
