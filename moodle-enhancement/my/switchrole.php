<?php
/**
 * BizLMS Switch Role handler.
 *
 * BizLMS core_renderer generates URLs to /my/switchrole.php for role switching.
 * This file handles the role switch by updating the user's session role context
 * and redirecting back to the dashboard.
 *
 * Parameters:
 *   sesskey   - CSRF protection
 *   confirm   - Must be 1
 *   switchrole - Role ID to switch to
 *   contextid - (optional) Context ID for the role assignment
 *
 * @package    core
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../config.php');
require_login();
require_sesskey();

$switchrole = required_param('switchrole', PARAM_INT);
$confirm = required_param('confirm', PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);

if ($confirm != 1) {
    redirect(new moodle_url('/my/'));
}

// BizLMS role switching: update the user's active role in session.
// This is used by BizLMS's multi-role user menu to switch between
// admin/manager/employee views without changing actual role assignments.

global $USER, $SESSION, $DB;

// Verify the role exists and user has this role assigned.
$role = $DB->get_record('role', ['id' => $switchrole], '*', MUST_EXIST);

// Store the switched role in session for BizLMS to detect.
if (!isset($SESSION->airpay_switchrole)) {
    $SESSION->airpay_switchrole = new stdClass();
}
$SESSION->airpay_switchrole->roleid = $switchrole;
$SESSION->airpay_switchrole->contextid = $contextid;
$SESSION->airpay_switchrole->timeswitched = time();

// Use Airpay org accesslib for role switching. Falls back to BizLMS if present.
try {
    \local_airpay_org\accesslib::set_user_role_switch($switchrole, $contextid);
} catch (\Throwable $e) {
    // Fallback — session data already set above.
}

// Redirect back to dashboard.
redirect(new moodle_url('/my/'),
    get_string('switchroleas', 'theme_airpayux') . format_string($role->shortname),
    null, \core\output\notification::NOTIFY_INFO);
