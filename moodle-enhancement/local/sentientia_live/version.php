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
$plugin->version   = 2026052401;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // Phase E.0 — scaffold only
$plugin->release   = '0.1.1-alpha';
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
