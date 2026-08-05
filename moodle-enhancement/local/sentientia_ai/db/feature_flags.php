<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_ai (the AI gateway).
 *
 * Two flags, both default OFF (CLAUDE.md §13):
 *
 *  1. sentientia.ai.gateway.enabled — master switch. When OFF the gateway
 *     still answers every client::complete() call with the calling
 *     component's deterministic mock (demo-safe, zero spend) and still
 *     writes ledger rows, but the live path is unreachable regardless of
 *     any other flag. This means consumers can migrate onto the gateway
 *     without waiting for a flag decision.
 *
 *  2. sentientia.ai.live_api.enabled — the org-level live-spend gate
 *     (signed Addendum A, memo 2026-08-04). Live calls require BOTH
 *     flags ON + a configured key + quota headroom + whatever gates the
 *     calling component enforces on top (e.g. aiquiz's own live_api flag
 *     and per-action [CONFIRM] — the ADR-012 layers stay intact; the
 *     gateway is layer 0 underneath them).
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — AI Gateway ────────────────────────────
    'sentientia.ai.gateway.enabled' => [
        'default'     => false,
        'description' => 'Sentientia AI Gateway ROUTING switch (ADR-028
                          Phase 2.3). Migrated consumer plugins check this
                          before delegating: when OFF (default) they run
                          their legacy standalone path — byte- and
                          side-effect-identical to the pre-gateway builds
                          (local mocks, no ledger writes) — so migration
                          ships dormant and reversible. When ON, consumer
                          calls route through the gateway (central key,
                          spend ledger, fail-closed quotas); they still
                          return mocks until sentientia.ai.live_api.enabled
                          also flips. Direct client::complete() callers are
                          always gated by the pair of flags inside the
                          gateway itself (OFF ⇒ mock, live unreachable).',
    ],

    'sentientia.ai.live_api.enabled' => [
        'default'     => false,
        'description' => 'Org-level Anthropic live-spend gate — the single
                          switch the signed Addendum-A budget decision
                          governs. When OFF, no plugin can reach
                          api.anthropic.com through the gateway no matter
                          what its own flags say. When ON, live calls are
                          still bounded by the central key, the per-day
                          token quotas (global + per-customer) and the
                          monthly estimated-cost cap in the gateway
                          settings — all fail-closed (a zero/empty cap
                          means NO live calls, never unlimited).',
    ],

];
