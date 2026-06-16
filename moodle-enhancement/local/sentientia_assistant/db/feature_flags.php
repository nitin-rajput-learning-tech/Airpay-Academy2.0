<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_assistant.
 *
 * P1.3 — Agentic Copilot (2026-06-16) adds two NEW flags on top of the
 * pre-existing `ai.assistant.enabled` Switchboard flag (registered in
 * local_sentientia_platform, governs the legacy nav Q&A surface — NOT
 * redefined here):
 *
 *  1. sentientia.assistant.agentic.enabled — master switch for the
 *     RAG + tool-use AGENT loop. Default OFF. When OFF, the assistant
 *     behaves EXACTLY as it did before this build — pure navigation Q&A
 *     via {@see \local_sentientia_assistant\ai_client::ask()}. When ON,
 *     the agent loop can PROPOSE guarded tool calls (enrol, book ILT,
 *     surface gap content) that the platform authorises and executes.
 *
 *  2. sentientia.assistant.agentic.live_api — gates whether the agent
 *     loop's reasoning step ACTUALLY POSTs to api.anthropic.com vs.
 *     returning a deterministic mock proposal. Default OFF. Mirrors the
 *     sentientia.aiquiz.live_api pattern exactly: mock mode is what makes
 *     the agent end-to-end demonstrable with zero API spend and no key.
 *
 * Per CLAUDE.md §13: every new feature MUST ship behind a default-OFF
 * flag, and existing production behaviour must be byte-identical when the
 * flag is OFF.
 *
 * @package local_sentientia_assistant
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Agentic Copilot (P1.3) ────────────────
    'sentientia.assistant.agentic.enabled' => [
        'default'     => false,
        'description' => 'Sentientia Agentic Copilot (P1.3). Master switch
                          for the RAG + tool-use agent loop. When OFF the
                          assistant stays a pure navigation Q&A bot
                          (unchanged production behaviour). When ON, the
                          agent can PROPOSE guarded tool calls — enrol in a
                          course, book an ILT session, surface gap-closing
                          content — which the platform then authorises
                          (require_capability + tenant scope), executes
                          idempotently, and audit-logs. The LLM never
                          executes a tool directly; it only proposes.',
    ],

    'sentientia.assistant.agentic.live_api' => [
        'default'     => false,
        'description' => 'Anthropic live API gate for the agent loop. When
                          OFF (default), the agent uses
                          agent_client::call_mock() — a deterministic
                          proposal driven by keyword intent, zero cost, no
                          API key needed. When ON, the same code path POSTs
                          to api.anthropic.com using the key from
                          local_sentientia_assistant | api_key. Companion
                          flag sentientia.assistant.agentic.enabled must
                          also be ON. Mock-mode is the default so the agent
                          can be demoed end-to-end without spending money.',
    ],

];
