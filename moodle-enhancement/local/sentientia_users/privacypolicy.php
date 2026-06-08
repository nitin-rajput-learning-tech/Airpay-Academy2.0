<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// W1-8 (2026-05-16) — Public privacy policy page (no login required).
//
// Renders an admin-configurable HTML block from
// `local_sentientia_users/custom_privacy_policy_html`. Falls back to a
// GDPR-compliant default text covering the signup form's data fields if
// the admin hasn't set anything. Anonymous users CAN view this page —
// they need to before they can tick the ToS consent checkbox on signup.

require_once(__DIR__ . '/../../config.php');

global $CFG, $OUTPUT, $PAGE;

$PAGE->set_url(new moodle_url('/local/sentientia_users/privacypolicy.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_pagelayout('login');  // no nav clutter
$PAGE->set_title(get_string('privacy_pagetitle', 'local_sentientia_users'));
$PAGE->set_heading(format_string($CFG->fullname ?? 'Airpay Academy'));

// Admin override wins. Trust it (it goes through Moodle's HTML editor +
// format_text on render).
$custom = (string) get_config('local_sentientia_users', 'custom_privacy_policy_html');

echo $OUTPUT->header();
echo html_writer::start_div('container my-4', ['style' => 'max-width: 860px;']);

if (trim($custom) !== '') {
    echo format_text($custom, FORMAT_HTML, ['noclean' => false, 'context' => $PAGE->context]);
} else {
    // GDPR-compliant default (mirrors BizLMS local_users/privacypolicy.php
    // content but updated for the W1-8 field set).
    echo html_writer::tag('h2',
        get_string('privacy_heading', 'local_sentientia_users'),
        ['class' => 'mb-3']);
    echo '<p>At <strong>Airpay Academy</strong>, we take the privacy of our learners very seriously. This policy outlines the personal data we collect, how we use it, and the rights you have under applicable privacy regulations including the EU GDPR and India\'s DPDP Act.</p>';

    echo '<h3 class="mt-4">1. Data we collect</h3>';
    echo '<p>When you self-register on Airpay Academy, we collect the following <strong>mandatory</strong> data:</p>';
    echo '<ul>';
    echo '<li>First and last name</li>';
    echo '<li>Email address</li>';
    echo '<li>Password (stored as a one-way hash; the plaintext is never recorded)</li>';
    echo '<li>Country</li>';
    echo '<li>Preferred language</li>';
    echo '</ul>';
    echo '<p>Optional data you may add later from your profile page includes phone number, designation, region, and time zone.</p>';

    echo '<h3 class="mt-4">2. Why we collect it</h3>';
    echo '<ul>';
    echo '<li>To create and maintain your learner account.</li>';
    echo '<li>To grant access to courses, classrooms, and certifications.</li>';
    echo '<li>To send transactional notifications (course enrolment, completion, password reset).</li>';
    echo '<li>To comply with statutory record-keeping obligations.</li>';
    echo '</ul>';

    echo '<h3 class="mt-4">3. Legal basis</h3>';
    echo '<p>Our lawful basis for processing your data is your <strong>explicit consent</strong>, given when you tick the "I agree" checkbox on the signup form. You can withdraw consent at any time by emailing <a href="mailto:academy@airpay.co.in">academy@airpay.co.in</a>.</p>';

    echo '<h3 class="mt-4">4. Your rights</h3>';
    echo '<ul>';
    echo '<li><strong>Access</strong>: request a copy of your data.</li>';
    echo '<li><strong>Rectify</strong>: ask us to correct inaccurate data.</li>';
    echo '<li><strong>Erase</strong>: ask us to delete your account.</li>';
    echo '<li><strong>Object</strong>: opt out of marketing communications.</li>';
    echo '<li><strong>Portability</strong>: receive your data in a structured machine-readable format.</li>';
    echo '</ul>';

    echo '<h3 class="mt-4">5. Data protection</h3>';
    echo '<p>We host Airpay Academy on infrastructure that enforces TLS in transit and encryption at rest. Access to personal data is limited to staff on a need-to-know basis and is logged. We carry out periodic audits and vulnerability scans.</p>';

    echo '<h3 class="mt-4">6. Changes to this policy</h3>';
    echo '<p>We may update this policy from time to time. Material changes will be communicated by email, and continued use of the service after the change date constitutes acceptance of the revised terms.</p>';

    echo '<p class="mt-4 text-muted small">Last updated: 16 May 2026.</p>';
}

echo html_writer::tag('div',
    html_writer::link(get_login_url(),
        '← ' . get_string('signup_back_to_login', 'local_sentientia_users'),
        ['class' => 'btn btn-outline-secondary']),
    ['class' => 'mt-4 mb-2']);

echo html_writer::end_div();
echo $OUTPUT->footer();
