<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase E.5 (2026-05-08) — in-page photo upload for the user profile.
//
// Uses Moodle's core_user::update_picture() for server-side resize +
// thumbnail generation. Crop UI is deferred — current behaviour:
// upload a square-ish photo (Moodle scales to 100×100, 35×35 thumb).

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/gdlib.php');

require_login();

$userid = optional_param('id', $USER->id, PARAM_INT);
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0],
    '*', MUST_EXIST);

// Auth: self OR has edit cap.
$context_sys = context_system::instance();
$is_self = ((int) $userid === (int) $USER->id);
if (!$is_self && !has_capability('local/airpay_users:edit', $context_sys)
    && !is_siteadmin()) {
    throw new \moodle_exception('nopermissions', 'error', '',
        'change another user\'s photo');
}

$PAGE->set_url(new moodle_url('/local/airpay_users/photo.php',
    ['id' => $userid]));
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Change profile photo');
$PAGE->set_heading('Change profile photo for ' . fullname($user));
$PAGE->navbar->add('Profile',
    new moodle_url('/local/airpay_users/profile.php', ['id' => $userid]));
$PAGE->navbar->add('Change photo');

class local_airpay_users_photo_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id',
            $this->_customdata['userid'] ?? 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('filemanager', 'newpicture', 'Photo', null, [
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
            'subdirs' => 0,
        ]);
        $mform->addRule('newpicture', 'A photo is required', 'required',
            null, 'server');
        $mform->addElement('static', 'help', '',
            '<div class="alert alert-info small">'
            . 'Upload a square-ish image (JPEG/PNG/WebP). Moodle resizes '
            . 'it to <strong style="color:#0a3d62;">100×100</strong> for '
            . 'the profile and <strong style="color:#0a3d62;">35×35</strong> '
            . 'for the thumbnail. Crop UI is on the roadmap.'
            . '</div>');
        $this->add_action_buttons(true, 'Save photo');
    }
}

$form = new local_airpay_users_photo_form(null,
    ['userid' => $userid]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/airpay_users/profile.php',
        ['id' => $userid]));
}

if ($data = $form->get_data()) {
    require_sesskey();

    // Pull the file from the draft area.
    $usercontext = context_user::instance($userid);
    $fs = get_file_storage();
    $draftfiles = $fs->get_area_files((int) $data->newpicture, 'user',
        'draft', false, '', false);
    if (empty($draftfiles)) {
        \core\notification::error('No file selected.');
    } else {
        $iconfile = reset($draftfiles);
        // Save the uploaded file to user picture via gdlib helper.
        $newpicture = (int) process_new_icon($usercontext, 'user',
            'icon', 0, $iconfile->copy_content_to_temp());
        if ($newpicture > 0) {
            $DB->set_field('user', 'picture', $newpicture, ['id' => $userid]);
            \core\notification::success('Photo updated.');
            redirect(new moodle_url('/local/airpay_users/profile.php',
                ['id' => $userid]));
        } else {
            \core\notification::error('Could not process the uploaded image. '
                . 'Make sure it\'s a valid JPEG/PNG/WebP and at least 100×100.');
        }
    }
}

echo $OUTPUT->header();

// Show current photo.
echo '<div class="mb-3 d-flex align-items-center">';
echo '<div class="me-3" style="width:100px;height:100px;border-radius:50%;overflow:hidden;border:2px solid var(--ap-text-secondary, #5a6070);">';
echo $OUTPUT->user_picture($user, ['size' => 100, 'link' => false]);
echo '</div>';
echo '<div><strong>Current photo</strong><br><span class="small text-muted">'
    . s(fullname($user)) . '</span></div>';
echo '</div>';

$form->display();

echo $OUTPUT->footer();
