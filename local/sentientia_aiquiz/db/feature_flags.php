<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_aiquiz.
 *
 * Phase G.0 (2026-05-24) — three flags:
 *
 *  1. sentientia.aiquiz.enabled         — master switch, default OFF
 *  2. sentientia.aiquiz.live_api        — gates whether the call ACTUALLY
 *                                          POSTs to api.anthropic.com vs.
 *                                          returning a deterministic mock
 *                                          response. Default OFF. ON in
 *                                          production only when an API key
 *                                          is configured AND a human has
 *                                          flipped this AND the per-call
 *                                          [CONFIRM] gate has been ticked
 *                                          by the calling user. The mock
 *                                          mode is what makes the MVP
 *                                          end-to-end demonstrable without
 *                                          spending money.
 *  3. sentientia.aiquiz.auto_push       — when ON, "Push approved to course
 *                                          quiz" enables. Default OFF until
 *                                          mod_quiz push is verified.
 *
 * Per CLAUDE.md §13: every new feature MUST ship behind a default-OFF
 * flag. This file satisfies that mandate for Phase G.0.
 *
 * @package local_sentientia_aiquiz
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — AI Quiz Generation ────────────────────
    'sentientia.aiquiz.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS AI Quiz Generation (Tier 1 #4).
                          Master switch. When OFF, /local/sentientia_aiquiz/
                          pages return 403 and the navigation link is hidden.
                          When ON, Course Authors with the :generate cap
                          can paste source text and request a quiz draft —
                          each generation still requires the per-call
                          [CONFIRM] gate plus sentientia.aiquiz.live_api=ON
                          for a real Anthropic call (otherwise the
                          deterministic mock client runs).',
    ],

    'sentientia.aiquiz.live_api' => [
        'default'     => false,
        'description' => 'Anthropic live API gate. When OFF, every generate
                          request uses anthropic_client::call_mock() —
                          deterministic 10-question output, zero cost. When
                          ON, the same code path POSTs to api.anthropic.com
                          using the API key from
                          local_sentientia_aiquiz | api_key (admin setting).
                          Companion flag sentientia.aiquiz.enabled must also
                          be ON. Mock-mode is the default so the MVP can be
                          demoed end-to-end without spending money.',
    ],

    'sentientia.aiquiz.auto_push' => [
        'default'     => false,
        'description' => 'Push-to-mod_quiz workflow. Phase G.4 (2026-08-05)
                          replaced the G.0 "quiz id 0" stub with the REAL
                          publisher: approved/edited questions are imported
                          into the course\'s default shared question bank
                          (GIFT pipeline) and a real, HIDDEN quiz activity
                          is created and populated; the draft records the
                          actual quiz id. When OFF the push button is
                          disabled — reviewers can still approve/edit/
                          reject. Default stays OFF until the publisher is
                          verified end-to-end on the ninja/staging
                          rehearsal (ADR-028 engineering gate #3
                          discipline).',
    ],

];
