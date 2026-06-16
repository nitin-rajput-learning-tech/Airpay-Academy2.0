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
$plugin->version   = 2026061600;  // YYYYMMDDNN
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.0.0';
