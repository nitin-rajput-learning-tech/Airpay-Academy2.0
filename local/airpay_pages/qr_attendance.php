<?php
/**
 * QR Attendance — Generate QR code for classroom session.
 * Trainer displays this page on projector. Employees scan to mark attendance.
 *
 * Usage: /local/airpay_pages/qr_attendance.php?sessionid=123
 *
 * @package    local_airpay_pages
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$sessionid = required_param('sessionid', PARAM_INT);
$context = context_system::instance();

// Only trainers and admins can generate QR codes.
if (!has_capability('local/classroom:takesessionattendance', $context) && !is_siteadmin()) {
    throw new moodle_exception('nopermission');
}

global $DB, $CFG, $OUTPUT, $PAGE;

$PAGE->set_context($context);
$PAGE->set_url('/local/airpay_pages/qr_attendance.php', ['sessionid' => $sessionid]);
$PAGE->set_title('QR Attendance');
$PAGE->set_heading('QR Attendance');
$PAGE->set_pagelayout('standard');

// Get session info.
$session = $DB->get_record('local_classroom_sessions', ['id' => $sessionid], '*', IGNORE_MISSING);
if (!$session) {
    // Fallback: try classroom table directly.
    $session = $DB->get_record('local_classroom', ['id' => $sessionid], '*', IGNORE_MISSING);
}

// Generate a time-limited token for this session.
$token = hash('sha256', $sessionid . '|' . date('Y-m-d-H') . '|' . $CFG->passwordsaltmain);
$scanurl = $CFG->wwwroot . '/local/airpay_pages/qr_scan.php?sessionid=' . $sessionid . '&token=' . $token;

// Use Google Charts API for QR code generation (no library needed).
$qrurl = 'https://chart.googleapis.com/chart?chs=400x400&cht=qr&chl=' . urlencode($scanurl) . '&choe=UTF-8';

echo $OUTPUT->header();

echo '<div class="airpay-qr" style="text-align: center; padding: 40px;">';
echo '<h2><i class="fa fa-qrcode"></i> Scan to Mark Attendance</h2>';
if ($session) {
    echo '<p style="font-size: 1.1rem; color: #374151;">' . format_string($session->fullname ?? $session->name ?? 'Session #' . $sessionid) . '</p>';
}
echo '<div style="margin: 24px auto; display: inline-block; padding: 20px; background: #fff; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">';
echo '<img src="' . s($qrurl) . '" alt="QR Code" width="400" height="400" style="display: block;">';
echo '</div>';
echo '<p style="font-size: 0.9rem; color: #5a6070;">This QR code refreshes every hour for security.</p>';
echo '<p style="font-size: 0.8rem; color: #9ca3af;">Session ID: ' . $sessionid . ' | Generated: ' . userdate(time(), '%d %b %Y %I:%M %p') . '</p>';
echo '</div>';

echo $OUTPUT->footer();
