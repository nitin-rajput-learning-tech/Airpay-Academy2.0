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
 * Phase 2.1 addition: when the OAuth master flag is ON AND the admin
 * has configured client credentials, an additional "OAuth bi-directional
 * sync" card renders Connect / Disconnect controls per provider with
 * a live status badge (connected / expired / disconnected).
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

// OAuth connection summary — only renders when the OAuth flag is ON
// AND at least one provider has a client_id set. Templates respect
// `oauth_section_visible` to render-or-skip the whole card.
$oauth_section = local_sentientia_calendar_oauth_section_context((int) $USER->id);

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

$data = array_merge($data, $oauth_section);

echo $OUTPUT->render_from_template('local_sentientia_calendar/subscription_page', $data);

echo $OUTPUT->footer();

/**
 * Build the OAuth section's template context.
 *
 * When the master OAuth flag is OFF the whole card is hidden by setting
 * `oauth_section_visible = false`. When ON, each provider appears in
 * `oauth_providers` as a row that the template iterates over with a
 * status badge + Connect or Disconnect button.
 *
 * @param int $userid
 * @return array<string, mixed> template context keys to merge into $data
 */
function local_sentientia_calendar_oauth_section_context(int $userid): array {
    $flag_on = \local_sentientia_calendar\oauth\oauth_base::is_flag_enabled();
    if (!$flag_on) {
        return [
            'oauth_section_visible' => false,
            'oauth_providers'       => [],
        ];
    }

    $providers = [];
    foreach (['m365', 'google'] as $name) {
        $class = \local_sentientia_calendar\oauth\oauth_base::provider_class($name);
        $status = $class::describe_connection($userid);
        if (!$status['client_configured']) {
            // No client_id set in admin → admin has not registered an app
            // for this provider yet. Hide the row entirely rather than
            // showing a button that would just error.
            continue;
        }

        $connect_url = (new \moodle_url(
            '/local/sentientia_calendar/oauth/connect.php',
            ['provider' => $name, 'sesskey' => sesskey()]
        ))->out(false);

        $disconnect_url = (new \moodle_url(
            '/local/sentientia_calendar/oauth/disconnect.php',
            ['provider' => $name, 'sesskey' => sesskey()]
        ))->out(false);

        // Pre-computed status copy so Mustache stays logic-light.
        if (!$status['connected']) {
            $state_label  = get_string('oauth_status_disconnected', 'local_sentientia_calendar');
            $state_class  = 'badge-secondary';
        } elseif ($status['expired']) {
            $state_label  = get_string('oauth_status_expired', 'local_sentientia_calendar');
            $state_class  = 'badge-warning';
        } else {
            $state_label  = get_string('oauth_status_connected', 'local_sentientia_calendar',
                (object) ['date' => userdate($status['expires_at'], get_string('strftimedatetime'))]);
            $state_class  = 'badge-success';
        }

        $providers[] = [
            'name'             => $name,
            'label'            => get_string('oauth_provider_' . $name, 'local_sentientia_calendar'),
            'description'      => get_string('oauth_provider_desc_' . $name, 'local_sentientia_calendar'),
            'connected'        => $status['connected'],
            'expired'          => $status['expired'],
            'state_label'      => $state_label,
            'state_class'      => $state_class,
            'connect_url'      => $connect_url,
            'disconnect_url'   => $disconnect_url,
            'connect_label'    => get_string('oauth_connect_' . $name, 'local_sentientia_calendar'),
            'reconnect_label'  => get_string('oauth_reconnect', 'local_sentientia_calendar'),
            'disconnect_label' => get_string('oauth_disconnect', 'local_sentientia_calendar'),
        ];
    }

    return [
        'oauth_section_visible' => !empty($providers),
        'oauth_heading'         => get_string('oauth_heading', 'local_sentientia_calendar'),
        'oauth_intro'           => get_string('oauth_intro', 'local_sentientia_calendar'),
        'oauth_providers'       => $providers,
        'sesskey'               => sesskey(),
    ];
}
