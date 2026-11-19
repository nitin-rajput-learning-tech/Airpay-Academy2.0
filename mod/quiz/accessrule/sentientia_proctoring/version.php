<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quizaccess_sentientia_proctoring';
// B1 "Fix the foundation" (ADR-038 D2, 2026-11-20): consent + identity move into the quiz pre-start check (no more
// lockout), the monitor loads on every attempt page, the setting loads with the quiz (get_settings_sql) and is hidden
// and ignored while sentientia.proctoring.enabled is OFF; en + hi strings. No schema change. Was: 2026080400;  // 2026-08-04 privacy null-provider (GDPR registry closure)
// B2 "Secure assessment mode" (ADR-038 D1, D3 and D6, 2026-11-20, 2026112001): per-quiz Security level (Standard /
// Secure / Proctored), assessment purpose (sets the lowest level; hiring = auto-submit) and violation limit (default 3,
// 0 = never), three additive columns (db/upgrade.php). With sentientia.assessment.secure_mode.enabled ON the rule adds
// the rules notice to the pre-start check and loads local_sentientia_proctoring/secure_mode on the attempt pages; a
// Secure or Proctored quiz whose flag is OFF does not start (fail closed); teachers get the integrity report on the
// attempt review page. With the flag OFF a quiz with no level behaves exactly as in B1. en + hi strings.
// B2 review (2026-11-20, 2026112002): an open attempt keeps the policy its learner accepted (level and limit on its
// session) whatever the flags or the quiz settings say now, so switching a flag OFF no longer traps attempts in
// progress; the fail-closed block applies to new attempts only. Web-service calls (WS_SERVER: the mobile app or a direct
// REST call) get "desktop required" for Secure, Proctored and B1 proctored quizzes (new string blocked_desktop_required,
// en + hi); a Standard quiz runs as the stock quiz there (ADR-040, plan D29). The attempt pages pass the focus grace to
// secure_mode.js. No schema change.
$plugin->version   = 2026112002;  // B2 review, see above. Was: 2026112001;  // B2, see above. Was: 2026112000;
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.1';  // 1.3.1: B2 review fixes. 1.3.0: B2 security levels. 1.2.0: B1 pre-start check + per-page monitor. 1.1.1: privacy null provider.
$plugin->dependencies = [
    'local_sentientia_proctoring' => 2026112003,  // B2 review: quiz_integration::open_attempt_of(). B2: secure_mode, the session columns, the two web services. B1: quiz_integration, the attempt observers, the B1 flag
];
