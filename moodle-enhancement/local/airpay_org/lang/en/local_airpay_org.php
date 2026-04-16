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
 * Language strings — Airpay Organization Engine.
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Airpay Organization Engine';

// Capabilities.
$string['airpay_org:manage'] = 'Manage organizations';
$string['airpay_org:manage_multiorganizations'] = 'Manage multiple organizations';
$string['airpay_org:manage_ownorganization'] = 'Manage own organization';
$string['airpay_org:manage_owndepartments'] = 'Manage own departments';
$string['airpay_org:view'] = 'View organizations';

// Settings.
$string['settings_heading'] = 'Airpay Organization Settings';
$string['settings_heading_desc'] = 'Configure organization hierarchy and tenant management.';
$string['public_tenant_id'] = 'Public tenant ID';
$string['public_tenant_id_desc'] = 'Costcenter ID for the public (guest-facing) tenant. Default: auto-detect.';

// Errors.
$string['invalidtenant'] = 'Invalid tenant ID';
$string['orgnotfound'] = 'Organization not found';
$string['migrationcomplete'] = 'Data migration from local_costcenter completed successfully.';
$string['migrationskipped'] = 'Migration skipped — local_airpay_org table already has data.';
$string['sourcetablemissing'] = 'Source table local_costcenter does not exist. No data to migrate.';
