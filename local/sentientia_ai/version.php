<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia AI Gateway — the single model gateway every Sentientia AI
 * feature routes through (ADR-028 Phase 2.3 / hard call #4).
 *
 * Before this plugin, six plugins (aiquiz, skillsai, recommendations,
 * translate, authoring, assistant) each carried a near-identical
 * anthropic_client with its own admin-setting API key — no org-level
 * spend control, no cross-plugin quota, no shared eval baseline, and
 * six places to rotate a key. The gateway owns:
 *
 *   - central key management (one passwordunmask setting)
 *   - a spend ledger (local_sentientia_ai_ledger) recording every call
 *     (mock, live, failed, denied) with token counts + estimated cost
 *   - fail-closed daily/monthly quotas (global + per-customer)
 *   - mock-first routing: live calls require BOTH gateway flags ON, a
 *     key, and quota headroom; everything else returns the calling
 *     component's own deterministic mock (zero spend, demo-safe)
 *   - a golden-set eval harness (tests/) so mock output stays stable
 *
 * Consumers migrate by delegating their client dispatcher to
 * \local_sentientia_ai\client::complete() — reference migration:
 * local_sentientia_aiquiz (2026080401). Per the signed Addendum A (memo
 * 2026-08-04), NO live_api flag flips anywhere until this gateway ships
 * and each feature's last-mile integration is closed.
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_ai';
// 2026092500 — ADR-031: :viewledger and :manage have no default grant (new
// db/upgrade.php step revokes existing grants); the ledger page also requires
// tenant::is_cross_tenant() (ledger::can_view()).
$plugin->version   = 2026092500;  // YYYYMMDDNN — ADR-031 cross-tenant authority
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.1-alpha';
$plugin->dependencies = [
    // Flag resolution + customer/tenant scoping come from the platform layer;
    // 2026092500 is the ADR-031 tenant::is_cross_tenant() authority.
    'local_sentientia_platform' => 2026092500,
];
