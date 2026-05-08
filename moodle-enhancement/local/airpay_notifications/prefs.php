<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// User-facing notification preferences page. Anyone logged in can edit
// their own preferences here.

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_user::instance($USER->id);

$PAGE->set_url(new moodle_url('/local/airpay_notifications/prefs.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My notification preferences');
$PAGE->set_heading('My notification preferences');
$PAGE->navbar->add('My notifications',
    new moodle_url('/local/airpay_notifications/nudge.php'));
$PAGE->navbar->add('Preferences');

$prefs = \local_airpay_notifications\prefs_manager::get_for_user((int) $USER->id);

// Build list of rule types as toggle rows, with current opt-out state.
$ruletype_options = [];
foreach (\local_airpay_notifications\prefs_manager::RULE_TYPES as $key => $label) {
    $ruletype_options[] = [
        'key'      => s($key),
        'label'    => s($label),
        'disabled' => in_array($key, $prefs->disabled_rule_types ?? [], true),
    ];
}

// Hours dropdown 0-23 (+ "no quiet hours" as -1).
$hour_options = [['value' => -1, 'label' => '— none —', 'selected_start' => $prefs->quiet_hours_start === null,
    'selected_end' => $prefs->quiet_hours_end === null]];
for ($h = 0; $h < 24; $h++) {
    $label = sprintf('%02d:00', $h);
    $hour_options[] = [
        'value'         => $h,
        'label'         => $label,
        'selected_start' => $prefs->quiet_hours_start === $h,
        'selected_end'   => $prefs->quiet_hours_end === $h,
    ];
}

$digest_options = [];
foreach (\local_airpay_notifications\prefs_manager::DIGEST_FREQUENCIES as $f) {
    $digest_options[] = [
        'value'    => $f,
        'label'    => ucfirst($f),
        'selected' => $f === $prefs->digest_frequency,
    ];
}

$data = [
    'sesskey'         => sesskey(),
    'channel_inapp'   => (bool) $prefs->channel_inapp,
    'channel_email'   => (bool) $prefs->channel_email,
    'channel_push'    => (bool) $prefs->channel_push,
    'digest_options'  => $digest_options,
    'ruletype_options' => $ruletype_options,
    'hour_options'    => $hour_options,
    'has_quiet_hours' => $prefs->quiet_hours_start !== null
        && $prefs->quiet_hours_end !== null,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_notifications/prefs', $data);
$PAGE->requires->js_call_amd('local_airpay_notifications/prefs', 'init', []);
echo $OUTPUT->footer();
