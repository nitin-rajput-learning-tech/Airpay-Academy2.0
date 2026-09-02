<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_api.
 *
 * Both flags DEFAULT OFF per CLAUDE.md §13 — the public API surface and
 * LTI integration are additive and must not change Airpay's current
 * production behaviour until an admin flips them on (per customer + tenant
 * via the Switchboard).
 *
 * Resolution is performed by \local_sentientia_platform\feature_flags.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── API category ───────────────────────────────────────────────
    'sentientia.api.enabled' => [
        'default'     => false,
        'description' => 'Master switch for the versioned public REST API (/v1/).
                          When OFF, every local_sentientia_api_v1_* external function
                          returns an "api_disabled" exception and the OpenAPI
                          discovery endpoint 404s. Default OFF so existing
                          Airpay production exposes only its internal web
                          services until the public API is opened deliberately.',
    ],
    'sentientia.api.lti.enabled' => [
        'default'     => false,
        'description' => 'Master switch for the LTI 1.3 provider + consumer.
                          When OFF, the launch/login/JWKS endpoints 404 and no
                          external tool can be launched into or out of Sentientia.
                          Default OFF until LTI registrations are configured and
                          signature verification is smoke-tested.',
    ],
    'sentientia.api.write.enabled' => [
        'default'     => false,
        'description' => 'Sub-switch for WRITE endpoints of the public API
                          (e.g. v1 enrol create). Requires sentientia.api.enabled
                          as well. Kept separate so a customer can expose a
                          read-only public API without ever opening writes.
                          Default OFF — write endpoints are the highest-risk
                          surface and follow the CLAUDE.md [CONFIRM] discipline.',
    ],
    'sentientia.api.webhooks.enabled' => [
        'default'     => false,
        'description' => 'Sub-switch for OUTBOUND webhooks (ADR-030 Wave A): course
                          completion, enrolment and certificate events are queued and
                          POSTed to customer-registered https endpoints with an
                          HMAC-SHA256 signature and exponential-backoff retry.
                          Requires sentientia.api.enabled as well. When OFF the
                          observers are complete no-ops (nothing is queued). Default
                          OFF — outbound calls to customer infrastructure must be an
                          explicit per-customer decision.',
    ],

];
