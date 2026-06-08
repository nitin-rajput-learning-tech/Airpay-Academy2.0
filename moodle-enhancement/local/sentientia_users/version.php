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
$plugin->version   = 2026052900;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.7.1';  // signup UX fixes (honeypot + success page)
$plugin->dependencies = [
    'local_sentientia_org' => 2026051501,
];
