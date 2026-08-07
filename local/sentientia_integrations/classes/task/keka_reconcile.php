<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_integrations\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled reconciliation pull from KeKa (2026-08-07).
 *
 * Backstop for missed webhooks: nightly full pull via
 * keka_client::sync_employees(), which walks every KeKa employee through
 * upsert_employee() — the SAME canonical code path the webhook uses.
 *
 * This is NOT a resurrection of the task\hrms_sync class deleted on
 * 2026-05-07 (INTEGRATIONS-AUDIT.md §3.2). That task was a parallel
 * implementation with its own field shapes, status normalisation and
 * password defaults, which is exactly what created the duplicate-user
 * risk. There is one implementation now; the identity match runs
 * open_employeeid-first, so webhook-created and cron-created records
 * converge on the same account.
 *
 * Triple opt-in before anything runs:
 *   1. Platform flag sentientia.hrms.reconcile.enabled (default OFF)
 *   2. Admin setting hrms_enable (default 0)
 *   3. The task itself registers disabled in db/tasks.php
 *
 * @package    local_sentientia_integrations
 */
class keka_reconcile extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_keka_reconcile', 'local_sentientia_integrations');
    }

    public function execute() {
        global $DB;

        if (!\local_sentientia_integrations\keka_client::reconcile_enabled()) {
            mtrace('KeKa reconciliation skipped — sentientia.hrms.reconcile.enabled '
                . 'flag or hrms_enable setting is off.');
            return;
        }

        mtrace('KeKa reconciliation: starting full employee pull...');
        $client = new \local_sentientia_integrations\keka_client();
        $stats = $client->sync_employees();

        $summary = sprintf(
            'created=%d updated=%d suspended=%d skipped=%d errors=%d manager_links=%d',
            $stats['created'], $stats['updated'], $stats['suspended'],
            $stats['skipped'], $stats['errors'], $stats['manager_links']
        );
        mtrace('KeKa reconciliation: ' . $summary);

        // Audit trail — same log table the webhook writes to.
        $failed = $stats['errors'] > 0 || !empty($stats['error_message']);
        $DB->insert_record('local_sentientia_integration_log', (object) [
            'source'      => 'hrms_cron',
            'event_type'  => 'reconcile',
            'payload'     => json_encode($stats),
            'status'      => $failed ? 'failed' : 'processed',
            'errormsg'    => $stats['error_message'] ?? null,
            'timecreated' => time(),
        ]);
    }
}
