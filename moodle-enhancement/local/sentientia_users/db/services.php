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
 * Web service definitions for local_sentientia_users.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_users_list_users' => [
        'classname'   => 'local_sentientia_users\external\list_users',
        'description' => 'List users with server-side search, sort, and pagination',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_users:view',
    ],
    // P1 batch (2026-05-16) — distinct values for chip-filter dropdowns.
    'local_sentientia_users_list_filter_options' => [
        'classname'   => 'local_sentientia_users\external\list_filter_options',
        'description' => 'Return distinct designation/location/hrmsrole/employmenttype/region/grade values for filter dropdowns',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_users:view',
    ],
    // P1 batch (2026-05-16) — tenant-scoped supervisor autocomplete WS.
    // Replaces core_user/form_user_selector on the edit-user form which is
    // NOT tenant-aware — a Public-tenant admin could otherwise pick an
    // Airpay-tenant manager and silently break the org chart.
    'local_sentientia_users_search_supervisors' => [
        'classname'   => 'local_sentientia_users\external\search_supervisors',
        'description' => 'Tenant-scoped autocomplete for supervisor / reporting-manager picker',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_users:view',
    ],
    'local_sentientia_users_suspend_user' => [
        'classname'   => 'local_sentientia_users\external\suspend_user',
        'description' => 'Suspend or activate a user',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_users:edit',
    ],
    'local_sentientia_users_delete_user' => [
        'classname'   => 'local_sentientia_users\external\delete_user',
        'description' => 'Delete a user (soft delete)',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_users:delete',
    ],
    'local_sentientia_users_bulk_action' => [
        'classname'   => 'local_sentientia_users\external\bulk_action',
        'description' => 'Bulk suspend or activate multiple users by ID',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_users:edit',
    ],
];
