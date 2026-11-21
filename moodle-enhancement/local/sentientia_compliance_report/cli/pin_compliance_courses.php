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
 *   php local/sentientia_compliance_report/cli/pin_compliance_courses.php --unenrol-outsiders
 *       Dry run that also lists, per course and per tenant (no names), the
 *       enrolments the old auto-enrol made for people outside the course's
 *       tenants (decision D-10).
 *   php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute --unenrol-outsiders
 *       As --execute, and remove those enrolments through the manual enrolment
 *       plugin (unenrol_user: events fire, and Moodle's clean-up of the last
 *       enrolment runs). Only enrolments with the old auto-enrol's mark go
 *       (manual, end = start + deadline days); anyone also holding another
 *       role in the course, and accounts with no tenant, are left alone.
 *   php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute --unenrol-outsiders --include-no-tenant
 *       As above, and also remove the marked enrolments of accounts with no
 *       tenant (no org path).
 *
 * OPTIONS
 *   --execute             Write the changes (default: dry run).
 *   --clear-enddates      Also clear deadline-based enrolment end dates (with --execute only;
 *                         in a dry run it only changes the wording of the plan).
 *   --unenrol-outsiders   Also list (dry run) or remove (with --execute) the old auto-enrol's
 *                         enrolments of people outside each tracked course's tenants. A tenant
 *                         the course is actively shared to counts as its own.
 *   --include-no-tenant   With --unenrol-outsiders: accounts with no tenant count as outside too.
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
 * Enrolments are removed only with --execute --unenrol-outsiders (decision
 * D-10, Nitin 20 Nov 2026); otherwise never (decision D3). The pin part of
 * --execute is idempotent: running it again after an earlier --execute
 * changes nothing there.
 *
 * EXIT CODES
 *   0  done (or dry run printed) with no problems
 *   1  some group was left alone (read the "Problems" section), or an
 *      enrolment removal failed
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
    'help'              => false,
    'execute'           => false,
    'clear-enddates'    => false,
    'unenrol-outsiders' => false,
    'include-no-tenant' => false,
    'tenant'            => 0,
    'cohort-idnumber'   => compliance_pinning::DEFAULT_COHORT_IDNUMBER,
    'cohort-name'       => compliance_pinning::DEFAULT_COHORT_NAME,
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
$unenroloutsiders = !empty($options['unenrol-outsiders']);
$includenotenant = !empty($options['include-no-tenant']);
if ($includenotenant && !$unenroloutsiders) {
    cli_error('--include-no-tenant only goes with --unenrol-outsiders.', 2);
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

// Decision D-10: the old auto-enrol's enrolments of people outside each
// tracked course's tenants. Counts only, never a person's name or id.
$outsiders = null;
if ($unenroloutsiders) {
    $outsiders = compliance_pinning::outsider_enrolments($includenotenant);
    foreach (compliance_pinning::render_outsiders($outsiders, $execute) as $line) {
        cli_writeln($line);
    }
}

$failed = 0;
if ($execute) {
    cli_writeln('');
    cli_writeln('Applying ...');
    foreach (compliance_pinning::execute($plan, $clearenddates) as $line) {
        cli_writeln('  ' . $line);
    }
    if ($outsiders !== null) {
        $result = compliance_pinning::unenrol_outsiders($outsiders);
        foreach ($result['lines'] as $line) {
            cli_writeln('  ' . $line);
        }
        $failed = $result['failed'];
    }
    cli_writeln('Done. The next hourly compliance refresh applies the new rules.');
} else {
    cli_writeln('');
    cli_writeln('Dry run: nothing was written. Re-run with --execute (and --clear-enddates, --unenrol-outsiders)'
        . ' to apply.');
}

exit(($plan['problems'] || $failed > 0) ? 1 : 0);
