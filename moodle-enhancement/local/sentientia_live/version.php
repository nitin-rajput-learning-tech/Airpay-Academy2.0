<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia LMS Live engagement (Mentimeter clone).
 *
 * Stream E (Tier 1 #3) — real-time polls, quizzes, Q&A, word clouds,
 * ranking, rating scales. SSE-driven (one-way server push) per ADR-004.
 *
 * Estimated 8-12 sessions to v1. Phase E.0 (this version) ships the
 * scaffold: DB schema + capabilities + privacy provider + feature
 * flags. No UI yet.
 *
 * Roadmap:
 *  - E.0  Foundation: schema + privacy + flags + ADR
 *  - E.1  Trainer UI: create session, manage slides
 *  - E.2  Audience UI: join by code, respond
 *  - E.3  SSE realtime: trainer → audience push
 *  - E.4  Question type — Multiple choice (bar chart)
 *  - E.5  Question type — Word cloud
 *  - E.6  Question type — Open-ended
 *  - E.7  Question type — Rating scale (1-5 / NPS)
 *  - E.8  Question type — Quiz (right/wrong + leaderboard)
 *  - E.9  Question type — Ranking (drag-to-order)
 *  - E.10 Per-tenant + per-customer settings
 *  - E.11 Mobile responsiveness pass
 *  - E.12 Analytics + export
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_live';
$plugin->version   = 2026052501;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // Phase E.4 — multiple_choice live
$plugin->release   = '0.1.3-alpha';
$plugin->dependencies = [
    'local_airpay_core' => 2026051401,  // feature_flags resolver
];

// Release history
// 0.1.0-alpha  Phase E.0: scaffold + DB schema + capabilities + privacy
//              No UI yet. Tables exist; no callers.
// 0.1.1-alpha  P0 #8 a11y — aria-live regions added to result_panel,
//              result_bar_chart, audience/play.php, trainer/run.php;
//              chart_updater emits sr-only tally summary on each
//              response_added SSE event. +9 string pairs en+hi.
//              P1 #15 / F-24 — Sentientia BEM tokens (airpay-badge/airpay-btn)
//              layered over Bootstrap utility classes in trainer_dashboard
//              / result_panel / result_bar_chart. Theme overrides in
//              theme/airpayux/scss/moodle/partials/_bizlms-modern.scss.
//              P2 #22 / F-25 — Trainer-dashboard table a11y: added
//              <caption class="sr-only"> and scope="col" on every <th>.
//              New lang key trainer_sessions_table_caption (EN + HI).
// 0.1.2-alpha  Phase E.4-E.9 scaffold — abstract_question_type base
//              class + 6 concrete stubs (multiple_choice, word_cloud,
//              open_ended, rating_scale, quiz, ranking) + the
//              question_type_registry that maps slug→FQCN. Concrete
//              methods throw coding_exception('not_implemented') —
//              UI follows in E.4-E.9 chips. +12 string pairs en+hi
//              (6 names + 6 descriptions). +1 PHPUnit test class
//              with 7 assertions covering registry resolution +
//              interface conformance. Docs:
//              docs/sentientia-live/QUESTION-TYPES.md.
// 0.1.3-alpha  Phase E.4 multiple_choice — class fully implemented:
//              render() drives a Mustache audience template (radio or
//              button render style), persist_response() bounds-checks
//              option_index against the slide's stored options and
//              delegates to response_recorder::submit() for the
//              idempotent upsert + SSE event, tally() returns a rich
//              [{index, label, count, is_correct}, ...] shape, and
//              validate_config() enforces the class-layer 2-6 option
//              cap (slide_manager keeps its looser 2-20 for back-
//              compat with stored production rows).
//              +2 Mustache templates: qt_multiple_choice_audience
//              (radio | buttons render style, BEM names, aria-
//              labelledby, mobile <=590px overrides), and
//              qt_multiple_choice_result (sentientia-bar-row dom that
//              chart_updater.js already targets — bars update in
//              place via SSE without page reload).
//              +10 string pairs en+hi covering class-layer error
//              messages + render-style picker + 2 aria announcements
//              (mc tally + correct revealed). Hindi parity remains at
//              100% (287 / 287 keys verified).
//              +1 PHPUnit test class (multiple_choice_test, 16
//              assertions) covering 4 valid configs, 3 invalid
//              configs, persist with valid + 4 invalid payloads,
//              tally aggregation, idempotent resubmission, correct-
//              answer reveal, registry slug resolution, and aria
//              announcement contract.
