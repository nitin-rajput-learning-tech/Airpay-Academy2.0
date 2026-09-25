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
