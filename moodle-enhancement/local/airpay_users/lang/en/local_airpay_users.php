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
