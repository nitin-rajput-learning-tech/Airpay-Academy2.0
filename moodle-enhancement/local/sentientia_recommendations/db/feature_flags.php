<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_recommendations.
 *
 * Phase H.0 (2026-05-25) — three flags:
 *
 *  1. sentientia.recommendations.enabled  — master switch, default OFF
 *  2. sentientia.recommendations.live_api — gates whether the call ACTUALLY
 *                                            POSTs to api.anthropic.com vs.
 *                                            returning a deterministic mock
 *                                            response. Default OFF. ON in
 *                                            production only when an API
 *                                            key is configured AND a human
 *                                            has flipped this AND the per-
 *                                            call [CONFIRM] gate has been
 *                                            ticked. Mock mode is what
 *                                            makes the MVP end-to-end
 *                                            demoable without spending
 *                                            money.
 *  3. sentientia.recommendations.auto_cron — when ON, the scheduled
 *                                            recommendation_refresh cron
 *                                            task regenerates batches.
 *                                            Default OFF until the
 *                                            cost-per-day pattern is
 *                                            verified on staging.
 *
 * Per CLAUDE.md §13: every new feature MUST ship behind a default-OFF
 * flag. This file satisfies that mandate for Phase H.0.
 *
 * @package local_sentientia_recommendations
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — AI Recommendations ─────────────────────
    'sentientia.recommendations.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS AI Course Recommendations (Tier 1 AI #5).
                          Master switch. When OFF, the dashboard block hides,
                          /local/sentientia_recommendations/ pages return 403,
                          and the navigation link is suppressed.
                          When ON, learners see the most recent recommendation
                          batch (if any) in the dashboard block; managers can
                          trigger batch generation via the admin UI — each
                          generation still requires the per-call [CONFIRM]
                          gate plus sentientia.recommendations.live_api=ON
                          for a real Anthropic call (otherwise the
                          deterministic mock client runs).',
    ],

    'sentientia.recommendations.live_api' => [
        'default'     => false,
        'description' => 'Anthropic live API gate. When OFF, every batch uses
                          anthropic_client::call_mock() — deterministic
                          fixed-shape output, zero cost. When ON, the same
                          code path POSTs to api.anthropic.com using the API
                          key from local_sentientia_recommendations | api_key
                          (admin setting). Companion flag
                          sentientia.recommendations.enabled must also be
                          ON. Mock-mode is the default so the MVP can be
                          demoed end-to-end without spending money.',
    ],

    'sentientia.recommendations.auto_cron' => [
        'default'     => false,
        'description' => 'Background refresh task. When OFF (default),
                          recommendation batches are only generated when a
                          manager clicks the "Generate now" button on the
                          admin UI. When ON, a daily cron task
                          (local_sentientia_recommendations\task\refresh)
                          scans active learners and triggers a fresh batch.
                          Default OFF until the cost-per-day pattern is
                          verified on staging.',
    ],

];
