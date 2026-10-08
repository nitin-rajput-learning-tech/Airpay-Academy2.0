<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_emails';
// P1 #49 (2026-05-20) — Hindi top-up: 25 strings covering privacy metadata,
// ramping reminder + certificate settings, and cadence JSON errors.
$plugin->version   = 2026100802;  // 2026-10-08 FX-04 round 1: mustache_factory::engine() no longer probes class_exists(\Mustache\Engine) on 5.1 (PSR-0 loader re-declared \Mustache_Engine, uncatchable fatal on the 2nd e-mail of a cron run); legacy class is asked for by name first. No schema change.
// 2026100801: 2026-10-08 Moodle 5.3 compat FX-04: NEW classes/mustache_factory.php (\Mustache\Engine on 5.2+/5.3, \Mustache_Engine on 5.1) used by the tenant-override render, the AJAX preview and the preview WS; catch \Throwable so the file fallback runs. No schema change.
// 2026100701: 2026-10-07 owner decisions: idx_sender_userid on the log (F-64); the three BizLMS e-mails Sentientia had no sender for (course enrolment, learning-path enrolment, manager copy of a completion), each behind its own default-OFF flag (COMMS-N7); the importer reads the redaction, manager-copy and course-link decisions (COMMS-N1/N2/N3)
// 2026093001: ADR-032: BizLMS email-history importer (local_emaillogs + local_email_logs -> email_log), +4 columns and an index on the log, imported-history readers behind two default-OFF flags
// 2026092501: ADR-031 follow-up: upgrade switches off tenant overrides whose author was not entitled to that tenant; legacy templates + rule UI tenant-scoped; :manage_templates RISK_XSS|RISK_SPAM
// 2026092500: ADR-031: manage/editor/WS confined to the caller's tenant; global rules + overrides cross-tenant only; cron honours rule tenant
// 2026092200: cadence setting: validate() returns true on success so the value (and the install default) actually persists
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.0';  // +COMMS-N7 notification senders (flagged off), +COMMS-N1/N2/N3 importer rules, +idx_sender_userid (was 1.3.0 +ADR-032 BizLMS email-history importer)
$plugin->dependencies = [
    'local_sentientia_platform' => 2026093001,  // tenant::is_cross_tenant() (ADR-031); the ADR-032 import framework (bizlms\importer)
];
// 1.1   (Sprint B, 2026-05-13)
//   + course_completed event observer + tool_certificate PDF attachment
//   + course_incomplete rule type with ramping cadence + max-cap + completion auto-stop
//   + delivery_log schema: attachment_filename + certificate_issue_id columns
//   + rules schema: cadence_days_json + max_reminders_per_user + auto_stop_on_completion columns
// 1.1.1 (Sprint B hotfix, 2026-05-13)
//   * delivery_log.status: widen char(20) -> char(32) so the new
//     'suppressed_completion' enum value (21 chars) fits. Caught by
//     PHPUnit's observer_test:test_mark_reminders_suppressed_on_completion_stamps_sent_rows.
