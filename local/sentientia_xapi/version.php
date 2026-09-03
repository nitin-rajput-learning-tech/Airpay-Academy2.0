<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia xAPI / cmi5 + LRS.
 *
 * P1.4 from GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md §6:
 * Provides standards-grade xAPI (Tin Can) statement tracking and a
 * lightweight Learning Record Store (LRS) endpoint. Unlocks RFPs that
 * mandate xAPI / cmi5 compliance.
 *
 * Ships a REST-style LRS endpoint, xAPI statement validation (actor /
 * verb / object / result / context), tenant-scoped statement storage,
 * cmi5-aware session tracking, and Moodle event observers that
 * auto-emit xAPI statements for: course completion, quiz attempt
 * submission, course module viewed, and user login.
 *
 * All functionality is gated behind feature flag
 * `sentientia.xapi.enabled` (DEFAULT OFF). Flipping it ON has no
 * effect on Airpay Academy's existing production behaviour.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_xapi';
// 2026-09-03 — H3 fix (UAT-SECURITY-POSTURE-2026-09-03): /lrs/statements
// had no rate limiting; added a per-client fixed-window limiter
// (classes/lrs/rate_limiter.php, new local_sentientia_xapi_lrs_rate
// table) mirroring the SCIM endpoint's client::rate_check() technique,
// returning 429 + Retry-After before any statement is parsed or stored.
$plugin->version   = 2026090302;  // YYYYMMDDNN — 'stored' column renamed 'timestored' (MySQL 8 reserved word; UAT Stage A finding); then H3 rate limiter
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.0.2';
