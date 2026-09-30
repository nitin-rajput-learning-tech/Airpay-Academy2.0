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

// Record the scan in the Sentientia classroom tables. session_manager checks that the
// session exists, that its classroom is in this learner's tenant (ADR-031) and that
// the learner is on the roster and that the classroom is not cancelled, then writes the
// row the attendance grid reads back (local_sentientia_classroom_attendance, status
// Present). A repeat scan writes nothing.
// This page used to write {local_classroom_attendance}, a BizLMS table that a
// Sentientia install does not have.
$manager = '\local_sentientia_classroom\session_manager';
$result = null;
$errortext = null;
if (!class_exists($manager)) {
    $errortext = 'Classroom attendance is not available on this site.';
} else {
    try {
        $result = $manager::record_qr_attendance($sessionid, (int) $USER->id);
    } catch (\moodle_exception $e) {
        if ($e->errorcode === 'error_outoftenant') {
            $errortext = 'This session belongs to a different organisation, so your attendance was not recorded.';
        } else {
            $errortext = 'Could not record attendance. Please contact your trainer.';
        }
    } catch (\Exception $e) {
        $errortext = 'Could not record attendance. Please contact your trainer.';
    }
}

if ($errortext !== null) {
    echo '<div class="alert alert-danger" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-exclamation-triangle"></i> Error</h3>';
    echo '<p>' . s($errortext) . '</p>';
    echo '</div>';
} else if ($result === $manager::SCAN_NO_SESSION) {
    echo '<div class="alert alert-danger" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-times-circle"></i> Session Not Found</h3>';
    echo '<p>This session no longer exists. Please ask your trainer for a new QR code.</p>';
    echo '</div>';
} else if ($result === $manager::SCAN_CANCELLED) {
    echo '<div class="alert alert-warning" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-ban"></i> Classroom Cancelled</h3>';
    echo '<p>This classroom has been cancelled, so your attendance was not recorded. '
        . 'Please contact your trainer.</p>';
    echo '</div>';
} else if ($result === $manager::SCAN_NOT_ENROLLED) {
    echo '<div class="alert alert-warning" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-exclamation-circle"></i> Not Enrolled</h3>';
    echo '<p>You are not enrolled in this classroom, so your attendance was not recorded. '
        . 'Please contact your trainer.</p>';
    echo '</div>';
} else if ($result === $manager::SCAN_ALREADY) {
    echo '<div class="alert alert-info" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-check-circle"></i> Already Marked</h3>';
    echo '<p>Your attendance for this session has already been recorded.</p>';
    echo '</div>';
} else {
    echo '<div class="alert alert-success" style="text-align: center; margin: 40px auto; max-width: 500px;">';
    echo '<h3><i class="fa fa-check-circle"></i> Attendance Marked!</h3>';
    echo '<p><strong>' . s($USER->firstname . ' ' . $USER->lastname) . '</strong></p>';
    echo '<p>Your attendance has been successfully recorded at ' . userdate(time(), '%I:%M %p') . '.</p>';
    echo '</div>';
}

echo '<div style="text-align: center; margin-top: 20px;">';
echo '<a href="' . $CFG->wwwroot . '/my/" class="airpay-btn airpay-btn--primary airpay-btn--md">Back to Dashboard</a>';
echo '</div>';

echo $OUTPUT->footer();
