<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia LMS AI Content Translation.
 *
 * Stream G (Tier 1 #5b / Tier 1 #5 Hindi pipeline cousin) — Admins paste
 * English course content (descriptions, page text, narration excerpts)
 * and Anthropic Claude generates a translation into a target Indian or
 * regional language (Hindi, Marathi, Kannada, Swahili), with brand-name
 * preservation (e.g. "Airpay" kept verbatim or rendered in the target
 * script per a per-customer override) and native-script output
 * (Devanagari / Kannada / Latin).
 *
 * Like its sibling `local_sentientia_aiquiz`, this plugin ships disabled.
 * Even when the master flag is ON, every Anthropic POST is gated behind
 * a per-call [CONFIRM] gate. The admin sees a side-by-side diff of source
 * vs translation before choosing to save. The deterministic mock client
 * lets the MVP be demoed end-to-end without spending money.
 *
 * Roadmap
 *  - T.0  MVP — scaffold + DB schema + feature flag + translate UI + diff
 *  - T.1  Bulk course-content translation (walk a course, translate all)
 *  - T.2  ElevenLabs voice re-pack (Hindi narration -> SCORM re-pack)
 *  - T.3  Cost analytics dashboard + per-customer token quota
 *  - T.4  Translation memory (reuse prior translations for repeated strings)
 *
 * @package    local_sentientia_translate
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_translate';
// 2026-05-25 T.0 — MVP scaffold. Schema + feature flag + Anthropic
// client (curl-based) + prompt builder + translate engine + brand-name
// manager + mock pipeline + diff UI + Hindi pack + ADR-016.
// 2026-05-28 C16 — Admin queue/landing dashboard (Bucket C of the
// Stabilization Audit). New: admin/index.php + admin_externalpage
// registration + ~30 lang strings (en).
$plugin->version   = 2026052801;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;     // MVP — needs prod sign-off before flag flips
$plugin->release   = '0.2.0-alpha';
$plugin->dependencies = [
    'local_sentientia_platform' => 2026051401,   // feature_flags resolver + customer scope
];

// Release history
// 0.1.0-alpha  Phase T.0: MVP scaffold. Feature flag default OFF.
//              No live API calls without explicit per-call [CONFIRM].
