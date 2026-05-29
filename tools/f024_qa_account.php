<?php
// LOCAL-DEV-ONLY throwaway QA siteadmin account for the F-024 visual
// walk. Self-cleaning: --setup creates it, --teardown deletes it.
//
// Why this exists: mint_session.php cannot work on this box
// (dbsessions=0 → Moodle uses file sessions, so the DB session row it
// inserts is never read; and serialize_handler=php means its
// serialize()-array payload format is wrong anyway). So we auth the
// real browser via the actual login form instead, which needs a known
// password. Rather than reset the admin's password (surprising the
// user), we mint a dedicated throwaway siteadmin and delete it after.
//
// Password is the user-authorized LOCAL-DEV-ONLY value. Refuses to run
// on a production wwwroot.
//
// Usage:
//   php f024_qa_account.php --setup
//   php f024_qa_account.php --teardown

define('CLI_SCRIPT', true);
require('C:/xampp/htdocs/moodle5/public/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (str_contains((string) $CFG->wwwroot, 'airpay.academy')) {
    cli_error('Refuses to run on production wwwroot.');
}

[$options] = cli_get_params(['setup' => false, 'teardown' => false]);

const QA_USERNAME = 'f024qa';

/**
 * Generate a random, policy-compliant throwaway secret at runtime —
 * we never hardcode a credential literal (keeps the repo + the
 * pre-commit credential scanner clean). The value is printed once at
 * --setup time and used for that single browser login; the account is
 * deleted at --teardown, so the secret has a lifetime of minutes.
 * Satisfies the default Moodle policy: >=8 chars, upper, lower, digit,
 * special.
 */
function f024_random_secret(): string {
    $hex = bin2hex(random_bytes(6));   // 12 lowercase-hex + digit chars
    return 'Qa' . $hex . '#7';          // + upper, lower, special, digit
}

global $DB, $CFG;

$existing = $DB->get_record('user', ['username' => QA_USERNAME]);

if ($options['teardown']) {
    if (!$existing) {
        cli_writeln('No f024qa account to remove.');
        exit(0);
    }
    // Remove from siteadmins config list.
    $admins = explode(',', (string) $CFG->siteadmins);
    $admins = array_values(array_filter($admins,
        fn($id) => (int) $id !== (int) $existing->id));
    set_config('siteadmins', implode(',', $admins));
    // Delete the user.
    delete_user($existing);
    cli_writeln("Removed f024qa (id={$existing->id}) from siteadmins + deleted user.");
    exit(0);
}

if ($options['setup']) {
    if ($existing) {
        cli_writeln("f024qa already exists (id={$existing->id}). Re-run --teardown first to recreate.");
        exit(0);
    }
    $secret = f024_random_secret();
    $user = new \stdClass();
    $user->auth         = 'manual';
    $user->confirmed    = 1;
    $user->mnethostid   = $CFG->mnet_localhost_id;
    $user->username     = QA_USERNAME;
    $user->password     = $secret;   // user_create_user hashes it.
    $user->firstname    = 'F024';
    $user->lastname     = 'QA Walker';
    $user->email        = 'f024qa@localhost.invalid';
    $user->lang         = 'en';
    $userid = user_create_user($user, true, false);

    // Promote to siteadmin so the live:manage ownership gate passes for
    // any session (session 18 is owned by the academy admin, id=2).
    $admins = array_filter(explode(',', (string) $CFG->siteadmins));
    $admins[] = $userid;
    set_config('siteadmins', implode(',', array_unique($admins)));

    cli_writeln("Created f024qa (id={$userid}), promoted to siteadmin.");
    cli_writeln("Login: " . QA_USERNAME . " / " . $secret);
    cli_writeln("(random one-time secret — printed only here; --teardown deletes the account)");
    exit(0);
}

cli_writeln('Usage: php f024_qa_account.php --setup | --teardown');
