<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI front-end to `\local_sentientia_platform\cron_health`.
 *
 * Operations-friendly query tool. Use during incident response or as
 * part of the daily watch list per `SUPP-H Section 11` (long-term
 * observability mature-state).
 *
 * USAGE
 *   php local/sentientia_platform/cli/cron_health.php
 *     -- print the summary tuple (Airpay stuck / other stuck / in backoff)
 *
 *   php local/sentientia_platform/cli/cron_health.php --stuck
 *     -- print full list of stuck Airpay tasks with overdue duration
 *
 *   php local/sentientia_platform/cli/cron_health.php --stuck-other
 *     -- print full list of stuck non-Airpay tasks (Moodle core, others)
 *
 *   php local/sentientia_platform/cli/cron_health.php --backoff
 *     -- print tasks in Moodle's faildelay exponential-backoff
 *
 *   php local/sentientia_platform/cli/cron_health.php --all
 *     -- print all three sections
 *
 *   php local/sentientia_platform/cli/cron_health.php --json
 *     -- emit JSON instead of human-readable; for ops dashboards.
 *
 * EXIT CODES
 *   0  no stuck tasks, no backoff
 *   1  at least one Airpay task is stuck or in backoff
 *   2  invalid arguments
 *
 * @package local_sentientia_platform
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'        => false,
    'stuck'       => false,
    'stuck-other' => false,
    'backoff'     => false,
    'all'         => false,
    'json'        => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    fwrite(STDERR, "Unrecognised argument(s): "
        . implode(', ', $unrecognized) . "\n");
    exit(2);
}

if ($options['help']) {
    fwrite(STDOUT,
        "Usage: php local/sentientia_platform/cli/cron_health.php [options]\n"
        . "\n"
        . "Options:\n"
        . "  --stuck       Print Airpay tasks overdue by more than 6h\n"
        . "  --stuck-other Print non-Airpay tasks overdue by more than 6h\n"
        . "  --backoff     Print tasks in failure-backoff state\n"
        . "  --all         Print all three sections\n"
        . "  --json        Emit machine-readable JSON instead of text\n"
        . "  -h, --help    Show this help\n"
        . "\n"
        . "Exit codes: 0 = clean, 1 = stuck or backoff, 2 = bad args\n");
    exit(0);
}

$summary = \local_sentientia_platform\cron_health::summary();
$show_all = (bool) $options['all'];
$want_stuck       = $show_all || $options['stuck'];
$want_stuck_other = $show_all || $options['stuck-other'];
$want_backoff     = $show_all || $options['backoff'];

if ($options['json']) {
    $payload = ['summary' => $summary];
    if ($want_stuck) {
        $payload['stuck_airpay'] = array_map(
            fn($t) => json_decode(json_encode($t), true),
            \local_sentientia_platform\cron_health::get_stuck_airpay_tasks()
        );
    }
    if ($want_stuck_other) {
        $payload['stuck_other'] = array_map(
            fn($t) => json_decode(json_encode($t), true),
            \local_sentientia_platform\cron_health::get_stuck_other_tasks()
        );
    }
    if ($want_backoff) {
        $payload['in_backoff'] = array_map(
            fn($t) => json_decode(json_encode($t), true),
            \local_sentientia_platform\cron_health::get_tasks_in_failure_backoff()
        );
    }
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT) . "\n");
    exit_with_status($summary);
}

// Human-readable output.
fwrite(STDOUT,
    "Cron health summary:\n"
    . "  Stuck Airpay tasks: {$summary['stuck_airpay']}\n"
    . "  Stuck other tasks:  {$summary['stuck_other']}\n"
    . "  In backoff:         {$summary['in_backoff']}\n"
    . "\n");

if ($want_stuck) {
    $rows = \local_sentientia_platform\cron_health::get_stuck_airpay_tasks();
    fwrite(STDOUT, "Stuck Airpay tasks (" . count($rows) . "):\n");
    if (!$rows) {
        fwrite(STDOUT, "  (none)\n");
    }
    foreach ($rows as $t) {
        $overdue = \local_sentientia_platform\cron_health::format_overdue(
            (int) $t->overdue_seconds);
        $last = $t->lastruntime
            ? date('Y-m-d H:i:s', (int) $t->lastruntime) : '(never)';
        fwrite(STDOUT, "  {$t->classname}\n"
            . "    overdue: $overdue, lastruntime: $last, "
            . "faildelay: {$t->faildelay}s\n");
    }
    fwrite(STDOUT, "\n");
}

if ($want_stuck_other) {
    $rows = \local_sentientia_platform\cron_health::get_stuck_other_tasks();
    fwrite(STDOUT, "Stuck non-Airpay tasks (" . count($rows) . "):\n");
    if (!$rows) {
        fwrite(STDOUT, "  (none)\n");
    }
    foreach ($rows as $t) {
        $last = $t->lastruntime
            ? date('Y-m-d H:i:s', (int) $t->lastruntime) : '(never)';
        fwrite(STDOUT, "  {$t->classname}\n"
            . "    component: {$t->component}, lastruntime: $last, "
            . "faildelay: {$t->faildelay}s\n");
    }
    fwrite(STDOUT, "\n");
}

if ($want_backoff) {
    $rows = \local_sentientia_platform\cron_health::get_tasks_in_failure_backoff();
    fwrite(STDOUT, "Tasks in failure-backoff (" . count($rows) . "):\n");
    if (!$rows) {
        fwrite(STDOUT, "  (none)\n");
    }
    foreach ($rows as $t) {
        $next = date('Y-m-d H:i:s', (int) $t->nextruntime);
        fwrite(STDOUT, "  {$t->classname}\n"
            . "    faildelay: {$t->faildelay}s, next attempt: $next\n");
    }
    fwrite(STDOUT, "\n");
}

exit_with_status($summary);

/**
 * Exit with the appropriate status code based on whether anything is
 * stuck. Defined inline because this CLI is single-file by design.
 */
function exit_with_status(array $summary): void {
    if ($summary['stuck_airpay'] > 0 || $summary['in_backoff'] > 0) {
        exit(1);
    }
    exit(0);
}
