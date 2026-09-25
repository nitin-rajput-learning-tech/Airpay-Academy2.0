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
$plugin->version   = 2026092501;  // ADR-031 fix-forward (review S1-S4): scoped SCIM clients never touch cross-tenant principals; v1 scoping via is_cross_tenant(); create_enrolment target/role checks; LTI unique-registration match. db/access.php comment-only (no upgrade step).
// 2026092500 - ADR-031: :webhooks_manage/:scim_manage lose the manager default (revoked) + pages tenant-scoped via admin_scope
// 2026090200 - ADR-030 Wave C: SCIM Groups + attestation (B = 2026082900, A = 2026082800)
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.2';
$plugin->dependencies = [
    'local_sentientia_platform' => ANY_VERSION,  // feature_flags + tenant helpers
];
