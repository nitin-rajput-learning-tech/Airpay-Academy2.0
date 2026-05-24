<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-user leaderboard preferences page.
 *
 * One toggle: "Hide me from public leaderboards" (opt-out).
 * Surfaced inside Profile → Preferences via
 * local_sentientia_leaderboard_extend_navigation_user_settings (lib.php).
 *
 * @package local_sentientia_leaderboard
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$userid = optional_param('userid', 0, PARAM_INT);
if ($userid <= 0) {
    $userid = (int) $USER->id;
}

// Privilege check — own page, OR a siteadmin.
if ($userid !== (int) $USER->id && !is_siteadmin()) {
    throw new moodle_exception('error_outoftenant',
        'local_sentientia_leaderboard');
}

$context = context_user::instance($userid);
$PAGE->set_url('/local/sentientia_leaderboard/preferences.php',
    ['userid' => $userid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('preference_optout',
    'local_sentientia_leaderboard'));
$PAGE->set_heading(fullname($DB->get_record('user', ['id' => $userid],
    '*', MUST_EXIST)));

// Inline mini-form — one checkbox, one save button.
class local_sentientia_leaderboard_pref_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('checkbox', 'optout',
            get_string('label_optout', 'local_sentientia_leaderboard'));
        $mform->addElement('static', 'optout_desc', '',
            get_string('label_optout_desc', 'local_sentientia_leaderboard'));

        $this->add_action_buttons(true,
            get_string('action_save', 'local_sentientia_leaderboard'));
    }
}

$form = new local_sentientia_leaderboard_pref_form();
$form->set_data([
    'userid' => $userid,
    'optout' => \local_sentientia_leaderboard\optout_manager::is_opted_out($userid) ? 1 : 0,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/user/preferences.php', ['userid' => $userid]));
}

if ($data = $form->get_data()) {
    \local_sentientia_leaderboard\optout_manager::set_preference_value(
        $userid, !empty($data->optout));
    redirect(
        new moodle_url('/local/sentientia_leaderboard/preferences.php',
            ['userid' => $userid]),
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('preference_optout',
    'local_sentientia_leaderboard'));
$form->display();
echo $OUTPUT->footer();
