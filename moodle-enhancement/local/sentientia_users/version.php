<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version — Airpay User Engine.
 *
 * Replaces BizLMS local_users with Airpay-owned user management,
 * profile rendering, and open_* field ownership.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_users';
// W1-6 + W1-8 + P1 #1/3/5/7/16 (2026-05-16) — HRMS importer +
// Public-tenant self-registration + chip filters + tenant-scoped
// supervisor picker + DOB/DOJ on edit-user form +
// tenant-scoped welcome email with token replacement +
// cron-driven daily HRMS sync.
// P1 #47 (2026-05-20) — Hindi top-up: 128 strings covering capabilities,
// CRUD forms, errors, HRMS bulk import, welcome email, and HRMS sync cron.
// P1 #59 (2026-05-20) — defense-in-depth reCAPTCHA v2 on the public
// signup form. Admin opts in via $CFG->recaptchapublickey/privatekey;
// when unset (dev), only the honeypot guards remain.
// 2026-05-29 — signup UX fixes: (1) hide the honeypot field (wrapper is an
// ID #fitem_id_honeypot_url, not a class — the stray empty field is gone);
// (2) success page no longer double-renders the confirmation message and
// drops the dismissible-alert close glyph.
// 2026-08-29 — user_manager::suspend() ends sessions via
// core\session\manager::destroy_user_sessions() (kill_user_sessions was
// deprecated in 4.5 and surfaced as a notice once the SCIM endpoint —
// ADR-030 Wave B — became a regular caller).
// 2026-09-03 — H1 fix (UAT-SECURITY-POSTURE-2026-09-03): signup_service
// no longer surfaces an "email already registered" validation error
// (user-enumeration oracle, CWE-203); register() against an existing
// email now silently notifies the existing address and returns its id.
// 2026-09-24 — N1 fix (UAT Wave 2): profile_access::can_view() is the one
// tenant rule for reading another user's profile by id (profile.php,
// skillprofile.php, photo.php, the edit-user dynamic form). Own profile and
// site admin always; otherwise same open_path tenant root; an unresolvable
// viewer or target is refused. Refusal is error_profilenotavailable, the
// same for a missing id as for an out-of-tenant one.
// 2026-09-24 — N1 review follow-up: the edit-user form's supervisor label
// callback applies profile_access too (it printed name + email for any
// posted id); list_users and exportcsv fail closed for an unresolvable
// caller and for a missing org filter; local_sentientia_platform, whose
// tenant class this plugin already calls unconditionally, is declared.
// 2026-09-25 — ADR-031: :create/:edit say WHAT, never WHERE. HRMS import
// refuses a row matching an account outside the caller's tenant (or a site
// admin / cross-tenant account) and fails closed for a tenant-less caller;
// single suspend/delete, the edit form and bulk actions check the target
// (user_manager::require_can_act_on); the edit form offers and accepts only
// the caller's own tenant's orgs; index KPIs, filter chips and HRMS run
// history fail closed; invalidtenant/outoftenant strings added (en + hi).
// 2026-09-25 — welcome_mailer sends as a notification (notification=1).
// With notification=0, message_send() refused the local_sentientia_users
// provider outright (only moodle/instantmessage may send a personal
// message), so the welcome email had never been delivered.
$plugin->version   = 2026092501;  // welcome email actually sends
// 2026092500: ADR-031: target-tenant checks on every write.
// 2026092401: N1 review: supervisor label + list fail-closed.
// 2026092400: Profile reads are tenant-bounded (N1).
// 2026092200: Manage Users counts are tenant-bounded.
// 2026090302: H1, signup no longer reveals whether an email exists.
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.8.1';  // welcome email fix (2.8.0: ADR-031)
$plugin->dependencies = [
    'local_sentientia_org' => 2026051501,
    'local_sentientia_platform' => ANY_VERSION,
];
