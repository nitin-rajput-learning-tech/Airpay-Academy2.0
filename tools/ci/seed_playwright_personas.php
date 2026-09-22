<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Seed the four Playwright persona accounts on a CI Moodle.
 *
 * WHY THIS EXISTS
 * ---------------
 * tests/playwright/persona-helpers.ts reads PLAYWRIGHT_<PERSONA>_USER and
 * _PASS for LEARNER, MANAGER, COMPLIANCE and AUTHOR, and each persona spec
 * calls test.skip() when its pair is absent - a deliberate design so the suite
 * stays green before accounts exist.
 *
 * The CI job only ever set PLAYWRIGHT_ADMIN_USER and _PASS. So four of the
 * five personas skipped on every run, and the suite reported green while
 * proving nothing about them. CI-proven render coverage was ONE persona, not
 * five, and the summary we had been repeating overstated it.
 *
 * This script provisions those four accounts so the specs actually run.
 *
 * SAFETY
 * ------
 * It creates accounts with passwords that are written down in a public
 * workflow file. Running it anywhere real would be a serious incident, so it
 * refuses unless BOTH of these hold:
 *
 *   1. --i-am-ci is passed explicitly, and
 *   2. $CFG->wwwroot points at localhost / 127.0.0.1, OR the site shortname is
 *      the CI one.
 *
 * There is no flag to override the second check.
 *
 * USAGE
 * -----
 *   php tools/ci/seed_playwright_personas.php --i-am-ci
 *   php tools/ci/seed_playwright_personas.php --i-am-ci --password='...'
 *
 * Idempotent: re-running updates the existing accounts rather than failing.
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

// The password is taken from the environment, not a command-line flag: an
// argument is visible in `ps` to every other process on the host, and a
// literal in a workflow file is what the pre-commit credential scanner exists
// to stop. --password remains for interactive local use.
[$options, $unrecognised] = cli_get_params(
    [
        'i-am-ci' => false,
        'password' => '',
        'tenant' => '/1',
        'help' => false,
    ],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Seed Playwright persona accounts on a CI Moodle.\n");
    cli_writeln("  --i-am-ci            required; refuses to run without it");
    cli_writeln("  --password=VALUE     password for every persona");
    cli_writeln("  --tenant=/1          open_path to place the personas at");
    exit(0);
}

// ── Guard 1: explicit intent. ────────────────────────────────────────────
if (empty($options['i-am-ci'])) {
    cli_error("Refusing to run without --i-am-ci.\n"
        . "This creates accounts whose password is published in a workflow file.");
}

// ── Guard 2: this must actually be a throwaway site. ─────────────────────
$wwwroot = (string) $CFG->wwwroot;
// Terminated on purpose. An unanchored '#^https?://localhost#' also matches
// https://localhost.evil.com -- the same unbounded-prefix mistake this repo
// has now shipped fourteen times against tenant paths, so it gets the same
// treatment here. (/|$) allows a subdirectory install such as
// http://localhost:8080/moodle without letting the hostname run on.
$islocal = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#i', $wwwroot);
$shortname = (string) get_config('core', 'shortname');
$isciname = ($shortname === 'sentientia-ci');

if (!$islocal && !$isciname) {
    cli_error("Refusing to run against {$wwwroot} (shortname '{$shortname}').\n"
        . "This script only runs on a CI or localhost site. There is no override.");
}

// ── Personas ─────────────────────────────────────────────────────────────
// Role shortnames are tried in order; the first that exists on this site wins.
// A vanilla CI Moodle has none of the Airpay/BizLMS roles, so each persona
// falls back to the nearest core archetype.
$personas = [
    'LEARNER' => [
        'username' => 'ci.learner',
        'firstname' => 'Cee',
        'lastname' => 'Learner',
        'roles' => ['employee', 'student'],
    ],
    'MANAGER' => [
        'username' => 'ci.manager',
        'firstname' => 'Emm',
        'lastname' => 'Manager',
        'roles' => ['manager'],
    ],
    'COMPLIANCE' => [
        'username' => 'ci.compliance',
        'firstname' => 'See',
        'lastname' => 'Compliance',
        'roles' => ['administrator', 'manager'],
    ],
    'AUTHOR' => [
        'username' => 'ci.author',
        'firstname' => 'Ayy',
        'lastname' => 'Author',
        'roles' => ['sentientiaauthor', 'trainer', 'editingteacher'],
    ],
];

// Environment first, flag second.
$password = (string) ($options['password'] ?: getenv('SENTIENTIA_CI_PERSONA_PASS'));
if ($password === '') {
    cli_error("No password supplied.\n"
        . "Set SENTIENTIA_CI_PERSONA_PASS in the environment, or pass\n"
        . "--password=... for interactive local use.");
}
if (strlen($password) < 8) {
    cli_error('Password must be at least 8 characters.');
}
$tenantpath = (string) $options['tenant'];
$syscontext = context_system::instance();
$hasopenpath = $DB->get_manager()->field_exists('user', 'open_path');

if (!$hasopenpath) {
    cli_writeln('NOTE: {user}.open_path does not exist on this site (vanilla '
        . 'Moodle without the BizLMS columns). Tenant placement skipped.');
}

$exports = [];

foreach ($personas as $key => $spec) {
    $existing = $DB->get_record('user', [
        'username' => $spec['username'],
        'mnethostid' => $CFG->mnet_localhost_id,
    ]);

    if ($existing) {
        $user = $existing;
        $user->firstname = $spec['firstname'];
        $user->lastname = $spec['lastname'];
        $user->confirmed = 1;
        $user->deleted = 0;
        $user->suspended = 0;
        user_update_user($user, false, false);
        update_internal_user_password($user, $password);
        cli_writeln("updated  {$spec['username']}");
    } else {
        $user = new stdClass();
        $user->username = $spec['username'];
        $user->password = $password;
        $user->firstname = $spec['firstname'];
        $user->lastname = $spec['lastname'];
        $user->email = $spec['username'] . '@example.test';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->auth = 'manual';
        $user->lang = 'en';
        $user->id = user_create_user($user, true, false);
        cli_writeln("created  {$spec['username']} (id {$user->id})");
    }

    if ($hasopenpath) {
        $DB->set_field('user', 'open_path', $tenantpath, ['id' => $user->id]);
    }

    // Assign the first role shortname that exists here.
    $assigned = '(none available)';
    foreach ($spec['roles'] as $shortnametry) {
        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortnametry]);
        if ($roleid) {
            role_assign((int) $roleid, (int) $user->id, $syscontext->id);
            $assigned = $shortnametry;
            break;
        }
    }
    cli_writeln("         role: {$assigned}"
        . ($hasopenpath ? ", open_path: {$tenantpath}" : ''));

    $exports[] = "PLAYWRIGHT_{$key}_USER={$spec['username']}";
    $exports[] = "PLAYWRIGHT_{$key}_PASS={$password}";
}

$syscontext->mark_dirty();

cli_writeln('');
cli_writeln('Set these for the Playwright run:');
foreach ($exports as $line) {
    cli_writeln('  ' . $line);
}

exit(0);
