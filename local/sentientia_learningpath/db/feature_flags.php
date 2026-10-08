<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_learningpath — Adaptive journeys.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * ONE flag governs the entire adaptive engine.  When OFF (default) all
 * new code paths return early and the plugin behaves identically to
 * version 1.7.1 — the prerequisite engine, static sequences, and
 * enrolment logic are completely unaffected.
 *
 * Flag: sentientia.learningpath.adaptive.enabled
 *   default: false
 *   Enables:
 *     - journey_engine::evaluate() running after every quiz attempt
 *       (triggered via the mod_quiz_attempt_submitted event)
 *     - Branch / accelerate / remediate decisions written to
 *       local_sentientia_lp_adaptive_log
 *     - Scheduled task task\adaptive_sweep (daily) for completion-
 *       velocity recalculation
 *     - Graceful skills-gap feed consumption from local_sentientia_skillsai
 *       (falls back to completion+score when skillsai is absent)
 *
 * Per CLAUDE.md §13: every new feature ships behind a default-OFF flag.
 *
 * @package local_sentientia_learningpath
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ADR-032 (2026-09-30): the learner-facing page that lists a learner's own
    // learning paths, including the ones the BizLMS import brought over.
    // Default OFF (CLAUDE.md section 13). The import never flips it: whether it
    // is ON for the Airpay customer at cutover is Nitin's call, after he has
    // reviewed the visual evidence (decision framework.reader_flags_airpay_at_cutover).
    'sentientia.learningpath.learner_paths.enabled' => [
        'default'     => false,
        'description' => 'Learner "My learning paths" page (/local/sentientia_learningpath/mypaths.php).
                          When ON, a learner sees the active learning paths they are enrolled
                          in - their own rows only, inside their own tenant - with progress,
                          enrolment and completion dates, including history imported from
                          BizLMS. Completed history on archived paths stays admin-only.
                          It also governs the BizLMS cover image on the admin path page
                          (view.php): shown only while the flag is ON (owner decision
                          learningplan.cover_on_admin_view, 2026-10-07).
                          When OFF (default) the page refuses to open, the admin path page shows
                          no cover image, and nothing else changes.',
    ],

    'sentientia.learningpath.adaptive.enabled' => [
        'default'     => false,
        'description' => 'Adaptive Learning Journeys (P0.2). When ON, learning
                          paths pivot on learner quiz scores, completion velocity,
                          and skills-gap signals — automatically branching
                          (skip mastered content), accelerating (fast-track
                          high performers), or remediating (insert remedial
                          courses for low scorers). When OFF (default) paths
                          behave exactly as in v1.7.1: static sequential
                          ordering, no pivot logic, no new DB writes.',
    ],

];
