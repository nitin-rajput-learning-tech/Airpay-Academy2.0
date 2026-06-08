<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\task;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #42 (2026-05-20) — daily auto-expire of overdue assignments.
 *
 * Flips `status='assigned'` rows where `due_at > 0 AND now > due_at`
 * to `status='expired'`. Non-due rows (due_at = 0 / null) are left
 * alone — those are "open until the user responds" assignments
 * without a deadline.
 *
 * This is the housekeeping companion to P1 #37/#38. Without it, the
 * non-respondents page accumulates stale rows that compliance has to
 * mentally filter out ("yes this was assigned 18 months ago but the
 * deadline is long gone — ignore it"). With it, the "Pending" tab
 * only shows assignments that are actually still open.
 *
 * Idempotent. Safe to re-run.
 *
 * @package local_sentientia_evaluation
 */
class expire_assignments extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_expire_assignments', 'local_sentientia_evaluation');
    }

    public function execute(): void {
        global $DB;

        $now = time();
        // Single UPDATE — far cheaper than loop-and-update. The
        // WHERE narrows to (status, due_at) which both have index
        // coverage via idx_eval_status / FK-implicit indexes.
        $sql = "UPDATE {local_sentientia_evaluation_assign}
                   SET status = 'expired',
                       timemodified = :nowmod
                 WHERE status = 'assigned'
                   AND due_at IS NOT NULL
                   AND due_at > 0
                   AND due_at < :nowcmp";
        $params = ['nowmod' => $now, 'nowcmp' => $now];

        // Count first so we can mtrace usefully — execute() doesn't
        // return a row count on all DB drivers.
        $count = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_sentientia_evaluation_assign}
              WHERE status = 'assigned'
                AND due_at IS NOT NULL
                AND due_at > 0
                AND due_at < :nowcmp", ['nowcmp' => $now]);

        if ($count > 0) {
            $DB->execute($sql, $params);
        }

        mtrace('local_sentientia_evaluation expire_assignments: '
            . $count . ' assignment(s) expired.');
    }
}
