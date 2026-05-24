<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * User-facing calendar-subscription page.
 *
 * Shows the logged-in user their personal subscription URL with
 * copy-button + paste-into-Outlook / Google / Apple Calendar
 * instructions. Provides a "Regenerate URL" action that revokes the
 * old token and issues a new one.
 *
 * @package local_sentientia_calendar
 */

require(__DIR__ . '/../../config.php');

require_login();

global $USER, $PAGE, $OUTPUT;

$context = \context_user::instance($USER->id);
require_capability('local/sentientia_calendar:subscribe', $context);

// Master feature flag — page is 404 (via moodle_exception) when off, so
// a curious user who knows the URL can't probe whether the feature
// exists for other tenants.
if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('sentientia.calendar_sync.enabled')) {
        throw new \moodle_exception('error_flag_off', 'local_sentientia_calendar');
    }
}

$PAGE->set_url('/local/sentientia_calendar/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('page_title', 'local_sentientia_calendar'));
$PAGE->set_heading(fullname($USER));

// Provision (or fetch) the active token for this user.
$token = \local_sentientia_calendar\token_manager::get_or_create_for_user((int) $USER->id);
$subscription_url = \local_sentientia_calendar\token_manager::build_subscription_url($token);

$renderer = $PAGE->get_renderer('core');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('page_heading', 'local_sentientia_calendar'), 2);

$data = [
    'subscription_url' => $subscription_url->out(false),
    'regenerate_url'   => (new \moodle_url('/local/sentientia_calendar/regenerate.php',
        ['sesskey' => sesskey()]))->out(false),
    'sesskey'          => sesskey(),
    'intro'            => get_string('page_intro', 'local_sentientia_calendar'),
    'how_to_heading'   => get_string('how_to_heading', 'local_sentientia_calendar'),
    'how_to_outlook'   => get_string('how_to_outlook', 'local_sentientia_calendar'),
    'how_to_google'    => get_string('how_to_google', 'local_sentientia_calendar'),
    'how_to_apple'     => get_string('how_to_apple', 'local_sentientia_calendar'),
    'copy_label'       => get_string('copy_label', 'local_sentientia_calendar'),
    'copied_label'     => get_string('copied_label', 'local_sentientia_calendar'),
    'regenerate_label' => get_string('regenerate_label', 'local_sentientia_calendar'),
    'regenerate_help'  => get_string('regenerate_help', 'local_sentientia_calendar'),
    'security_note'    => get_string('security_note', 'local_sentientia_calendar'),
    'events_heading'   => get_string('events_heading', 'local_sentientia_calendar'),
    'events_courses'   => get_string('events_courses', 'local_sentientia_calendar'),
    'events_classroom' => get_string('events_classroom', 'local_sentientia_calendar'),
    'events_exams'     => get_string('events_exams', 'local_sentientia_calendar'),
];

echo $OUTPUT->render_from_template('local_sentientia_calendar/subscription_page', $data);

echo $OUTPUT->footer();
