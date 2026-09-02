<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Sentientia Public API + LTI.
 *
 * Gap P2.3 (2026-06-16) — versioned public REST API surface (/v1/) plus
 * LTI 1.3 provider/consumer scaffolding so Sentientia can launch external
 * tools and be launched as a tool. Built on Moodle's external_api / web
 * service framework with token auth, per-tenant scoping, rate-limit
 * awareness, and an OpenAPI spec.
 *
 * Everything ships behind two feature flags, both default OFF:
 *   - sentientia.api.enabled       (the v1 REST surface)
 *   - sentientia.api.lti.enabled   (the LTI 1.3 provider/consumer)
 *
 * @package    local_sentientia_api
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_api';
$plugin->version   = 2026082800;  // YYYYMMDDNN — ADR-030 Wave A: outbound webhooks
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
$plugin->dependencies = [
    'local_sentientia_platform' => ANY_VERSION,  // feature_flags + tenant helpers
];
