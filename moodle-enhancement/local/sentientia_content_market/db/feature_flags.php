<?php
/**
 * Feature flag registry for local_sentientia_content_market.
 *
 * P1.1 (2026-06-16) — Curated Content Marketplace gap closure.
 * All flags ship DEFAULT OFF so existing production is unchanged.
 * Per CLAUDE.md §13 absolute rule: NEVER ship a feature without a
 * feature flag (default OFF).
 *
 * @package local_sentientia_content_market
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Master switch ─────────────────────────────────────────────
    'sentientia.content_market.enabled' => [
        'default'     => false,
        'description' => 'Master switch for the third-party Curated Content Marketplace
                          (P1.1 — Invince gap closure). When OFF (default), the
                          marketplace browse page returns 403, the sync scheduled
                          task is skipped, and no provider adapters initialise.
                          Flip ON per tenant once provider credentials are configured
                          in the admin settings page.',
    ],

    // ─── Per-provider sub-flags ─────────────────────────────────────
    // Each provider requires its own flag so admins can enable Go1 without
    // also enabling Skillsoft, for example.

    'sentientia.content_market.go1.enabled' => [
        'default'     => false,
        'description' => 'Enable the Go1 content provider adapter. Requires
                          sentientia.content_market.enabled = ON.
                          Go1 API key must be set in plugin admin settings.',
    ],

    'sentientia.content_market.udemy_business.enabled' => [
        'default'     => false,
        'description' => 'Enable the Udemy Business content provider adapter.
                          Requires sentientia.content_market.enabled = ON.
                          Udemy Business account ID + API key must be configured.',
    ],

    'sentientia.content_market.coursera.enabled' => [
        'default'     => false,
        'description' => 'Enable the Coursera for Business content provider adapter.
                          Requires sentientia.content_market.enabled = ON.
                          Coursera API credentials must be set in plugin admin settings.',
    ],

    'sentientia.content_market.skillsoft.enabled' => [
        'default'     => false,
        'description' => 'Enable the Skillsoft Percipio content provider adapter.
                          Requires sentientia.content_market.enabled = ON.
                          Skillsoft subdomain + OAuth client credentials required.',
    ],

    // ─── Skills mapping sub-flag ────────────────────────────────────
    'sentientia.content_market.skills_mapping.enabled' => [
        'default'     => false,
        'description' => 'Auto-map imported catalog items to the Sentientia skills
                          taxonomy (provided by local_sentientia_skillsai).
                          When OFF, imported items are stored without skill tags.
                          When ON and local_sentientia_skillsai is installed, the
                          sync task attempts provider-supplied skill names →
                          taxonomy matching after each import batch.
                          Degrades gracefully when local_sentientia_skillsai is absent.',
    ],

];
