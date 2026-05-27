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
$plugin->version   = 2026052403;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // Phases E.4-E.9 — question types live
$plugin->release   = '0.2.0-alpha';
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
// 0.2.0-alpha  Wave D4 — full implementation of the remaining 4
//              question types (open_ended, rating_scale, quiz, ranking;
//              multichoice + word_cloud land via parallel chips C1/C2).
//              Each type now ships render() / persist_response() /
//              tally() / validate_config() / get_aria_announcements()
//              plus qt_<type>_audience + qt_<type>_result Mustache
//              templates. open_ended: 500-char cap, paginated display,
//              moderation toggle. rating_scale: stars (1-5) | NPS (1-10),
//              mean + median. quiz: required correct_index, per-response
//              scoring, top-10 fastest-correct leaderboard. ranking:
//              drag-to-order with numeric a11y fallback, Borda count +
//              average position. Trainer picker now registry-driven.
//              +66 string pairs en+hi (Hindi parity 100%). +4 PHPUnit
//              test classes (≥24 methods) covering valid/invalid config,
//              persist, tally aggregation, registry resolution.
