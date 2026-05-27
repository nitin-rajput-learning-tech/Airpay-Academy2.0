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
$plugin->maturity  = MATURITY_ALPHA;   // Phase E.5 — word cloud full impl
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
// 0.2.0-alpha  Phase E.5 — Word cloud full implementation. Replaces
//              the P3-R stub with: render() (text input + remaining
//              hint), persist_response() (tokenise → profanity-filter
//              → append to JSON-array value_text, capped at
//              max_responses_per_user), tally() (frequency map sorted
//              desc, case-insensitive aggregation),
//              validate_config() (max_responses_per_user 1-10,
//              min/max word length, locale). New
//              classes/profanity_filter.php with default English
//              denylist + per-customer override hook
//              (local_airpay_core::customer_config). New settings.php
//              exposing default_min_word_length (int, 2) and
//              default_max_responses (int, 3). New AMD modules:
//              wordcloud_loader.js (CSS-bucket renderer, no external
//              vendor — d3-cloud weight not justified for 5-bucket
//              CSS-driven cloud) + wordcloud_updater.js (SSE
//              subscriber, mutates DOM via textContent + className
//              only — XSS-safe). chart_updater extended with
//              HANDLED_ELSEWHERE_TYPES = ['wordcloud'] so it doesn't
//              fight wordcloud_updater. +19 string pairs en+hi.
//              +1 PHPUnit test class (word_cloud_test) with 24 test
//              methods covering profanity (whole-word, no
//              Scunthorpe false-positives), tokenisation, max-responses
//              cap, dedupe collapse (on/off), multi-word splitting,
//              lowercase aggregation, Unicode (Devanagari) survival,
//              and legacy-row decode back-compat. response_recorder
//              updated to delegate decode/tokenise to word_cloud
//              (back-compat preserved for any in-flight legacy
//              rows). audience/play.php now renders the cap-aware form
//              via word_cloud::render(); play.php + trainer/run.php
//              attach the new AMD modules. slide_form + edit_slide add
//              per-slide min_word_length + max_responses_per_user
//              fields. privacy provider decodes wordcloud value_text
//              to a readable word list on export. Default master flag
//              live.questiontype.wordcloud stays OFF — admins flip
//              it via Switchboard when ready.
//              Code-review fixes folded in pre-merge: whole-word
//              profanity matching (was substring → false-positives),
//              legacy plain-string rows decode as a single token
//              (was re-tokenised → tally/cap drift), dedupe setting
//              now honoured (was ignored), wordcloud_updater reloads
//              on the 0→1 transition (container only exists once
//              has_responses), validate_config cross-field error
//              attaches to the right field.
