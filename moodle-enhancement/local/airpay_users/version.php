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
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_users';
// W1-6 (2026-05-16) — HRMS 24-column Darwinbox/SAP CSV bulk import.
// First db/install.xml in this plugin (2 audit tables: sync_runs + sync_errors).
$plugin->version   = 2026051600;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.0.0';  // W1-6: HRMS 24-col importer + two-pass manager link
$plugin->dependencies = [
    'local_airpay_org' => 2026051501,
];
