<?php
// This file is part of Sentientia LMS.

/**
 * D4 data fix for the compliance list (post-demo plan, session 1, 2026-11-20).
 *
 * Decision D4 (Nitin, 19 Nov 2026): pin the active compliance rows for POSH
 * 2025, AML, Phishing and Infosec to Airpay; restrict "POSH for IC Members" to
 * an IC cohort; drop the deadline-based enrolment end date.
 *
 * DRY RUN BY DEFAULT: prints the courses it found (by name, never by id), what
 * it would change, and the problems it will not touch. Nothing is written
 * without --execute.
 *
 * USAGE (cwd = the Moodle public directory)
 *   php local/sentientia_compliance_report/cli/pin_compliance_courses.php
 *       Dry run: print the plan.
 *   php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute
 *       Pin the rows, create the IC cohort if it is missing (empty), restrict
 *       the IC row to it. Enrolment end dates are left alone.
 *   php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute --clear-enddates
 *       As above, and clear the deadline-based end dates the old auto-enrol set,
 *       for people the course reaches (its own tenant, or a tenant it is shared
 *       to). Other tenants' users the old refresh enrolled keep their ended
 *       enrolment, which keeps them out; they are counted for review. On the
 *       POSH for IC members course only cohort members' end dates are cleared:
 *       the cohort starts empty, so run this again once L&D has added them.
 *
 * OPTIONS
 *   --execute             Write the changes (default: dry run).
 *   --clear-enddates      Also clear deadline-based enrolment end dates (with --execute only;
 *                         in a dry run it only changes the wording of the plan).
 *   --tenant=N            Pin to tenant N instead of each course's own tenant.
 *   --cohort-idnumber=X   Id number of the IC cohort (default compliance_posh_ic_members).
 *   --cohort-name=X       Name used if the cohort has to be created
 *                         (default "POSH Internal Committee members").
 *   -h, --help            This help.
 *
 * After --execute: the next hourly "Refresh compliance snapshot" task applies
 * the new rules (or run it now with admin/cli/scheduled_task.php --execute=
 * \local_sentientia_compliance_report\task\refresh_snapshot). L&D then adds
 * the IC members to the cohort (Site administration > Users > Cohorts).
 * Enrolments are never removed by this script (decision D3).
 *
 * EXIT CODES
 *   0  done (or dry run printed) with no problems
 *   1  some group was left alone; read the "Problems" section
 *   2  invalid arguments
 *
 * @package    local_sentientia_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_compliance_report\local\compliance_pinning;

[$options, $unrecognised] = cli_get_params([
    'help'            => false,
    'execute'         => false,
    'clear-enddates'  => false,
    'tenant'          => 0,
    'cohort-idnumber' => compliance_pinning::DEFAULT_COHORT_IDNUMBER,
    'cohort-name'     => compliance_pinning::DEFAULT_COHORT_NAME,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognised), 2);
}

if ($options['help']) {
    // The doc block above is the help text.
    $doc = file_get_contents(__FILE__);
    preg_match('#/\*\*(.*?)\*/#s', $doc, $m);
    echo preg_replace('/^\s*\* ?/m', '', $m[1] ?? '') . PHP_EOL;
    exit(0);
}

$tenant = $options['tenant'];
if ($tenant !== 0 && (!is_numeric($tenant) || (int) $tenant <= 0)) {
    cli_error('--tenant must be a positive tenant id.', 2);
}
$cohortidnumber = trim(clean_param((string) $options['cohort-idnumber'], PARAM_ALPHANUMEXT));
if ($cohortidnumber === '') {
    cli_error('--cohort-idnumber must be letters, digits, - or _.', 2);
}
$cohortname = trim(clean_param((string) $options['cohort-name'], PARAM_TEXT));
if ($cohortname === '') {
    cli_error('--cohort-name must not be empty.', 2);
}

$execute = !empty($options['execute']);
$clearenddates = !empty($options['clear-enddates']);
if ($clearenddates && !$execute) {
    cli_writeln('Note: this is a dry run; --clear-enddates only shows what --execute --clear-enddates would clear.');
}

// The cohort and enrolment events record who acted: the site admin, as for
// every other CLI write.
\core\session\manager::set_user(get_admin());

$plan = compliance_pinning::plan([
    'tenant'         => (int) $tenant,
    'cohortidnumber' => $cohortidnumber,
    'cohortname'     => $cohortname,
]);

foreach (compliance_pinning::render($plan, $execute, $clearenddates) as $line) {
    cli_writeln($line);
}

if ($execute) {
    cli_writeln('');
    cli_writeln('Applying ...');
    foreach (compliance_pinning::execute($plan, $clearenddates) as $line) {
        cli_writeln('  ' . $line);
    }
    cli_writeln('Done. The next hourly compliance refresh applies the new rules.');
} else {
    cli_writeln('');
    cli_writeln('Dry run: nothing was written. Re-run with --execute (and --clear-enddates) to apply.');
}

exit($plan['problems'] ? 1 : 0);
