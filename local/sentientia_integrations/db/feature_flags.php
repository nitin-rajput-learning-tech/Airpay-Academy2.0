<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_integrations.
 *
 * KeKa JML hardening (2026-08-07) — two flags, both default OFF per
 * CLAUDE.md §13 (every feature ships behind a default-OFF flag):
 *
 *  1. sentientia.hrms.webhook.enabled — gates the inbound webhook
 *     endpoint (webhook.php). Before this flag existed, the endpoint
 *     went live the moment webhook_secret was configured; hrms_enable
 *     was read by nothing. Both the flag AND hrms_enable must now be
 *     on (keka_client::webhook_enabled()).
 *
 *  2. sentientia.hrms.reconcile.enabled — gates the scheduled
 *     reconciliation pull (task\keka_reconcile). The task is also
 *     registered disabled in db/tasks.php, so enabling requires an
 *     explicit flag flip + task enable + hrms_enable — deliberate
 *     triple opt-in for a job that mass-writes user accounts.
 *
 * @package local_sentientia_integrations
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — KeKa HRMS integration ──────────────────
    'sentientia.hrms.webhook.enabled' => [
        'default'     => false,
        'description' => 'KeKa HRMS inbound webhook (JML events at
                          /local/sentientia_integrations/webhook.php).
                          When OFF (default) the endpoint answers 403 to
                          everything, even with a valid secret. When ON,
                          requests still need the hrms_enable admin
                          setting plus the X-Webhook-Secret header.',
    ],

    'sentientia.hrms.reconcile.enabled' => [
        'default'     => false,
        'description' => 'KeKa HRMS scheduled reconciliation pull
                          (task\\keka_reconcile → keka_client::
                          sync_employees). Backstop for missed webhooks.
                          When OFF (default) the task exits without
                          calling KeKa. Uses the SAME upsert code path
                          as the webhook — no duplicate-user risk
                          (INTEGRATIONS-AUDIT.md §3.2 addendum).',
    ],

];
