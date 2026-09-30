<?php
/**
 * QR Scan — Process QR code scan for attendance marking.
 * Employee scans the QR code, this page validates the token and marks attendance.
 *
 * @package    local_sentientia_pages
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$sessionid = required_param('sessionid', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/sentientia_pages/qr_scan.php', ['sessionid' => $sessionid, 'token' => $token]);
$PAGE->set_title(get_string('qr_scan_title', 'local_sentientia_pages'));
$PAGE->set_heading(get_string('qr_scan_heading', 'local_sentientia_pages'));
$PAGE->set_pagelayout('standard');

/**
 * One result box. The title is plain text (a lang string) and is escaped here.
 *
 * @param string $type alert type: success, info, warning or danger
 * @param string $icon Font Awesome icon name, without the fa- prefix
 * @param string $title
 * @param string[] $paragraphs HTML paragraphs that are already escaped: build them with
 *                 local_sentientia_pages_qr_p()
 */
function local_sentientia_pages_qr_box(string $type, string $icon, string $title, array $paragraphs): void {
    echo '<div class="alert alert-' . $type . '" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-' . $icon . '"></i> ' . s($title) . '</h3>';
    foreach ($paragraphs as $p) {
        echo $p;
    }
    echo '</div>';
}

/** A plain-text paragraph, escaped. */
function local_sentientia_pages_qr_p(string $text): string {
    return '<p>' . s($text) . '</p>';
}

echo $OUTPUT->header();

// The QR token and the attendance rules live in the classroom plugin (session_manager):
// the token is an HMAC of the session id and the hour, signed with a per-site secret that
// this page never sees. It used to be a sha256 over $CFG->passwordsaltmain, which a new
// Moodle install does not have, so anyone could work the token out.
$manager = '\local_sentientia_classroom\session_manager';
$result = null;
$errortext = null;
if (!class_exists($manager)) {
    $errortext = get_string('qr_error_unavailable', 'local_sentientia_pages');
} else if (!$manager::qr_token_is_valid($sessionid, $token)) {
    // Wrong, forged, for another session, or older than the previous hour.
    local_sentientia_pages_qr_box('danger', 'times-circle', get_string('qr_expired_title', 'local_sentientia_pages'),
        [local_sentientia_pages_qr_p(get_string('qr_expired_body', 'local_sentientia_pages'))]);
    echo $OUTPUT->footer();
    exit;
} else {
    // Record the scan in the Sentientia classroom tables. session_manager checks that the
    // session exists, that its classroom is in this learner's tenant (ADR-031), that the
    // learner is on the roster, that the classroom is not cancelled, that the learner has no
    // mark yet (the trainer's mark always wins) and that the time is inside the session's
    // window (30 minutes before it starts to 30 minutes after it ends), then writes the row
    // the attendance grid reads back (local_sentientia_classroom_attendance, status Present).
    // This page used to write {local_classroom_attendance}, a BizLMS table that a Sentientia
    // install does not have.
    try {
        $result = $manager::record_qr_attendance($sessionid, (int) $USER->id);
    } catch (\moodle_exception $e) {
        if ($e->errorcode === 'error_outoftenant') {
            $errortext = get_string('qr_error_otherorg', 'local_sentientia_pages');
        } else {
            $errortext = get_string('qr_error_generic', 'local_sentientia_pages');
        }
    } catch (\Exception $e) {
        $errortext = get_string('qr_error_generic', 'local_sentientia_pages');
    }
}

if ($errortext !== null) {
    local_sentientia_pages_qr_box('danger', 'exclamation-triangle', get_string('qr_error_title', 'local_sentientia_pages'),
        [local_sentientia_pages_qr_p($errortext)]);
} else if ($result === $manager::SCAN_NO_SESSION) {
    local_sentientia_pages_qr_box('danger', 'times-circle', get_string('qr_nosession_title', 'local_sentientia_pages'),
        [local_sentientia_pages_qr_p(get_string('qr_nosession_body', 'local_sentientia_pages'))]);
} else if ($result === $manager::SCAN_CANCELLED) {
    local_sentientia_pages_qr_box('warning', 'ban', get_string('qr_cancelled_title', 'local_sentientia_pages'),
        [local_sentientia_pages_qr_p(get_string('qr_cancelled_body', 'local_sentientia_pages'))]);
} else if ($result === $manager::SCAN_NOT_ENROLLED) {
    local_sentientia_pages_qr_box('warning', 'exclamation-circle', get_string('qr_notenrolled_title', 'local_sentientia_pages'),
        [local_sentientia_pages_qr_p(get_string('qr_notenrolled_body', 'local_sentientia_pages'))]);
} else if ($result === $manager::SCAN_ALREADY) {
    // Says "marked", not "recorded": the mark may be the trainer's, and it may be Absent. So it
    // carries an info icon, not the success page's check-circle: at a glance a learner the
    // trainer marked Absent must not read this as a tick.
    local_sentientia_pages_qr_box('info', 'info-circle', get_string('qr_already_title', 'local_sentientia_pages'),
        [local_sentientia_pages_qr_p(get_string('qr_already_body', 'local_sentientia_pages'))]);
} else if ($result === $manager::SCAN_TOO_EARLY || $result === $manager::SCAN_TOO_LATE) {
    // The window's own times (with the 30 minute allowance), so the learner knows when to come back.
    $window = $manager::get_scan_window($sessionid);
    $fmt = get_string('strftimedatetimeshort', 'langconfig');
    if ($window === null) {
        local_sentientia_pages_qr_box('warning', 'clock-o', get_string('qr_toolate_title', 'local_sentientia_pages'),
            [local_sentientia_pages_qr_p(get_string('qr_notime_body', 'local_sentientia_pages'))]);
    } else if ($result === $manager::SCAN_TOO_EARLY) {
        local_sentientia_pages_qr_box('warning', 'clock-o', get_string('qr_tooearly_title', 'local_sentientia_pages'),
            [local_sentientia_pages_qr_p(get_string('qr_tooearly_body', 'local_sentientia_pages',
                userdate($window[0], $fmt)))]);
    } else {
        local_sentientia_pages_qr_box('warning', 'clock-o', get_string('qr_toolate_title', 'local_sentientia_pages'),
            [local_sentientia_pages_qr_p(get_string('qr_toolate_body', 'local_sentientia_pages',
                userdate($window[1], $fmt)))]);
    }
} else {
    local_sentientia_pages_qr_box('success', 'check-circle', get_string('qr_success_title', 'local_sentientia_pages'), [
        '<p><strong>' . s($USER->firstname . ' ' . $USER->lastname) . '</strong></p>',
        local_sentientia_pages_qr_p(get_string('qr_success_body', 'local_sentientia_pages',
            userdate(time(), '%I:%M %p'))),
    ]);
}

echo '<div style="text-align: center; margin-top: 20px;">';
echo '<a href="' . $CFG->wwwroot . '/my/" class="airpay-btn airpay-btn--primary airpay-btn--md">'
    . s(get_string('qr_back_dashboard', 'local_sentientia_pages')) . '</a>';
echo '</div>';

echo $OUTPUT->footer();
