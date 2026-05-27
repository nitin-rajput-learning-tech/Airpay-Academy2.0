<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS — Calendar Sync (Outlook / Google / Apple)
 *
 * Tier 2 #6 on the Sentientia LMS roadmap. Phase 1 (this version) ships
 * OUTBOUND sync only: per-user ICS subscription URL that any RFC 5545
 * calendar client can subscribe to. Inbound bi-directional sync (Phase
 * 2) is deferred — see ADR-013 for the no-OAuth reasoning.
 *
 * Roadmap:
 *  - 1.0  Outbound ICS feed: courses + classrooms + exams
 *  - 1.1  Block + dashboard widget for "Subscribe to my calendar" CTA
 *  - 1.2  Per-event-type filters (toggle classrooms-only etc.)
 *  - 2.0  Inbound OAuth sync (Microsoft Graph + Google Calendar API)
 *
 * @package    local_sentientia_calendar
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_calendar';
$plugin->version   = 2026052700;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.0-beta';
$plugin->dependencies = [
    'local_airpay_core' => 2026051401,  // feature_flags resolver
];

// Release history.
// 1.0.0-beta  Tier 2.6 Phase 1: outbound ICS feed, token-authenticated
//             subscription URL. Default OFF behind
//             sentientia.calendar_sync.enabled. Surfaces course
//             completion deadlines, classroom sessions, and quiz close
//             dates for the authenticated user.
// 1.1.0-beta  Tier 2.6 Phase 2 SCAFFOLDING: OAuth 2.0 Authorization
//             Code + PKCE skeleton for Microsoft 365 + Google Calendar.
//             New table {local_sentientia_calendar_oauth} for
//             encrypted-at-rest access + refresh tokens (via
//             \core\encryption / Sodium). New master feature flag
//             sentientia.calendar_sync.oauth.enabled (default OFF).
//             No live HTTP calls in this chip — handle_callback() and
//             refresh_token() throw oauth_not_live until Phase 2.1.
// 1.2.0-beta  Tier 2.6 Phase 2.1 (Wave C4): LIVE OAuth wired. The
//             Authorization Code + PKCE flow now exchanges the code for
//             tokens via the provider token endpoint, refreshes on
//             expiry, and revokes at the provider (Google) + locally.
//             New public endpoints oauth/{connect,callback,disconnect}.php
//             (sesskey + state-CSRF protected). index.php renders
//             per-provider connection status with connect/disconnect
//             buttons. Outbound HTTP is mockable via
//             oauth_base::set_http_handler_for_testing() so CI never
//             hits a live provider. Master flag stays default OFF — live
//             traffic requires (a) flag ON for the customer AND (b) no
//             test mock registered.
