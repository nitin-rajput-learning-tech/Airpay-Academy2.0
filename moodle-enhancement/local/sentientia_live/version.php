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
$plugin->version   = 2026052504;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // Phases E.4-E.9 — all 6 question types live + verified
$plugin->release   = '0.2.1-alpha';
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
// 0.2.1-alpha  Verification + robustness. Two-browser acceptance test of
//              all 6 question types on local XAMPP: every audience render
//              confirmed (multichoice/wordcloud/openended/rating/quiz/
//              ranking), SSE live auto-advance demonstrated, server
//              persist+tally proven on real DB, zero JS console errors.
//              Evidence in docs/visual-evidence/2026-05-27/.
//              BUGFIX: session_manager::create() hard-selected the
//              BizLMS-only `open_path` column from {user}, which threw
//              `Unknown column 'open_path'` on a vanilla Moodle (and the
//              PHPUnit test DB), erroring all 76 persist/tally tests. Now
//              reads open_path defensively via get_columns() — works on
//              BizLMS (Airpay), vanilla Moodle (future Sentientia
//              customers), and the test DB alike. multiple_choice_test now
//              18/18 green. New QA CLIs: cli/set_live_flags.php (flip the
//              Live flag set) + cli/seed_demo_session.php (seed a LIVE
//              session with all 6 types + responses).
//              Full-suite green follow-up (0 errors / 0 failures): three
//              stale test expectations corrected to match the reviewed
//              code — word_cloud min>max error attaches to max_word_length
//              (not min_word_length); a legacy plain-text decode_words is
//              ONE token (not whitespace-split, matching the cap-drift
//              fix); session_manager owner-id compared int-to-int (was
//              assertSame string-vs-int). Residual local PHPUnit noise is
//              the third-party block_learnerscript parse_url/REQUEST_URI
//              deprecation — absent in the hermetic CI plugin set.
