<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Stage B report of the enrolments import (ADR-032, owner decision CRS-01 of 2026-10-07): for every BizLMS enrol instance
 * that holds enrolments, whether its learners keep the same access through manual enrolments, and which instances the import
 * switched off. Read-only. Run it after the enrolments feature has been applied (before it, every enrolment on an instance
 * "regresses" because the manual enrolments are not there yet).
 *
 *   php local/sentientia_courses/cli/enrolments_access_report.php            summary and one line per instance
 *   php local/sentientia_courses/cli/enrolments_access_report.php --json     the same as JSON
 *
 * It prints, per instance: its id, course id and method, whether it is enabled and whether the import switched it off, how
 * many enrolments it holds, how many are not settled in the legacy map (an account that is deleted, a suspended or shorter
 * manual enrolment the import left alone), how many learner-course pairs hold access to keep, and how many of those the manual
 * enrolments do not cover, with the ids of the legacy enrolments (never a learner). The totals include the pair count.
 *
 * Exit codes: 0 every switched-off instance keeps its learners' access and every other instance is settled; 1 a switched-off
 * instance does NOT (undo it with UPDATE {enrol} SET status = priorstatus from local_sentientia_courses_enroloff, then look);
 * 2 an instance that is still enabled holds an unsettled row or a regression, which L&D has to act on (the learners keep today's
 * access meanwhile).
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['help' => false, 'json' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unknown option: ' . implode(', ', array_keys($unrecognised)));
}
if ($options['help']) {
    cli_writeln('Access report of the BizLMS enrol instances after the enrolments import. Options: --json, --help.');
    exit(0);
}

global $DB;

$importer = new \local_sentientia_courses\bizlms\enrolments_importer();
$ctx = \local_sentientia_platform\bizlms\context::build($importer, false, 0, \local_sentientia_platform\bizlms\decisions::none());

$trail = array_map('intval', $DB->get_fieldset_sql(
    'SELECT enrolid FROM {' . \local_sentientia_courses\bizlms\enrolments_importer::TRAIL . '} ORDER BY enrolid'));
$candidates = \local_sentientia_courses\bizlms\enrolments_access::candidate_instances($ctx);
// An instance the import switched off is judged as it was before: enabled.
$verdicts = \local_sentientia_courses\bizlms\enrolments_access::verdicts($ctx, array_merge($candidates, $trail), time(), $trail);

$lines = [];
$totals = ['instances' => 0, 'switched_off' => 0, 'enrolments' => 0, 'pairs' => 0, 'unsettled_rows' => 0,
    'regressions' => 0, 'off_with_a_problem' => 0, 'enabled_with_a_problem' => 0];
foreach ($verdicts as $id => $verdict) {
    $off = in_array($id, $trail, true);
    $problem = $verdict['unsettled'] > 0 || $verdict['regressions'] > 0;
    $totals['instances']++;
    $totals['switched_off'] += $off ? 1 : 0;
    $totals['enrolments'] += $verdict['rows'];
    $totals['pairs'] += $verdict['pairs'];
    $totals['unsettled_rows'] += $verdict['unsettled'];
    $totals['regressions'] += $verdict['regressions'];
    if ($problem) {
        $totals[$off ? 'off_with_a_problem' : 'enabled_with_a_problem']++;
    }
    $lines[] = [
        'instance' => (int) $id,
        'course' => $verdict['courseid'],
        'method' => $verdict['method'],
        'enabled' => $verdict['status'] === 0,
        'switched_off_by_the_import' => $off,
        'enrolments' => $verdict['rows'],
        'unsettled_rows' => $verdict['unsettled'],
        'pairs_with_access_to_keep' => $verdict['pairs'],
        'pairs_not_covered' => $verdict['regressions'],
        'not_covered_enrolment_ids' => $verdict['regressionrows'],
    ];
}

if ($options['json']) {
    cli_writeln(json_encode(['totals' => $totals, 'instances' => $lines], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    cli_writeln('BizLMS enrol instances with enrolments: ' . $totals['instances']);
    cli_writeln('switched off by the import: ' . $totals['switched_off']);
    cli_writeln('enrolments on them: ' . $totals['enrolments']);
    cli_writeln('learner-course pairs holding access to keep: ' . $totals['pairs']);
    cli_writeln('enrolments not settled in the map: ' . $totals['unsettled_rows']);
    cli_writeln('pairs whose access the manual enrolments do not cover: ' . $totals['regressions']);
    foreach ($lines as $line) {
        $flag = ($line['unsettled_rows'] > 0 || $line['pairs_not_covered'] > 0) ? 'ATTENTION' : 'ok';
        cli_writeln(sprintf('%-9s instance=%d course=%d method=%s enabled=%s off_by_import=%s enrolments=%d unsettled=%d pairs=%d '
            . 'not_covered=%d%s', $flag, $line['instance'], $line['course'], $line['method'], $line['enabled'] ? 'yes' : 'no',
            $line['switched_off_by_the_import'] ? 'yes' : 'no', $line['enrolments'], $line['unsettled_rows'],
            $line['pairs_with_access_to_keep'], $line['pairs_not_covered'],
            $line['not_covered_enrolment_ids'] ? ' ids=' . implode(',', $line['not_covered_enrolment_ids']) : ''));
    }
}

if ($totals['off_with_a_problem'] > 0) {
    exit(1);
}
exit($totals['enabled_with_a_problem'] > 0 ? 2 : 0);
