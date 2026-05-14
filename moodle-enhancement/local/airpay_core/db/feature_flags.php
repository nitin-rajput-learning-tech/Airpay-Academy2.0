<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_airpay_core.
 *
 * Phase A0 (2026-05-14) — seeds the first batch of platform-wide
 * flags. Per the configurability architecture (§3.1), every plugin
 * that introduces a flag adds a similar file under its own
 * db/feature_flags.php. The local_airpay_core\feature_flags resolver
 * walks every plugin and merges them all.
 *
 * The full target inventory (60+ flags by end of 2026) is enumerated
 * in docs/platform-review-2026-05-14/CONFIGURABILITY-ARCHITECTURE.md
 * §6. This file declares the FIVE flags shipped in Phase A0 plus
 * placeholders for flags whose owning plugin's db/feature_flags.php
 * hasn't been added yet — keeping the registry self-documenting.
 *
 * @package local_airpay_core
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── AI category ────────────────────────────────────────────────
    'ai.assistant.enabled' => [
        'default'     => true,
        'description' => 'Master switch for the AI chat assistant drawer. When off,
                          the floating assistant button is hidden everywhere and
                          \\local_airpay_assistant\\ai_client::send_message() returns
                          a polite "temporarily unavailable" response.',
    ],
    'ai.sentientia.enabled' => [
        'default'     => false,
        'description' => 'SOP→SCORM authoring pipeline (Phase B1). When off, the
                          SENTIENTIA Studio UI is hidden and the CLI commands
                          short-circuit. Default off until Phase B1 ships.',
    ],
    'ai.recommendations.enabled' => [
        'default'     => false,
        'description' => 'Personalised course recommendations on catalogue + dashboard.
                          When off, the "For You" feed is replaced by "Trending this week".
                          Default off — feature in Phase Β.',
    ],

    // ─── Engagement category ───────────────────────────────────────
    'engagement.gamification.enabled' => [
        'default'     => true,
        'description' => 'Master switch for points, badges, streaks. When off, the
                          gamification dashboard widget hides itself, observers stop
                          awarding points on course completion, and the leaderboard
                          link is removed from navigation.',
    ],
    'engagement.gamification.confetti' => [
        'default'     => true,
        'description' => 'Celebration confetti animation when a learner completes a
                          course. Some tenants (compliance-heavy environments)
                          prefer a quieter experience. Hidden on the first 3
                          completions regardless to avoid spam.',
    ],
    'engagement.whatsapp.enabled' => [
        'default'     => false,
        'description' => 'WhatsApp Business API notification channel (Phase Α1).
                          When off, notification_sender skips WhatsApp dispatch
                          and falls back to the next channel in the chain
                          (typically email). Default off until Α1 ships.',
    ],
    'engagement.sms.enabled' => [
        'default'     => false,
        'description' => 'SMS fallback notification channel via Twilio (Phase Α1).
                          When off, SMS dispatch is skipped. Default off until
                          Twilio integration ships.',
    ],

    // ─── Commerce category ─────────────────────────────────────────
    'commerce.crossTenantShare.enabled' => [
        'default'     => true,
        'description' => 'Sprint C push-share UI. When off, the "Share" button on
                          the course management table is hidden and the share_course
                          web service returns "feature disabled". Active shares are
                          preserved.',
    ],
    'commerce.crossTenantRequest.enabled' => [
        'default'     => true,
        'description' => 'Sprint D pull/request workflow. When off, the
                          "Browse Airpay Library" sidebar link is hidden, the
                          /browse_airpay.php page returns 403, and pending
                          requests stay in their current state.',
    ],
    'commerce.publicMarketplace.enabled' => [
        'default'     => false,
        'description' => 'Open marketplace at /local/airpay_marketplace/public.php
                          accessible without tenant authentication (Phase F).
                          Default off until the marketplace ships.',
    ],

    // ─── Identity category ─────────────────────────────────────────
    'identity.sso.enabled' => [
        'default'     => false,
        'description' => 'Master switch for SSO providers (Okta + Azure AD + Google).
                          When off, only username/password login is available.
                          Default off until Phase D1 SSO ships.',
    ],

    // ─── UX category ───────────────────────────────────────────────
    'ux.commandPalette.enabled' => [
        'default'     => true,
        'description' => 'Cmd+K command palette for power-user navigation.
                          Hidden when off; users still navigate via the sidebar.',
    ],
    'ux.darkMode.enabled' => [
        'default'     => true,
        'description' => 'Dark mode toggle availability. When off, the
                          appearance toggle in user-menu is hidden and every
                          user sees the light theme.',
    ],

];
