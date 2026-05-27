<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Airpay Core (shared infrastructure).
 *
 * Tenant-scoping helpers, cross-plugin shared traits.
 * Created Phase 8.1 (2026-05-12) to systematize the tenant-equality
 * check that 10/11 blocking security findings traced back to.
 *
 * @package    local_airpay_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_core';
// P1 #24 (2026-05-16) — extend audit_log::SENSITIVE_EVENTS to cover
// course_updated + course section/category CRUD. Closes audit item #13
// from parity-audit-2026-05-15/airpay_courses.md ("local_logs parity").
// P1 #51 (2026-05-20) — Hindi pack: 20 strings (tenant errors, scheduled
// tasks, cache definitions, Switchboard, Style Guide, flag categories).
// Session 2 / ADR-002 (2026-05-20) — customer-level feature flag scope.
// Adds customer_id column to local_airpay_feature_flags + audit table.
// New 5-level resolver (customer+tenant > customer > legacy tenant >
// global > registered default). Gated by sentientia.customer_level_flags.enabled
// (default OFF). New classes/customer.php helper + Switchboard customer tabs.
//
// P0 borrow #9 (Moodle 5.2, 2026-05-23) — cm_info::get_navigation_url()
// resolver shim. Adds classes/cm_navigation.php + tests/cm_navigation_test.php.
// Pure additive — no schema change, no behaviour change for callers that
// keep using $cm->url. Wired into theme_airpayux course_view trait so a
// module that registers mod_xxx_get_navigation_url($cm) can override the
// launch URL. 5.2 migration: search-and-replace + delete cm_navigation.php.
//
// P0 borrow #10 (Moodle 5.2, 2026-05-23) — suspended-user status badge.
// Adds classes/user_status.php — request-cached lookup of suspended/deleted
// state. Lang strings userstatus_* in en + hi (4 each = 8 total). Consumed
// by theme_airpayux/before_standard_top_of_body_html (server-rendered JSON)
// + amd/user_status_badge.js (DOM decorator). PHPUnit covers cache, batch
// lookup, defensive zero-id input, and badge HTML escaping.
//
// P0 borrow #11 (Moodle 5.2, 2026-05-23) — backup-filename template helper.
// Adds classes/backup_filename.php — token-substitution helper for the
// SENTIENTIA SCORM pipeline and future Sentientia LMS export jobs. Admin
// setting at Site Admin → Plugins → Local plugins → Airpay Core. Default
// template matches Moodle's built-in behaviour so the change is opt-in.
// Lang strings settings_pagetitle + setting_backup_filename_* in en + hi
// (4 each = 8 total). PHPUnit covers token substitution, sanitisation,
// path traversal blocking, max length, and the fallback-on-empty contract.
//
// G.1 (2026-05-25) — per-customer scoped config registry. Adds
// customer::get_customer_config() + set_customer_config() — a stable
// get/set surface for plugins that need configuration varying per
// Sentientia LMS customer (first consumer: local_sentientia_aiquiz's
// AI-quiz prompt-template override). Pure additive — no schema change,
// stored under config_plugins (local_airpay_core/customer_<id>_<key>).
// PHPUnit in tests/customer_config_test.php (12 methods).
$plugin->version   = 2026052500;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.0';  // +G.1 per-customer config registry (get/set_customer_config)
// Release history
// 1.1.0  cron-health publisher + audit_log + structured_logger
// 1.2.0  Phase A0 — feature flags + Switchboard infrastructure.
//          + local_airpay_feature_flags table (overrides)
//          + local_airpay_feature_flag_audit table (history)
//          + \local_airpay_core\feature_flags resolver class
//          + Switchboard admin page at /local/airpay_core/admin/switchboard.php
//          + first 5 flags wired (assistant, gamification x2, share, request)
// 1.2.1  Phase A0.5 — Style Guide + design-token expansion.
//          + admin/styleguide.php — visual reference of every design token
//          + theme/airpayux/scss/moodle/_tokens.scss extended with:
//              motion (durations + easings from manifesto §5),
//              breakpoint SCSS vars from manifesto §3,
//              touch-target + control-height tokens from manifesto §8/§9,
//              focus-ring tokens (WCAG 2.4.11),
//              prefers-reduced-motion auto-collapse
