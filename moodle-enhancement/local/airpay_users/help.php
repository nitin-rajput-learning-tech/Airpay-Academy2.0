<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Self-service help / support links page.
 *
 * Closes Phase 5 A.6 — replaces the BizLMS users/help.php.
 *
 * @package local_airpay_users
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_users/help.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Help & support');
$PAGE->set_heading('Help & support');

// Pull per-tenant help URL + support email from airpay_org/tenant_settings.
$support_email = '';
$help_url = '';
if (class_exists('\\local_airpay_org\\tenant_settings')) {
    $support_email = \local_airpay_org\tenant_settings::support_email();
    $help_url      = \local_airpay_org\tenant_settings::help_url();
}

$links = [
    [
        'icon' => 'fa-book',
        'title' => 'My courses',
        'desc'  => 'Resume learning from where you left off.',
        'url'   => (new moodle_url('/local/sentientia_catalog/mycourses.php'))->out(false),
    ],
    [
        'icon' => 'fa-search',
        'title' => 'Course catalog',
        'desc'  => 'Browse all available courses.',
        'url'   => (new moodle_url('/local/sentientia_catalog/index.php'))->out(false),
    ],
    [
        'icon' => 'fa-certificate',
        'title' => 'My certificates',
        'desc'  => 'View and download your earned certificates.',
        'url'   => (new moodle_url('/local/sentientia_pages/certificates.php'))->out(false),
    ],
    [
        'icon' => 'fa-user',
        'title' => 'Edit my profile',
        'desc'  => 'Update photo, contact info, and preferences.',
        'url'   => (new moodle_url('/local/airpay_users/profile.php'))->out(false),
    ],
    [
        'icon' => 'fa-bell',
        'title' => 'Notification preferences',
        'desc'  => 'Choose how and when to be notified.',
        'url'   => (new moodle_url('/local/airpay_notifications/prefs.php'))->out(false),
    ],
    [
        'icon' => 'fa-lock',
        'title' => 'Privacy & data',
        'desc'  => 'Review consents and request your data.',
        'url'   => (new moodle_url('/local/sentientia_privacy/index.php'))->out(false),
    ],
];

$data = [
    'links'         => $links,
    'has_support_email' => !empty($support_email),
    'support_email' => $support_email,
    'has_help_url'  => !empty($help_url),
    'help_url'      => $help_url,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_users/help', $data);
echo $OUTPUT->footer();
