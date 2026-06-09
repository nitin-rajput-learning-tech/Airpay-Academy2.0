<?php
/**
 * QR Attendance — Generate QR code for classroom session.
 * Trainer displays this page on projector. Employees scan to mark attendance.
 *
 * Uses Moodle's built-in QR library (no external API dependency).
 * Includes countdown timer + fullscreen mode for projector display.
 *
 * Usage: /local/sentientia_pages/qr_attendance.php?sessionid=123
 *
 * @package    local_sentientia_pages
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
$PAGE->set_url('/local/sentientia_pages/qr_attendance.php', ['sessionid' => $sessionid]);
$PAGE->set_title('QR Attendance');
$PAGE->set_heading('QR Attendance');
$PAGE->set_pagelayout('standard');

// Get session info.
$session = $DB->get_record('local_classroom_sessions', ['id' => $sessionid], '*', IGNORE_MISSING);
if (!$session) {
    $session = $DB->get_record('local_classroom', ['id' => $sessionid], '*', IGNORE_MISSING);
}
$sessionname = $session ? format_string($session->fullname ?? $session->name ?? 'Session #' . $sessionid) : 'Session #' . $sessionid;

// Generate a time-limited token (rotates hourly).
$token = hash('sha256', $sessionid . '|' . date('Y-m-d-H') . '|' . $CFG->passwordsaltmain);
$scanurl = $CFG->wwwroot . '/local/sentientia_pages/qr_scan.php?sessionid=' . $sessionid . '&token=' . $token;

// Generate QR using Moodle's built-in library (no Google Charts dependency).
require_once($CFG->libdir . '/phpqrcode/qrlib.php');
$tmpfile = tempnam($CFG->tempdir, 'qr_') . '.png';
QRcode::png($scanurl, $tmpfile, QR_ECLEVEL_M, 10, 2);
$qrbase64 = base64_encode(file_get_contents($tmpfile));
@unlink($tmpfile);

// Calculate minutes until token expires (top of next hour).
$now = time();
$nextrotation = strtotime(date('Y-m-d H:00:00', $now + 3600));
$minutesremaining = max(1, round(($nextrotation - $now) / 60));

echo $OUTPUT->header();
?>

<div class="airpay-qr" id="airpay-qr-container">
    <div class="airpay-qr__header">
        <h2 class="airpay-qr__title"><i class="fa fa-qrcode"></i> Scan to Mark Attendance</h2>
        <p class="airpay-qr__session"><?php echo s($sessionname); ?></p>
    </div>

    <div class="airpay-qr__code-wrap">
        <img src="data:image/png;base64,<?php echo $qrbase64; ?>" alt="QR Code" class="airpay-qr__code" width="400" height="400">
    </div>

    <div class="airpay-qr__timer" id="airpay-qr-timer">
        <i class="fa fa-clock-o"></i>
        Refreshes in <strong id="ap-qr-countdown"><?php echo $minutesremaining; ?></strong> minutes
    </div>

    <div class="airpay-qr__actions">
        <button onclick="toggleFullscreen()" class="airpay-qr__btn" title="Fullscreen for projector">
            <i class="fa fa-expand"></i> Fullscreen
        </button>
        <button onclick="window.location.reload()" class="airpay-qr__btn airpay-qr__btn--refresh">
            <i class="fa fa-refresh"></i> Refresh Now
        </button>
    </div>

    <p class="airpay-qr__meta">
        Session ID: <?php echo $sessionid; ?> &middot;
        Generated: <?php echo userdate(time(), '%d %b %Y %I:%M %p'); ?>
    </p>
</div>

<style>
.airpay-qr { text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto; }
.airpay-qr__title { font-size: 24px; font-weight: 800; color: var(--ap-text, #1a1a2e); margin: 0 0 4px; }
.airpay-qr__session { font-size: 16px; color: var(--ap-text-secondary, #607286); margin: 0 0 24px; }
.airpay-qr__code-wrap {
    display: inline-block; padding: 24px; background: #fff;
    border-radius: 20px; box-shadow: 0 8px 32px rgba(0,0,0,0.08);
    border: 2px solid var(--ap-border, #e3eaf3); margin-bottom: 20px;
}
.airpay-qr__code { display: block; border-radius: 8px; }
.airpay-qr__timer {
    font-size: 14px; color: var(--ap-text-secondary, #607286);
    margin-bottom: 16px;
}
.airpay-qr__timer strong { color: var(--ap-primary, #0066A7); font-size: 16px; }
.airpay-qr__timer--danger strong { color: #dc2626 !important; }
.airpay-qr__actions { display: flex; gap: 8px; justify-content: center; margin-bottom: 16px; }
.airpay-qr__btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 10px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    border: 1px solid var(--ap-border, #e3eaf3);
    background: var(--ap-surface, #fff); color: var(--ap-text, #1a1a2e);
    transition: all 0.15s; font-family: inherit;
}
.airpay-qr__btn:hover { border-color: var(--ap-primary); color: var(--ap-primary); }
.airpay-qr__btn--refresh {
    background: var(--ap-gradient, linear-gradient(135deg, #0066A7, #0f7a73));
    color: #fff; border-color: transparent;
}
.airpay-qr__btn--refresh:hover { opacity: 0.9; color: #fff; }
.airpay-qr__meta { font-size: 12px; color: var(--ap-text-muted, #8896a6); }

/* Fullscreen mode */
.airpay-qr--fullscreen {
    position: fixed; inset: 0; z-index: 9999;
    background: #fff; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    max-width: none; padding: 0;
}
.airpay-qr--fullscreen .airpay-qr__code { width: 60vmin; height: 60vmin; }

/* Dark mode */
body.dark-mode .airpay-qr__title { color: #e8eaed; }
body.dark-mode .airpay-qr__code-wrap { background: #fff; border-color: #2d3140; }
body.dark-mode .airpay-qr__btn { background: #1a1d27; border-color: #2d3140; color: #e8eaed; }
body.dark-mode .airpay-qr--fullscreen { background: #14161e; }
</style>

<script>
// Countdown timer (refresh page when token expires).
(function() {
    var remaining = <?php echo (int)$minutesremaining; ?> * 60; // seconds
    var el = document.getElementById('ap-qr-countdown');
    var timerWrap = document.getElementById('airpay-qr-timer');
    setInterval(function() {
        remaining--;
        if (remaining <= 0) { window.location.reload(); return; }
        var mins = Math.ceil(remaining / 60);
        el.textContent = mins;
        if (remaining < 300) { // < 5 min: turn red
            timerWrap.classList.add('airpay-qr__timer--danger');
        }
    }, 1000);
})();

function toggleFullscreen() {
    var container = document.getElementById('airpay-qr-container');
    container.classList.toggle('airpay-qr--fullscreen');
    // Also try native fullscreen API.
    if (!document.fullscreenElement) {
        container.requestFullscreen && container.requestFullscreen();
    } else {
        document.exitFullscreen && document.exitFullscreen();
    }
}
</script>

<?php
echo $OUTPUT->footer();
