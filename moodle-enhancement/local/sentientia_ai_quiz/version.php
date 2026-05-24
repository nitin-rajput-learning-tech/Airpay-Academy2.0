<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia LMS AI Quiz (Stream G Phase G.1 scaffold).
 *
 * Stream G (Tier 1 #4) — Hindi quiz generation with per-customer
 * Anthropic prompt templates. Customer-zero (Airpay) gets the default
 * prompt; future customers can override via local_airpay_core
 * customer-config hooks.
 *
 * This chip is SCAFFOLD ONLY. No live POST to api.anthropic.com is
 * wired up — anthropic_client::generate_quiz() throws
 * `\moodle_exception('confirm_required')` until the live wiring lands
 * in a future chip with a fresh per-call [CONFIRM] gate.
 *
 * Sibling plugin local_sentientia_aiquiz already ships the G.0 MVP
 * (English, single-tenant, draft + review workflow). This Phase G.1
 * scaffold is intentionally isolated so the Hindi + per-customer-prompt
 * work can land additively without touching G.0's production-bound
 * surfaces.
 *
 * @package    local_sentientia_ai_quiz
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_ai_quiz';
// 2026-05-24 G.1 scaffold — DB log table + feature flag + capability +
// privacy provider + settings + throw-only anthropic_client. No live
// network calls. No live UI. PHPUnit tests cover feature-flag
// registration and privacy metadata only.
$plugin->version   = 2026052410;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0-alpha';
$plugin->dependencies = [
    'local_airpay_core' => 2026051401,
];
