<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_recompletion\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Daily scheduled task — runs every enabled recompletion rule.
 */
class run_rules extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Airpay Recompletion: evaluate rules + reset due completions';
    }

    public function execute() {
        $dryrun = (bool) get_config('local_airpay_recompletion', 'dry_run_default');
        $totals = \local_airpay_recompletion\recompletion_engine::run_all($dryrun);
        mtrace(sprintf(
            "airpay_recompletion: rules=%d reset=%d notified=%d skipped=%d errors=%d%s",
            $totals['rules_run'], $totals['reset'], $totals['notified'],
            $totals['skipped'], $totals['errors'],
            $dryrun ? ' (DRY-RUN)' : ''));
    }
}
