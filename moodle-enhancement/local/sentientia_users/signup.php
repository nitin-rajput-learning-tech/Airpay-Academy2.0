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

$PAGE->set_url(new moodle_url('/local/sentientia_users/signup.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_pagetype('signup');
$PAGE->set_title(get_string('signup_pagetitle', 'local_sentientia_users'));
$PAGE->set_heading(format_string($CFG->fullname ?? 'Airpay Academy'));

// Already logged in? Send them home.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/dashboard.php'));
}

// Feature flag — admin must opt in to self-registration via settings.
if (!\local_sentientia_users\signup_service::is_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('signup_disabled_notice', 'local_sentientia_users'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$success = optional_param('success', 0, PARAM_INT);

$mform = new \local_sentientia_users\form\signup_form();

if ($mform->is_cancelled()) {
    redirect(get_login_url());
}

// All three views (form / success / registration-error) render through the
// split-screen template that mirrors the corporate login page design
// (theme_sentientia core/loginform): gradient hero left, form panel right.
// 'output' must be passed explicitly so the template can call the
// core_renderer login_stat_* methods for the live hero stats bar.
$templatecontext = [
    'output'    => $OUTPUT,
    'sitename'  => format_string($SITE->fullname ?? 'airpay academy', true,
        ['context' => \context_course::instance(SITEID), 'escape' => false]),
    'loginurl'  => get_login_url(),
    'success'   => (bool) $success,
    'formhtml'  => '',
    'errorhtml' => '',
];

if ($success) {
    // Step-2 view: we just submitted successfully — confirmation panel
    // inside the same split-screen shell (non-dismissible, role=status).
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template(
        'local_sentientia_users/signup_page', $templatecontext);
    echo $OUTPUT->footer();
    exit;
}

if ($data = $mform->get_data()) {
    try {
        \local_sentientia_users\signup_service::register($data);
        // No flash message here — the success view below renders the
        // confirmation panel. Passing a message to redirect() too would
        // queue a SECOND copy (the duplicate the success page showed).
        redirect(
            new moodle_url('/local/sentientia_users/signup.php',
                ['success' => 1])
        );
    } catch (\moodle_exception $e) {
        // Re-render the form with the error inside the panel.
        $templatecontext['errorhtml'] =
            $OUTPUT->notification($e->getMessage(), 'error');
        $templatecontext['formhtml'] = $mform->render();
        echo $OUTPUT->header();
        echo $OUTPUT->render_from_template(
            'local_sentientia_users/signup_page', $templatecontext);
        echo $OUTPUT->footer();
        exit;
    }
}

$templatecontext['formhtml'] = $mform->render();
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'local_sentientia_users/signup_page', $templatecontext);
echo $OUTPUT->footer();
