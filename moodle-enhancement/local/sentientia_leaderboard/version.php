<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia LMS Real-time leaderboards.
 *
 * Tier 2 #7. Builds on the SSE infrastructure proved out in
 * `local_sentientia_live` (ADR-004) — see ADR-014 for the decision to reuse
 * the pattern via a dedicated events table + stream endpoint rather than
 * coupling to the live plugin's tables.
 *
 * Three board types ship in Phase L.0:
 *   - quiz       — top scorers on a single quiz (mod_quiz attempts)
 *   - completion — fastest learners to N% completion on a course
 *   - skill      — most skill points earned in a date range
 *
 * Every type is independently feature-flagged so customers can enable them
 * incrementally. The master flag `sentientia.leaderboards.enabled` gates
 * the entire plugin (default OFF — additive shipping, per CLAUDE.md).
 *
 * @package    local_sentientia_leaderboard
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_leaderboard';
$plugin->version   = 2026052500;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.2.0-alpha';
$plugin->dependencies = [
    'local_airpay_core' => 2026051401,
];

// Release history
// 0.1.0-alpha  Phase L.0 (Tier 2 #7): MVP — 3 board types, opt-out, SSE,
//              block widget, WS API, PHPUnit. Default OFF behind
//              sentientia.leaderboards.enabled.
// 0.2.0-alpha  Phase L.1: rank-change Moodle messaging. New
//              `rankings_updated` event + observer + message_helper +
//              `local_sentientia_lb_notify_log` throttle table. Default
//              OFF behind sentientia.leaderboards.notifications.enabled.
