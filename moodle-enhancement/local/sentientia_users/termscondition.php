<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// W1-8 (2026-05-16) — Public Terms of Use page (no login required).
//
// Renders an admin-configurable HTML block from
// `local_sentientia_users/custom_tos_html`. Falls back to a default ToS text
// that covers GDPR + DPDP-Act language if the admin hasn't set anything.

require_once(__DIR__ . '/../../config.php');

global $CFG, $OUTPUT, $PAGE;

$PAGE->set_url(new moodle_url('/local/sentientia_users/termscondition.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('tos_pagetitle', 'local_sentientia_users'));
$PAGE->set_heading(format_string($CFG->fullname ?? 'Airpay Academy'));

$custom = (string) get_config('local_sentientia_users', 'custom_tos_html');

echo $OUTPUT->header();
echo html_writer::start_div('container my-4', ['style' => 'max-width: 860px;']);

if (trim($custom) !== '') {
    echo format_text($custom, FORMAT_HTML, ['noclean' => false, 'context' => $PAGE->context]);
} else {
    echo html_writer::tag('h2',
        get_string('tos_heading', 'local_sentientia_users'),
        ['class' => 'mb-3']);

    echo '<p>Welcome to Airpay Academy. Please read these Terms of Use carefully before accessing or using our learning platform. By creating an account and clicking the "I agree" checkbox on the signup form, you agree to be bound by these terms.</p>';

    echo '<h3 class="mt-4">1. Eligibility</h3>';
    echo '<p>You must be at least 18 years old to create an Airpay Academy account on your own behalf. By signing up you confirm you meet this requirement.</p>';

    echo '<h3 class="mt-4">2. Use of the platform</h3>';
    echo '<p>Airpay Academy is intended for personal learning and professional development. You may not use the platform:</p>';
    echo '<ul>';
    echo '<li>for any unlawful purpose, or in a way that breaches any applicable law or regulation;</li>';
    echo '<li>to harvest user data, scrape content, or run automated agents;</li>';
    echo '<li>to upload malware, run penetration tests, or attempt to bypass access controls.</li>';
    echo '</ul>';

    echo '<h3 class="mt-4">3. Your account</h3>';
    echo '<p>You are responsible for keeping your password confidential and for every action taken under your account. Notify <a href="mailto:academy@airpay.co.in">academy@airpay.co.in</a> immediately if you suspect unauthorised access.</p>';

    echo '<h3 class="mt-4">4. Content ownership</h3>';
    echo '<p>All course content — including text, video, slides, quizzes, certificates, and software — is the intellectual property of Airpay Payment Services Private Limited or its licensors. You may not reproduce, distribute, or create derivative works without our prior written consent.</p>';

    echo '<h3 class="mt-4">5. Privacy</h3>';
    echo '<p>Your personal data is handled in line with our <a href="' . s(new \moodle_url('/local/sentientia_users/privacypolicy.php')) . '">Privacy Policy</a>. By using the platform you also agree to that policy.</p>';

    echo '<h3 class="mt-4">6. Limitation of liability</h3>';
    echo '<p>Airpay Academy is provided "as is". We do not warrant that the platform will be uninterrupted or error-free. To the maximum extent permitted by law, Airpay Payment Services Private Limited will not be liable for indirect, incidental, or consequential damages arising from your use of the platform. This limitation does not apply to wilful misconduct or gross negligence on our part.</p>';

    echo '<h3 class="mt-4">7. Termination</h3>';
    echo '<p>We may suspend or terminate your account if you breach these terms, or if doing so is required to comply with law. You can close your account at any time by emailing <a href="mailto:academy@airpay.co.in">academy@airpay.co.in</a>.</p>';

    echo '<h3 class="mt-4">8. Governing law</h3>';
    echo '<p>These terms are governed by the laws of India. Any disputes will be subject to the exclusive jurisdiction of the courts of Mumbai.</p>';

    echo '<p class="mt-4 text-muted small">Last updated: 16 May 2026.</p>';
}

echo html_writer::tag('div',
    html_writer::link(get_login_url(),
        '← ' . get_string('signup_back_to_login', 'local_sentientia_users'),
        ['class' => 'btn btn-outline-secondary']),
    ['class' => 'mt-4 mb-2']);

echo html_writer::end_div();
echo $OUTPUT->footer();
