<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS PWA — user preferences page (Phase B.2.b).
 *
 * Lets the logged-in user enable / disable browser push notifications
 * for their account. The actual subscription is browser-side (one row
 * in mdl_local_sentientia_push_subs per device), so this page is
 * effectively "manage this browser's notifications".
 *
 * @package local_sentientia_pwa
 */

require(__DIR__ . '/../../config.php');

require_login();

$context = \context_user::instance($USER->id);
require_capability('local/sentientia_pwa:subscribe', $context);

// PWA feature flag must be on at minimum — if push subflag is off the
// widget itself displays a "currently disabled" notice instead of the
// button, but we still let users LAND on this page so they understand
// the feature exists.
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.pwa.enabled')) {
        // Parent flag is off — the SW won't even be registered, so push
        // is unreachable. Redirect to user profile with a notice.
        redirect(
            new \moodle_url('/user/profile.php', ['id' => $USER->id]),
            get_string('pwa_disabled_redirect', 'local_sentientia_pwa'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

$PAGE->set_url('/local/sentientia_pwa/preferences.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('preferences_page_title', 'local_sentientia_pwa'));
$PAGE->set_heading(fullname($USER));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('preferences_page_heading', 'local_sentientia_pwa'), 2);

echo \html_writer::tag('p',
    get_string('preferences_page_intro', 'local_sentientia_pwa'),
    ['class' => 'lead']
);

// Render the subscribe widget — handles all UI states (not set up,
// flag off, unsupported browser, denied, subscribed, unsubscribed).
$widget = new \local_sentientia_pwa\output\subscribe_widget();
echo $OUTPUT->render_from_template('local_sentientia_pwa/subscribe_widget',
    $widget->export_for_template($OUTPUT));

// Help text linking to the install banner for users who haven't added
// the PWA to their home screen yet.
echo \html_writer::start_tag('div', ['class' => 'mt-4']);
echo $OUTPUT->heading(get_string('preferences_install_heading', 'local_sentientia_pwa'), 4);
echo \html_writer::tag('p',
    get_string('preferences_install_intro', 'local_sentientia_pwa'),
    ['class' => 'text-muted']
);
echo \html_writer::end_tag('div');

echo $OUTPUT->footer();
