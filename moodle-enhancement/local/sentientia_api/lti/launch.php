<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LTI 1.3 launch landing endpoint (provider side).
 *
 * The platform form-POSTs the signed id_token + state here. We hand them to
 * \local_sentientia_api\lti\launch::process(), which verifies the signature,
 * the registered claims, and the one-time nonce. On success we have a set of
 * verified LTI claims — this scaffold renders a confirmation; a production
 * build maps claims → a provisioned Sentientia user + session and redirects
 * to the resource.
 *
 * Returns 404 unless the LTI feature flag is ON.
 *
 * @package local_sentientia_api
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/sentientia_api/lib.php');

if (!local_sentientia_api_lti_is_enabled()) {
    header('HTTP/1.1 404 Not Found');
    die();
}

$idtoken = required_param('id_token', PARAM_RAW);
$state   = optional_param('state', '', PARAM_RAW_TRIMMED);

$costcenterid = 0;
if (!empty($USER->id)) {
    $costcenterid = \local_sentientia_platform\tenant::root_for_current_user();
}

// Verifies signature + claims + nonce. Throws on any failure.
$result = \local_sentientia_api\lti\launch::process($idtoken, $state, $costcenterid);
$claims = $result['claims'];

// SECURITY: we render only non-PII, structural claims here. A production
// build would now provision/match a user (by the platform's `sub` mapped
// through a registration policy) and start a session. We deliberately do NOT
// auto-create a session in the scaffold.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_api/lti/launch.php'));
$PAGE->set_title(get_string('lti_launch_title', 'local_sentientia_api'));
$PAGE->set_heading(get_string('lti_launch_title', 'local_sentientia_api'));
$PAGE->set_pagelayout('embedded');

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('lti_launch_verified', 'local_sentientia_api'), 'success');

$msgtype = $claims['https://purl.imsglobal.org/spec/lti/claim/message_type'] ?? '';
echo html_writer::tag('p', s(get_string('lti_message_type', 'local_sentientia_api')) . ': ' . s((string) $msgtype));

echo $OUTPUT->footer();
