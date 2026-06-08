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
 * CLI QA tool — verify the airpayux sidebar role-switcher options builder.
 *
 * Exercises core_renderer::get_role_switch_options() (the data-builder that
 * feeds the "Switch role to:" control in templates/dashboard.mustache) for a
 * given user across the THREE states of
 * $USER->useraccess['currentroleinfo'], asserting which option is marked
 * active in each:
 *
 *   A  fresh session, no prior switch  -> highest category role active
 *      (via the role_detector fallback — the same source of truth that
 *       selects which dashboard renders).
 *   B  switched to the employee role   -> Employee active.
 *   C  switched to a category role     -> that category role active.
 *
 * Background: the two writers of currentroleinfo store different keys
 * (accesslib::set_user_role_switch => {roleid, contextid};
 *  core_renderer::role_switch_basedon_userroles =>
 *  {roleid, orgcatid, depth, contextinfo}). The builder matches on roleid
 * (the only shared key), tightens with contextid/orgcatid when present, and
 * falls back to role_detector when there is no switch state. This tool is the
 * headless regression check for that logic (the browser two-tab walk in
 * docs/visual-evidence/ is the visual counterpart).
 *
 * Usage:
 *   cd <moodle-public-root>
 *   php ../theme/airpayux/cli/verify_roleswitch.php [--userid=142]
 *
 * Exit codes:
 *   0   All three states marked exactly one expected option active
 *   1   One or more states failed the assertion
 *   2   Setup problem (user not multi-role, renderer missing the method, etc.)
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'userid' => 142],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}
if ($options['help']) {
    cli_writeln("Verify airpayux role-switcher options builder.\n  php ../theme/airpayux/cli/verify_roleswitch.php [--userid=142]");
    exit(0);
}

global $DB, $PAGE, $USER, $CFG;

$userid = (int) $options['userid'];
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
\core\session\manager::set_user($user);

// Bring up the airpayux renderer in CLI.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/my/');
try {
    $PAGE->set_pagelayout('mydashboard');
} catch (\Throwable $e) {
    // Layout selection is non-fatal for renderer instantiation.
}
// Under CLI_SCRIPT, $PAGE->get_renderer('core') returns core_renderer_cli
// (the factory forces it), which lacks our trait. Instantiate the airpayux
// renderer directly — get_role_switch_options() needs only $USER / $DB /
// accesslib / role_detector / sesskey, no web-output context.
$rendererclass = '\\theme_airpayux\\output\\core_renderer';
if (!class_exists($rendererclass)) {
    cli_error("$rendererclass not autoloadable — is the airpayux theme deployed?", 2);
}
$target = defined('RENDERER_TARGET_GENERAL') ? RENDERER_TARGET_GENERAL : 'general';
$output = new $rendererclass($PAGE, $target);

if (!method_exists($output, 'get_role_switch_options')) {
    cli_error('Renderer ' . get_class($output) . ' lacks get_role_switch_options().', 2);
}

$failures = 0;

/**
 * Run the builder in the current session state and assert the active option.
 *
 * @param string $label     human description of the state
 * @param string $expectsub a substring the ACTIVE option's label must contain
 */
$assert = function (string $label, string $expectsub) use ($output, &$failures) {
    $r = $output->get_role_switch_options();
    $active = array_values(array_filter($r['options'], fn($o) => $o['active']));
    $activecount = count($active);
    $activelabel = $activecount ? $active[0]['label'] : '(none)';

    cli_writeln("=== $label ===");
    cli_writeln('  hasoptions=' . ($r['hasoptions'] ? 'true' : 'false') .
        ' | options=' . count($r['options']) .
        ' | currentlabel="' . $r['currentlabel'] . '"');
    foreach ($r['options'] as $o) {
        cli_writeln(sprintf('   %s %s', $o['active'] ? '[* ACTIVE ]' : '[  switch ]', $o['label']));
    }

    $ok = ($activecount === 1) && (stripos($activelabel, $expectsub) !== false);
    if ($ok) {
        cli_writeln("  PASS — exactly one active, matches \"$expectsub\"");
    } else {
        cli_writeln("  FAIL — expected exactly one active containing \"$expectsub\", got $activecount active (\"$activelabel\")");
        $failures++;
    }
    cli_writeln('');
};

// Resolve the ids we need to simulate switches.
$empid = (int) $DB->get_field('role', 'id', ['shortname' => 'employee']);
$catroles = \local_sentientia_org\accesslib::get_user_roles_in_catgeorycontexts($userid);
if (!is_array($catroles) || count($catroles) === 0) {
    cli_error("User $userid has no category-context roles — not a multi-role user; nothing to verify.", 2);
}
$firstcat = reset($catroles);

// Resolve the category name the same way the builder does (categoryname is
// NOT on the raw accesslib row — the builder derives it from ->path).
$firstcatids = array_values(array_filter(explode('/', (string) $firstcat->path)));
$firstcatname = '';
if (!empty($firstcatids)) {
    $firstcatname = (string) \local_sentientia_org\accesslib::get_category_info(end($firstcatids), 'name');
}
$firstcatexpect = ($firstcatname !== '') ? $firstcatname : ' - '; // ' - ' = the "cat - role" separator, always present on a category option

// ── State A: fresh session, no prior switch. ──
unset($USER->useraccess['currentroleinfo']);
unset($SESSION->airpay_switchrole);
$assert('A: fresh login (no switch) — expect highest category role active', $firstcatexpect);

// ── State B: switched to the employee/learner role. ──
if ($empid) {
    \local_sentientia_org\accesslib::set_user_role_switch($empid, (int) SYSCONTEXTID);
    $assert('B: switched to Employee — expect Employee active',
        get_string('employee', 'theme_airpayux'));
}

// ── State C: switched to a category role (set_user_role_switch shape:
//    roleid + contextid only — the case the old triple-match missed). ──
\local_sentientia_org\accesslib::set_user_role_switch((int) $firstcat->roleid, (int) $firstcat->contextid);
$assert('C: switched to category role — expect that category role active', $firstcatexpect);

if ($failures > 0) {
    cli_writeln("RESULT: $failures state(s) FAILED.");
    exit(1);
}
cli_writeln('RESULT: all states PASS — exactly one option active per state.');
exit(0);
