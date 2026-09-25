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

// Page URL and a system context come first, so a refusal below renders as a
// normal error page rather than one carrying a "$PAGE->context was not set"
// notice. The user context is set only after the check: context_user::
// instance() on a missing id throws its own error, which would tell a
// missing id apart from a refused one.
$context_sys = context_system::instance();
$PAGE->set_context($context_sys);
$PAGE->set_url(new moodle_url('/local/sentientia_users/photo.php',
    ['id' => $userid]));

// N1 (UAT 2026-09-24): tenant boundary first, before the record is loaded.
// The old MUST_EXIST load ran ahead of the auth check, so a missing id and a
// refused id gave different errors. Both now raise the same exception; the
// edit-cap check below still applies on top, within the tenant.
$user = \local_sentientia_users\profile_access::get_viewable_user(
    (int) $USER->id, (int) $userid);

// Auth: self, OR :edit plus the ADR-031 target check.
// ADR-031 follow-up (2026-09-25): same-tenant (above) was the only target
// rule here, so a tenant admin holding :edit could replace the picture of a
// site admin or a :crosstenant account whose open_path sits in their tenant.
// user_manager::require_can_change_photo() applies require_can_act_on(), the
// rule suspend, delete and the edit form already use.
\local_sentientia_users\user_manager::require_can_change_photo((int) $userid);

$PAGE->set_context(context_user::instance($userid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Change profile photo');
$PAGE->set_heading('Change profile photo for ' . fullname($user));
$PAGE->navbar->add('Profile',
    new moodle_url('/local/sentientia_users/profile.php', ['id' => $userid]));
$PAGE->navbar->add('Change photo');

class local_sentientia_users_photo_form extends moodleform {
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

$form = new local_sentientia_users_photo_form(null,
    ['userid' => $userid]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/sentientia_users/profile.php',
        ['id' => $userid]));
}

if ($data = $form->get_data()) {
    require_sesskey();

    // Pull the file from the draft area.
    // Bug-fix 2026-05-10 (UAT-L1.5): get_area_files signature is
    // ($contextid, $component, $filearea, $itemid, ...). The original
    // code passed $newpicture (the itemid) as the first arg (contextid),
    // causing file_storage to look in the wrong context. Result: no
    // draft files found, "No file selected" error fired, user.picture
    // never updated. UAT caught it on day 2 of UAT walks.
    //
    // Draft area lives under the CURRENT USER's context (the uploader),
    // which for self-photo is == $usercontext. For admin-edits-someone-else
    // it's the admin's user context, not the target user's. Look up the
    // current user context dynamically.
    $current_user_context = context_user::instance($USER->id);
    $usercontext = context_user::instance($userid);
    $fs = get_file_storage();
    $draftfiles = $fs->get_area_files($current_user_context->id, 'user',
        'draft', (int) $data->newpicture, 'id', false);
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
            redirect(new moodle_url('/local/sentientia_users/profile.php',
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
