<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia LMS GenAI Authoring Studio.
 *
 * Roadmap gap P0.3 (GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md §6) — the
 * Invince "Craft" competitor. Unifies into ONE authoring studio:
 *
 *   1. prompt / PDF / Doc  → full microlearning course draft
 *   2. editable instructional-design TEMPLATES (CRUD, tenant-scoped)
 *   3. TTS voiceover (productizes the Workstream-B ElevenLabs pipeline,
 *      [CONFIRM]-gated + MOCK by default — no live spend in this build)
 *   4. expanded question types beyond MCQ — multi-response (MRQ) +
 *      match-the-following
 *   5. AI contextual feedback per question
 *   6. interactive cards + mastery scores
 *
 * Localized output is routed through local_sentientia_translate when
 * present (class_exists-guarded; degrades to source language otherwise).
 *
 * The plugin ships DISABLED. AI + TTS run in deterministic MOCK mode by
 * default — the live_api and tts flags are default OFF, so the studio is
 * end-to-end demonstrable with zero API spend and no credentials in code.
 * Every generated course/question/voiceover passes through a mandatory
 * human-review gate before anything is treated as publishable.
 *
 * Reuses the aiquiz generation+review pattern (mock-mode + [CONFIRM] gate
 * + per-draft prompt_version audit), the translate localization plugin,
 * and the platform feature_flags / tenant / customer resolvers.
 *
 * @package    local_sentientia_authoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_authoring';
// 2026-06-16 P0.3.0 — MVP scaffold. Five tables (template, draft, card,
// question, voiceover) + three feature flags (enabled / tts / live_api,
// all default OFF) + course_generator (mock+live dispatcher) + prompt
// builder + response parser + draft_manager + template_manager +
// question_type (MRQ/match validation) + tts_client (mock+live) +
// localizer (translate routing) + studio/template/review/voiceover UI +
// Hindi pack at 100% parity + PHPUnit suite (security, tenant isolation,
// generation pipeline, question-type validation, template CRUD).
// 2026-06-17 — db/upgrade.php gains two role steps: (2026061700) back-fills
// the author/SME caps onto existing teacher-archetype roles (the airpay
// `trainer` role) so SMEs reach the GenAI Authoring Studio + Skills
// Intelligence, and (2026061701) ships a dedicated, scoped "Sentientia Author"
// system-context role (shortname `sentientiaauthor`) carrying ONLY those five
// author/SME caps — the clean production way to provision SME content authors
// without granting the broad teacher/manager archetype.
$plugin->version   = 2026061701;
$plugin->requires  = 2022041900;          // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;      // MVP — prod sign-off before any flag flips
$plugin->release   = '0.1.0-alpha';
$plugin->dependencies = [
    'local_sentientia_platform' => 2026051401,   // feature_flags resolver + tenant + customer scope
];

// Release history
// 0.1.0-alpha  P0.3.0: MVP scaffold. All three flags default OFF.
//              AI + TTS deterministic mock-mode. No live API spend.
//              Mandatory human-review gate before publish.
