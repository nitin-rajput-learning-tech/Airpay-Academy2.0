<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Cron-failure detection helper.
 *
 * Mitigates Supplement-A risk I5 (cron silent failure undetected) by
 * providing a queryable view of "tasks that should have run by now but
 * haven't". A cron failure looks like a task whose `nextruntime` is far
 * in the past — Moodle's task scheduler doesn't auto-disable on
 * failure, so a stuck task quietly stops running.
 *
 * Three call sites:
 *
 *   1. Dashboard widget for siteadmin — renders the list of stuck tasks
 *      on /admin/index.php as a red banner.
 *
 *   2. The audit_log helper's sensitive_actions() feed picks up
 *      `task_stuck` events emitted by this class.
 *
 *   3. The deployment runbook §8 post-cutover monitoring section
 *      invokes get_stuck_tasks() as part of the 24-hour watch list.
 *
 * The class is read-only against the Moodle task tables — it does not
 * attempt to restart or re-schedule tasks (that is operator judgment).
 *
 * @package local_airpay_core
 */
class cron_health {

    /**
     * Threshold beyond which a task is considered "stuck". Default: a
     * task whose nextruntime is more than 6 hours past now and which
     * has not run since.
     */
    public const STUCK_THRESHOLD_SECONDS = 6 * 3600;

    /**
     * Airpay-owned task classes that this helper cares about. Other
     * tasks (Moodle core or third-party) are surfaced under
     * `other_stuck_tasks()` but are not the L&D team's responsibility
     * to fix.
     */
    public const AIRPAY_TASK_CLASSES = [
        '\\local_sentientia_recompletion\\task\\run_rules',
        '\\local_sentientia_org\\task\\sync_cohorts',
        '\\local_sentientia_proctoring\\task\\purge_old_recordings',
        '\\local_sentientia_request\\task\\escalate_overdue',
        '\\local_sentientia_request\\task\\auto_expire',
        '\\local_sentientia_notifications\\task\\dispatcher',
        '\\local_sentientia_compliance_report\\task\\refresh_aggregates',
        '\\local_sentientia_integrations\\task\\sync_keka_users',
        '\\local_sentientia_emails\\task\\dispatcher',
        '\\local_sentientia_emails\\task\\cleanup',
    ];

    /**
     * Return Airpay scheduled tasks that are overdue beyond the
     * stuck threshold.
     *
     * @return array of stdClass: classname, lastruntime, nextruntime,
     *               overdue_seconds, disabled, faildelay
     */
    public static function get_stuck_airpay_tasks(): array {
        global $DB;
        $now = time();
        $threshold = $now - self::STUCK_THRESHOLD_SECONDS;
        [$insql, $inparams] = $DB->get_in_or_equal(
            self::AIRPAY_TASK_CLASSES, SQL_PARAMS_NAMED, 'tc');
        $rows = $DB->get_records_sql(
            "SELECT id, classname, component, lastruntime, nextruntime,
                    disabled, faildelay
               FROM {task_scheduled}
              WHERE classname $insql
                AND disabled = 0
                AND nextruntime > 0
                AND nextruntime < :threshold
                AND (lastruntime IS NULL OR lastruntime < :threshold2)
           ORDER BY nextruntime ASC",
            array_merge($inparams,
                ['threshold' => $threshold, 'threshold2' => $threshold]));
        $out = [];
        foreach ($rows as $r) {
            $r->overdue_seconds = $now - (int) $r->nextruntime;
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Same but for non-Airpay tasks. Used by the dashboard widget to
     * surface a less-prominent secondary list.
     */
    public static function get_stuck_other_tasks(): array {
        global $DB;
        $now = time();
        $threshold = $now - self::STUCK_THRESHOLD_SECONDS;
        [$notsql, $notparams] = $DB->get_in_or_equal(
            self::AIRPAY_TASK_CLASSES, SQL_PARAMS_NAMED, 'tc', false);
        $rows = $DB->get_records_sql(
            "SELECT id, classname, component, lastruntime, nextruntime,
                    disabled, faildelay
               FROM {task_scheduled}
              WHERE classname $notsql
                AND disabled = 0
                AND nextruntime > 0
                AND nextruntime < :threshold
                AND (lastruntime IS NULL OR lastruntime < :threshold2)
           ORDER BY nextruntime ASC",
            array_merge($notparams,
                ['threshold' => $threshold, 'threshold2' => $threshold]));
        return array_values($rows);
    }

    /**
     * Tasks with non-zero faildelay (Moodle's exponential backoff on
     * failure). Faildelay > 0 means the task has failed at least once
     * recently and is in retry-backoff mode.
     */
    public static function get_tasks_in_failure_backoff(): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT id, classname, component, lastruntime, nextruntime,
                    disabled, faildelay
               FROM {task_scheduled}
              WHERE faildelay > 0
                AND disabled  = 0
           ORDER BY faildelay DESC");
        return array_values($rows);
    }

    /**
     * One-shot health summary for the dashboard widget.
     *
     * @return array{stuck_airpay: int, stuck_other: int, in_backoff: int}
     */
    public static function summary(): array {
        return [
            'stuck_airpay' => count(self::get_stuck_airpay_tasks()),
            'stuck_other'  => count(self::get_stuck_other_tasks()),
            'in_backoff'   => count(self::get_tasks_in_failure_backoff()),
        ];
    }

    /**
     * Convenience: format an overdue duration for display.
     */
    public static function format_overdue(int $seconds): string {
        if ($seconds < 60)        return "{$seconds}s";
        if ($seconds < 3600)      return floor($seconds / 60) . "m";
        if ($seconds < 86400)     return floor($seconds / 3600) . "h "
            . floor(($seconds % 3600) / 60) . "m";
        return floor($seconds / 86400) . "d "
            . floor(($seconds % 86400) / 3600) . "h";
    }
}
