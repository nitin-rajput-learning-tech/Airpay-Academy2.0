<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_evaluation';
// W1-5 + W1-9 + P1 #17 + P1 #18 + P1 #19 (2026-05-16) — observer +
// trigger queue + scheduled task + availability window + pulse mode +
// numeric + multi-select multichoice question types + email-on-response
// admin notification.
// P1 #27 (2026-05-20) — Hindi (hi) lang pack catch-up: 132 strings
// translated, covering all P1 #17/#18/#19 additions.
// P1 #30 (2026-05-20) — conditional question display.
// P1 #31 (2026-05-20) — front-end JS show/hide.
// P1 #37 (2026-05-20) — assignments table.
// P1 #38 (2026-05-20) — show-non-respondents admin page.
// P1 #39 (2026-05-20) — bulk-assign by audience back-end.
// P1 #40 (2026-05-20) — bulk-assign modal + AMD wiring.
// P1 #41 (2026-05-20) — DB-backed template library.
// P1 #42 (2026-05-20) — auto-expire overdue assignments cron.
// P1 #43 (2026-05-20) — Hindi pack catch-up: 56 new strings translated
//                       for P1 #30/#37/#38/#39/#40/#41/#42 additions.
// ADR-032 (2026-09-30) - the BizLMS evaluation importer (db/bizlms_import.php, classes/bizlms/): forms keep their
// BizLMS ids and arrive archived, with their questions, templates, assignments and answers; responses gain
// subject_userid (supervisor forms), which the privacy provider declares; an imported form is read-only; and
// the learner's own evaluation history (my_evaluations.php) sits behind the default-OFF flag
// sentientia.evaluation.learner_history. The importer class refuses to run below this version
// (importer::REQUIRES_VERSION).
// 2026100801 - Moodle 5.3 compat FX-20: the question card menu emits data-bs-toggle beside the Bootstrap 4 data-toggle. No schema change.
$plugin->version   = 2026100801;  // Moodle 5.3 compat FX-20 (on top of 2026093001 ADR-032 evaluation import)
// 2026093001:  // ADR-032: evaluation importer, responses.subject_userid, imported forms read-only
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.16.0';  // +ADR-032 BizLMS evaluation import. 1.15.3: ADR-031 tenant scope
$plugin->dependencies = [
    'local_sentientia_platform' => 2026093001,  // ADR-032 classes/bizlms (importer framework, provenance); ADR-031 tenant::is_cross_tenant() / scope_path()
];
