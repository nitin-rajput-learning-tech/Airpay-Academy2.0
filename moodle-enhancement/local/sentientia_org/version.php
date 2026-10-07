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
 * Plugin version — Airpay Organization Engine.
 *
 * Replaces BizLMS local_costcenter with Airpay-owned org hierarchy,
 * tenant management, accesslib, and branding.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_org';
// P1 #54 (2026-05-20) — Hindi pack: 55 strings covering capabilities,
// settings, CRUD form, hierarchy, branding, errors, confirmations.
// ADR-031 (2026-09-25) — the org tree, list_children, admin.php and every org
// write are bounded to the caller's tenant (fail closed on no tenant), and
// edit_org refuses a parent outside the caller's scope instead of silently
// creating a new top-level tenant.
// ADR-032 (2026-09-30) - the org importer (db/bizlms_import.php, classes/bizlms/): local_costcenter ->
// local_sentientia_org with the BizLMS ids kept, logos copied, no schema change. The BizLMS capability
// fallbacks are removed from accesslib in the same release (ADR-032 gate 3), so the importer class refuses
// to run below this version (importer::REQUIRES_VERSION).
// 2026093001: ADR-032 org importer + BizLMS capability fallbacks removed.
// 2026100701: ADR-032 owner decision IDN-04 - the org and cohort_scope importers implement the platform's copies_files
// marker (their logo and description copies are a declared side effect, counted in the run report), and
// org_source::root_is_registered() delegates to tenant_resolver (F-11). No schema change. The marker interface ships
// with local_sentientia_platform 2026100701, which this plugin therefore requires.
$plugin->version   = 2026100701;  // ADR-032 IDN-04: copies_files marker (on top of 2026093002 cohort_scope importer)
// 2026092500: ADR-031 tenant-bounded org tree + parent pick.
// 2026092200: descendants-only access filter is /-bounded.
// 2026052002:
$plugin->requires  = 2022041900; // Moodle 4.0+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0'; // ADR-032 org importer + accesslib fallbacks removed. 1.4.4: ADR-031 tenant scope. 1.4.3: +2026-09-08 cascade_* strings (org cascade filter i18n, en+hi) — this ME tree is what UAT runs
$plugin->dependencies = [
    'local_sentientia_platform' => 2026100701, // the copies_files marker the two importers implement (IDN-04)
];
$plugin->release   = '1.6.1'; // ADR-032 IDN-04 copies_files marker. 1.6.0: ADR-032 cohort_scope importer + cohort scope table + real privacy provider. 1.5.0: ADR-032 org importer + accesslib fallbacks removed. 1.4.4: ADR-031 tenant scope. 1.4.3: +2026-09-08 cascade_* strings (org cascade filter i18n, en+hi) — this ME tree is what UAT runs
