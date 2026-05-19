<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// W1-8 (2026-05-16) — Public-tenant self-registration page.
//
// Replaces the redirect-to-BizLMS stub. Renders Moodleform, validates,
// creates user with email-confirmation pending, then shows success page.

require_once(__DIR__ . '/../../config.php');

global $CFG, $OUTPUT, $PAGE, $USER;

$PAGE->set_url(new moodle_url('/local/airpay_users/signup.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_pagetype('signup');
$PAGE->set_title(get_string('signup_pagetitle', 'local_airpay_users'));
$PAGE->set_heading(format_string($CFG->fullname ?? 'Airpay Academy'));

// Already logged in? Send them home.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/dashboard.php'));
}

// Feature flag — admin must opt in to self-registration via settings.
if (!\local_airpay_users\signup_service::is_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('signup_disabled_notice', 'local_airpay_users'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$success = optional_param('success', 0, PARAM_INT);

$mform = new \local_airpay_users\form\signup_form();

if ($mform->is_cancelled()) {
    redirect(get_login_url());
}

if ($success) {
    // Step-2 view: we just submitted successfully.
    echo $OUTPUT->header();
    echo html_writer::start_div('container my-4');
    echo $OUTPUT->notification(
        get_string('signup_success_check_email', 'local_airpay_users'),
        'success');
    echo html_writer::tag('p',
        get_string('signup_success_help', 'local_airpay_users'),
        ['class' => 'text-muted small']);
    echo html_writer::tag('div',
        html_writer::link(get_login_url(),
            get_string('signup_back_to_login', 'local_airpay_users'),
            ['class' => 'btn btn-primary']),
        ['class' => 'mt-3']);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if ($data = $mform->get_data()) {
    try {
        \local_airpay_users\signup_service::register($data);
        redirect(
            new moodle_url('/local/airpay_users/signup.php',
                ['success' => 1]),
            get_string('signup_success_check_email', 'local_airpay_users'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $e) {
        // Re-render the form with the error.
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('container my-4');
echo html_writer::tag('p',
    get_string('signup_intro', 'local_airpay_users'),
    ['class' => 'text-muted']);
$mform->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
