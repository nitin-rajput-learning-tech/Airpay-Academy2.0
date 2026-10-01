<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Daily scheduled task — runs every enabled recompletion rule.
 *
 * ADR-032 (2026-09-30): the task is gated by the default-OFF flag
 * sentientia.recompletion.run_rules. The engine runs every ENABLED rule with no
 * other switch and deletes completions, grades and quiz attempts, so a
 * restored database that carries imported BizLMS rules must not be able to
 * start resetting learners on the first 03:15 run. The import creates every rule
 * disabled; this flag is the second lock, and a person turns both.
 */
class run_rules extends \core\task\scheduled_task {

    /** The flag that lets the task run. */
    public const FLAG = 'sentientia.recompletion.run_rules';

    public function get_name(): string {
        return 'Airpay Recompletion: evaluate rules + reset due completions';
    }

    public function execute() {
        // A scheduled task has no user. The cron runs as an administrator, who resolves to the first customer, so
        // is_enabled() would let a customer or tenant override switch the task on for every tenant. Read the
        // site-wide value (customer 0, tenant 0), which is what the flag's description promises.
        if (!\local_sentientia_platform\feature_flags::is_enabled_for(self::FLAG, 0, 0)) {
            mtrace('sentientia_recompletion: skipped, feature flag ' . self::FLAG . ' is OFF (no rule was evaluated)');
            return;
        }
        $dryrun = (bool) get_config('local_sentientia_recompletion', 'dry_run_default');
        $totals = \local_sentientia_recompletion\recompletion_engine::run_all($dryrun);
        mtrace(sprintf(
            "sentientia_recompletion: rules=%d reset=%d notified=%d skipped=%d errors=%d%s",
            $totals['rules_run'], $totals['reset'], $totals['notified'],
            $totals['skipped'], $totals['errors'],
            $dryrun ? ' (DRY-RUN)' : ''));
    }
}
