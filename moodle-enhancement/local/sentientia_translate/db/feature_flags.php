<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_translate.
 *
 * Phase T.0 (2026-05-25) — two flags:
 *
 *  1. sentientia.translate.enabled  — master switch, default OFF
 *  2. sentientia.translate.live_api — gates whether the call ACTUALLY
 *                                      POSTs to api.anthropic.com vs.
 *                                      returning a deterministic mock
 *                                      response. Default OFF. ON in
 *                                      production only when an API key
 *                                      is configured AND a human has
 *                                      flipped this AND the per-call
 *                                      [CONFIRM] gate has been ticked.
 *                                      Mock mode is what makes the MVP
 *                                      end-to-end demoable without
 *                                      spending money.
 *
 * Per CLAUDE.md §13: every new feature MUST ship behind a default-OFF
 * flag. This file satisfies that mandate for Phase T.0.
 *
 * @package local_sentientia_translate
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — AI Translation ─────────────────────────
    'sentientia.translate.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS AI Content Translation (Tier 1 AI).
                          Master switch. When OFF, /local/sentientia_translate/
                          pages return 403 and the navigation link is hidden.
                          When ON, admins with the :translate cap can paste
                          English source text, pick a target language, and
                          request a translation — each translation still
                          requires the per-call [CONFIRM] gate plus
                          sentientia.translate.live_api=ON for a real
                          Anthropic call (otherwise the deterministic mock
                          client runs).',
    ],

    'sentientia.translate.live_api' => [
        'default'     => false,
        'description' => 'Anthropic live API gate. When OFF, every translate
                          request uses anthropic_client::call_mock() —
                          deterministic marked output, zero cost. When ON,
                          the same code path POSTs to api.anthropic.com
                          using the API key from
                          local_sentientia_translate | api_key (admin
                          setting). Companion flag
                          sentientia.translate.enabled must also be ON.
                          Mock-mode is the default so the MVP can be demoed
                          end-to-end without spending money.',
    ],

];
