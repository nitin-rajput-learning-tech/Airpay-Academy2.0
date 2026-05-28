<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia LMS Microsoft 365 Knowledge Automation.
 *
 * Workstream C (Tier 3) — bridges the LMS to a customer's Microsoft 365
 * tenant so SharePoint documents, Teams meeting summaries, and Outlook
 * calendar entries become first-class LMS content. The eventual
 * customer-facing surface is "Pull this SharePoint folder of SOPs into
 * the SENTIENTIA pipeline once a week" or "When my manager schedules
 * an enablement session in Outlook, auto-create the LMS classroom."
 *
 * Phase C.1 (this version) ships ONLY the OAuth scaffold + Graph API
 * client stub. Every public method on graph_client throws
 * \moodle_exception('confirm_required') so the higher-level features
 * cannot accidentally hit graph.microsoft.com without a deliberate
 * unlock. The msal_client persistence layer encrypts every token with
 * \core\encryption so a leaked DB dump cannot be replayed against
 * Microsoft.
 *
 * Roadmap
 *  - C.1  Scaffold + OAuth scaffolding + Graph stubs + privacy (THIS)
 *  - C.2  Replace confirm_required with real Graph calls, gated per
 *         per-customer flag + per-call [CONFIRM] (same pattern as
 *         sentientia_aiquiz). Adds get_me/list_sites/calendar working.
 *  - C.3  SharePoint document ingestion → SENTIENTIA SOP parser hand-off
 *  - C.4  Outlook meeting → LMS classroom event sync
 *  - C.5  Teams attendance ingestion → completion record
 *  - C.6  Per-customer prompt + scope overrides + Hindi consent UI
 *
 * Default state: feature flag sentientia_m365_enabled OFF for every
 * customer. Capability local/sentientia_m365:use default false on every
 * archetype. No live API calls are reachable from this version.
 *
 * @package    local_sentientia_m365
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_m365';
// 2026-05-24 C.1 — OAuth scaffold + Graph stubs + privacy + Hindi pack.
// 2026-05-28 C15 — OAuth admin landing dashboard (Bucket C of the
// Stabilization Audit). New: admin/index.php + admin_externalpage
// registration + ~25 lang strings (en) surfacing config status,
// feature-flag state, connected-token count, and C.1–C.6 roadmap.
$plugin->version   = 2026052801;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;     // Scaffold — no live calls
$plugin->release   = '0.2.0-alpha';
$plugin->dependencies = [
    'local_airpay_core' => 2026051401,   // feature_flags resolver + customer scope
];

// Release history
// 0.1.0-alpha  Phase C.1: OAuth + Graph scaffolding. Feature flag
//              sentientia_m365_enabled default OFF; graph_client
//              methods throw confirm_required. No HTTP to Microsoft.
