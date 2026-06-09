<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_emails';
// P1 #49 (2026-05-20) — Hindi top-up: 25 strings covering privacy metadata,
// ramping reminder + certificate settings, and cadence JSON errors.
$plugin->version   = 2026052001;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.2';  // +P1 #49 Hindi top-up
// 1.1   (Sprint B, 2026-05-13)
//   + course_completed event observer + tool_certificate PDF attachment
//   + course_incomplete rule type with ramping cadence + max-cap + completion auto-stop
//   + delivery_log schema: attachment_filename + certificate_issue_id columns
//   + rules schema: cadence_days_json + max_reminders_per_user + auto_stop_on_completion columns
// 1.1.1 (Sprint B hotfix, 2026-05-13)
//   * delivery_log.status: widen char(20) -> char(32) so the new
//     'suppressed_completion' enum value (21 chars) fits. Caught by
//     PHPUnit's observer_test:test_mark_reminders_suppressed_on_completion_stamps_sent_rows.
