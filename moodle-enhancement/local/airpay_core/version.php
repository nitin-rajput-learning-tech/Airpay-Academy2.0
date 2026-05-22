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
$plugin->version   = 2026052201;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0';  // ADR-008 — customer_brand DB table + cached resolver
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
