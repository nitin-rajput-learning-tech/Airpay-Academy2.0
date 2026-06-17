<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia LMS Skills Intelligence (AI).
 *
 * Gap P0.1 (Skills Intelligence) — the highest-leverage competitive gap
 * from GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md §6. Four capabilities:
 *
 *   (a) EXTRACT — Anthropic Claude reads course/SCORM transcripts, SOP
 *       excerpts and narration text and proposes candidate skills.
 *   (b) TAXONOMY — a per-tenant skills taxonomy assembled from approved
 *       candidates, behind a MANDATORY human review (approve / edit /
 *       reject) gate. No AI output becomes canonical without a reviewer.
 *   (c) GAP ENGINE — compares role-required skills (sentientia_skills'
 *       local_sentientia_role_skills) against learner-held skills
 *       (local_sentientia_user_skills) and emits a per-user gap feed.
 *   (d) IMPACT — a skills -> business-impact mapping surface so L&D can
 *       tie a skill (and its gap) to a measurable business outcome.
 *
 * The plugin ships disabled. Default call mode is MOCK — a deterministic
 * fake extraction that costs nothing and needs no API key, so the MVP
 * demos end-to-end without spending money. A separate live_api flag
 * (also default OFF) gates real api.anthropic.com calls; even then every
 * extraction passes the per-call [CONFIRM] gate at the UI layer.
 *
 * Roadmap
 *  - P0.1.0  MVP — scaffold + schema + flags + extract (mock) + review
 *            gate + taxonomy CRUD + gap engine + impact mapping + tests.
 *  - P0.1.1  Live API + per-customer extraction prompt overrides.
 *  - P0.1.2  Auto-extract cron over newly-published SCORM packages.
 *  - P0.1.3  Gap-feed -> recommendations bridge (sentientia_recommendations
 *            consumes the gap feed to rank remedial courses).
 *
 * @package    local_sentientia_skillsai
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_skillsai';
// 2026-06-16 P0.1.0 — MVP scaffold. Schema (extraction job + candidate +
// taxonomy + impact + gap-feed) + 4 feature flags (all default OFF) +
// Anthropic client (curl) with mock-mode default + prompt builder +
// response parser + taxonomy_manager (human-review gate) + gap_engine +
// impact_manager + extract/review/gap/impact UIs + EN/HI lang packs +
// PHPUnit (security, tenant isolation, taxonomy CRUD, gap engine, mock AI).
$plugin->version   = 2026061700;
$plugin->requires  = 2022041900;        // Moodle 4.5+ (matches sibling plugins).
$plugin->maturity  = MATURITY_ALPHA;    // MVP — needs prod sign-off before flag flips.
$plugin->release   = '0.1.0-alpha';
$plugin->dependencies = [
    // Tenant helper + 5-level feature_flags resolver + per-customer config.
    'local_sentientia_platform' => 2026051401,
    // Skill schema (local_sentientia_skills + role_skills + user_skills) that
    // the gap engine reads and the taxonomy promotes candidates into.
    'local_sentientia_skills'   => 2026041000,
];

// Release history
// 0.1.0-alpha  Gap P0.1: MVP. Feature flags default OFF. No live API calls
//              without explicit per-call [CONFIRM] + live_api flag (OFF).
//              Human-review gate before any AI candidate becomes canonical
//              taxonomy. Mock-mode demoable end-to-end with zero spend.
