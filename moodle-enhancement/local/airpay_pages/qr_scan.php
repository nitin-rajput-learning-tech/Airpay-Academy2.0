<?php
/**
 * QR Scan — Process QR code scan for attendance marking.
 * Employee scans the QR code, this page validates the token and marks attendance.
 *
 * @package    local_airpay_pages
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$sessionid = required_param('sessionid', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/airpay_pages/qr_scan.php', ['sessionid' => $sessionid, 'token' => $token]);
$PAGE->set_title('Attendance Confirmation');
$PAGE->set_heading('Attendance');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// Validate token (hourly rotation).
$expectedtoken = hash('sha256', $sessionid . '|' . date('Y-m-d-H') . '|' . $CFG->passwordsaltmain);
if ($token !== $expectedtoken) {
    // Try previous hour's token (grace period).
    $prevtoken = hash('sha256', $sessionid . '|' . date('Y-m-d-H', strtotime('-1 hour')) . '|' . $CFG->passwordsaltmain);
    if ($token !== $prevtoken) {
        echo '<div class="alert alert-danger" style="text-align: center; margin: 40px auto; max-width: 500px;">';
        echo '<h3><i class="fa fa-times-circle"></i> QR Code Expired</h3>';
        echo '<p>This QR code has expired. Please scan the current QR code displayed by your trainer.</p>';
        echo '</div>';
        echo $OUTPUT->footer();
        exit;
    }
}

// Check if already marked.
$alreadymarked = $DB->record_exists('local_classroom_attendance', [
    'sessionid' => $sessionid,
    'userid' => $USER->id,
]);

if ($alreadymarked) {
    echo '<div class="alert alert-info" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-check-circle"></i> Already Marked</h3>';
    echo '<p>Your attendance for this session has already been recorded.</p>';
    echo '</div>';
} else {
    // Mark attendance.
    try {
        $attendance = new stdClass();
        $attendance->sessionid = $sessionid;
        $attendance->userid = $USER->id;
        $attendance->status = 1; // Present
        $attendance->timecreated = time();
        $attendance->timemodified = time();
        $DB->insert_record('local_classroom_attendance', $attendance);

        echo '<div class="alert alert-success" style="text-align: center; margin: 40px auto; max-width: 500px;">';
        echo '<h3><i class="fa fa-check-circle"></i> Attendance Marked!</h3>';
        echo '<p><strong>' . s($USER->firstname . ' ' . $USER->lastname) . '</strong></p>';
        echo '<p>Your attendance has been successfully recorded at ' . userdate(time(), '%I:%M %p') . '.</p>';
        echo '</div>';
    } catch (\Exception $e) {
        echo '<div class="alert alert-danger" style="text-align: center; margin: 40px auto; max-width: 500px;">';
        echo '<h3><i class="fa fa-exclamation-triangle"></i> Error</h3>';
        echo '<p>Could not record attendance. Please contact your trainer.</p>';
        echo '</div>';
    }
}

echo '<div style="text-align: center; margin-top: 20px;">';
echo '<a href="' . $CFG->wwwroot . '/my/" class="airpay-btn airpay-btn--primary airpay-btn--md">Back to Dashboard</a>';
echo '</div>';

echo $OUTPUT->footer();
