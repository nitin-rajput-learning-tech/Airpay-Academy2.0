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
// EV-17 (2026-10-07) - local_sentientia_evaluation.evaluationmode (SE or SP, default SE): the form says whether it is a
// supervisor evaluation, so the person evaluated is never told they "responded" (anonymous supervisor forms keep no
// subject, and old completions carry no evaluator). The importer writes it from BizLMS evaluationmode; the learner
// history, the Subject column and a verify check read it. Needs a (guarded) upgrade step, and importer::REQUIRES_VERSION
// is the same number.
// 2026100702 (2026-10-07, review round) - the EV-17 back-fill (db/upgrade.php, classes/evaluation_mode_backfill.php): a site
// that imported before evaluationmode existed gets its imported supervisor forms marked SP; the individual responses pages
// read the flag for the EVALUATION's tenant and are not offered on an identity-protected form. importer::REQUIRES_VERSION
// stays 2026100701: it names the version that adds the column the importer writes, and the back-fill adds no schema.
// The platform dependency is raised to 2026100701 (the framework code the importer relies on: step::target_children(), the
// PRESERVE sequence floor, row-bounded acceptances, customer::of_tenant()).
$plugin->version   = 2026100702;  // EV-17 back-fill; drilldown flag per evaluation tenant; protected forms offer no individual responses
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.17.1';  // +EV-17 back-fill, review fixes. 1.17.0: +EV-17 evaluationmode. 1.16.0: ADR-032 BizLMS evaluation import. 1.15.3: ADR-031 tenant scope
$plugin->dependencies = [
    'local_sentientia_platform' => 2026100701,  // ADR-032 classes/bizlms with step::target_children(), the EV-26 sequence floor and row-bounded acceptances; customer::of_tenant(); ADR-031 tenant::is_cross_tenant() / scope_path()
];
