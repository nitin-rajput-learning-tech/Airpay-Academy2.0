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
 * Language strings — Airpay User Engine.
 *
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay User Engine';

// Capabilities.
$string['airpay_users:edit'] = 'Edit users';
$string['airpay_users:view'] = 'View user profiles';
$string['airpay_users:bulkstatuschange'] = 'Bulk status change';

// Profile.
$string['employee'] = 'Employee';
$string['na'] = 'N/A';
$string['all'] = 'All';
$string['profile'] = 'Profile';
$string['editprofile'] = 'Edit Profile';
$string['reportingto'] = 'Reporting To';

// Settings.
$string['settings_heading'] = 'Airpay User Settings';
$string['organization_shortname'] = 'Organization shortname for registration';
$string['activeregistration'] = 'Enable user registration';

// CRUD form strings.
$string['adduser'] = 'Add User';
$string['edituser'] = 'Edit User';
$string['deleteuser'] = 'Delete User';
$string['suspenduser'] = 'Suspend User';
$string['activateuser'] = 'Activate User';

// Form section headings.
$string['heading_account'] = 'Account';
$string['heading_personal'] = 'Personal Details';
$string['heading_organisation'] = 'Organisation';
$string['heading_password'] = 'Password';

// Form field labels.
$string['username'] = 'Username';
$string['username_help'] = 'Lowercase, no spaces. Used for login.';
$string['email'] = 'Email';
$string['firstname'] = 'First name';
$string['lastname'] = 'Last name';
$string['employeeid'] = 'Employee ID';
$string['designation'] = 'Designation';
$string['supervisor'] = 'Reporting manager';
$string['organisation'] = 'Organisation';
$string['department'] = 'Department';
$string['location'] = 'Location';
$string['phone'] = 'Phone';
$string['authmethod'] = 'Authentication method';
$string['password'] = 'Password';
$string['newpassword'] = 'New password';
$string['newpassword_help'] = 'Leave blank to keep current password.';
$string['emailwelcome'] = 'Email welcome message with login details';

// Error messages.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['usernametaken'] = 'This username is already taken. Please choose another.';
$string['emailtaken'] = 'This email is already registered. Please use a different email.';
$string['cannotdeleteself'] = 'You cannot delete your own account.';
$string['cannotdeletesystemuser'] = 'System users cannot be deleted.';
$string['confirmdelete'] = 'Are you sure you want to delete {$a}? This cannot be undone.';
$string['confirmsuspend'] = 'Are you sure you want to suspend {$a}? They will be unable to log in.';
$string['confirmactivate'] = 'Are you sure you want to reactivate {$a}?';

// Success messages.
$string['usercreated'] = 'User created successfully.';
$string['userupdated'] = 'User updated successfully.';
$string['userdeleted'] = 'User deleted.';
$string['usersuspended'] = 'User suspended.';
$string['useractivated'] = 'User activated.';

// Privacy.
$string['privacy:metadata'] = 'The Airpay Users plugin extends the core {user} table via open_* fields. These are exported by core_user; no airpay-owned tables store additional personal data.';

// W1-6 (2026-05-16) — HRMS 24-column bulk import.
$string['hrms_pagetitle']         = 'HRMS bulk import (24-column CSV)';
$string['hrms_pageheading']       = 'HRMS bulk import';
$string['hrms_breadcrumb']        = 'HRMS import';
$string['hrms_csvfile']           = 'HRMS CSV file';
$string['hrms_runimport']         = 'Run import';
$string['hrms_empty_csv']         = 'The uploaded CSV is empty. Please upload a file with a header row plus one or more data rows.';
$string['hrms_import_done']       = 'HRMS import complete. Review results below.';
$string['hrms_view_history']      = 'View import history';
$string['hrms_history_title']     = 'HRMS import history';
$string['hrms_history_breadcrumb'] = 'HRMS history';
$string['hrms_new_import']        = 'New HRMS import';
$string['hrms_no_runs']           = 'No HRMS imports have been run yet for this tenant.';
$string['hrms_back_to_history']   = 'Back to history';
$string['hrms_run_detail_title']  = 'HRMS run #{$a}';
$string['hrms_run_detail_heading'] = 'HRMS run #{$a} — details';
$string['hrms_no_errors']         = 'This run completed with no row-level errors or warnings.';
$string['hrms_error_log']         = 'Row-level errors and warnings';
$string['back_to_users']          = 'Back to user list';
$string['manage_users']           = 'Manage users';

// History table column headers.
$string['hrms_col_id']        = 'Run #';
$string['hrms_col_filename']  = 'File';
$string['hrms_col_time']      = 'Time';
$string['hrms_col_user']      = 'Run by';
$string['hrms_col_source']    = 'Source';
$string['hrms_col_status']    = 'Status';
$string['hrms_col_total']     = 'Total rows';
$string['hrms_col_inserted']  = 'Inserted';
$string['hrms_col_updated']   = 'Updated';
$string['hrms_col_errors']    = 'Errors';
$string['hrms_col_warnings']  = 'Warnings';

// Run-detail table column headers.
$string['hrms_col_line']      = 'Line #';
$string['hrms_col_severity']  = 'Severity';
$string['hrms_col_email']     = 'Email';
$string['hrms_col_empcode']   = 'Employee code';
$string['hrms_col_username']  = 'Username';
$string['hrms_col_name']      = 'Name';
$string['hrms_col_message']   = 'Message';
$string['hrms_col_missing']   = 'Missing mandatory';

// CSV parse errors.
$string['error_csv_header_missing'] = 'Required header column(s) missing: {$a}';
