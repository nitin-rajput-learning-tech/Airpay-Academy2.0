<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_emails';
$plugin->version   = 2026051301;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1';
// 1.1 (Sprint B, 2026-05-13)
//   + course_completed event observer + tool_certificate PDF attachment
//   + course_incomplete rule type with ramping cadence + max-cap + completion auto-stop
//   + delivery_log schema: attachment_filename + certificate_issue_id columns
//   + rules schema: cadence_days_json + max_reminders_per_user + auto_stop_on_completion columns
