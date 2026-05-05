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

// CRUD strings.
$string['addorg']    = 'Add Organisation';
$string['editorg']   = 'Edit Organisation';
$string['deleteorg'] = 'Delete Organisation';
$string['hideorg']   = 'Hide Organisation';
$string['showorg']   = 'Show Organisation';

// Form section headings.
$string['heading_basic']      = 'Identity';
$string['heading_hierarchy']  = 'Hierarchy';
$string['heading_branding']   = 'Branding (optional)';
$string['heading_visibility'] = 'Visibility';

// Form labels.
$string['org_fullname']      = 'Full name';
$string['org_shortname']     = 'Short name';
$string['shortname_help']    = 'A unique short identifier for this org. Used in path-based filtering and URL slugs. e.g. "AirPay_Acquiring".';
$string['description']       = 'Description';
$string['parent_org']        = 'Parent organisation';
$string['parent_org_help']   = 'Pick where this org sits in the hierarchy. Choose "Top-level tenant" to create a new tenant. Hierarchy is computed automatically — depth and path inherit from the parent.';
$string['top_level_tenant']  = '— Top-level tenant —';
$string['brand_color']       = 'Brand colour (hex)';
$string['button_color']      = 'Button colour (hex)';
$string['hover_color']       = 'Hover colour (hex)';
$string['theme_scheme']      = 'Theme scheme';
$string['branding_help']     = 'Per-tenant overrides for the airpayux theme. Leave blank to use site defaults. Hex format e.g. #0066A7.';
$string['visible']           = 'Visibility';
$string['visible_yes']       = 'Active (visible to users)';
$string['visible_no']        = 'Hidden (admin-only)';
$string['sortorder']         = 'Sort order';

// Errors specific to CRUD.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['name_required']         = 'Organisation name is required.';
$string['invalid_color']         = 'Use a valid hex colour, e.g. #0066A7.';
$string['invalidparent']         = 'Selected parent organisation does not exist.';
$string['cannotdeletetenant']    = 'Top-level tenants cannot be deleted. Hide them instead to keep historical data intact.';
$string['orghaschildren']        = 'Cannot delete: this organisation still has sub-orgs. Delete or move them first.';
$string['orghasusers']           = 'Cannot delete: this organisation still has users assigned. Reassign them first.';

// Confirmation dialogs.
$string['confirmdelete'] = 'Delete "{$a}"? This will permanently remove the organisation. The action is blocked if it has sub-orgs or assigned users.';
$string['confirmhide']   = 'Hide "{$a}"? Existing users keep their assignment, but the org will not appear in pickers and filters.';
$string['confirmshow']   = 'Show "{$a}"? It will reappear in pickers and filters.';

// Toast messages.
$string['org_created']           = 'Organisation created.';
$string['org_updated']           = 'Organisation updated.';
$string['orgdeleted']            = 'Organisation deleted.';
$string['orgvisibilitychanged']  = 'Organisation visibility updated.';
