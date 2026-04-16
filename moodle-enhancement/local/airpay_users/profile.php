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
 * User profile page — Airpay User Engine.
 *
 * Drop-in replacement for /local/users/profile.php.
 * Renders the Airpay-branded user profile with org hierarchy,
 * gamification, skills, and plugin tabs.
 *
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$id = optional_param('id', $USER->id, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_users/profile.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');

$user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
$PAGE->set_title(fullname($user) . ' - ' . get_string('profile', 'local_airpay_users'));
$PAGE->set_heading(fullname($user));

// Build profile context via user_manager.
$profilecontext = \local_airpay_users\user_manager::build_profile_context($id);

echo $OUTPUT->header();

if (!empty($profilecontext)) {
    echo $OUTPUT->render_from_template('local_airpay_users/profile', $profilecontext);
} else {
    echo $OUTPUT->notification(get_string('orgnotfound', 'local_airpay_org'), 'error');
}

echo $OUTPUT->footer();
