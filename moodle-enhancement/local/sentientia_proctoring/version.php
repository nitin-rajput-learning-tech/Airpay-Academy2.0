<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_proctoring';
// P1 #55 (2026-05-20) — Hindi pack: 90 strings covering consent flow,
// identity verification, live monitoring, behavioural events, review queue,
// status, settings, notifications, errors, privacy metadata (incl. AWS).
// Goal A audit Bug #10 (2026-05-22) — align list_review_queue WS with the
// shared theme_sentientia/datatable contract (accept `search`, `sort`,
// `sortdir`, `filters`).
// B1 "Fix the foundation" (ADR-038 D2, 2026-11-20): db/feature_flags.php (sentientia.proctoring.enabled + the four
// reserved B2-B5 flags, all default OFF); quiz_integration (pre-start consent + identity, attempt binding); attempt
// observers close the session on the server (db/events.php); report_event takes only the monitor's event types on an
// open attempt; proctor.js restarts on every attempt page, no camera/mic/screen; notifier, settings headings and the
// purge task name in lang (en + hi); purge reads the S3 keys settings.php stores; no aws face-match provider (D10).
// No schema change.
// B1 review (2026-11-20, 2026112001): give_consent and submit_identity act only on a session with no attempt that has
// not started (an active, flagged or reviewed session is refused and left unchanged); upload_chunk refuses while
// sentientia.proctoring.recording.enabled is OFF (new string error_recording_disabled, en + hi); proctor.js also
// listens inside the page's own frames (the TinyMCE editor) for pastes and window switches, and polls focus.
// B2 "Secure assessment mode" (ADR-038 D3 and D6, post-demo plan B2, session 7, 2026-11-20, 2026112002): behind
// sentientia.assessment.secure_mode.enabled (default OFF). New db/upgrade.php (the plugin's first): six additive session
// columns (securitylevel, violationlimit, violations, lastviolation, lastheartbeat, timeautosubmitted). New secure_mode
// class (server-side violation counting with a 2-second de-duplication and a 10-second focus grace after a page opens,
// heartbeat gaps, rate limit, hiring auto-submit), web services secure_report (the attempt page, sesskey via core ajax)
// and integrity_report (teachers, review page), AMD modules secure_mode (fullscreen gate, copy/paste/print/shortcut
// blocking, watermark, heartbeat) and integrity_report, template integrity_report, styles.css; close_session flags a
// secure session with violations, gaps or an auto-submit; privacy provider names the new columns; en + hi strings.
// B2 review (2026-11-20, 2026112003): the focus grace excuses only a lost focus that ends within it. A lost focus that
// began in the grace stays open on the server and counts once (window_blur, reason grace_exceeded) when the page reports
// the focus back (window_focus, now sent at once) or, when the grace is over, that it is still lost (window_still_away,
// never stored itself); secure_mode.js reports both (AMD rebuilt). close_session sends to review an attempt the server
// never sent a page of (monitor_missing with served 0, e.g. one taken through the quiz web services). New
// quiz_integration::open_attempt_of() for the access rule. New string report_counted_grace_exceeded (en + hi). No
// schema change.
$plugin->version   = 2026112003;  // B2 review, see above. Was: 2026112002;  // B2, see above. Was: 2026112001;  // B1 review, see above. Was: 2026112000;  // B1, see above. Was: 2026111200;  // decision A5 (2026-11-12): the session_flagged and review_submitted audit events the platform's audit log lists (event classes only, no schema/cap change); privacy provider links the logstore plugin type. Was: 2026110300;  // user search matches a full name ("Rahul Sharma"), 2026-11-03. Was: 2026092500;  // ADR-031: badge tenant-scoped; no-tenant reviewers reach no session
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.1';  // 1.2.1: B2 review fixes. 1.2.0: B2 secure assessment mode. 1.1.1: B1 review fixes. 1.1.0: B1 foundation (pre-start check, server finalize, per-page monitor). 1.0.6: session_flagged + review_submitted audit events (decision A5). 1.0.5: full-name user search.     // +ADR-031 tenant scope
$plugin->dependencies = [
    'local_sentientia_org'     => 2026040100,
    'local_sentientia_privacy' => 2026040100,  // DSR pipeline integration
    'local_sentientia_platform'    => 2026092500,  // Shared tenant helper (ADR-031 is_cross_tenant), feature_flags
];
