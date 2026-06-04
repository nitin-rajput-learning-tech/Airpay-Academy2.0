<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks for local_sentientia_integrations.
 *
 * The HRMS sync task that used to live here was a duplicate of
 * keka_client::sync_employees — different field shapes, different
 * status normalisation, different password defaults — which created
 * a duplicate-user risk if both ran. The task class was removed in
 * commit 2026050700 (INTEGRATIONS-AUDIT.md §3.2). KeKa sync is now
 * webhook-driven only; for reconciliation backstop see Phase-2.
 */
defined('MOODLE_INTERNAL') || die();

$tasks = [];
