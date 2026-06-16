<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_skillsai.
 *
 * Gap P0.1.0 (2026-06-16) — four flags, ALL default OFF per CLAUDE.md §13:
 *
 *  1. sentientia.skillsai.enabled    — master switch. When OFF every page
 *                                       returns 403 and the navigation
 *                                       links are hidden.
 *  2. sentientia.skillsai.live_api   — gates whether extraction ACTUALLY
 *                                       POSTs to api.anthropic.com vs.
 *                                       returning a deterministic mock
 *                                       response. Default OFF. ON only when
 *                                       an API key is configured AND a human
 *                                       flipped this AND the per-call
 *                                       [CONFIRM] gate was ticked. Mock mode
 *                                       is what makes the MVP demoable
 *                                       end-to-end without spending money.
 *  3. sentientia.skillsai.gap_engine — gates the skills-gap engine + its
 *                                       UI + the gap-feed rebuild task.
 *                                       Default OFF until role/skill data
 *                                       quality is verified per tenant.
 *  4. sentientia.skillsai.impact_map — gates the skill -> business-impact
 *                                       mapping surface. Default OFF; the
 *                                       gap engine still runs without it
 *                                       (impact_weight just stays 0).
 *
 * Mirrors the registration pattern used by sentientia_aiquiz /
 * sentientia_recommendations exactly — the resolver in
 * \local_sentientia_platform\feature_flags discovers this file
 * automatically.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Skills Intelligence (AI) ───────────────
    'sentientia.skillsai.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS Skills Intelligence (Gap P0.1).
                          Master switch. When OFF, /local/sentientia_skillsai/
                          pages return 403 and navigation links are hidden.
                          When ON, L&D authors with :extract can paste a
                          transcript/SOP/narration and request AI skill
                          extraction — each extraction still requires the
                          per-call [CONFIRM] gate plus
                          sentientia.skillsai.live_api=ON for a real
                          Anthropic call (otherwise the deterministic mock
                          client runs). No AI candidate becomes canonical
                          taxonomy without passing the human-review gate.',
    ],

    'sentientia.skillsai.live_api' => [
        'default'     => false,
        'description' => 'Anthropic live API gate. When OFF, every extraction
                          uses anthropic_client::call_mock() — deterministic
                          fixed-shape output, zero cost. When ON, the same
                          code path POSTs to api.anthropic.com using the API
                          key from local_sentientia_skillsai | api_key (admin
                          setting). Companion flag sentientia.skillsai.enabled
                          must also be ON. Mock-mode is the default so the MVP
                          can be demoed end-to-end without spending money.',
    ],

    'sentientia.skillsai.gap_engine' => [
        'default'     => false,
        'description' => 'Skills-gap engine. When OFF, the gap UI hides and
                          the gap-feed rebuild task no-ops. When ON, managers
                          with :viewgaps can rebuild + view per-user gap feeds
                          comparing role-required skills
                          (local_sentientia_role_skills) against held skills
                          (local_sentientia_user_skills). Default OFF until
                          per-tenant role/skill data quality is verified.',
    ],

    'sentientia.skillsai.impact_map' => [
        'default'     => false,
        'description' => 'Skill -> business-impact mapping surface. When OFF,
                          the impact UI hides and gap rows carry impact_weight
                          = 0 (the gap engine still works, just unranked by
                          business priority). When ON, managers with
                          :manage_taxonomy can map taxonomy skills to business
                          metrics + weights so the gap feed can be sorted by
                          business priority. Default OFF.',
    ],

];
