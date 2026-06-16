<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Developer landing page for the Sentientia Public API.
 *
 * Shows whether the API is enabled, the documented v1 endpoints, the REST
 * base URL, and a link to the OpenAPI spec. Read-only; requires the
 * local/sentientia_api:read capability.
 *
 * @package local_sentientia_api
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/sentientia_api/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_api:read', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_api/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_sentientia_api'));
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_api'));

echo $OUTPUT->header();

$enabled = local_sentientia_api_is_enabled();
$ltienabled = local_sentientia_api_lti_is_enabled();

if (!$enabled) {
    echo $OUTPUT->notification(get_string('api_disabled', 'local_sentientia_api'), 'info');
} else {
    echo $OUTPUT->notification(get_string('api_enabled_notice', 'local_sentientia_api'), 'success');
}

$resturl = new moodle_url('/webservice/rest/server.php');
echo html_writer::tag('h3', get_string('rest_base', 'local_sentientia_api'));
echo html_writer::tag('pre', s($resturl->out(false)));

$endpoints = [
    'local_sentientia_api_v1_list_courses',
    'local_sentientia_api_v1_get_course',
    'local_sentientia_api_v1_list_enrolments',
    'local_sentientia_api_v1_list_completions',
    'local_sentientia_api_v1_list_skills',
    'local_sentientia_api_v1_create_enrolment',
    'local_sentientia_api_v1_openapi',
];
echo html_writer::tag('h3', get_string('v1_endpoints', 'local_sentientia_api'));
echo html_writer::start_tag('ul');
foreach ($endpoints as $fn) {
    echo html_writer::tag('li', s($fn));
}
echo html_writer::end_tag('ul');

echo html_writer::tag('h3', get_string('lti_status', 'local_sentientia_api'));
echo html_writer::tag('p', $ltienabled
    ? get_string('lti_enabled', 'local_sentientia_api')
    : get_string('lti_disabled', 'local_sentientia_api'));

echo $OUTPUT->footer();
